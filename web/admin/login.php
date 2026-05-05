<?php
declare(strict_types=1);

// Admin/Staff login page.
// Authenticates against the Admin table, checks isActive, then redirects to the dashboard.

require_once __DIR__ . '/../lib/layout.php';
ensure_session_started();

if (current_admin()) {
  header('Location: /admin/index.php');
  exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_valid_csrf_token();
  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($email === '' || $password === '') {
    $error = 'Email and password are required.';
  } else {
    try {
      $pdo = db();
      
      $stmt = $pdo->prepare('SELECT adminId, email, passwordHash, role, isActive FROM Admin WHERE email = ? LIMIT 1');
      $stmt->execute([$email]);
      $row = $stmt->fetch();
      
      if (
        !$row
        || !(bool)$row['isActive']
        || !password_verify($password, (string)$row['passwordHash'])
      ) {
        $error = 'Invalid admin credentials.';
      } else {
        login_admin($row);
        header('Location: /admin/index.php');
        exit;
      }
    } catch (Throwable $e) {
      $error = 'Sign-in failed. Please try again.';
    }
  }
}

render_header('Internal Staff Portal | Veg Buffet');
?>

<div class="centered-page">
  <section class="card card-accent-accent">
    <div class="text-center">
      <span class="status-pill text-accent">Internal Portal</span>
    </div>
    <h2 class="section-title-centered">Staff And Super Admin Login</h2>
    <p class="muted section-subtitle-centered">Internal accounts are created and managed by the super admin.</p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/login.php" autocomplete="off">
      <?= csrf_input() ?>
      <div class="field">
        <label for="email">Staff Email</label>
        <input id="email" name="email" type="email" required value="<?= h((string)($_POST['email'] ?? '')) ?>" />
      </div>
      <div class="field field-top-md">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required />
      </div>
      <div class="btnrow btnrow-top-lg">
        <button class="btn btn-primary btn-full btn-lg" type="submit">Access Dashboard</button>
      </div>
    </form>
  </section>
</div>

<?php render_footer(); ?>
