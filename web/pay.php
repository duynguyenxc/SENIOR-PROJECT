<?php
declare(strict_types=1);

// Payment redirect — sends the customer to Stripe Checkout (or mock success).
// No UI here, just a server-side redirect.

require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/payment.php';
ensure_session_started();

$customer = require_customer('/cart.php');
$orderId = (int)($_GET['orderId'] ?? 0);
if ($orderId <= 0) {
    die("Invalid order");
}

try {
    $redirectUrl = create_payment_redirect_url(db(), $orderId, (int)$customer['customerId']);
    header('Location: ' . $redirectUrl);
    exit;
} catch (Throwable $e) {
    die("Payment initialization failed: " . $e->getMessage());
}
