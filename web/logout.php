<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
logout_customer();
logout_admin();
header('Location: /');
exit;
