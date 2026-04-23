<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/payment.php';

$customer = current_customer();
$orderId = (int)($_GET['order_id'] ?? 0);
$orderWasCancelled = false;

if ($orderId > 0 && is_array($customer)) {
  $orderWasCancelled = cancel_pending_order_for_customer(db(), $orderId, (int)$customer['customerId']);
}

$message = 'You cancelled the checkout process before payment could be completed.';
if ($orderId > 0) {
  $message = $orderWasCancelled
    ? 'Your unpaid order has been cancelled and removed from the active payment flow.'
    : 'This order was already updated earlier, so there was nothing left to cancel here.';
}

render_header('Payment Cancelled');
?>
<section class="hero hero-centered">
  <div class="hero-message">❌</div>
  <h1 class="text-danger">Payment Cancelled</h1>
  <p class="muted"><?= h($message) ?></p>
  <div class="btnrow btnrow-centered">
    <?php if ($orderId > 0 && is_array($customer)): ?>
      <a href="/takeout.php" class="btn btn-primary">Return to Takeout</a>
      <a href="/my_orders.php" class="btn">View My Orders</a>
    <?php else: ?>
      <a href="/cart.php" class="btn btn-primary">Return to Cart</a>
    <?php endif; ?>
  </div>
</section>
<?php render_footer(); ?>
