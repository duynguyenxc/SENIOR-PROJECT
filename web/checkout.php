<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/orders.php';
ensure_session_started();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['cart'])) {
    header('Location: /cart.php');
    exit;
}

require_valid_csrf_token();

$cust = require_customer('/cart.php');

$name = trim($_POST['customerName'] ?? '');
$phone = trim($_POST['customerPhone'] ?? '');
$time = trim($_POST['pickupTime'] ?? '');

if (!$name || !$phone || !$time) {
    die("Invalid input");
} 

try {
    $orderId = create_takeout_order_from_cart(
        db(),
        (int)$cust['customerId'],
        $name,
        $phone,
        $time,
        $_SESSION['cart']
    );
    $_SESSION['cart'] = []; //clear cart
    
    header('Location: /pay.php?orderId=' . $orderId);
    exit;
} catch (Exception $e) {
    die("Checkout failed: " . $e->getMessage());
}
