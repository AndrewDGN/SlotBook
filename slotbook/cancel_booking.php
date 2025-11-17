<?php

session_start();
require_once __DIR__ . '/includes/functions.php';
require_login();
global $mysqli;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'])) {
    $reservation_id = intval($_POST['reservation_id']);
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    // Get reservation details
    $stmt = $mysqli->prepare("
        SELECT r.*, u.full_name, f.name as facility_name 
        FROM reservations r 
        JOIN users u ON r.user_id = u.id 
        JOIN facilities f ON r.facility_id = f.id 
        WHERE r.id = ?
    ");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();

    if (!$reservation) {
        echo json_encode(['success' => false, 'error' => 'Reservation not found.']);
        exit;
    }

    // Check if user owns the reservation or is admin
    if ($user_role !== 'admin' && $reservation['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'You can only cancel your own reservations.']);
        exit;
    }

    // Check if reservation can be cancelled
    $current_time = date('Y-m-d H:i:s');
    $reservation_datetime = $reservation['date'] . ' ' . $reservation['start_time'];

    if (strtotime($reservation_datetime) < strtotime($current_time)) {
        echo json_encode(['success' => false, 'error' => 'Cannot cancel past reservations.']);
        exit;
    }

    if ($reservation['status'] === 'cancelled' || $reservation['status'] === 'denied') {
        echo json_encode(['success' => false, 'error' => 'Reservation is already cancelled or denied.']);
        exit;
    }

    // Update reservation status
    $stmt = $mysqli->prepare("UPDATE reservations SET status = 'cancelled', cancelled_by = ?, cancelled_at = NOW() WHERE id = ?");
    $stmt->bind_param("ii", $user_id, $reservation_id);

    if ($stmt->execute()) {
        // Create notification
        if ($user_role === 'admin') {
            $message = "Your reservation for {$reservation['facility_name']} has been cancelled by admin";
            $details = "Facility: {$reservation['facility_name']}\nDate: {$reservation['date']}\nTime: {$reservation['start_time']} - {$reservation['end_time']}\nCancelled by: Administrator";

            $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message, details, type, related_id, is_read, created_at) VALUES (?, ?, ?, 'reservation_cancelled', ?, 0, NOW())");
            $stmt->bind_param("issi", $reservation['user_id'], $message, $details, $reservation_id);
            $stmt->execute();
        } else {
            $message = "Reservation cancelled for {$reservation['facility_name']} by {$reservation['full_name']}";
            $details = "Facility: {$reservation['facility_name']}\nDate: {$reservation['date']}\nTime: {$reservation['start_time']} - {$reservation['end_time']}\nCancelled by: {$reservation['full_name']}";

            $admins = $mysqli->query("SELECT id FROM users WHERE role = 'admin'");
            while ($admin = $admins->fetch_assoc()) {
                $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message, details, type, related_id, is_read, created_at) VALUES (?, ?, ?, 'reservation_cancelled', ?, 0, NOW())");
                $stmt->bind_param("issi", $admin['id'], $message, $details, $reservation_id);
                $stmt->execute();
            }
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $mysqli->error]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
}
?>