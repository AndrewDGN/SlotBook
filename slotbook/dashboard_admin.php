<?php
// dashboard_admin.php
session_start();
require_once __DIR__ . '/includes/functions.php';
require_login();
global $mysqli;

// Include analytics
require_once __DIR__ . '/includes/analytics.php';
$analytics = new SlotBookAnalytics($mysqli);
$insights = $analytics->generateInsights();
$utilization = $analytics->getUtilizationRate();
$popularFacilities = $analytics->getPopularFacilities();
$peakData = $analytics->getPeakHours();
$trends = $analytics->getBookingTrends(7);

// Handle facility creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_facility'])) {
    $name = trim($_POST['name']);
    $building = trim($_POST['building']);
    $capacity = intval($_POST['capacity']);
    $status = $_POST['status'] ?? 'available';

    // Check if facility with same name and building already exists
    $check_stmt = $mysqli->prepare("SELECT id FROM facilities WHERE name = ? AND building = ?");
    $check_stmt->bind_param("ss", $name, $building);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $_SESSION['error'] = "A facility with the name '$name' in building '$building' already exists.";
        header("Location: dashboard_admin.php");
        exit;
    }

    $check_stmt->close();

    // Insert if no duplicate found
    $stmt = $mysqli->prepare("INSERT INTO facilities (name, building, capacity, status, created_at)
                              VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssis", $name, $building, $capacity, $status);

    if ($stmt->execute()) {
        $new_facility_id = $mysqli->insert_id;

        // After adding facility, notify all faculty
        $faculty_users = $mysqli->query("SELECT id FROM users WHERE role = 'faculty'");
        while ($user = $faculty_users->fetch_assoc()) {
            $message = "New facility available: {$name} in {$building}";
            $details = "Facility: {$name}\nBuilding: {$building}\nCapacity: {$capacity}";

            $notif_stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message, details, type, related_id, is_read, created_at) VALUES (?, ?, ?, 'new_facility', ?, 0, NOW())");
            $notif_stmt->bind_param("issi", $user['id'], $message, $details, $new_facility_id);
            $notif_stmt->execute();
            $notif_stmt->close();
        }

        $_SESSION['success'] = "Facility '$name' added successfully!";
    } else {
        $_SESSION['error'] = "Error adding facility: " . $mysqli->error;
    }

    header("Location: dashboard_admin.php");
    exit;
}

// Handle facility status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_facility_status'])) {
    $facility_id = intval($_POST['facility_id']);
    $new_status = $_POST['status'];

    $stmt = $mysqli->prepare("UPDATE facilities SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $facility_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Facility status updated successfully!";

        // If setting to maintenance, notify users with upcoming reservations
        if ($new_status === 'maintenance') {
            $upcoming_reservations = $mysqli->query("
                SELECT r.user_id, r.id 
                FROM reservations r 
                WHERE r.facility_id = $facility_id 
                AND r.date >= CURDATE() 
                AND r.status = 'approved'
            ");

            while ($reservation = $upcoming_reservations->fetch_assoc()) {
                $facility_info = $mysqli->query("SELECT name FROM facilities WHERE id = $facility_id")->fetch_assoc();
                $message = "Facility Maintenance: {$facility_info['name']}";
                $details = "The facility '{$facility_info['name']}' has been put under maintenance. Please check your upcoming reservations.";

                $notif_stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message, details, type, related_id, is_read, created_at) VALUES (?, ?, ?, 'maintenance', ?, 0, NOW())");
                $notif_stmt->bind_param("issi", $reservation['user_id'], $message, $details, $reservation['id']);
                $notif_stmt->execute();
                $notif_stmt->close();
            }
        }
    } else {
        $_SESSION['error'] = "Error updating facility status: " . $mysqli->error;
    }

    header("Location: dashboard_admin.php");
    exit;
}

// Handle facility deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_facility'])) {
    $facility_id = intval($_POST['facility_id']);

    // Check if facility has any reservations
    $check_stmt = $mysqli->prepare("SELECT COUNT(*) as reservation_count FROM reservations WHERE facility_id = ?");
    $check_stmt->bind_param("i", $facility_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();

    if ($result['reservation_count'] > 0) {
        $_SESSION['error'] = "Cannot delete facility. There are existing reservations for this facility.";
    } else {
        $stmt = $mysqli->prepare("DELETE FROM facilities WHERE id = ?");
        $stmt->bind_param("i", $facility_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Facility deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting facility: " . $mysqli->error;
        }
    }

    header("Location: dashboard_admin.php");
    exit;
}

// Get dashboard counts
$totalActive = $mysqli->query("SELECT COUNT(*) AS c FROM reservations WHERE status='approved'")->fetch_assoc()['c'] ?? 0;
$totalPending = $mysqli->query("SELECT COUNT(*) AS c FROM reservations WHERE status='pending'")->fetch_assoc()['c'] ?? 0;
$totalFacilities = $mysqli->query("SELECT COUNT(*) AS c FROM facilities")->fetch_assoc()['c'] ?? 0;
$totalMonth = $mysqli->query("SELECT COUNT(*) AS c FROM reservations WHERE MONTH(created_at)=MONTH(CURDATE())")->fetch_assoc()['c'] ?? 0;

// Get facilities list
$facilities = $mysqli->query("SELECT * FROM facilities ORDER BY created_at DESC");

// Get pending reservations
$pending_res = $mysqli->query("
    SELECT r.id, u.full_name AS user_name, f.name AS facility_name, r.date, r.start_time, r.end_time, r.status, r.user_id
    FROM reservations r
    JOIN users u ON r.user_id = u.id
    JOIN facilities f ON r.facility_id = f.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SlotBook</title>
    <link rel="stylesheet" href=css\dashboard_admin.css>

</head>

<body>

    <header>
        <div class="nav-brand">SlotBook Admin</div>
        <nav>
            <a href="dashboard_admin.php">Dashboard</a>
            <a href="#facilities">Facilities Manager</a>
            <a href="#reservations">Reservations</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <main>
        <!-- Display success/error messages at the top -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="notification-status status-success">
                <?= htmlspecialchars($_SESSION['success']); ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="notification-status status-error">
                <?= htmlspecialchars($_SESSION['error']); ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="cards">
            <div class="card">
                <h2><?= $totalActive ?></h2>
                <p>Active Reservations</p>
            </div>
            <div class="card">
                <h2><?= $totalPending ?></h2>
                <p>Pending Requests</p>
            </div>
            <div class="card">
                <h2><?= $totalFacilities ?></h2>
                <p>Available Facilities</p>
            </div>
            <div class="card">
                <h2><?= $totalMonth ?></h2>
                <p>This Month</p>
            </div>
        </div>

        <!-- AI Analytics Dashboard -->
        <section id="analytics">
            <h3 style="color: var(--maroon); border-bottom: 2px solid var(--maroon); padding-bottom: 10px;">
                Smart Insights
            </h3>

            <!-- Insights Cards -->
            <div class="insights-grid">
                <?php foreach ($insights as $insight): ?>
                    <div class="insight-card"
                        style="border-left-color: <?= $insight['priority'] == 'high' ? 'var(--maroon)' : '#ffc107' ?>;">
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 24px; margin-right: 10px;"></span>
                            <h3 style="margin: 0; color: #333;"><?= $insight['title'] ?></h3>
                        </div>
                        <p style="margin: 0; color: #666; line-height: 1.5;"><?= $insight['message'] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid">

                <!-- Utilization Chart -->
                <div class="chart-container">
                    <h3 style="color: #333; margin-bottom: 15px;"> Slot Utilization</h3>
                    <canvas id="utilizationChart" width="400" height="200"></canvas>
                    <div style="text-align: center; margin-top: 10px; color: #666;">
                        <?= $utilization['booked_slots'] ?> / <?= $utilization['total_slots'] ?> slots booked
                    </div>
                </div>

                <!-- Peak Hours Chart -->
                <div class="chart-container">
                    <h3 style="color: #333; margin-bottom: 15px;"> Peak Booking Hours</h3>
                    <canvas id="peakHoursChart" width="400" height="200"></canvas>
                </div>

                <!-- Popular Facilities Chart -->
                <div class="chart-container">
                    <h3 style="color: #333; margin-bottom: 15px;"> Popular Facilities</h3>
                    <canvas id="facilitiesChart" width="400" height="200"></canvas>
                </div>

                <!-- Trends Chart -->
                <div class="chart-container">
                    <h3 style="color: #333; margin-bottom: 15px;"> Booking Trends (7 Days)</h3>
                    <canvas id="trendsChart" width="400" height="200"></canvas>
                </div>
            </div>
        </section>

        <!-- Intelligent Recommendations Section -->
        <section id="intelligent-recommendations">
            <h3 style="color: var(--maroon); border-bottom: 2px solid var(--maroon); padding-bottom: 10px;">
                AI-Powered Recommendations
            </h3>

            <?php
            $predictions = $analytics->predictBusyPeriods();
            $maintenance = $analytics->suggestMaintenanceSchedules();
            $upgrades = $analytics->recommendFacilityUpgrades();
            ?>

            <div class="intelligent-grid"
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-top: 20px;">

                <!-- Demand Predictions -->
                <div class="intelligent-card"
                    style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); border-left: 4px solid #ff6b6b;">
                    <h4 style="color: #333; margin-bottom: 15px;"> Demand Predictions (Next Week)</h4>
                    <?php if (!empty($predictions)): ?>
                        <?php $highDemand = array_filter($predictions, function ($p) {
                            return $p['busy_level'] == 'high';
                        }); ?>
                        <p><strong><?= count($highDemand) ?> high-demand periods predicted</strong></p>
                        <div style="max-height: 200px; overflow-y: auto;">
                            <?php foreach (array_slice($predictions, 0, 5) as $prediction): ?>
                                <div
                                    style="padding: 8px; margin: 5px 0; background: #f8f9fa; border-radius: 5px; border-left: 3px solid <?= $prediction['busy_level'] == 'high' ? '#dc3545' : '#ffc107' ?>;">
                                    <small>
                                        <strong><?= $prediction['day'] ?></strong> at <?= $prediction['time_slot'] ?><br>
                                        <span
                                            style="color: <?= $prediction['busy_level'] == 'high' ? '#dc3545' : '#856404' ?>;">
                                            <?= strtoupper($prediction['busy_level']) ?> demand
                                        </span>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #666; font-style: italic;">No significant demand patterns detected</p>
                    <?php endif; ?>
                </div>

                <!-- Maintenance Suggestions -->
                <div class="intelligent-card"
                    style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); border-left: 4px solid #ffd93d;">
                    <h4 style="color: #333; margin-bottom: 15px;"> Maintenance Schedule</h4>
                    <?php if (!empty($maintenance)): ?>
                        <?php $priorityMaintenance = array_filter($maintenance, function ($m) {
                            return $m['priority'] == 'high';
                        }); ?>
                        <p><strong><?= count($priorityMaintenance) ?> priority maintenance items</strong></p>
                        <div style="max-height: 200px; overflow-y: auto;">
                            <?php foreach (array_slice($maintenance, 0, 5) as $item): ?>
                                <div
                                    style="padding: 8px; margin: 5px 0; background: #f8f9fa; border-radius: 5px; border-left: 3px solid <?= $item['priority'] == 'high' ? '#dc3545' : '#ffc107' ?>;">
                                    <small>
                                        <strong><?= $item['facility_name'] ?></strong><br>
                                        <?= $item['reason'] ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #666; font-style: italic;">All facilities maintenance is up to date</p>
                    <?php endif; ?>
                </div>

                <!-- Upgrade Recommendations -->
                <div class="intelligent-card"
                    style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); border-left: 4px solid #6f42c1;">
                    <h4 style="color: #333; margin-bottom: 15px;"> Upgrade Recommendations</h4>
                    <?php if (!empty($upgrades)): ?>
                        <p><strong><?= count($upgrades) ?> facilities need attention</strong></p>
                        <div style="max-height: 200px; overflow-y: auto;">
                            <?php foreach (array_slice($upgrades, 0, 5) as $upgrade): ?>
                                <div
                                    style="padding: 8px; margin: 5px 0; background: #f8f9fa; border-radius: 5px; border-left: 3px solid <?= $upgrade['priority'] == 'high' ? '#dc3545' : '#6f42c1' ?>;">
                                    <small>
                                        <strong><?= $upgrade['facility_name'] ?></strong><br>
                                        <?= $upgrade['recommendation'] ?> (<?= $upgrade['denial_rate'] ?>% denial rate)
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #666; font-style: italic;">All facilities are adequately sized</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="reservations">
            <h3>Recent Reservation Requests</h3>

            <table>
                <thead>
                    <tr>
                        <th>Faculty</th>
                        <th>Facility</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Update the query to include all reservations 
                    $all_reservations = $mysqli->query("
                SELECT r.id, u.full_name AS user_name, f.name AS facility_name, r.date, r.start_time, r.end_time, r.status, r.user_id
                FROM reservations r
                JOIN users u ON r.user_id = u.id
                JOIN facilities f ON r.facility_id = f.id
                WHERE r.status IN ('pending', 'approved')
                ORDER BY r.date DESC, r.start_time DESC
                LIMIT 15
            ");
                    ?>

                    <?php if ($all_reservations && $all_reservations->num_rows > 0): ?>
                        <?php while ($r = $all_reservations->fetch_assoc()): ?>
                            <tr data-id="<?= $r['id'] ?>" data-user-id="<?= $r['user_id'] ?>">
                                <td><?= htmlspecialchars($r['user_name']) ?></td>
                                <td><?= htmlspecialchars($r['facility_name']) ?></td>
                                <td><?= htmlspecialchars($r['date']) ?></td>
                                <td><?= htmlspecialchars($r['start_time'] . ' - ' . $r['end_time']) ?></td>
                                <td class="status-cell">
                                    <span class="status-badge status-<?= $r['status'] ?>">
                                        <?= htmlspecialchars(ucfirst($r['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <button class="btn btn-approve">Approve</button>
                                        <button class="btn btn-deny">Deny</button>
                                    <?php endif; ?>
                                    <button class="btn btn-cancel"
                                        onclick="cancelReservation(<?= $r['id'] ?>, <?= $r['user_id'] ?>)">
                                        Cancel
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center;">No reservations found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section id="facilities">
            <h3>Facilities Manager</h3>
            <form method="POST" class="add-facility">
                <div>
                    <label>Facility Name</label>
                    <input type="text" name="name" required>
                </div>
                <div>
                    <label>Building</label>
                    <input type="text" name="building" required>
                </div>
                <div>
                    <label>Capacity</label>
                    <input type="number" name="capacity" required min="1">
                </div>
                <div>
                    <label>Status</label>
                    <select name="status" required>
                        <option value="available">Available</option>
                        <option value="maintenance">Under Maintenance</option>
                    </select>
                </div>
                <div>
                    <button type="submit" name="add_facility" class="btn btn-add">Add Facility</button>
                </div>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Building</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($facilities && $facilities->num_rows > 0): ?>
                        <?php while ($f = $facilities->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($f['name']) ?></td>
                                <td><?= htmlspecialchars($f['building']) ?></td>
                                <td><?= htmlspecialchars($f['capacity']) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="facility_id" value="<?= $f['id'] ?>">
                                        <select name="status" class="status-dropdown" onchange="this.form.submit()">
                                            <option value="available" <?= $f['status'] == 'available' ? 'selected' : '' ?>>
                                                Available</option>
                                            <option value="maintenance" <?= $f['status'] == 'maintenance' ? 'selected' : '' ?>>
                                                Maintenance</option>
                                        </select>
                                        <input type="hidden" name="update_facility_status" value="1">
                                    </form>
                                </td>
                                <td><?= htmlspecialchars($f['created_at']) ?></td>
                                <td class="actions-cell">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="facility_id" value="<?= $f['id'] ?>">
                                        <button type="submit" name="delete_facility" class="delete-btn"
                                            onclick="return confirm('Are you sure you want to delete this facility? This action cannot be undone.')">
                                            ×
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center;">No facilities found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section id="export-section">
            <h3>Export Reports</h3>
            <p>Generate Excel summary of all reservations</p>
            <form method="POST" action="export_excel.php">
                <div style="display: flex; gap: 15px; align-items: end;">
                    <div>
                        <label>Date From</label>
                        <input type="date" name="date_from"
                            style="padding: 8px; border: 1px solid #ccc; border-radius: 5px;">
                    </div>
                    <div>
                        <label>Date To</label>
                        <input type="date" name="date_to"
                            style="padding: 8px; border: 1px solid #ccc; border-radius: 5px;">
                    </div>
                    <div>
                        <button type="submit" name="export_excel" class="btn"
                            style="background: #28a745; color: white; padding: 8px 20px; border: none; border-radius: 5px; cursor: pointer;">
                            Export to Excel
                        </button>
                    </div>
                </div>
            </form>
        </section>

    </main>

    <!-- Library for Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Analytics Charts
        // Utilization Chart 
        const utilizationCtx = document.getElementById('utilizationChart').getContext('2d');
        const utilizationChart = new Chart(utilizationCtx, {
            type: 'doughnut',
            data: {
                labels: ['Booked Slots', 'Available Slots'],
                datasets: [{
                    data: [
                        <?= $utilization['booked_slots'] ?>,
                        <?= $utilization['total_slots'] - $utilization['booked_slots'] ?>
                    ],
                    backgroundColor: ['var(--maroon)', '#e0e0e0'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Peak Hours Chart
        const peakHoursCtx = document.getElementById('peakHoursChart').getContext('2d');
        const peakHoursChart = new Chart(peakHoursCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($peakData['peak_hours'] as $hour): ?>
                        '<?= $hour['hour'] ?>:00',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Number of Bookings',
                    data: [
                        <?php foreach ($peakData['peak_hours'] as $hour): ?>
                            <?= $hour['booking_count'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'var(--maroon)',
                    borderColor: 'var(--maroon-dark)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Bookings'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Hour of Day'
                        }
                    }
                }
            }
        });

        // Popular Facilities Chart 
        const facilitiesCtx = document.getElementById('facilitiesChart').getContext('2d');
        const facilitiesChart = new Chart(facilitiesCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($popularFacilities as $facility): ?>
                        '<?= addslashes($facility['name']) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Bookings',
                    data: [
                        <?php foreach ($popularFacilities as $facility): ?>
                            <?= $facility['request_count'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#ffc107',
                    borderColor: '#e0a800',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Bookings'
                        }
                    }
                }
            }
        });

        // Trends Chart 
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        const trendsChart = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($trends as $trend): ?>
                        '<?= date('M j', strtotime($trend['date'])) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Confirmed Bookings',
                    data: [
                        <?php foreach ($trends as $trend): ?>
                            <?= $trend['confirmed_bookings'] ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: 'var(--maroon)',
                    backgroundColor: 'rgba(122, 0, 0, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Bookings per Day'
                        }
                    }
                }
            }
        });

        // Existing reservation management functions
        document.querySelectorAll('.btn-approve').forEach(btn => {
            btn.addEventListener('click', function () {
                const row = this.closest('tr');
                const reservationId = row.dataset.id;
                const userId = row.dataset.userId;
                updateReservationStatus(reservationId, userId, 'approved', row);
            });
        });

        document.querySelectorAll('.btn-deny').forEach(btn => {
            btn.addEventListener('click', function () {
                const row = this.closest('tr');
                const reservationId = row.dataset.id;
                const userId = row.dataset.userId;
                updateReservationStatus(reservationId, userId, 'denied', row);
            });
        });

        function updateReservationStatus(reservationId, userId, status, row) {
            fetch('update_reservation_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `reservation_id=${reservationId}&user_id=${userId}&status=${status}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Reservation ${status} successfully!`);
                        location.reload(); // Reload the page to reflect changes
                    } else {
                        alert('Error updating status: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating status.');
                });
        }

        function cancelReservation(reservationId, userId) {
            if (!confirm('Are you sure you want to cancel this reservation? This will free up the time slot for other users.')) {
                return;
            }

            const formData = new FormData();
            formData.append('reservation_id', reservationId);
            formData.append('user_id', userId);

            fetch('cancel_booking.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Reservation cancelled successfully!');
                        location.reload(); // Reload to show updated status
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error cancelling reservation.');
                });
        }
    </script>

</body>

</html>