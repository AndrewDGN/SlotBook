<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get the specific facility from the URL
$facility_id = $_GET['facility_id'] ?? null;
$date = $_GET['date'] ?? date('Y-m-d');

if (!$facility_id) {
    header('Location: facilities_f.php');
    exit;
}

// Get the specific facility details
$facility_stmt = $mysqli->prepare("SELECT * FROM facilities WHERE id = ?");
$facility_stmt->bind_param('i', $facility_id);
$facility_stmt->execute();
$facility_result = $facility_stmt->get_result();
$facility = $facility_result->fetch_assoc();

if (!$facility) {
    header('Location: facilities_f.php');
    exit;
}

// Get approved reservations for the selected facility and date
$reservations_stmt = $mysqli->prepare("
    SELECT facility_id, start_time, end_time 
    FROM reservations 
    WHERE facility_id = ? AND date = ? AND status = 'approved'
");
$reservations_stmt->bind_param('is', $facility_id, $date);
$reservations_stmt->execute();
$reservations_result = $reservations_stmt->get_result();

// Convert reservations to array for easier access
$booked_slots = [];
while ($reservation = $reservations_result->fetch_assoc()) {
    $booked_slots[] = [
        'start' => $reservation['start_time'],
        'end' => $reservation['end_time']
    ];
}

// Get user's own reservations for the selected date (to prevent double-booking)
$user_reservations_stmt = $mysqli->prepare("
    SELECT facility_id, start_time, end_time, f.name as facility_name
    FROM reservations r
    JOIN facilities f ON r.facility_id = f.id
    WHERE r.user_id = ? AND r.date = ? AND r.status IN ('approved', 'pending') AND r.facility_id != ?
");
$user_reservations_stmt->bind_param('isi', $user_id, $date, $facility_id);
$user_reservations_stmt->execute();
$user_reservations_result = $user_reservations_stmt->get_result();

// Convert user's reservations to array
$user_booked_slots = [];
while ($user_reservation = $user_reservations_result->fetch_assoc()) {
    $user_booked_slots[] = [
        'facility_id' => $user_reservation['facility_id'],
        'facility_name' => $user_reservation['facility_name'],
        'start' => $user_reservation['start_time'],
        'end' => $user_reservation['end_time']
    ];
}

// Handle reservation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_facility_id = $_POST['facility_id'];
    $selected_date = $_POST['date'];
    $selected_start = $_POST['start_time'];
    $selected_end = $_POST['end_time'];

    // Check for facility conflicts (other users bookings)
    $facility_conflict_check = $mysqli->prepare("
        SELECT reservations.id FROM reservations 
        WHERE facility_id = ? 
        AND date = ? 
        AND status IN ('approved', 'pending')
        AND NOT (end_time <= ? OR start_time >= ?)
    ");
    $facility_conflict_check->bind_param('isss', $selected_facility_id, $selected_date, $selected_start, $selected_end);
    $facility_conflict_check->execute();
    $facility_conflict_result = $facility_conflict_check->get_result();

    // Check for user conflicts (same user other facilities)
    $user_conflict_check = $mysqli->prepare("
        SELECT r.id, f.name as facility_name 
        FROM reservations r
        JOIN facilities f ON r.facility_id = f.id
        WHERE r.user_id = ? 
        AND r.date = ? 
        AND r.status IN ('approved', 'pending')
        AND NOT (r.end_time <= ? OR r.start_time >= ?)
    ");
    $user_conflict_check->bind_param('isss', $user_id, $selected_date, $selected_start, $selected_end);
    $user_conflict_check->execute();
    $user_conflict_result = $user_conflict_check->get_result();

    if ($facility_conflict_result->num_rows > 0) {
        $error = "This time slot is already booked in the selected facility. Please choose a different time.";
    } elseif ($user_conflict_result->num_rows > 0) {
        $conflicting_booking = $user_conflict_result->fetch_assoc();
        $error = "You already have a booking at {$conflicting_booking['facility_name']} during this time. You cannot be in two places at once!";
    } else {
        $stmt = $mysqli->prepare("INSERT INTO reservations (user_id, facility_id, date, start_time, end_time, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param('iisss', $user_id, $selected_facility_id, $selected_date, $selected_start, $selected_end);

        if ($stmt->execute()) {
            $success = "Reservation request submitted successfully! Waiting for approval.";

            // Clear selected time slots after successful submission
            unset($_POST);
        } else {
            $error = "Error submitting reservation. Please try again.";
        }
    }
}

// Generate time slots from 7:00 AM to 6:00 PM with 30-minute intervals
$time_slots = [];
for ($hour = 7; $hour <= 18; $hour++) {
    $time_slots[] = sprintf('%02d:00', $hour);
    if ($hour < 18) {
        $time_slots[] = sprintf('%02d:30', $hour);
    }
}

// Check if facility is available for reservation
$is_available = ($facility['status'] === 'available');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserve <?= htmlspecialchars($facility['name']) ?></title>
    <link rel="stylesheet" href="css\reserve.css">
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
        <a href="facilities_f.php" class="back-btn">← Back to Facilities</a>

        <div class="facility-info">
            <h2><?= htmlspecialchars($facility['name']) ?></h2>
            <div class="facility-details">
                <div class="detail-item">
                    <strong>Building:</strong> <?= htmlspecialchars($facility['building']) ?>
                </div>
                <div class="detail-item">
                    <strong>Capacity:</strong> <?= htmlspecialchars($facility['capacity']) ?> people
                </div>
                <div class="detail-item">
                    <strong>Status:</strong>
                    <span style="color: <?= $facility['status'] === 'available' ? '#28a745' : '#dc3545' ?>;">
                        <?= ucfirst($facility['status']) ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!$is_available): ?>
            <div class="error">
                This facility is currently
                <?= $facility['status'] === 'maintenance' ? 'under maintenance' : 'unavailable' ?> for reservations. Please
                check back later or choose another facility.
            </div>
        <?php endif; ?>

        <?php if ($is_available): ?>
            <div class="instructions">
                <strong>How to reserve:</strong> Click on a start time, then click on an end time. All time slots in between
                will be automatically selected.
            </div>

            <?php if (count($user_booked_slots) > 0): ?>
                <div class="user-bookings">
                    <h4>Your Other Bookings for <?= date('l, F j, Y', strtotime($date)) ?></h4>
                    <?php foreach ($user_booked_slots as $user_booking): ?>
                        <div class="booking-item">
                            <strong><?= htmlspecialchars($user_booking['facility_name']) ?></strong>:
                            <?= date("g:i A", strtotime($user_booking['start'])) ?> -
                            <?= date("g:i A", strtotime($user_booking['end'])) ?>
                        </div>
                    <?php endforeach; ?>
                    <p><small>You cannot book overlapping times across different facilities.</small></p>
                </div>
            <?php endif; ?>

            <div class="filters">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Select Date:</label>
                    <input type="date" id="dateSelect" value="<?= $date ?>" onchange="updateDate()">
                </div>
                <div>
                    <button onclick="applyFilters()">Refresh Availability</button>
                </div>
            </div>

            <div class="time-slot-table">
                <div class="table-header">
                    <div>Time</div>
                    <div>Availability</div>
                </div>

                <?php foreach ($time_slots as $time_slot): ?>
                    <?php
                    $time_display = date("g:i A", strtotime($time_slot));

                    $is_booked = false;
                    $is_user_booked = false;

                    // Check if facility is booked by anyone
                    foreach ($booked_slots as $booking) {
                        if (
                            strtotime($time_slot) >= strtotime($booking['start']) &&
                            strtotime($time_slot) < strtotime($booking['end'])
                        ) {
                            $is_booked = true;
                            break;
                        }
                    }

                    // Check if user has booking in OTHER facilities at this time
                    foreach ($user_booked_slots as $user_booking) {
                        if (
                            strtotime($time_slot) >= strtotime($user_booking['start']) &&
                            strtotime($time_slot) < strtotime($user_booking['end'])
                        ) {
                            $is_user_booked = true;
                            break;
                        }
                    }

                    $status_class = 'available';
                    $status_text = 'Available';

                    if ($is_user_booked) {
                        $status_class = 'user-booked';
                        $status_text = 'Your Other Booking';
                    } elseif ($is_booked) {
                        $status_class = 'booked';
                        $status_text = 'Booked';
                    }
                    ?>

                    <div class="time-row">
                        <div class="time-slot"><?= $time_display ?></div>
                        <div class="time-slot <?= $status_class ?>" data-facility-id="<?= $facility['id'] ?>"
                            data-facility-name="<?= htmlspecialchars($facility['name']) ?>" data-time="<?= $time_slot ?>"
                            data-time-display="<?= $time_display ?>" onclick="handleTimeSlotClick(this)">
                            <?= $status_text ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color booked-color"></div>
                    <span>Booked by Others</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color user-booked-color"></div>
                    <span>Your Other Booking</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color available-color"></div>
                    <span>Available</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color selected-color"></div>
                    <span>Start/End</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color range-color"></div>
                    <span>Selected Range</span>
                </div>
            </div>

            <form id="reservationForm" method="POST" action="reserve.php?facility_id=<?= $facility_id ?>">
                <input type="hidden" name="facility_id" id="facilityId" value="<?= $facility['id'] ?>">
                <input type="hidden" name="date" value="<?= $date ?>">
                <input type="hidden" name="start_time" id="startTime">
                <input type="hidden" name="end_time" id="endTime">

                <div class="selected-info" id="selectedInfo" style="display: none;">
                    <h3>Selected Reservation</h3>
                    <p><strong>Facility:</strong> <?= htmlspecialchars($facility['name']) ?></p>
                    <p><strong>Date:</strong> <?= date('l, F j, Y', strtotime($date)) ?></p>
                    <p><strong>Time:</strong> <span id="selectedTime"></span></p>
                    <p><strong>Duration:</strong> <span id="selectedDuration"></span></p>
                    <button type="submit" class="confirm-btn" id="confirmBtn">Confirm Reservation</button>
                </div>
            </form>
        <?php endif; ?>
    </main>

    <script>
        let currentFacility = <?= $facility['id'] ?>;
        let startSlot = null;
        let endSlot = null;

        function updateDate() {
            const date = document.getElementById('dateSelect').value;
            window.location.href = `reserve.php?facility_id=<?= $facility_id ?>&date=${date}`;
        }

        function applyFilters() {
            updateDate();
        }

        // Initialize date input
        document.addEventListener('DOMContentLoaded', function () {
            const dateInput = document.getElementById('dateSelect');
            if (!dateInput.value) {
                dateInput.value = new Date().toISOString().split('T')[0];
            }
        });

        function handleTimeSlotClick(element) {
            // Don't allow selection of booked or user-booked slots
            if (element.classList.contains('booked') || element.classList.contains('user-booked')) {
                return;
            }

            const facilityId = element.dataset.facilityId;
            const facilityName = element.dataset.facilityName;
            const time = element.dataset.time;
            const timeDisplay = element.dataset.timeDisplay;

            // If no start slot selected yet
            if (!startSlot) {
                startSlot = { element, time, timeDisplay, facilityId, facilityName };
                element.classList.add('selected-start');
                updateSelectionDisplay();
                return;
            }

            // If we have a start slot but no end slot yet
            if (startSlot && !endSlot) {
                const startTime = timeToMinutes(startSlot.time);
                const endTime = timeToMinutes(time);

                if (endTime <= startTime) {
                    // If end time is before start time, swap them
                    endSlot = startSlot;
                    startSlot = { element, time, timeDisplay, facilityId, facilityName };

                    // Update visual selection
                    endSlot.element.classList.remove('selected-start');
                    endSlot.element.classList.add('selected-end');
                    element.classList.add('selected-start');
                } else {
                    endSlot = { element, time, timeDisplay, facilityId, facilityName };
                    element.classList.add('selected-end');
                }

                // Highlight the range between start and end
                highlightTimeRange(startSlot.time, endSlot.time);
                updateSelectionDisplay();
            } else {
                // Reset and start new selection
                resetSelection();
                startSlot = { element, time, timeDisplay, facilityId, facilityName };
                element.classList.add('selected-start');
                updateSelectionDisplay();
            }
        }

        function timeToMinutes(time) {
            const [hours, minutes] = time.split(':').map(Number);
            return hours * 60 + minutes;
        }

        function minutesToTime(minutes) {
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
        }

        function highlightTimeRange(startTime, endTime) {
            const startMinutes = timeToMinutes(startTime);
            const endMinutes = timeToMinutes(endTime);

            // Get all time slots
            const allSlots = document.querySelectorAll('.time-slot:not(:first-child)');

            allSlots.forEach(slot => {
                if (slot.classList.contains('booked') || slot.classList.contains('user-booked')) return;

                const slotTime = slot.dataset.time;
                const slotMinutes = timeToMinutes(slotTime);

                if (slotMinutes > startMinutes && slotMinutes < endMinutes) {
                    slot.classList.add('selected-range');
                }
            });
        }

        function resetSelection() {
            // Clear all selection classes
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.classList.remove('selected-start', 'selected-end', 'selected-range');
            });

            startSlot = null;
            endSlot = null;
            document.getElementById('selectedInfo').style.display = 'none';
        }

        function updateSelectionDisplay() {
            if (!startSlot) return;

            let displayText = '';
            let durationText = '';
            let startTimeValue = startSlot.time;
            let endTimeValue = '';

            if (endSlot) {
                displayText = `${startSlot.timeDisplay} - ${endSlot.timeDisplay}`;
                endTimeValue = endSlot.time;

                // Calculate duration
                const startMinutes = timeToMinutes(startSlot.time);
                const endMinutes = timeToMinutes(endSlot.time);
                const durationMinutes = endMinutes - startMinutes;
                const hours = Math.floor(durationMinutes / 60);
                const minutes = durationMinutes % 60;

                if (hours > 0 && minutes > 0) {
                    durationText = `${hours} hour${hours > 1 ? 's' : ''} ${minutes} minutes`;
                } else if (hours > 0) {
                    durationText = `${hours} hour${hours > 1 ? 's' : ''}`;
                } else {
                    durationText = `${minutes} minutes`;
                }
            } else {
                displayText = `${startSlot.timeDisplay} (select end time)`;
                durationText = 'Select end time to see duration';
            }

            document.getElementById('selectedTime').textContent = displayText;
            document.getElementById('selectedDuration').textContent = durationText;

            document.getElementById('startTime').value = startTimeValue;
            document.getElementById('endTime').value = endTimeValue;

            document.getElementById('selectedInfo').style.display = 'block';
            document.getElementById('confirmBtn').disabled = !endSlot;
        }
    </script>
</body>

</html>