<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/orders.php';
$cust = require_customer();

$pdo = db();
$stmt = $pdo->prepare("
  SELECT * FROM `Order` 
  WHERE customerId = ? 
  ORDER BY createdTime DESC
");
$stmt->execute([(int)$cust['customerId']]);
$orders = $stmt->fetchAll();

$itemsByOrder = fetch_order_items_by_order_ids($pdo, array_column($orders, 'orderId'));

render_header('My Orders | Veg Buffet');
?>
<section class="hero">
  <h1>My Orders</h1>
  <p class="muted">Track the status of your recent takeout orders.</p>
</section>

<div class="stack-lg">
  <?php if (!$orders): ?>
    <div class="alert">You haven't placed any orders yet. <a href="/takeout.php">Order now!</a></div>
  <?php else: ?>
    <div class="order-list">
      <?php foreach ($orders as $o): ?>
        <?php
          $statusClass = order_status_badge_class((string)$o['status']);
        ?>
        <div class="card order-card <?= $statusClass ?>">
          <div class="order-header">
            <div>
              <h3 class="order-title">Order #<?= str_pad((string)$o['dailyOrderNumber'], 3, '0', STR_PAD_LEFT) ?></h3>
              <div class="muted">Pickup: <strong><?= date('M j, Y g:i A', strtotime($o['pickupTime'])) ?></strong></div>
              <div class="muted meta-top-xs">Total: <strong>$<?= h($o['totalAmount']) ?></strong></div>
            </div>
            <div class="order-side">
              <strong class="status-badge <?= $statusClass ?>"><?= h($o['status']) ?></strong>
              <div class="muted meta-small-top">Ordered: <?= date('M j, g:i A', strtotime($o['createdTime'])) ?></div>
            </div>
          </div>
          <div class="order-items">
            <strong class="muted">Items:</strong>
            <ul class="order-items-list">
              <?php foreach (($itemsByOrder[$o['orderId']] ?? []) as $i): ?>
                <li><?= $i['quantity'] ?>x <?= h($i['setName']) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
