<?php
declare(strict_types=1);

/**
 * Authentication and session management for both customer and admin users.
 */

require_once __DIR__ . '/db.php';

// --- Session setup ---

/** Check if the current request is served over HTTPS (direct or behind a proxy). */
function session_should_use_secure_cookie(): bool {
  $https = $_SERVER['HTTPS'] ?? '';
  if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
    return true;
  }

  $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
  return is_string($forwardedProto) && strtolower($forwardedProto) === 'https';
}

/** Start the session with secure cookie settings if it hasn't started yet. */
function ensure_session_started(): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
      'httponly' => true,
      'samesite' => 'Lax',
      'secure' => session_should_use_secure_cookie(),
    ]);
    session_start();
  }
}

// --- Customer auth ---

/** Return the logged-in customer's session data, or null if not logged in. */
function current_customer(): ?array {
  ensure_session_started();
  if (!isset($_SESSION['customer'])) return null;
  if (!is_array($_SESSION['customer'])) return null;
  return $_SESSION['customer'];
}

/** Sanitize a redirect target to prevent open-redirect attacks. */
function safe_redirect_target(?string $target, string $default = '/'): string {
  if (!is_string($target) || $target === '') return $default;
  if ($target[0] !== '/') return $default;
  if (str_starts_with($target, '//')) return $default;
  return $target;
}

/** Render a standalone error page (used for access-denied scenarios). */
function exit_with_html_error_page(
  int $statusCode,
  string $title,
  string $heading,
  string $message,
  string $primaryHref = '/',
  string $primaryLabel = 'Return Home'
): never {
  http_response_code($statusCode);
  ?>
  <!doctype html>
  <html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/style.css" />
  </head>
  <body>
    <main class="container">
      <section class="hero hero-centered">
        <div class="hero-message">403</div>
        <h1><?= htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
        <p class="muted"><?= htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <div class="btnrow btnrow-centered">
          <a href="<?= htmlspecialchars($primaryHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn btn-primary">
            <?= htmlspecialchars($primaryLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          </a>
          <a href="/" class="btn">Public Site</a>
        </div>
      </section>
    </main>
  </body>
  </html>
  <?php
  exit;
}

/** Require a logged-in customer; redirect to login page if not authenticated. */
function require_customer(?string $redirectTo = null): array {
  $cust = current_customer();
  if ($cust !== null) return $cust;
  $target = safe_redirect_target($redirectTo ?? ($_SERVER['REQUEST_URI'] ?? '/'), '/');
  header('Location: /login.php?redirect=' . urlencode($target));
  exit;
}

/** Save customer info into the session after successful login. */
function login_customer(array $customerRow): void {
  ensure_session_started();
  session_regenerate_id(true);
  $_SESSION['customer'] = [
    'customerId' => (int)$customerRow['customerId'],
    'email' => (string)$customerRow['email'],
    'fullName' => (string)$customerRow['fullName'],
  ];
}

function logout_customer(): void {
  ensure_session_started();
  unset($_SESSION['customer']);
}

// --- Admin/Staff auth ---

/**
 * Return the current admin's session data, or null.
 * Re-validates against the database on every request to catch deactivated accounts.
 */
function current_admin(): ?array {
  ensure_session_started();
  if (!isset($_SESSION['admin'])) return null;
  if (!is_array($_SESSION['admin'])) return null;

  $adminId = (int)($_SESSION['admin']['adminId'] ?? 0);
  if ($adminId <= 0) {
    unset($_SESSION['admin']);
    return null;
  }

  // Re-check DB so a deactivated account is immediately locked out
  try {
    $stmt = db()->prepare('SELECT adminId, email, role, isActive FROM Admin WHERE adminId = ? LIMIT 1');
    $stmt->execute([$adminId]);
    $row = $stmt->fetch();
  } catch (Throwable $e) {
    return $_SESSION['admin'];
  }

  if (!$row || !(bool)$row['isActive']) {
    unset($_SESSION['admin']);
    return null;
  }

  $_SESSION['admin'] = [
    'adminId' => (int)$row['adminId'],
    'email' => (string)$row['email'],
    'role' => (string)$row['role'],
  ];

  return $_SESSION['admin'];
}

/** Require an admin session; redirect to the admin login if not authenticated. */
function require_admin(): array {
  $admin = current_admin();
  if ($admin !== null) return $admin;
  header('Location: /admin/login.php');
  exit;
}

function is_super_admin(?array $admin): bool {
  return is_array($admin) && (($admin['role'] ?? '') === 'SuperAdmin');
}

/** Require SuperAdmin access; show an access-denied page for regular Staff. */
function require_super_admin(): array {
  $admin = require_admin();
  if (is_super_admin($admin)) {
    return $admin;
  }

  exit_with_html_error_page(
    200,
    'Access Denied',
    'Super Admin Access Required',
    'This page is limited to the super admin account. Staff members can continue using the order queue and order history.',
    '/admin/index.php',
    'Back to Dashboard'
  );
}

/** Save admin info into the session after successful login. */
function login_admin(array $row): void {
  ensure_session_started();
  session_regenerate_id(true);
  $_SESSION['admin'] = [
    'adminId' => (int)$row['adminId'],
    'email' => (string)$row['email'],
    'role' => (string)$row['role']
  ];
}

function logout_admin(): void {
  ensure_session_started();
  unset($_SESSION['admin']);
}
