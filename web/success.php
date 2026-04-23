<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/payment.php';
ensure_session_started();

$customer = require_customer('/my_orders.php');
$orderId = (int)($_GET['order_id'] ?? 0);
$sessionId = $_GET['session_id'] ?? '';

if ($orderId <= 0 || !$sessionId) {
    die("Invalid request.");
}

$pdo = db();
$order = fetch_order_for_customer($pdo, $orderId, (int)$customer['customerId']);
$paymentVerified = false;
$statusMessage = 'We could not match this payment return to an active order.';
$orderNumberLabel = '#' . $orderId;

if ($order) {
    $orderNumberLabel = display_order_number($order);
    if ((string)$order['status'] === ORDER_STATUS_PENDING) {
        try {
            $paymentVerified = finalize_payment_success($pdo, $orderId, $sessionId);
            $statusMessage = $paymentVerified
                ? 'Your order ' . $orderNumberLabel . ' has been verified and is being prepared.'
                : 'This payment return was already processed earlier for order ' . $orderNumberLabel . '.';
        } catch (Throwable $e) {
            die("Error finalizing payment: " . $e->getMessage());
        }
    } elseif (in_array((string)$order['status'], order_counted_revenue_statuses(), true)) {
        $statusMessage = 'Your order ' . $orderNumberLabel . ' was already verified earlier and is in the kitchen workflow.';
    } elseif ((string)$order['status'] === ORDER_STATUS_CANCELLED) {
        $statusMessage = 'Order ' . $orderNumberLabel . ' was already cancelled before payment could be finalized.';
    }
}

render_header('Payment Success');
?>
<section class="hero hero-centered">
  <div class="hero-message">✅</div>
  <h1 class="text-ok">Payment Successful!</h1>
  <p class="muted"><?= h($statusMessage) ?></p>
  <div class="btnrow btnrow-centered">
    <a href="/" class="btn btn-primary">Return Home</a>
    <a href="/my_orders.php" class="btn">View My Orders</a>
  </div>
</section>
<?php render_footer(); ?>
