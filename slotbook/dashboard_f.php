<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
  header('Location: login.php');
  exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Faculty';

// Get reservation summary
$total_res = $pending = $approved = $available = 0;

// Count all reservations
$res_q = $mysqli->query("SELECT 
    COUNT(*) AS total,
    SUM(status='pending') AS pending,
    SUM(status='approved') AS approved
    FROM reservations WHERE user_id = $user_id");
if ($res_q && $r = $res_q->fetch_assoc()) {
  $total_res = $r['total'];
  $pending = $r['pending'];
  $approved = $r['approved'];
}

// Count available facilities - FIXED: using status column instead of is_available
$fac_q = $mysqli->query("SELECT COUNT(*) AS available FROM facilities WHERE status = 'available'");
if ($fac_q && $f = $fac_q->fetch_assoc()) {
  $available = $f['available'];
}

// Fetch recent reservations
$recent = $mysqli->query("
    SELECT r.*, f.name AS facility_name
    FROM reservations r
    JOIN facilities f ON r.facility_id = f.id
    WHERE r.user_id = $user_id
    ORDER BY r.created_at DESC
    LIMIT 5
");

// Fetch upcoming reservations for today
$today = date('Y-m-d');
$upcoming = $mysqli->query("
    SELECT r.*, f.name AS facility_name
    FROM reservations r
    JOIN facilities f ON r.facility_id = f.id
    WHERE r.user_id = $user_id AND r.date = '$today'
    ORDER BY r.start_time ASC
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Faculty Dashboard</title>
  <link rel="stylesheet" href="css\dashboard_f.css">

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
          <?= htmlspecialchars($user_name) ?> ▼
        </button>
        <div class="dropdown-content">
          <a href="logout.php">Logout</a>
        </div>
      </div>
    </nav>
  </header>

  <main>
    <h1>Dashboard</h1>
    <p class="subtitle">Manage your facility reservations and view upcoming bookings</p>

    <!-- Display success/error messages -->
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

    <div class="summary-cards">
      <div class="card">
        <h2><?= $approved ?></h2>
        <p>Active Reservations</p>
      </div>
      <div class="card">
        <h2><?= $pending ?></h2>
        <p>Pending Requests</p>
      </div>
      <div class="card">
        <h2><?= $available ?></h2>
        <p>Available Facilities</p>
      </div>
      <div class="card">
        <h2><?= $total_res ?></h2>
        <p>This Month</p>
      </div>
    </div>

    <div class="grid-container">
      <div class="left-panel">
        <div class="section">
          <h3>Recent Reservations</h3>
          <?php if ($recent && $recent->num_rows > 0): ?>
            <?php while ($r = $recent->fetch_assoc()): ?>
              <?php
              $current_time = date('Y-m-d H:i:s');
              $reservation_datetime = $r['date'] . ' ' . $r['start_time'];
              $can_cancel = ($r['status'] === 'pending' || $r['status'] === 'approved') &&
                strtotime($reservation_datetime) > strtotime($current_time);
              ?>
              <div class="reservation-item" data-id="<?= $r['id'] ?>">
                <div class="reservation-info">
                  <strong><?= htmlspecialchars($r['facility_name']) ?></strong><br>
                  <span><?= htmlspecialchars($r['date']) ?> • <?= date("g:i A", strtotime($r['start_time'])) ?> -
                    <?= date("g:i A", strtotime($r['end_time'])) ?></span>
                </div>
                <div class="reservation-actions">
                  <span class="status-badge status-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
                  <?php if ($can_cancel): ?>
                    <button class="btn-cancel" onclick="cancelReservation(<?= $r['id'] ?>)">Cancel</button>
                  <?php endif; ?>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <p>No recent reservations.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="right-panel">
        <div class="section quick-actions">
          <h3>Quick Actions</h3>
          <a href="facilities_f.php" class="btn">Add New Reservation</a>
          <a href="calendar_view.php" class="btn">View Calendar</a>
          <a href="history.php" class="btn">View History</a>
        </div>

        <div class="section" style="margin-top:20px;">
          <h3>Upcoming Today</h3>
          <?php if ($upcoming && $upcoming->num_rows > 0): ?>
            <?php while ($u = $upcoming->fetch_assoc()): ?>
              <?php
              $current_time = date('Y-m-d H:i:s');
              $reservation_datetime = $u['date'] . ' ' . $u['start_time'];
              $can_cancel = ($u['status'] === 'pending' || $u['status'] === 'approved') &&
                strtotime($reservation_datetime) > strtotime($current_time);
              ?>
              <div class="reservation-item" data-id="<?= $u['id'] ?>">
                <div class="reservation-info">
                  <strong><?= htmlspecialchars($u['facility_name']) ?></strong><br>
                  <span><?= date("g:i A", strtotime($u['start_time'])) ?> -
                    <?= date("g:i A", strtotime($u['end_time'])) ?></span>
                </div>
                <div class="reservation-actions">
                  <span class="status-badge status-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span>
                  <?php if ($can_cancel): ?>
                    <button class="btn-cancel" onclick="cancelReservation(<?= $u['id'] ?>)">Cancel</button>
                  <?php endif; ?>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <p>No upcoming reservations today.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <script>
    function cancelReservation(reservationId) {
      if (!confirm('Are you sure you want to cancel this reservation? This will free up the time slot for other users.')) {
        return;
      }

      const formData = new FormData();
      formData.append('reservation_id', reservationId);

      fetch('cancel_booking.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Reservation cancelled successfully! The time slot is now available for others.');
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

  <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>

</html>