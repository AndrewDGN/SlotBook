<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get search parameters
$search = $_GET['search'] ?? '';
$date_range = $_GET['date_range'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

// Build the query
$query = "
    SELECT r.*, f.name as facility_name 
    FROM reservations r 
    JOIN facilities f ON r.facility_id = f.id 
    WHERE r.user_id = $user_id
";

// Add search filter
if (!empty($search)) {
    $query .= " AND f.name LIKE '%$search%'";
}

// Add date range filter
if ($date_range !== 'all') {
    $today = date('Y-m-d');
    if ($date_range === 'week') {
        $query .= " AND r.date >= DATE_SUB('$today', INTERVAL 7 DAY)";
    } elseif ($date_range === 'month') {
        $query .= " AND r.date >= DATE_SUB('$today', INTERVAL 30 DAY)";
    }
}

// Add status filter
if ($status_filter !== 'all') {
    $query .= " AND r.status = '$status_filter'";
}

$query .= " ORDER BY r.date DESC, r.created_at DESC";

// Execute query
$reservations = $mysqli->query($query);
$total_reservations = $reservations->num_rows;

// Pagination
$per_page = 6;
$total_pages = ceil($total_reservations / $per_page);
$current_page = $_GET['page'] ?? 1;
$offset = ($current_page - 1) * $per_page;

$query .= " LIMIT $offset, $per_page";
$reservations = $mysqli->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History</title>
    <link rel="stylesheet" href="css\history.css">

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
        <div class="page-header">
            <h1>Reservation History</h1>
            <p>View all your past facility reservations and their status.</p>
        </div>

        <div class="history-container">
            <!-- Filters -->
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Search by facility name..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="filter-group">
                    <label>Date Range</label>
                    <select name="date_range">
                        <option value="all" <?= $date_range === 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="week" <?= $date_range === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="month" <?= $date_range === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Completed</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="denied" <?= $status_filter === 'denied' ? 'selected' : '' ?>>Denied</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled
                        </option>
                    </select>
                </div>
                <button type="submit" class="apply-btn">Apply</button>
            </form>

            <!-- Reservations Table -->
            <h3>Past Reservations</h3>
            <?php if ($reservations && $reservations->num_rows > 0): ?>
                <table class="reservations-table">
                    <thead>
                        <tr>
                            <th>Facility Name</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($reservation = $reservations->fetch_assoc()): ?>
                            <tr>
                                <td class="facility-name"><?= htmlspecialchars($reservation['facility_name']) ?></td>
                                <td><?= date('F j, Y', strtotime($reservation['date'])) ?></td>
                                <td><?= date('H:i', strtotime($reservation['start_time'])) ?> -
                                    <?= date('H:i', strtotime($reservation['end_time'])) ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    switch ($reservation['status']) {
                                        case 'approved':
                                            $status_class = 'status-completed';
                                            $status_text = 'Completed';
                                            break;
                                        case 'pending':
                                            $status_class = 'status-pending';
                                            $status_text = 'Pending';
                                            break;
                                        case 'denied':
                                            $status_class = 'status-cancelled';
                                            $status_text = 'Denied';
                                            break;
                                        case 'cancelled':
                                            $status_class = 'status-cancelled';
                                            $status_text = 'Cancelled';
                                            break;
                                    }
                                    ?>
                                    <span class="<?= $status_class ?>"><?= $status_text ?></span>
                                </td>
                                <td>
                                    <!-- ITO NA YUNG BAGONG VIEW DETAILS LINK -->
                                    <a href="reservation_details.php?id=<?= $reservation['id'] ?>" class="view-details">View
                                        Details</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_reservations) ?> of
                        <?= $total_reservations ?> results
                    </div>
                    <div class="pagination-controls">
                        <?php if ($current_page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>"
                                class="pagination-btn">Previous</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= min($total_pages, 4); $i++): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                                class="pagination-btn <?= $i == $current_page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>"
                                class="pagination-btn">Next</a>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="no-reservations">
                    <p>No reservations found.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>© 2025 SlotBook. All rights reserved.</p>
    </footer>
</body>

</html>