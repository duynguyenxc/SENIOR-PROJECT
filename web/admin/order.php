<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/orders.php';

$admin = require_admin();
$pdo = db();
$orderId = (int)($_GET['orderId'] ?? 0);
$order = $orderId > 0 ? fetch_order_detail($pdo, $orderId) : null;
$itemsByOrder = $order ? fetch_order_items_by_order_ids($pdo, [$orderId]) : [];
$items = $itemsByOrder[$orderId] ?? [];

render_header('Order Details');
?>
<section class="hero hero-compact">
  <h2>Order Detail</h2>
  <p class="muted">Review a complete order record without changing permissions for <?= h((string)$admin['role']) ?> accounts.</p>
</section>

<div class="stack-lg">
  <?php if (!$order): ?>
    <div class="alert alert-danger">Order not found.</div>
  <?php else: ?>
    <?php $statusClass = order_status_badge_class((string)$order['status']); ?>
    <section class="card">
      <div class="section-header">
        <div>
          <h3 class="section-title">Order <?= h(display_order_number($order)) ?></h3>
          <p class="muted">Placed by <?= h((string)$order['customerName']) ?> on <?= date('M j, Y g:i A', strtotime((string)$order['createdTime'])) ?></p>
        </div>
        <span class="status-badge <?= $statusClass ?>"><?= h((string)$order['status']) ?></span>
      </div>

      <div class="detail-grid">
        <div>
          <h4>Pickup</h4>
          <p><?= date('M j, Y g:i A', strtotime((string)$order['pickupTime'])) ?></p>
        </div>
        <div>
          <h4>Contact</h4>
          <p><?= h((string)$order['customerPhone']) ?></p>
        </div>
        <div>
          <h4>Total</h4>
          <p>$<?= number_format((float)$order['totalAmount'], 2) ?></p>
        </div>
        <div>
          <h4>Payment Reference</h4>
          <p><?= h((string)($order['referenceId'] ?? 'Not recorded yet')) ?></p>
        </div>
      </div>

      <?php if (!empty($order['specialInstructions']) || !empty($order['allergyNotes'])): ?>
        <div class="detail-grid">
          <div>
            <h4>Special Instructions</h4>
            <div class="note-callout note-callout-danger">
              <?= nl2br(h((string)($order['specialInstructions'] ?? 'None'))) ?>
            </div>
          </div>
          <div>
            <h4>Allergy Notes</h4>
            <div class="note-callout note-callout-danger">
              <?= nl2br(h((string)($order['allergyNotes'] ?? 'None'))) ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </section>

    <section class="card">
      <h3 class="section-title">Ordered Items</h3>
      <div class="order-list">
        <?php foreach ($items as $item): ?>
          <article class="order-items order-item-detail">
            <div class="section-header">
              <div>
                <strong><?= (int)$item['quantity'] ?>x <?= h((string)$item['lineLabel']) ?></strong>
                <div class="muted text-sm">$<?= number_format((float)$item['unitPrice'], 2) ?> each</div>
              </div>
              <span class="status-pill <?= (string)$item['lineType'] === ORDER_LINE_TYPE_CUSTOM ? 'status-paid' : 'status-ready' ?>">
                <?= h(ucfirst((string)$item['lineType'])) ?>
              </span>
            </div>
            <?php if (!empty($item['lineDescription'])): ?>
              <p class="muted"><?= nl2br(h((string)$item['lineDescription'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($item['lineNotes'])): ?>
              <div class="note-callout note-callout-danger note-callout-inline">
                <strong>Item note:</strong> <?= nl2br(h((string)$item['lineNotes'])) ?>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<?php render_footer(); ?>
