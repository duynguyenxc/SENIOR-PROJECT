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

if ($order) {
    try {
        finalize_payment_success($pdo, $orderId, $sessionId);
    } catch (Throwable $e) {
        die("Error finalizing payment: " . $e->getMessage());
    }
}

render_header('Payment Success');
?>
<section class="hero hero-centered">
  <div class="hero-message">✅</div>
  <h1 class="text-ok">Payment Successful!</h1>
  <p class="muted">Your order #<?= $orderId ?> has been paid and is being prepared.</p>
  <div class="btnrow btnrow-centered">
    <a href="/" class="btn btn-primary">Return Home</a>
  </div>
</section>
<?php render_footer(); ?>
