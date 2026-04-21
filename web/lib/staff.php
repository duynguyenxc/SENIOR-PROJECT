<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const INTERNAL_ROLE_SUPER_ADMIN = 'SuperAdmin';
const INTERNAL_ROLE_STAFF = 'Staff';

function fetch_staff_accounts(PDO $pdo): array {
  $stmt = $pdo->query(
    'SELECT adminId, email, role, isActive, createdTime
     FROM Admin
     WHERE role = "Staff"
     ORDER BY createdTime DESC, adminId DESC'
  );

  return $stmt->fetchAll();
}

function create_staff_account(PDO $pdo, string $email, string $password): void {
  $normalizedEmail = strtolower(trim($email));
  if ($normalizedEmail === '' || $password === '') {
    throw new InvalidArgumentException('Email and password are required.');
  }

  if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
    throw new InvalidArgumentException('Please enter a valid staff email address.');
  }

  if (strlen($password) < 8) {
    throw new InvalidArgumentException('Staff password must be at least 8 characters.');
  }

  $stmt = $pdo->prepare('SELECT adminId FROM Admin WHERE email = ? LIMIT 1');
  $stmt->execute([$normalizedEmail]);
  if ($stmt->fetch()) {
    throw new InvalidArgumentException('That email is already assigned to an internal account.');
  }

  $insert = $pdo->prepare(
    'INSERT INTO Admin (email, passwordHash, role, isActive, createdTime)
     VALUES (?, ?, ?, 1, ?)'
  );
  $insert->execute([
    $normalizedEmail,
    password_hash($password, PASSWORD_DEFAULT),
    INTERNAL_ROLE_STAFF,
    date('Y-m-d H:i:s'),
  ]);
}

function set_staff_account_active(PDO $pdo, int $adminId, bool $isActive): void {
  $stmt = $pdo->prepare(
    'UPDATE Admin
     SET isActive = ?
     WHERE adminId = ? AND role = ?'
  );
  $stmt->execute([$isActive ? 1 : 0, $adminId, INTERNAL_ROLE_STAFF]);
}

function reset_staff_account_password(PDO $pdo, int $adminId): string {
  $temporaryPassword = generate_temporary_password();

  $stmt = $pdo->prepare(
    'UPDATE Admin
     SET passwordHash = ?
     WHERE adminId = ? AND role = ?'
  );
  $stmt->execute([
    password_hash($temporaryPassword, PASSWORD_DEFAULT),
    $adminId,
    INTERNAL_ROLE_STAFF,
  ]);

  if ($stmt->rowCount() === 0) {
    throw new RuntimeException('Staff account not found.');
  }

  return $temporaryPassword;
}

function generate_temporary_password(int $length = 12): string {
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
  $maxIndex = strlen($alphabet) - 1;
  $password = '';

  for ($i = 0; $i < $length; $i++) {
    $password .= $alphabet[random_int(0, $maxIndex)];
  }

  return $password;
}
