<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Restrict access to logged-in faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header('Location: login.php');
    exit;
}

// Fetch facilities with status
$query = "SELECT * FROM facilities ORDER BY name ASC";
$result = $mysqli->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilities</title>
    <link rel="stylesheet" href="css\facilities_f.css">
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
        <section>
            <h2>Facilities</h2>
            <p>Browse and reserve BPSU facilities</p>
        </section>

        <section class="filter-bar">
            <input type="text" id="searchInput" placeholder="Search facilities..." onkeyup="filterFacilities()">
            <select id="filterStatus" onchange="filterFacilities()">
                <option value="">All Status</option>
                <option value="available">Available</option>
                <option value="occupied">Occupied</option>
                <option value="maintenance">Maintenance</option>
            </select>
        </section>

        <section>
            <div class="facility-grid">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                        // Determine status for display and filtering
                        $status = $row['status'] ?? 'available';
                        $status_display = ucfirst($status);
                        $status_class = $status;

                        // Check if facility is available for reservation
                        $can_reserve = ($status === 'available');
                        ?>

                        <div class="facility-card" data-status="<?= $status ?>">
                            <div>
                                <h3><?= htmlspecialchars($row['name']); ?></h3>
                                <p>Building: <?= htmlspecialchars($row['building']); ?></p>
                                <p>Capacity: <?= htmlspecialchars($row['capacity']); ?></p>
                                <p>Status:
                                    <span class="status <?= $status_class ?>">
                                        <?= $status_display ?>
                                    </span>
                                </p>
                            </div>
                            <div class="button-group">
                                <?php if ($can_reserve): ?>
                                    <a href="reserve.php?facility_id=<?= $row['id']; ?>" class="reserve-btn">Reserve</a>
                                <?php else: ?>
                                    <button class="reserve-btn disabled" disabled>
                                        <?= $status === 'maintenance' ? 'Under Maintenance' : 'Not Available' ?>
                                    </button>
                                <?php endif; ?>
                                <a href="calendar_view.php?facility_id=<?= $row['id']; ?>" class="calendar-btn">View
                                    Calendar</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="font-size: 16px; color: #666;">No facilities found.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer>
        © 2025 SlotBook
    </footer>

    <script>
        function filterFacilities() {
            const searchInput = document.getElementById("searchInput").value.toLowerCase();
            const filterStatus = document.getElementById("filterStatus").value;
            const facilityCards = document.querySelectorAll(".facility-card");

            facilityCards.forEach(card => {
                const name = card.querySelector("h3").textContent.toLowerCase();
                const status = card.getAttribute("data-status");
                const matchesSearch = name.includes(searchInput);
                const matchesStatus = filterStatus === "" || status === filterStatus;
                card.style.display = (matchesSearch && matchesStatus) ? "flex" : "none";
            });
        }
    </script>

</body>

</html>