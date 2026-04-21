<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/orders.php';
require_once __DIR__ . '/../lib/reporting.php';
$admin = require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['orderId'] ?? 0);
    
    if ($orderId > 0) {
        if ($action === 'prepare') {
            $pdo->prepare("UPDATE `Order` SET status = ? WHERE orderId = ?")->execute([ORDER_STATUS_PREPARING, $orderId]);
        } elseif ($action === 'ready') {
            $pdo->prepare("UPDATE `Order` SET status = ? WHERE orderId = ?")->execute([ORDER_STATUS_READY, $orderId]);
        } elseif ($action === 'complete') {
            $pdo->prepare("UPDATE `Order` SET status = ? WHERE orderId = ?")->execute([ORDER_STATUS_COMPLETED, $orderId]);
        } elseif ($action === 'cancel' && is_super_admin($admin)) {
            $pdo->prepare("UPDATE `Order` SET status = ? WHERE orderId = ?")->execute([ORDER_STATUS_CANCELLED, $orderId]);
        }
    }
    header('Location: /admin/index.php');
    exit;
}

$queueStatuses = order_active_queue_statuses();
$stmt = $pdo->prepare(
    'SELECT o.*, p.referenceId
     FROM `Order` o
     LEFT JOIN Payment p ON o.orderId = p.orderId
     WHERE o.status IN (' . sql_placeholders($queueStatuses) . ')
     ORDER BY o.pickupTime ASC'
);
$stmt->execute($queueStatuses);
$orders = $stmt->fetchAll();

$orderIds = array_column($orders, 'orderId');
$itemsByOrder = fetch_order_items_by_order_ids($pdo, $orderIds);
$todayKpi = fetch_sales_kpi($pdo, new DateTimeImmutable('today'), new DateTimeImmutable('today'));
$todayStatusCounts = fetch_status_breakdown($pdo, new DateTimeImmutable('today'), new DateTimeImmutable('today'));
$activeQueueCount = count($orders);

render_header('Dashboard');
?>
<section class="hero hero-compact">
  <h2>Active Fulfillment Queue</h2>
  <p class="muted">Manage and process orders in real time.</p>
</section>

<section class="metrics-grid stack-lg">
  <article class="card metric-card">
    <span class="metric-label">Today's Revenue</span>
    <strong class="metric-value">$<?= number_format($todayKpi['totalRevenue'], 2) ?></strong>
  </article>
  <article class="card metric-card">
    <span class="metric-label">Completed Today</span>
    <strong class="metric-value"><?= (int)($todayStatusCounts[ORDER_STATUS_COMPLETED] ?? 0) ?></strong>
  </article>
  <article class="card metric-card">
    <span class="metric-label">Active Queue</span>
    <strong class="metric-value"><?= $activeQueueCount ?></strong>
  </article>
</section>

<div class="stack-lg">
  <?php if (!$orders): ?>
    <div class="alert">No active orders at this time.</div>
  <?php else: ?>
    <div class="order-list">
      <?php foreach ($orders as $o): ?>
        <?php
          $statusClass = order_status_badge_class((string)$o['status']);
        ?>
        <div class="card order-card <?= $statusClass ?>">
          <div class="order-header">
            <div>
              <h3 class="order-title">Order #<?= str_pad((string)$o['dailyOrderNumber'], 3, '0', STR_PAD_LEFT) ?> - <?= h($o['customerName']) ?></h3>
              <div class="muted">Phone: <?= h($o['customerPhone']) ?> | Total: <strong>$<?= h($o['totalAmount']) ?></strong></div>
            </div>
            <div class="order-side">
              <strong class="status-badge <?= $statusClass ?>"><?= h($o['status']) ?></strong>
              <div class="pickup-time"><?= date('h:i A', strtotime($o['pickupTime'])) ?></div>
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
          
          <div class="order-actions">
            <?php if ($o['status'] === 'Paid'): ?>
              <form method="post" class="form-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="prepare" />
                <input type="hidden" name="orderId" value="<?= $o['orderId'] ?>" />
                <button type="submit" class="btn btn-warning-soft">Start Preparing</button>
              </form>
            <?php elseif ($o['status'] === 'Preparing'): ?>
              <form method="post" class="form-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="ready" />
                <input type="hidden" name="orderId" value="<?= $o['orderId'] ?>" />
                <button type="submit" class="btn btn-ok-soft">Mark Ready</button>
              </form>
            <?php elseif ($o['status'] === 'Ready'): ?>
              <form method="post" class="form-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="complete" />
                <input type="hidden" name="orderId" value="<?= $o['orderId'] ?>" />
                <button type="submit" class="btn btn-primary">Complete Order</button>
              </form>
            <?php endif; ?>
            
            <?php if (is_super_admin($admin)): ?>
              <form method="post" class="form-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="cancel" />
                <input type="hidden" name="orderId" value="<?= $o['orderId'] ?>" />
                <button type="submit" class="btn btn-danger-outline">Cancel Order</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php render_footer(); ?>
