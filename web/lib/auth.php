<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function session_should_use_secure_cookie(): bool {
  $https = $_SERVER['HTTPS'] ?? '';
  if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
    return true;
  }

  $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
  return is_string($forwardedProto) && strtolower($forwardedProto) === 'https';
}

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

function current_customer(): ?array {
  ensure_session_started();
  if (!isset($_SESSION['customer'])) return null;
  if (!is_array($_SESSION['customer'])) return null;
  return $_SESSION['customer'];
}

function safe_redirect_target(?string $target, string $default = '/'): string {
  if (!is_string($target) || $target === '') return $default;
  if ($target[0] !== '/') return $default;
  if (str_starts_with($target, '//')) return $default;
  return $target;
}

function require_customer(?string $redirectTo = null): array {
  $cust = current_customer();
  if ($cust !== null) return $cust;
  $target = safe_redirect_target($redirectTo ?? ($_SERVER['REQUEST_URI'] ?? '/'), '/');
  header('Location: /login.php?redirect=' . urlencode($target));
  exit;
}

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

function current_admin(): ?array {
  ensure_session_started();
  if (!isset($_SESSION['admin'])) return null;
  if (!is_array($_SESSION['admin'])) return null;

  $adminId = (int)($_SESSION['admin']['adminId'] ?? 0);
  if ($adminId <= 0) {
    unset($_SESSION['admin']);
    return null;
  }

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

function require_admin(): array {
  $admin = current_admin();
  if ($admin !== null) return $admin;
  header('Location: /admin/login.php');
  exit;
}

function is_super_admin(?array $admin): bool {
  return is_array($admin) && (($admin['role'] ?? '') === 'SuperAdmin');
}

function require_super_admin(): array {
  $admin = require_admin();
  if (is_super_admin($admin)) {
    return $admin;
  }

  http_response_code(403);
  exit('Access denied. Super admin permissions are required.');
}

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
