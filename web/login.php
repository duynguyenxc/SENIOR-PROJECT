<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
ensure_session_started();

$redirectTarget = safe_redirect_target(
  (string)($_POST['redirect'] ?? $_GET['redirect'] ?? '/'),
  '/'
);

if (current_customer()) {
  header('Location: ' . $redirectTarget);
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
      
      $stmt = $pdo->prepare('SELECT customerId, email, passwordHash, fullName FROM Customer WHERE email = ? LIMIT 1');
      $stmt->execute([$email]);
      $row = $stmt->fetch();
      
      if (!$row || !password_verify($password, (string)$row['passwordHash'])) {
        $error = 'Invalid email or password.';
      } else {
        login_customer($row);
        header('Location: ' . $redirectTarget);
        exit;
      }
    } catch (Throwable $e) {
      $error = 'Sign-in failed. Please try again.';
    }
  }
}

render_header('Customer Login | Veg Buffet');
?>

<div class="centered-page">
  <section class="card card-accent-primary">
    <h2 class="section-title-centered">Customer Login</h2>
    <p class="muted section-subtitle-centered">Welcome back! Please sign in to track your orders.</p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login.php" autocomplete="on">
      <?= csrf_input() ?>
      <input type="hidden" name="redirect" value="<?= h($redirectTarget) ?>" />
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required value="<?= h((string)($_POST['email'] ?? '')) ?>" />
      </div>
      <div class="field field-top-md">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required />
      </div>
      <div class="btnrow btnrow-top-lg">
        <button class="btn btn-primary btn-full btn-lg" type="submit">Log In</button>
      </div>
      <div class="auth-footer">
        <p class="muted">Don't have an account?</p>
        <a href="/register.php?redirect=<?= urlencode($redirectTarget) ?>" class="btn btn-full">Create a customer account</a>
      </div>
    </form>
  </section>
</div>

<?php render_footer(); ?>
