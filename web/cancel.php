<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/payment.php';
$orderId = (int)($_GET['order_id'] ?? 0);
render_header('Payment Cancelled');
?>
<section class="hero hero-centered">
  <div class="hero-message">❌</div>
  <h1 class="text-danger">Payment Cancelled</h1>
  <p class="muted"><?= h(payment_cancel_message($orderId)) ?></p>
  <div class="btnrow btnrow-centered">
    <?php if ($orderId > 0): ?>
      <a href="/pay.php?orderId=<?= $orderId ?>" class="btn btn-primary">Try Payment Again</a>
      <a href="/my_orders.php" class="btn">View My Orders</a>
    <?php else: ?>
      <a href="/cart.php" class="btn btn-primary">Return to Cart</a>
    <?php endif; ?>
  </div>
</section>
<?php render_footer(); ?>
