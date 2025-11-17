<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$facility_id = $_GET['facility_id'] ?? null;
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');

// Get all facilities for dropdown
$facilities_result = $mysqli->query("SELECT id, name FROM facilities ORDER BY name");

if ($facility_id) {
    // Get facility details
    $stmt = $mysqli->prepare("SELECT * FROM facilities WHERE id = ?");
    $stmt->bind_param('i', $facility_id);
    $stmt->execute();
    $facility = $stmt->get_result()->fetch_assoc();

    // Calculate first and last day of month
    $first_day = date("Y-m-01", strtotime("$year-$month-01"));
    $last_day = date("Y-m-t", strtotime("$year-$month-01"));

    // Get approved reservations for this facility in the current month with user names
    $reservations = $mysqli->prepare("
        SELECT r.date, r.start_time, r.end_time, u.full_name 
        FROM reservations r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.facility_id = ? AND r.date BETWEEN ? AND ? AND r.status = 'approved'
        ORDER BY r.date, r.start_time
    ");
    $reservations->bind_param('iss', $facility_id, $first_day, $last_day);
    $reservations->execute();
    $booked_slots_result = $reservations->get_result();

    // Convert to array 
    $booked_slots = [];
    while ($slot = $booked_slots_result->fetch_assoc()) {
        $booked_slots[$slot['date']][] = $slot;
    }
}

// Calculate calendar data
if ($facility_id) {
    $month_name = date("F Y", strtotime("$year-$month-01"));
    $first_day_of_month = date("w", strtotime("$year-$month-01")); // 0=Sunday, 1=Monday, etc.
    $days_in_month = date("t", strtotime("$year-$month-01"));

    // Previous and next month links
    $prev_month = $month - 1;
    $prev_year = $year;
    if ($prev_month == 0) {
        $prev_month = 12;
        $prev_year = $year - 1;
    }

    $next_month = $month + 1;
    $next_year = $year;
    if ($next_month == 13) {
        $next_month = 1;
        $next_year = $year + 1;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar</title>
    <link rel=stylesheet href=css\calendar_view.css>

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

            <div class="user-menu">
                <button class="user-dropdown">
                    <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?> ▼
                </button>
                <div class="dropdown-content">
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <h1>Facility Calendar</h1>
        <p>View booking schedules and available time slots</p>

        <div class="facility-selector">
            <form method="GET" action="calendar_view.php">
                <select name="facility_id" required>
                    <option value="">Select a Facility</option>
                    <?php while ($fac = $facilities_result->fetch_assoc()): ?>
                        <option value="<?= $fac['id'] ?>" <?= $facility_id == $fac['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fac['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit">View Calendar</button>
            </form>
        </div>

        <?php if ($facility_id && $facility): ?>
            <div class="calendar-container">
                <div class="calendar-header">
                    <div class="calendar-nav">
                        <a href="?facility_id=<?= $facility_id ?>&month=<?= $prev_month ?>&year=<?= $prev_year ?>">
                            <button>&larr; Previous</button>
                        </a>
                    </div>
                    <h2><?= $month_name ?> - <?= htmlspecialchars($facility['name']) ?></h2>
                    <div class="calendar-nav">
                        <a href="?facility_id=<?= $facility_id ?>&month=<?= $next_month ?>&year=<?= $next_year ?>">
                            <button>Next &rarr;</button>
                        </a>
                    </div>
                </div>

                <div class="calendar-grid">
                    <!-- Day headers -->
                    <div class="calendar-day-header">Sunday</div>
                    <div class="calendar-day-header">Monday</div>
                    <div class="calendar-day-header">Tuesday</div>
                    <div class="calendar-day-header">Wednesday</div>
                    <div class="calendar-day-header">Thursday</div>
                    <div class="calendar-day-header">Friday</div>
                    <div class="calendar-day-header">Saturday</div>

                    <!-- Empty cells for days before the first day of month -->
                    <?php for ($i = 0; $i < $first_day_of_month; $i++): ?>
                        <div class="calendar-day other-month"></div>
                    <?php endfor; ?>

                    <!-- Days of the month -->
                    <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                        <?php
                        $current_date = date("Y-m-d", strtotime("$year-$month-$day"));
                        $is_today = $current_date == date('Y-m-d');
                        $day_class = $is_today ? 'calendar-day today' : 'calendar-day';
                        ?>
                        <div class="<?= $day_class ?>">
                            <div class="day-number"><?= $day ?></div>
                            <?php if (isset($booked_slots[$current_date])): ?>
                                <?php foreach ($booked_slots[$current_date] as $booking): ?>
                                    <div class="reservation-item">
                                        <?= date("g:i A", strtotime($booking['start_time'])) ?> -
                                        <?= date("g:i A", strtotime($booking['end_time'])) ?>
                                        <div class="booker-name">
                                            By: <?= htmlspecialchars($booking['full_name']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>

                    <!-- Empty cells for days after the last day of month -->
                    <?php
                    $total_cells = $first_day_of_month + $days_in_month;
                    $remaining_cells = 42 - $total_cells; // 6 rows x 7 days = 42 cells
                    if ($remaining_cells > 0) {
                        for ($i = 0; $i < $remaining_cells; $i++) {
                            echo '<div class="calendar-day other-month"></div>';
                        }
                    }
                    ?>
                </div>
            </div>

            <div style="margin-top: 20px; text-align: center;">
                <a href="reserve.php?id=<?= $facility_id ?>"
                    style="background: #800000; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">
                    Make Reservation for <?= htmlspecialchars($facility['name']) ?>
                </a>
            </div>

        <?php elseif ($facility_id): ?>
            <div class="no-facility">
                <p>Facility not found.</p>
            </div>
        <?php else: ?>
            <div class="no-facility">
                <p>Please select a facility to view its calendar.</p>
            </div>
        <?php endif; ?>
    </main>
</body>

</html>