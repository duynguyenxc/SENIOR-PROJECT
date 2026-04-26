<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/orders.php';
$cust = require_customer();

$pdo = db();
cancel_stale_pending_orders($pdo);
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

<div class="stack-lg" data-order-stream="/order_status_stream.php">
  <?php if (!$orders): ?>
    <div class="alert">You haven't placed any orders yet. <a href="/takeout.php">Order now!</a></div>
  <?php else: ?>
    <div class="order-list">
      <?php foreach ($orders as $o): ?>
        <?php
          $statusClass = order_status_badge_class((string)$o['status']);
          $orderNumberLabel = display_order_number($o);
        ?>
        <div class="card order-card <?= $statusClass ?>">
          <div class="order-header">
            <div>
              <h3 class="order-title">Order <?= h($orderNumberLabel) ?></h3>
              <div class="muted">Pickup: <strong><?= date('M j, Y g:i A', strtotime($o['pickupTime'])) ?></strong></div>
              <div class="muted meta-top-xs">Total: <strong>$<?= h($o['totalAmount']) ?></strong></div>
              <?php if (!empty($o['specialInstructions'])): ?>
                <div class="muted meta-top-xs">Special instructions: <?= h((string)$o['specialInstructions']) ?></div>
              <?php endif; ?>
              <?php if (!empty($o['allergyNotes'])): ?>
                <div class="muted meta-top-xs">Allergy notes: <?= h((string)$o['allergyNotes']) ?></div>
              <?php endif; ?>
            </div>
            <div class="order-side">
              <strong class="status-badge <?= $statusClass ?>"><?= h($o['status']) ?></strong>
              <div class="muted meta-small-top">Ordered: <?= date('M j, g:i A', strtotime($o['createdTime'])) ?></div>
            </div>
          </div>
          <?php if ((string)$o['status'] === ORDER_STATUS_PENDING): ?>
            <div class="order-pending-actions">
              <p class="muted text-sm">Payment was not completed for this order. You can resume payment or cancel it.</p>
              <div class="btnrow">
                <a href="/pay.php?orderId=<?= (int)$o['orderId'] ?>" class="btn btn-primary btn-sm">Resume Payment</a>
                <a href="/cancel.php?order_id=<?= (int)$o['orderId'] ?>" class="btn btn-danger btn-sm"
                   onclick="return confirm('Cancel this unpaid order?')">Cancel Order</a>
              </div>
            </div>
          <?php endif; ?>
          <div class="order-items">
            <strong class="muted">Items:</strong>
            <ul class="order-items-list">
              <?php foreach (($itemsByOrder[$o['orderId']] ?? []) as $i): ?>
                <li>
                  <?= $i['quantity'] ?>x <?= h((string)$i['lineLabel']) ?>
                  <?php if (!empty($i['lineDescription']) && (string)$i['lineType'] === ORDER_LINE_TYPE_CUSTOM): ?>
                    <div class="muted text-sm"><?= nl2br(h((string)$i['lineDescription'])) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($i['lineNotes'])): ?>
                    <div class="muted text-sm">Item note: <?= h((string)$i['lineNotes']) ?></div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
