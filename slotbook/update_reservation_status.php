<?php
// update_reservation_status.php
session_start();
require_once __DIR__ . '/includes/functions.php';
require_login();
global $mysqli;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservation_id = intval($_POST['reservation_id']);
    $user_id = intval($_POST['user_id']);
    $status = $_POST['status'];

    // Update reservation status
    $stmt = $mysqli->prepare("UPDATE reservations SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $reservation_id);

    if ($stmt->execute()) {
        // Create notification for the user
        $reservation_query = $mysqli->query("
            SELECT r.*, f.name as facility_name 
            FROM reservations r 
            JOIN facilities f ON r.facility_id = f.id 
            WHERE r.id = $reservation_id
        ");
        $reservation = $reservation_query->fetch_assoc();

        $message = "Your reservation for {$reservation['facility_name']} has been {$status}";
        $details = "Facility: {$reservation['facility_name']}\n";
        $details .= "Date: {$reservation['date']}\n";
        $details .= "Time: {$reservation['start_time']} - {$reservation['end_time']}\n";
        $details .= "Status: " . ucfirst($status);

        $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message, details, type, related_id, is_read, created_at) VALUES (?, ?, ?, 'reservation_updated', ?, 0, NOW())");
        $stmt->bind_param("issi", $user_id, $message, $details, $reservation_id);
        $stmt->execute();

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $mysqli->error]);
    }
}
?>