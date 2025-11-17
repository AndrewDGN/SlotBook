<?php
session_start();
require_once "includes/config.php"; // Database connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $sql = "SELECT id, full_name, email, password_hash, role FROM users WHERE email = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row["password_hash"])) {
            $_SESSION["user_id"] = $row["id"];
            $_SESSION["full_name"] = $row["full_name"];
            $_SESSION["role"] = $row["role"];

            // Normalize the role (trim + lowercase)
            $role = strtolower(trim($row["role"]));

            if ($role === "admin") {
                header("Location: dashboard_admin.php");
                exit;
            } elseif ($role === "faculty") {
                header("Location: dashboard_f.php");
                exit;
            } else {
                echo "⚠ Unknown role type detected: " . htmlspecialchars($role);
                exit;
            }
        } else {
            $error = "Incorrect email or password.";
        }
    } else {
        $error = "Incorrect email or password.";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="css\login.css">

</head>

<body>
    <div class="login-container">
        <h2>SlotBook Login</h2>
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label><br>
                <input type="text" id="email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label><br>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <p>Don’t have an account? <a href="register.php">Register here</a></p>
    </div>
</body>

</html>