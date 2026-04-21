<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';

ensure_session_started();

$redirectTarget = safe_redirect_target(
  (string)($_POST['redirect'] ?? $_GET['redirect'] ?? '/'),
  '/'
);

$error = null;
$ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_valid_csrf_token();
  $email = trim((string)($_POST['email'] ?? ''));
  $fullName = trim((string)($_POST['fullName'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($email === '' || $fullName === '' || $password === '') {
    $error = 'email, full name, and password are required';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'please enter a valid email';
  } elseif (strlen($password) < 8) {
    $error = 'password must be at least 8 characters';
  } else {
    try {
      $pdo = db();
      $stmt = $pdo->prepare('SELECT customerId FROM Customer WHERE email = ? LIMIT 1');
      $stmt->execute([$email]);
      $exists = $stmt->fetch();
      if ($exists) {
        $error = 'that email is already registered, please sign in';
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $created = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $ins = $pdo->prepare('INSERT INTO Customer (email, passwordHash, fullName, phone, createdTime) VALUES (?, ?, ?, ?, ?)');
        $ins->execute([$email, $hash, $fullName, $phone !== '' ? $phone : null, $created]);

        $customerId = (int)$pdo->lastInsertId();
        login_customer([
          'customerId' => $customerId,
          'email' => $email,
          'fullName' => $fullName,
        ]);

        header('Location: ' . $redirectTarget);
        exit;
      }
    } catch (Throwable $e) {
      $error = 'sign-up failed, please try again.';
    }
  }
}

render_header('Create account');
?>

<div class="centered-page">
  <section class="card card-accent-primary">
    <h2 class="section-title-centered">Create an Account</h2>
    <p class="muted section-subtitle-centered">Join Veg Buffet to track your orders easily.</p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php elseif ($ok): ?>
      <div class="alert alert-ok"><?= h($ok) ?></div>
    <?php endif; ?>

    <form method="post" action="/register.php" autocomplete="on">
      <?= csrf_input() ?>
      <input type="hidden" name="redirect" value="<?= h($redirectTarget) ?>" />
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required value="<?= h((string)($_POST['email'] ?? '')) ?>" />
      </div>
      <div class="field">
        <label for="fullName">Full name</label>
        <input id="fullName" name="fullName" type="text" required value="<?= h((string)($_POST['fullName'] ?? '')) ?>" />
      </div>
      <div class="field">
        <label for="phone">Phone (optional)</label>
        <input id="phone" name="phone" type="tel" value="<?= h((string)($_POST['phone'] ?? '')) ?>" />
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required minlength="8" />
      </div>
      <div class="btnrow btnrow-top-lg">
        <button class="btn btn-primary btn-full btn-lg" type="submit">Create Account</button>
      </div>
      <div class="auth-footer">
        <p class="muted">Already have an account?</p>
        <a href="/login.php?redirect=<?= urlencode($redirectTarget) ?>" class="btn btn-full">Sign in instead</a>
      </div>
    </form>
  </section>
</div>

<?php render_footer(); ?>

