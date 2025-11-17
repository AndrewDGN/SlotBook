<?php
session_start();
require_once __DIR__ . '/includes/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die('Unauthorized access');
}

// Get date range from form
$date_from = $_POST['date_from'] ?? '';
$date_to = $_POST['date_to'] ?? '';

// Build the query with date range if provided
$query = "
    SELECT 
        r.id,
        u.full_name as user_name,
        u.email as user_email,
        f.name as facility_name,
        f.building,
        r.date,
        r.start_time,
        r.end_time,
        r.status,
        r.created_at
    FROM reservations r
    JOIN users u ON r.user_id = u.id
    JOIN facilities f ON r.facility_id = f.id
    WHERE 1=1
";

if (!empty($date_from)) {
    $query .= " AND r.date >= '$date_from'";
}
if (!empty($date_to)) {
    $query .= " AND r.date <= '$date_to'";
}

$query .= " ORDER BY r.date DESC, r.start_time DESC";

$result = $mysqli->query($query);

// Set headers for Excel file download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="reservations_summary_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Excel header
echo "<html>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<style>
    table { border-collapse: collapse; width: 100%; }
    th { background-color: #800000; color: white; padding: 10px; border: 1px solid #ddd; }
    td { padding: 8px; border: 1px solid #ddd; }
    .header { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
</style>";
echo "</head>";
echo "<body>";

// Report title and date range
echo "<div class='header'>Reservations Summary Report</div>";
if (!empty($date_from) || !empty($date_to)) {
    echo "<div>Date Range: " .
        (!empty($date_from) ? "From $date_from " : "") .
        (!empty($date_to) ? "To $date_to" : "") .
        "</div>";
}
echo "<div>Generated on: " . date('F j, Y g:i A') . "</div>";
echo "<br>";

// Start table
echo "<table border='1'>";
echo "<tr>
        <th>Reservation ID</th>
        <th>User Name</th>
        <th>User Email</th>
        <th>Facility Name</th>
        <th>Building</th>
        <th>Date</th>
        <th>Start Time</th>
        <th>End Time</th>
        <th>Status</th>
        <th>Reserved On</th>
      </tr>";

// Table data
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['facility_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['building']) . "</td>";
        echo "<td>" . htmlspecialchars($row['date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['start_time']) . "</td>";
        echo "<td>" . htmlspecialchars($row['end_time']) . "</td>";
        echo "<td>" . htmlspecialchars(ucfirst($row['status'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='10' style='text-align: center;'>No reservations found</td></tr>";
}

echo "</table>";

// Summary statistics
echo "<br><br>";
echo "<div class='header'>Summary Statistics</div>";

$stats_query = "
    SELECT 
        COUNT(*) as total_reservations,
        SUM(status = 'approved') as approved,
        SUM(status = 'pending') as pending,
        SUM(status = 'denied') as denied,
        SUM(status = 'cancelled') as cancelled,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT facility_id) as unique_facilities
    FROM reservations 
    WHERE 1=1
";

if (!empty($date_from)) {
    $stats_query .= " AND date >= '$date_from'";
}
if (!empty($date_to)) {
    $stats_query .= " AND date <= '$date_to'";
}

$stats_result = $mysqli->query($stats_query);
$stats = $stats_result->fetch_assoc();

echo "<table border='1'>";
echo "<tr>
        <th>Total Reservations</th>
        <th>Approved</th>
        <th>Pending</th>
        <th>Denied</th>
        <th>Cancelled</th>
        <th>Unique Users</th>
        <th>Unique Facilities</th>
      </tr>";
echo "<tr>";
echo "<td>" . $stats['total_reservations'] . "</td>";
echo "<td>" . $stats['approved'] . "</td>";
echo "<td>" . $stats['pending'] . "</td>";
echo "<td>" . $stats['denied'] . "</td>";
echo "<td>" . $stats['cancelled'] . "</td>";
echo "<td>" . $stats['unique_users'] . "</td>";
echo "<td>" . $stats['unique_facilities'] . "</td>";
echo "</tr>";
echo "</table>";

echo "</body>";
echo "</html>";
exit;
?>