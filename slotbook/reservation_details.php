<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header('Location: login.php');
    exit;
}

$reservation_id = $_GET['id'] ?? null;

if (!$reservation_id) {
    header('Location: history.php');
    exit;
}

// Get reservation details
$stmt = $mysqli->prepare("
    SELECT 
        r.*,
        f.name as facility_name,
        f.building,
        f.capacity,
        u.full_name as user_name,
        u.email as user_email
    FROM reservations r
    JOIN facilities f ON r.facility_id = f.id
    JOIN users u ON r.user_id = u.id
    WHERE r.id = ? AND r.user_id = ?
");
$stmt->bind_param('ii', $reservation_id, $_SESSION['user_id']);
$stmt->execute();
$reservation = $stmt->get_result()->fetch_assoc();

if (!$reservation) {
    header('Location: history.php');
    exit;
}

// Format status display
$status_display = [
    'approved' => 'Completed',
    'pending' => 'Pending Approval',
    'denied' => 'Denied',
    'cancelled' => 'Cancelled'
];

$status_class = [
    'approved' => 'status-completed',
    'pending' => 'status-pending',
    'denied' => 'status-denied',
    'cancelled' => 'status-cancelled'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Details</title>
    <link rel="stylesheet" href="css\reservation_details.css">
</head>

<body>
    <header>
        <div class="brand">SlotBook</div>
        <nav>
            <a href="dashboard_f.php">Home</a>
            <a href="facilities_f.php">Facilities</a>
            <a href="calendar_view.php">Calendar</a>
            <a href="notifications.php">Notifications</a>
            <a href="history.php">History</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <main>
        <div class="details-container">
            <div class="page-header">
                <h1>Reservation Details</h1>
                <a href="history.php" class="back-btn">← Back to History</a>
            </div>

            <div class="reservation-card">
                <div class="info-section">
                    <h3>Reservation Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Reservation ID:</span>
                            <span class="info-value">#<?= $reservation['id'] ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status:</span>
                            <span class="status-badge <?= $status_class[$reservation['status']] ?>">
                                <?= $status_display[$reservation['status']] ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date:</span>
                            <span class="info-value"><?= date('F j, Y', strtotime($reservation['date'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Time:</span>
                            <span class="info-value">
                                <?= date('g:i A', strtotime($reservation['start_time'])) ?> -
                                <?= date('g:i A', strtotime($reservation['end_time'])) ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Duration:</span>
                            <span class="info-value">
                                <?php
                                $start = new DateTime($reservation['start_time']);
                                $end = new DateTime($reservation['end_time']);
                                $duration = $start->diff($end);
                                echo $duration->h . ' hours ' . $duration->i . ' minutes';
                                ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Requested On:</span>
                            <span
                                class="info-value"><?= date('F j, Y g:i A', strtotime($reservation['created_at'])) ?></span>
                        </div>
                    </div>
                </div>

                <div class="info-section">
                    <h3>Facility Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Facility:</span>
                            <span class="info-value"><?= htmlspecialchars($reservation['facility_name']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Building:</span>
                            <span class="info-value"><?= htmlspecialchars($reservation['building']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Capacity:</span>
                            <span class="info-value"><?= $reservation['capacity'] ?> people</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="info-section">
                <h3>Reservation Timeline</h3>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-date"><?= date('F j, Y g:i A', strtotime($reservation['created_at'])) ?>
                        </div>
                        <div class="timeline-content">Reservation requested</div>
                    </div>
                    <?php if ($reservation['status'] == 'approved'): ?>
                        <div class="timeline-item">
                            <div class="timeline-date">Approved</div>
                            <div class="timeline-content">Reservation confirmed by administrator</div>
                        </div>
                    <?php elseif ($reservation['status'] == 'denied'): ?>
                        <div class="timeline-item">
                            <div class="timeline-date">Denied</div>
                            <div class="timeline-content">Reservation was not approved</div>
                        </div>
                    <?php elseif ($reservation['status'] == 'cancelled'): ?>
                        <div class="timeline-item">
                            <div class="timeline-date">Cancelled</div>
                            <div class="timeline-content">Reservation was cancelled</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="actions-section">
                <button onclick="window.print()" class="btn btn-print">Print Details</button>
            </div>
        </div>
    </main>

    <script>
        // Print styling
        window.addEventListener('beforeprint', function () {
            document.querySelector('header').style.display = 'none';
            document.querySelector('.back-btn').style.display = 'none';
            document.querySelector('.actions-section').style.display = 'none';
        });

        window.addEventListener('afterprint', function () {
            document.querySelector('header').style.display = 'flex';
            document.querySelector('.back-btn').style.display = 'inline-block';
            document.querySelector('.actions-section').style.display = 'block';
        });
    </script>
</body>

</html>