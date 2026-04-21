<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const ORDER_STATUS_PENDING = 'Pending';
const ORDER_STATUS_PAID = 'Paid';
const ORDER_STATUS_PREPARING = 'Preparing';
const ORDER_STATUS_READY = 'Ready';
const ORDER_STATUS_COMPLETED = 'Completed';
const ORDER_STATUS_CANCELLED = 'Cancelled';

function order_counted_revenue_statuses(): array {
  return [
    ORDER_STATUS_PAID,
    ORDER_STATUS_PREPARING,
    ORDER_STATUS_READY,
    ORDER_STATUS_COMPLETED,
  ];
}

function order_active_queue_statuses(): array {
  return [
    ORDER_STATUS_PAID,
    ORDER_STATUS_PREPARING,
    ORDER_STATUS_READY,
  ];
}

function order_status_badge_class(string $status): string {
  return match ($status) {
    ORDER_STATUS_PAID => 'status-paid',
    ORDER_STATUS_PREPARING => 'status-preparing',
    ORDER_STATUS_READY => 'status-ready',
    ORDER_STATUS_COMPLETED => 'status-completed',
    ORDER_STATUS_CANCELLED => 'status-cancelled',
    default => 'status-muted',
  };
}

function sql_placeholders(array $values): string {
  if ($values === []) {
    throw new InvalidArgumentException('At least one value is required.');
  }

  return implode(', ', array_fill(0, count($values), '?'));
}

function fetch_order_items_by_order_ids(PDO $pdo, array $orderIds): array {
  if ($orderIds === []) {
    return [];
  }

  $stmt = $pdo->prepare(
    'SELECT oi.orderId, oi.quantity, ts.setName
     FROM OrderItem oi
     JOIN TakeoutSet ts ON oi.setId = ts.setId
     WHERE oi.orderId IN (' . sql_placeholders($orderIds) . ')'
  );
  $stmt->execute($orderIds);

  $itemsByOrder = [];
  foreach ($stmt->fetchAll() as $item) {
    $itemsByOrder[(int)$item['orderId']][] = $item;
  }

  return $itemsByOrder;
}

function fetch_pending_order_for_customer(PDO $pdo, int $orderId, int $customerId): ?array {
  $stmt = $pdo->prepare(
    'SELECT *
     FROM `Order`
     WHERE orderId = ? AND customerId = ? AND status = ?
     LIMIT 1'
  );
  $stmt->execute([$orderId, $customerId, ORDER_STATUS_PENDING]);
  $order = $stmt->fetch();

  return is_array($order) ? $order : null;
}

function fetch_order_takeout_items(PDO $pdo, int $orderId): array {
  $stmt = $pdo->prepare(
    'SELECT oi.quantity, ts.setName, ts.price
     FROM OrderItem oi
     JOIN TakeoutSet ts ON oi.setId = ts.setId
     WHERE oi.orderId = ?
     ORDER BY ts.setName'
  );
  $stmt->execute([$orderId]);
  return $stmt->fetchAll();
}

function next_daily_order_number(PDO $pdo, string $createdTime): int {
  $stmt = $pdo->prepare(
    'SELECT IFNULL(MAX(dailyOrderNumber), 0) + 1
     FROM `Order`
     WHERE DATE(createdTime) = DATE(?)'
  );
  $stmt->execute([$createdTime]);
  return (int)$stmt->fetchColumn();
}

function fetch_takeout_price_map(PDO $pdo, array $cart): array {
  if ($cart === []) {
    throw new InvalidArgumentException('Cart cannot be empty.');
  }

  $setIds = array_map('intval', array_keys($cart));
  $stmt = $pdo->prepare(
    'SELECT setId, price
     FROM TakeoutSet
     WHERE setId IN (' . sql_placeholders($setIds) . ')'
  );
  $stmt->execute($setIds);

  $priceMap = [];
  foreach ($stmt->fetchAll() as $row) {
    $priceMap[(int)$row['setId']] = (float)$row['price'];
  }

  if (count($priceMap) !== count(array_unique($setIds))) {
    throw new RuntimeException('One or more cart items are no longer available.');
  }

  return $priceMap;
}

function calculate_cart_total_amount(PDO $pdo, array $cart): float {
  $priceMap = fetch_takeout_price_map($pdo, $cart);
  $totalAmount = 0.0;

  foreach ($cart as $setId => $quantity) {
    $normalizedSetId = (int)$setId;
    $normalizedQuantity = (int)$quantity;
    if (isset($priceMap[$normalizedSetId]) && $normalizedQuantity > 0) {
      $totalAmount += $priceMap[$normalizedSetId] * $normalizedQuantity;
    }
  }

  return $totalAmount;
}

function create_takeout_order_from_cart(
  PDO $pdo,
  int $customerId,
  string $customerName,
  string $customerPhone,
  string $pickupTime,
  array $cart
): int {
  if ($cart === []) {
    throw new InvalidArgumentException('Cart cannot be empty.');
  }

  $createdTime = date('Y-m-d H:i:s');
  $pickupDateTime = date('Y-m-d') . ' ' . $pickupTime . ':00';
  $totalAmount = calculate_cart_total_amount($pdo, $cart);
  $dailyOrderNumber = next_daily_order_number($pdo, $createdTime);

  $pdo->beginTransaction();

  try {
    $insertOrder = $pdo->prepare(
      'INSERT INTO `Order`
        (dailyOrderNumber, customerId, customerName, customerPhone, pickupTime, status, totalAmount, createdTime)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertOrder->execute([
      $dailyOrderNumber,
      $customerId,
      $customerName,
      $customerPhone,
      $pickupDateTime,
      ORDER_STATUS_PENDING,
      $totalAmount,
      $createdTime,
    ]);

    $orderId = (int)$pdo->lastInsertId();

    $insertItem = $pdo->prepare(
      'INSERT INTO OrderItem (orderId, setId, quantity) VALUES (?, ?, ?)'
    );
    foreach ($cart as $setId => $quantity) {
      $insertItem->execute([$orderId, (int)$setId, (int)$quantity]);
    }

    $pdo->commit();
    return $orderId;
  } catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
  }
}

function mark_order_paid(PDO $pdo, int $orderId, string $referenceId): bool {
  $stmt = $pdo->prepare('SELECT status FROM `Order` WHERE orderId = ? LIMIT 1');
  $stmt->execute([$orderId]);
  $order = $stmt->fetch();

  if (!$order) {
    throw new RuntimeException('Order not found.');
  }

  if ((string)$order['status'] !== ORDER_STATUS_PENDING) {
    return false;
  }

  $pdo->beginTransaction();

  try {
    $updateOrder = $pdo->prepare('UPDATE `Order` SET status = ? WHERE orderId = ?');
    $updateOrder->execute([ORDER_STATUS_PAID, $orderId]);

    $upsertPayment = $pdo->prepare(
      'INSERT INTO Payment (orderId, paymentStatus, referenceId)
       VALUES (?, ?, ?)
       ON DUPLICATE KEY UPDATE
         paymentStatus = VALUES(paymentStatus),
         referenceId = VALUES(referenceId)'
    );
    $upsertPayment->execute([$orderId, 'Succeeded', $referenceId]);

    $pdo->commit();
    return true;
  } catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
  }
}
