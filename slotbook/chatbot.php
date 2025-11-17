<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['response' => 'Please log in to use the chatbot.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message']);
    $response = processChatMessage($message);
    echo json_encode(['response' => $response]);
    exit;
}

function processChatMessage($message)
{
    global $mysqli;

    $original_message = $message;
    $message_lower = strtolower($message);

    // Store conversation context
    if (!isset($_SESSION['chat_context'])) {
        $_SESSION['chat_context'] = [];
    }

    // Get all facilities from database for room detection
    $facilities_stmt = $mysqli->prepare("SELECT id, name, building FROM facilities");
    $facilities_stmt->execute();
    $all_facilities = $facilities_stmt->get_result();

    $facility_names = [];
    while ($facility = $all_facilities->fetch_assoc()) {
        $facility_names[] = [
            'id' => $facility['id'],
            'name' => $facility['name'],
            'name_lower' => strtolower($facility['name']),
            'building' => $facility['building']
        ];
    }

    // Check for different language
    if (isDifferentLanguage($message_lower)) {
        return getLanguageResponse();
    }

    // Check if message is a greeting or off-topic question
    if (isGreeting($message_lower)) {
        return getGreetingResponse();
    }

    if (isOffTopic($message_lower)) {
        return getOffTopicResponse();
    }

    if (isHelpRequest($message_lower)) {
        return getHelpResponse();
    }

    // Room detection using database
    $detected_room = detectRoomFromDatabase($message_lower, $facility_names);
    $room_id = $detected_room['id'] ?? null;
    $room_name = $detected_room['name'] ?? null;

    // Time and date patterns
    $time_pattern = '/(\d{1,2}(?::\d{2})?\s*(?:am|pm)|noon|morning|afternoon|evening|\\bam\\b|\\bpm\\b)/i';
    $date_pattern = '/(today|tomorrow|\d{1,2}\/\d{1,2}\/\d{4}|\d{4}-\d{2}-\d{2}|next week|this week|monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i';

    preg_match($time_pattern, $original_message, $time_matches);
    preg_match($date_pattern, $original_message, $date_matches);

    $time = $time_matches[1] ?? null;
    $date = $date_matches[1] ?? 'today';

    // Store room in context for follow-up questions
    if ($room_id) {
        $_SESSION['chat_context']['last_room_id'] = $room_id;
        $_SESSION['chat_context']['last_room_name'] = $room_name;
    }

    // Convert relative dates to actual dates
    $date = convertRelativeDate($date);

    // Convert time to proper format
    if ($time) {
        $time = convertTo24Hour($time);
    }

    // If we have both room and time, check availability
    if ($room_id && $time) {
        return checkRoomAvailabilityById($room_id, $time, $date);
    }

    // If we have room but no time
    if ($room_id && !$time) {
        $_SESSION['chat_context']['awaiting_time'] = true;
        $responses = [
            "I can check $room_name for you. What time are you interested in?",
            "Looking for $room_name! What time would you like me to check?",
            "Great! I can check $room_name's availability. What time are you thinking?",
            "I see you want $room_name. What time should I look for?",
            "Checking $room_name for you. Just need to know what time!"
        ];
        return $responses[array_rand($responses)];
    }

    // If we have time but no room 
    if ($time && !$room_id) {
        // Check if we have room from context
        if (isset($_SESSION['chat_context']['last_room_id'])) {
            $room_id = $_SESSION['chat_context']['last_room_id'];
            $room_name = $_SESSION['chat_context']['last_room_name'];
            return checkRoomAvailabilityById($room_id, $time, $date);
        }

        $responses = [
            "I'd be happy to help! Please specify which room you're asking about.",
            "Which room are you interested in? I can check its availability for you.",
            "To check availability, please tell me which room you're looking for.",
            "I see you mentioned a time. Which room would you like me to check?",
            "What room are you thinking of for that time?"
        ];
        return $responses[array_rand($responses)];
    }

    // If we detected a room but no time, and user didn't ask clearly
    if ($room_id) {
        $responses = [
            "I found $room_name in your message! What time would you like me to check?",
            "I see you're interested in $room_name. What time should I look for?",
            "Great, $room_name! What time are you thinking of?",
            "$room_name is available for booking. What time do you need it?"
        ];
        return $responses[array_rand($responses)];
    }

    // Default responses for unclear messages
    $responses = [
        "I'm here to help with room bookings! Try asking something like 'Is room 202 available at 2pm?'",
        "I can check room availability for you. Just tell me the room and time you're interested in!",
        "Need to book a room? Tell me which room and what time, and I'll check if it's available!",
        "I specialize in room availability. Ask me about any room and time combination!"
    ];
    return $responses[array_rand($responses)];
}

// Detect room by searching through database facility names
function detectRoomFromDatabase($message, $facility_names)
{
    $best_match = null;
    $best_score = 0;

    foreach ($facility_names as $facility) {
        $score = calculateMatchScore($message, $facility['name_lower'], $facility['building']);

        if ($score > $best_score) {
            $best_score = $score;
            $best_match = $facility;
        }
    }

    // Only return if we have a good match
    if ($best_score >= 2) {
        return $best_match;
    }

    return null;
}

// Check how well the message matches a facility using score
function calculateMatchScore($message, $facility_name, $building)
{
    $score = 0;

    // Exact match gets highest score
    if (strpos($message, $facility_name) !== false) {
        $score += 10;
    }

    // Partially match
    $facility_words = explode(' ', $facility_name);
    foreach ($facility_words as $word) {
        if (strlen($word) > 2 && strpos($message, $word) !== false) {
            $score += 3;
        }
    }

    // Building match
    if (strpos($message, strtolower($building)) !== false) {
        $score += 2;
    }

    // Room number patterns 
    preg_match('/\b(\d{3})\b/', $message, $number_matches);
    if (isset($number_matches[1])) {
        $room_number = $number_matches[1];
        if (strpos($facility_name, $room_number) !== false) {
            $score += 5;
        }
    }

    return $score;
}

// Check availability using facility ID instead of name
function checkRoomAvailabilityById($room_id, $time, $date)
{
    global $mysqli;

    // Clear context after checking availability
    unset($_SESSION['chat_context']['last_room_id']);
    unset($_SESSION['chat_context']['awaiting_time']);

    // Get facility details
    $stmt = $mysqli->prepare("SELECT id, name, building FROM facilities WHERE id = ?");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $facility_result = $stmt->get_result();

    if ($facility_result->num_rows === 0) {
        return "I encountered an error finding that room. Please try again.";
    }

    $facility = $facility_result->fetch_assoc();

    // Check for bookings at that time
    $check_stmt = $mysqli->prepare("
        SELECT r.id, u.full_name, r.start_time, r.end_time 
        FROM reservations r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.facility_id = ? 
        AND r.date = ? 
        AND r.status = 'approved'
        AND ? BETWEEN r.start_time AND r.end_time
    ");
    $check_stmt->bind_param('iss', $room_id, $date, $time);
    $check_stmt->execute();
    $booking_result = $check_stmt->get_result();

    // Generate responses
    $date_display = date('F j, Y', strtotime($date));
    $time_display = date('g:i A', strtotime($time));

    if ($booking_result->num_rows > 0) {
        $booking = $booking_result->fetch_assoc();
        $responses = [
            "Yes, {$facility['name']} is already booked at $time_display on $date_display by {$booking['full_name']}.",
            "Unfortunately {$facility['name']} is booked during that time. {$booking['full_name']} has it reserved from {$booking['start_time']} to {$booking['end_time']}.",
            "That time slot is taken! {$facility['name']} is booked by {$booking['full_name']}.",
            "Sorry, {$facility['name']} is unavailable. It's booked by {$booking['full_name']} during that period.",
            "Booked! {$facility['name']} is taken at $time_display by {$booking['full_name']}.",
            "Not available. {$facility['name']} is reserved by {$booking['full_name']}."
        ];
        return $responses[array_rand($responses)];
    } else {
        $responses = [
            "Great news! {$facility['name']} is available at $time_display on $date_display!",
            "Perfect! {$facility['name']} is free during that time slot. You can book it now!",
            "Yes! {$facility['name']} is available at $time_display. Go ahead and make your reservation!",
            "That time works! {$facility['name']} is open and ready to be booked on $date_display.",
            "Available! {$facility['name']} is free at $time_display. Perfect timing!",
            "All clear! {$facility['name']} is available for booking at $time_display."
        ];
        return $responses[array_rand($responses)];
    }
}


function isDifferentLanguage($message)
{
    $filipino_words = [
        'kumain',
        'ka na',
        'ba',
        'saan',
        'ano',
        'bakit',
        'paano',
        'sige',
        'opo',
        'hindi',
        'oo',
        'mahal',
        'salamat',
        'kamusta',
        'maganda',
        'pangit',
        'mabuti',
        'masama',
        'gutom',
        'uhaw',
        'tulog',
        'gising',
        'laro',
        'trabaho',
        'bahay',
        'kalsada'
    ];

    $spanish_words = [
        'hola',
        'como',
        'estas',
        'gracias',
        'por favor',
        'que',
        'donde',
        'cuando',
        'porque',
        'amigo',
        'casa',
        'comida',
        'agua',
        'bueno',
        'malo',
        'si',
        'no'
    ];

    $words = explode(' ', $message);

    foreach ($words as $word) {
        if (in_array($word, $filipino_words) || in_array($word, $spanish_words)) {
            return true;
        }
    }

    return false;
}

function getLanguageResponse()
{
    $responses = [
        "I'm an English-speaking booking assistant! I can help you check room availability in English.",
        "I only understand English for room booking queries. Please ask about rooms in English!",
        "Sorry, I'm programmed for English room booking questions. Try 'Is room 202 available?'",
        "I specialize in English room availability queries. Let me know which room and time in English!",
        "For room bookings, I work best with English. Ask me something like 'Room 301 at 3pm'!"
    ];
    return $responses[array_rand($responses)];
}

function convertRelativeDate($date)
{
    $date = strtolower($date);

    switch ($date) {
        case 'today':
            return date('Y-m-d');
        case 'tomorrow':
            return date('Y-m-d', strtotime('+1 day'));
        case 'next week':
            return date('Y-m-d', strtotime('+1 week'));
        case 'this week':
            return date('Y-m-d');
        case 'monday':
        case 'tuesday':
        case 'wednesday':
        case 'thursday':
        case 'friday':
        case 'saturday':
        case 'sunday':
            return date('Y-m-d', strtotime("next $date"));
        default:

            $parsed_date = strtotime($date);
            if ($parsed_date !== false) {
                return date('Y-m-d', $parsed_date);
            }
            return date('Y-m-d');
    }
}

function isGreeting($message)
{
    $greetings = [
        'hello',
        'hi',
        'hey',
        'good morning',
        'good afternoon',
        'good evening',
        'howdy',
        'greetings',
        'what\'s up',
        'sup',
        'yo',
        '\'sup',
        'hi there',
        'hello there',
        'hey there',
        'good day',
        'wassup'
    ];

    foreach ($greetings as $greeting) {
        if (strpos($message, $greeting) !== false) {
            return true;
        }
    }
    return false;
}

function isOffTopic($message)
{
    $off_topic_patterns = [
        '/eat|eaten|ate|food|hungry|dinner|lunch|breakfast|meal/',
        '/how are you|how do you do|how you doing/',
        '/weather|rain|sunny|cloudy|temperature/',
        '/joke|funny|haha|lol|lmao|hilarious/',
        '/sport|game|football|basketball|soccer|baseball/',
        '/movie|music|song|band|artist|concert/',
        '/love|like you|crush|date|romantic/',
        '/who are you|what are you|your name/',
        '/age|old|young|birthday/',
        '/family|mother|father|parents|sibling/',
        '/hobby|interest|passion|free time/',
        '/job|work|career|occupation/',
        '/color|favorite|prefer/',
        '/pet|dog|cat|animal/',
        '/travel|vacation|holiday/',
        '/sleep|tired|bed|nap/',
        '/shopping|buy|purchase/',
        '/drink|coffee|tea|beer|wine/',
        '/phone|computer|technology|internet/',
        '/news|politics|government/'
    ];

    foreach ($off_topic_patterns as $pattern) {
        if (preg_match($pattern, $message)) {
            return true;
        }
    }
    return false;
}

function isHelpRequest($message)
{
    $help_patterns = [
        '/help|what can you do|how to use/',
        '/what do you do|your purpose/',
        '/capabilit|function|feature/',
        '/can you help|assist me/',
        '/what questions|what can I ask/',
        '/check/'
    ];

    foreach ($help_patterns as $pattern) {
        if (preg_match($pattern, $message)) {
            return true;
        }
    }
    return false;
}

function getGreetingResponse()
{
    $greetings = [
        "Hello! I'm the SlotBook assistant. I can help you check room availability!",
        "Hi there! I specialize in checking room bookings. How can I assist you today?",
        "Hey! I'm here to help you find available rooms. What can I check for you?",
        "Greetings! I can tell you which rooms are free at any time. What do you need?",
        "Good day! I'm your room booking assistant. Ask me about room availability!",
        "Hi! I'm your friendly room booking bot. How can I help with your reservation?",
        "Hello there! Ready to find you the perfect room. What are you looking for?",
        "Hey there! I'm here to make room booking easy. What can I help you with?"
    ];
    return $greetings[array_rand($greetings)];
}

function getOffTopicResponse()
{
    $responses = [
        "I'm just a simple booking assistant! I can only help you check room availability.",
        "Sorry, I'm only programmed to answer questions about room bookings and availability.",
        "I'd love to chat, but I'm designed specifically for room booking queries!",
        "As much as I'd like to help with that, I'm focused on room availability questions.",
        "I'm afraid I can only assist with room booking inquiries. Try asking about a room!",
        "That's outside my expertise! I specialize in checking room availability for you.",
        "I'm just a booking bot - I can only help you find available rooms and time slots.",
        "While that sounds interesting, I'm here to help with room bookings specifically!",
        "I wish I could help with that, but I'm strictly a room availability assistant.",
        "My programming is limited to room booking queries. Ask me about available rooms!",
        "I'm flattered you'd ask, but I only know about room schedules and availability.",
        "That's beyond my capabilities! I'm here to help you book rooms, not chat about other topics.",
        "I'm a specialist in room bookings, so I'll stick to what I know best!",
        "Let's get back to rooms! I can help you find available time slots.",
        "I'm not equipped for that question, but I'm great at finding free rooms!",
        "My knowledge is strictly room-related. Want to check availability for a specific room?",
        "I'd rather not steer off topic - I'm here to help with room reservations!",
        "That's not in my database! But I can definitely check room availability for you.",
        "I'm a room booking expert, not a general assistant. Let me help you find a room!",
        "My programming focuses on room queries. Try asking about room 202 at 2pm!"
    ];
    return $responses[array_rand($responses)];
}

function getHelpResponse()
{
    $help_responses = [
        "I can help you check if rooms are available! Try asking: 'Is room 202 free at 2pm?' or 'Check the research lab for tomorrow morning'",
        "I'm your room availability assistant! Ask me things like: 'Is room 301 booked for 9am?' or 'What's available in the main building at 3pm?'",
        "I specialize in room bookings! Just ask: 'Is the smart lab available Friday at 10am?' or 'Check room 205 for next Monday'",
        "I can check room availability for you! Examples: 'Is room 102 free right now?' or 'Check if anyone booked the conference room tomorrow'",
        "Need to book a room? I can help! Try: 'Room 404 at 11am tomorrow' or 'Is the lecture hall available this afternoon?'"
    ];
    return $help_responses[array_rand($help_responses)];
}

function convertTo24Hour($time)
{
    $time = strtolower(trim($time));

    if ($time === 'noon')
        return '12:00:00';
    if ($time === 'morning')
        return '09:00:00';
    if ($time === 'afternoon')
        return '14:00:00';
    if ($time === 'evening')
        return '17:00:00';

    // Handle standalone am/pm
    if ($time === 'am')
        return '08:00:00';
    if ($time === 'pm')
        return '13:00:00';

    // Handle am/pm times
    if (strpos($time, 'am') !== false || strpos($time, 'pm') !== false) {
        return date('H:i:s', strtotime($time));
    }

    // Assume it's in 24-hour format or add :00
    if (strpos($time, ':') === false) {
        $time .= ':00';
    }

    return $time . ':00';
}

function checkRoomAvailability($room, $time, $date)
{
    global $mysqli;

    // Clear context after checking availability
    unset($_SESSION['chat_context']['last_room']);
    unset($_SESSION['chat_context']['awaiting_time']);

    // Find the facility
    $stmt = $mysqli->prepare("
        SELECT id, name, building 
        FROM facilities 
        WHERE name LIKE ? OR building LIKE ? OR name LIKE ? OR id = ?
    ");
    $search_term = "%$room%";
    $room_id = is_numeric($room) ? $room : 0;
    $stmt->bind_param('sssi', $search_term, $search_term, $search_term, $room_id);
    $stmt->execute();
    $facilities = $stmt->get_result();

    if ($facilities->num_rows === 0) {
        $responses = [
            "I couldn't find any room matching '$room'. Could you check the room name?",
            "Room '$room' doesn't seem to exist in our system. Please verify the room name.",
            "I don't see '$room' in our facilities list. Maybe try a different name?",
            "No rooms found matching '$room'. Are you sure that's the right room number?"
        ];
        return $responses[array_rand($responses)];
    }

    $available_rooms = [];
    $booked_rooms = [];

    while ($facility = $facilities->fetch_assoc()) {
        // Check for bookings at that time
        $check_stmt = $mysqli->prepare("
            SELECT r.id, u.full_name, r.start_time, r.end_time 
            FROM reservations r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.facility_id = ? 
            AND r.date = ? 
            AND r.status = 'approved'
            AND ? BETWEEN r.start_time AND r.end_time
        ");
        $check_stmt->bind_param('iss', $facility['id'], $date, $time);
        $check_stmt->execute();
        $booking_result = $check_stmt->get_result();

        if ($booking_result->num_rows > 0) {
            $booking = $booking_result->fetch_assoc();
            $booked_rooms[] = [
                'name' => $facility['name'],
                'booked_by' => $booking['full_name'],
                'time' => date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time']))
            ];
        } else {
            $available_rooms[] = $facility['name'];
        }
    }

    // Generate responses
    $date_display = date('F j, Y', strtotime($date));
    $time_display = date('g:i A', strtotime($time));

    if (!empty($booked_rooms)) {
        $booked_room = $booked_rooms[0];
        $responses = [
            "Yes, {$booked_room['name']} is already booked at $time_display on $date_display by {$booked_room['booked_by']}.",
            "Unfortunately {$booked_room['name']} is booked during that time. {$booked_room['booked_by']} has it reserved from {$booked_room['time']}.",
            "That time slot is taken! {$booked_room['name']} is booked by {$booked_room['booked_by']} for {$booked_room['time']} on $date_display.",
            "Sorry, {$booked_room['name']} is unavailable. It's booked by {$booked_room['booked_by']} during that period.",
            "Booked! {$booked_room['name']} is taken at $time_display by {$booked_room['booked_by']}.",
            "Not available. {$booked_room['name']} is reserved by {$booked_room['booked_by']} for {$booked_room['time']}."
        ];
        return $responses[array_rand($responses)];
    }

    if (!empty($available_rooms)) {
        $room_list = implode(', ', $available_rooms);
        $responses = [
            "Great news! $room_list is available at $time_display on $date_display!",
            "Perfect! $room_list is free during that time slot. You can book it now!",
            "Yes! $room_list is available at $time_display. Go ahead and make your reservation!",
            "That time works! $room_list is open and ready to be booked on $date_display.",
            "Available! $room_list is free at $time_display. Perfect timing!",
            "All clear! $room_list is available for booking at $time_display."
        ];
        return $responses[array_rand($responses)];
    }

    return "I'm not sure about the availability. Please try asking in a different way.";
}
?>