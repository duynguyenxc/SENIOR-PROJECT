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
$order = fetch_pending_order_for_customer($pdo, $orderId, (int)$customer['customerId']);
$paymentVerified = false;

if ($order) {
    try {
        $paymentVerified = finalize_payment_success($pdo, $orderId, $sessionId);
    } catch (Throwable $e) {
        die("Error finalizing payment: " . $e->getMessage());
    }
}

render_header('Payment Success');
?>
<section class="hero hero-centered">
  <div class="hero-message">✅</div>
  <h1 class="text-ok">Payment Successful!</h1>
  <p class="muted">
    <?php if ($paymentVerified || !$order): ?>
      Your order #<?= $orderId ?> has been verified and is being prepared.
    <?php else: ?>
      We received your payment return and are finalizing order #<?= $orderId ?> now.
    <?php endif; ?>
  </p>
  <div class="btnrow btnrow-centered">
    <a href="/" class="btn btn-primary">Return Home</a>
    <a href="/my_orders.php" class="btn">View My Orders</a>
  </div>
</section>
<?php render_footer(); ?>
