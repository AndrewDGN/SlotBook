<link rel="stylesheet" href="css\index.css">
<main class="container">
  <div class="card">
    <h2>Welcome to SlotBook</h2>
    <p>Choose your login type:</p>
    <div class="login-cards">
      <div class="login-card" onclick="window.location.href='register.php?role=admin'">
        <h3>Admin</h3>
        <p>Administrators manage facilities and requests.</p>
      </div>
      <div class="login-card" onclick="window.location.href='register.php?role=faculty'">
        <h3>Faculty</h3>
        <p>Faculty can request reservations and view schedules.</p>
      </div>
    </div>
  </div>
</main>


  