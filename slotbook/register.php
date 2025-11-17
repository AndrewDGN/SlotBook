<?php
require_once __DIR__ . '/includes/config.php';


$errors = [];

// SECRET ADMIN AUTHENTICATION PASSWORD HERE
$valid_admin_key = "BPSU_ADMIN_2025_SECRET";

// Get role from URL parameter 
$role = $_GET['role'] ?? 'faculty';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name = trim($_POST['full_name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';
  $admin_key = trim($_POST['admin_key'] ?? '');

  // Use the role from URL, not from form
  $role = $_GET['role'] ?? 'faculty';

  // Validate admin registration
  if ($role === 'admin') {
    if ($admin_key !== $valid_admin_key) {
      $errors[] = "Invalid admin authorization key! Only authorized personnel can create admin accounts.";
    }
  }

  // Validation
  if ($full_name === '' || $email === '' || $password === '' || $confirm === '') {
    $errors[] = "All fields are required.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Enter a valid email.";
  } elseif (!preg_match('/@bpsu\.edu\.ph$/i', $email)) {
    $errors[] = "Please use your BPSU email (@bpsu.edu.ph).";
  } elseif ($password !== $confirm) {
    $errors[] = "Passwords do not match.";
  } elseif (strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters.";
  }

  if (empty($errors)) {
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      $errors[] = "An account with that email already exists.";
    } else {
      $stmt->close();
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $mysqli->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)");
      $stmt->bind_param("ssss", $full_name, $email, $hash, $role);
      if ($stmt->execute()) {
        // Redirect based on role
        if ($role === 'admin') {
          header("Location: login.php?role=admin&registered=1");
        } else {
          header("Location: login.php?role=faculty&registered=1");
        }
        exit;
      } else {
        $errors[] = "Error creating account. Please try again.";
      }
    }
  }
}
?>

<link rel="stylesheet" href="css\register.css">
<style>
  .admin-key-group {
    <?php echo $role === 'admin' ? 'display: block;' : 'display: none;'; ?>
    margin-top: 10px;
    padding: 10px;
    background: #fff3cd;
    border-radius: 5px;
    border-left: 4px solid #ffc107;
  }

  .admin-note {
    font-size: 0.8rem;
    color: #666;
    margin-top: 5px;
  }
</style>

<main class="container">
  <div class="card">
    <h2>Create Account</h2>

    <!-- Show the selected role as a badge -->
    <div class="role-badge">
      <?php echo htmlspecialchars(ucfirst($role)); ?> Registration
    </div>

    <?php if ($errors): ?>
      <div class="error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <form method="post">
      <!-- Admin authorization key field (only shown for admin registration) -->
      <?php if ($role === 'admin'): ?>
        <div class="form-field admin-key-group" id="adminKeyGroup">
          <label>Admin Authorization Key:</label>
          <input type="password" name="admin_key" placeholder="Enter secret admin key" required>
          <div class="admin-note">🔐 Required for administrator registration</div>
        </div>
      <?php endif; ?>

      <div class="form-field">
        <label>Full Name</label>
        <input name="full_name" value="<?= isset($full_name) ? htmlspecialchars($full_name) : '' ?>" required>
      </div>

      <div class="form-field">
        <label>Email</label>
        <input type="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>
      </div>

      <div class="form-field">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>

      <div class="form-field">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required>
      </div>

      <button class="btn" type="submit">Register</button>
      <button type="button" class="btn-secondary" onclick="window.location.href='index.php'">Back to Roles</button>

      <p>
        <br>
        Already have an account?
        <a href="login.php">Login here</a>
      </p>
    </form>
  </div>
</main>