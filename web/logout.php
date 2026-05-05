<?php
declare(strict_types=1);

// Logout — clears both customer and admin sessions, then sends the user home.

require_once __DIR__ . '/lib/auth.php';
logout_customer();
logout_admin();
header('Location: /');
exit;
