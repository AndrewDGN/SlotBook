<?php

session_start();
require_once __DIR__ . '/includes/functions.php';
require_login();
global $mysqli;

// Get user info for navbar
$user_id = $_SESSION['user_id'];
$user_query = $mysqli->query("SELECT full_name, role FROM users WHERE id = $user_id");
$user = $user_query->fetch_assoc();

// Get unread notification count for badge
$unread_count = $mysqli->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0")->fetch_assoc()['count'];

// Mark notification as read when clicked
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = intval($_GET['mark_read']);
    $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $_SESSION['user_id']);
    $stmt->execute();
    header("Location: notifications.php");
    exit;
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    header("Location: notifications.php");
    exit;
}

// Fetch notifications for current user
$notifications = $mysqli->query("
    SELECT * FROM notifications 
    WHERE user_id = {$_SESSION['user_id']} 
    ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="css\notifications.css">
</head>

<body>
    <header>
        <div class="brand">SlotBook</div>
        <nav>
            <a href="dashboard_f.php">Home</a>
            <a href="facilities_f.php">Facilities</a>
            <a href="calendar_view.php">Calendar</a>
            <a href="notifications.php">Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?= $unread_count ?></span>
                <?php endif; ?>
            </a>
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
        <div class="notifications-container">
            <div class="notification-header">
                <h1>Notifications</h1>
                <a href="?mark_all_read" class="btn">Mark All as Read</a>
            </div>

            <div class="notifications-list">
                <?php if ($notifications->num_rows > 0): ?>
                    <?php while ($notification = $notifications->fetch_assoc()): ?>
                        <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>"
                            onclick="showNotification(<?= htmlspecialchars(json_encode($notification)) ?>)">
                            <div class="message"><?= htmlspecialchars($notification['message']) ?></div>
                            <div class="time"><?= date('M j, g:i A', strtotime($notification['created_at'])) ?></div>
                            <div style="clear: both;"></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="background: white; padding: 3rem; text-align: center; color: #666;">
                        <p>No notifications yet</p>
                        <p style="margin-top: 0.5rem; font-size: 0.9rem;">You'll see notifications here for reservation
                            updates and new facilities.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Notification Detail Modal -->
    <div id="notificationModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 id="modalTitle">Notification Details</h3>
            <div id="modalContent"></div>
            <div style="margin-top: 1.5rem; text-align: right;">
                <button onclick="closeModal()" class="btn">Close</button>
            </div>
        </div>
    </div>

    <script>
        function showNotification(notification) {
            // Mark as read
            if (!notification.is_read) {
                window.location.href = `notifications.php?mark_read=${notification.id}`;
                return;
            }

            // Show details in modal
            document.getElementById('modalContent').innerHTML = `
                <p><strong>Message:</strong> ${notification.message}</p>
                <p><strong>Date:</strong> ${new Date(notification.created_at).toLocaleString()}</p>
                ${notification.details ? `<p><strong>Details:</strong><br>${notification.details.replace(/\n/g, '<br>')}</p>` : ''}
            `;
            document.getElementById('notificationModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('notificationModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('notificationModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>

</html>