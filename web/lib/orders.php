<?php
declare(strict_types=1);

/**
 * Order management: cart operations, order creation, status transitions,
 * and payment finalization. This is the core business-logic file.
 */

require_once __DIR__ . '/auth.php';

// --- Order lifecycle statuses ---
const ORDER_STATUS_PENDING = 'Pending';
const ORDER_STATUS_PAID = 'Paid';
const ORDER_STATUS_PREPARING = 'Preparing';
const ORDER_STATUS_READY = 'Ready';
const ORDER_STATUS_COMPLETED = 'Completed';
const ORDER_STATUS_CANCELLED = 'Cancelled';

// Cart line types
const ORDER_LINE_TYPE_SET = 'set';
const ORDER_LINE_TYPE_CUSTOM = 'custom';
const CUSTOM_TAKEOUT_DEFAULT_LIMIT = 6;
const STALE_PENDING_ORDER_MINUTES = 90;

/** Statuses that count toward revenue numbers. */
function order_counted_revenue_statuses(): array {
  return [
    ORDER_STATUS_PAID,
    ORDER_STATUS_PREPARING,
    ORDER_STATUS_READY,
    ORDER_STATUS_COMPLETED,
  ];
}

/** Statuses shown in the staff active queue (excludes Pending and Completed). */
function order_active_queue_statuses(): array {
  return [
    ORDER_STATUS_PAID,
    ORDER_STATUS_PREPARING,
    ORDER_STATUS_READY,
  ];
}

/** Map a status string to a CSS class for badge styling. */
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

/** Format the daily order number as "#001" for display. */
function display_order_number(array $order): string {
  $dailyOrderNumber = (int)($order['dailyOrderNumber'] ?? 0);
  if ($dailyOrderNumber > 0) {
    return '#' . str_pad((string)$dailyOrderNumber, 3, '0', STR_PAD_LEFT);
  }

  $orderId = (int)($order['orderId'] ?? 0);
  if ($orderId > 0) {
    return '#' . $orderId;
  }

  return '#000';
}

/** Generate a "?, ?, ?" placeholder string for use in SQL IN clauses. */
function sql_placeholders(array $values): string {
  if ($values === []) {
    throw new InvalidArgumentException('At least one value is required.');
  }

  return implode(', ', array_fill(0, count($values), '?'));
}

// =====================================================================
// Cart operations (stored in $_SESSION['cart'])
// =====================================================================

/**
 * Read the cart from session and normalize it into a consistent format.
 * Handles migration from an older {setId => qty} format to the current
 * array-of-objects format.
 */
function normalize_cart_items(): array {
  ensure_session_started();

  $cart = $_SESSION['cart'] ?? [];
  if (!is_array($cart)) {
    $_SESSION['cart'] = [];
    return [];
  }

  // Detect old-style cart (simple setId => quantity map)
  $isLegacyCart = true;
  foreach ($cart as $item) {
    if (is_array($item)) {
      $isLegacyCart = false;
      break;
    }
  }

  // Convert legacy format
  if ($isLegacyCart) {
    $normalized = [];
    foreach ($cart as $setId => $quantity) {
      $normalizedQuantity = (int)$quantity;
      if ($normalizedQuantity <= 0) {
        continue;
      }

      $normalized[] = [
        'id' => 'set:' . (int)$setId,
        'lineType' => ORDER_LINE_TYPE_SET,
        'setId' => (int)$setId,
        'quantity' => $normalizedQuantity,
        'lineNotes' => '',
      ];
    }
    $_SESSION['cart'] = $normalized;
    return $normalized;
  }

  // Clean up current format
  $normalized = [];
  foreach ($cart as $item) {
    if (!is_array($item)) {
      continue;
    }

    $lineType = (string)($item['lineType'] ?? ORDER_LINE_TYPE_SET);
    $setId = isset($item['setId']) ? (int)$item['setId'] : null;
    $quantity = max(1, (int)($item['quantity'] ?? 1));
    $lineNotes = trim((string)($item['lineNotes'] ?? ''));
    $selectedDishes = trim((string)($item['selectedDishes'] ?? ''));
    $normalized[] = [
      'id' => (string)($item['id'] ?? uniqid($lineType . ':', true)),
      'lineType' => $lineType,
      'setId' => $setId,
      'quantity' => $quantity,
      'lineNotes' => $lineNotes,
      'selectedDishes' => $selectedDishes,
    ];
  }

  $_SESSION['cart'] = $normalized;
  return $normalized;
}

function save_cart_items(array $items): void {
  ensure_session_started();
  $_SESSION['cart'] = array_values($items);
}

/** Count total items in the cart (sum of quantities). */
function cart_item_count(): int {
  $count = 0;
  foreach (normalize_cart_items() as $item) {
    $count += max(1, (int)$item['quantity']);
  }

  return $count;
}

function cart_items_are_empty(): bool {
  return normalize_cart_items() === [];
}

/** Add a pre-built takeout set to the cart. Increments qty if already present with same notes. */
function add_set_to_cart(int $setId, string $lineNotes = ''): void {
  $lineNotes = trim($lineNotes);
  $items = normalize_cart_items();

  // If the exact same set + notes combo exists, just bump the quantity
  foreach ($items as &$item) {
    if (
      (string)$item['lineType'] === ORDER_LINE_TYPE_SET &&
      (int)$item['setId'] === $setId &&
      trim((string)($item['lineNotes'] ?? '')) === $lineNotes
    ) {
      $item['quantity'] = (int)$item['quantity'] + 1;
      save_cart_items($items);
      return;
    }
  }
  unset($item);

  $items[] = [
    'id' => uniqid('set:', true),
    'lineType' => ORDER_LINE_TYPE_SET,
    'setId' => $setId,
    'quantity' => 1,
    'lineNotes' => $lineNotes,
    'selectedDishes' => '',
  ];
  save_cart_items($items);
}

/** Parse and clean the comma/newline-separated dish list for custom takeout boxes. */
function normalize_custom_selected_dishes(string $selectedDishes, int $selectionLimit): string {
  $parts = preg_split('/[\r\n,]+/', $selectedDishes) ?: [];
  $cleaned = [];
  foreach ($parts as $part) {
    $trimmed = trim($part);
    if ($trimmed !== '') {
      $cleaned[] = $trimmed;
    }
  }

  if ($cleaned === []) {
    throw new InvalidArgumentException('Please list the dishes you want in the custom takeout box.');
  }

  return implode(', ', array_slice($cleaned, 0, $selectionLimit));
}

/** Add a custom-selection takeout box to the cart. Each custom box is a separate line item. */
function add_custom_takeout_to_cart(int $setId, string $selectedDishes, string $lineNotes, int $selectionLimit = CUSTOM_TAKEOUT_DEFAULT_LIMIT): void {
  $items = normalize_cart_items();
  $items[] = [
    'id' => uniqid('custom:', true),
    'lineType' => ORDER_LINE_TYPE_CUSTOM,
    'setId' => $setId,
    'quantity' => 1,
    'lineNotes' => trim($lineNotes),
    'selectedDishes' => normalize_custom_selected_dishes($selectedDishes, $selectionLimit),
  ];
  save_cart_items($items);
}

function remove_cart_item(string $lineId): void {
  $items = array_values(array_filter(
    normalize_cart_items(),
    static fn(array $item): bool => (string)$item['id'] !== $lineId
  ));
  save_cart_items($items);
}

function clear_cart(): void {
  save_cart_items([]);
}

// =====================================================================
// Takeout set lookups (used when building cart display / totals)
// =====================================================================

/** Batch-fetch takeout sets by ID, returned as a setId => row map. */
function fetch_takeout_set_map(PDO $pdo, array $setIds, bool $availableOnly = false): array {
  if ($setIds === []) {
    return [];
  }

  $uniqueSetIds = array_values(array_unique(array_map('intval', $setIds)));
  $sql = 'SELECT *
          FROM TakeoutSet
          WHERE setId IN (' . sql_placeholders($uniqueSetIds) . ')';
  if ($availableOnly) {
    $sql .= ' AND isAvailable = 1';
  }
  $sql .= ' ORDER BY sortOrder ASC, setName ASC';

  $stmt = $pdo->prepare($sql);
  $stmt->execute($uniqueSetIds);

  $map = [];
  foreach ($stmt->fetchAll() as $row) {
    $map[(int)$row['setId']] = $row;
  }

  return $map;
}

/** Enrich raw cart items with set details (name, price, image) for display. */
function build_cart_items_for_display(PDO $pdo, array $cartItems): array {
  if ($cartItems === []) {
    return [];
  }

  $setIds = array_values(array_filter(array_map(
    static fn(array $item): int => (int)($item['setId'] ?? 0),
    $cartItems
  )));
  $setMap = fetch_takeout_set_map($pdo, $setIds, true);

  $displayItems = [];
  foreach ($cartItems as $item) {
    $setId = (int)($item['setId'] ?? 0);
    $set = $setMap[$setId] ?? null;
    if (!$set) {
      continue;
    }

    $quantity = max(1, (int)$item['quantity']);
    $price = (float)$set['price'];
    $selectedDishes = trim((string)($item['selectedDishes'] ?? ''));
    $lineType = (string)$item['lineType'];
    $displayItems[] = [
      'id' => (string)$item['id'],
      'lineType' => $lineType,
      'setId' => $setId,
      'setName' => (string)$set['setName'],
      'description' => (string)$set['description'],
      'price' => $price,
      'qty' => $quantity,
      'subtotal' => $price * $quantity,
      'imageUrl' => (string)($set['imageUrl'] ?? ''),
      'lineNotes' => trim((string)($item['lineNotes'] ?? '')),
      'selectedDishes' => $selectedDishes,
      'selectionLimit' => max(0, (int)($set['selectionLimit'] ?? 0)),
      'allowsCustomSelection' => (bool)($set['allowsCustomSelection'] ?? false),
    ];
  }

  return $displayItems;
}

function calculate_cart_total_amount(PDO $pdo, array $cartItems): float {
  $totalAmount = 0.0;
  foreach (build_cart_items_for_display($pdo, $cartItems) as $item) {
    $totalAmount += (float)$item['subtotal'];
  }

  return $totalAmount;
}

// =====================================================================
// Order queries
// =====================================================================

/** Fetch order items grouped by orderId (for batch display of multiple orders). */
function fetch_order_items_by_order_ids(PDO $pdo, array $orderIds): array {
  if ($orderIds === []) {
    return [];
  }

  $stmt = $pdo->prepare(
    'SELECT
       oi.orderId,
       oi.orderItemId,
       oi.lineType,
       oi.quantity,
       oi.lineLabel,
       oi.unitPrice,
       oi.lineDescription,
       oi.lineNotes,
       oi.imageUrl,
       oi.setId
     FROM OrderItem oi
     WHERE oi.orderId IN (' . sql_placeholders($orderIds) . ')
     ORDER BY oi.orderItemId ASC'
  );
  $stmt->execute($orderIds);

  $itemsByOrder = [];
  foreach ($stmt->fetchAll() as $item) {
    $itemsByOrder[(int)$item['orderId']][] = $item;
  }

  return $itemsByOrder;
}

/** Find a pending (unpaid) order belonging to a specific customer. */
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

function fetch_order_for_customer(PDO $pdo, int $orderId, int $customerId): ?array {
  $stmt = $pdo->prepare(
    'SELECT *
     FROM `Order`
     WHERE orderId = ? AND customerId = ?
     LIMIT 1'
  );
  $stmt->execute([$orderId, $customerId]);
  $order = $stmt->fetch();

  return is_array($order) ? $order : null;
}

/** Fetch an order joined with its payment info (for the admin detail view). */
function fetch_order_detail(PDO $pdo, int $orderId): ?array {
  $stmt = $pdo->prepare(
    'SELECT o.*, p.referenceId, p.paymentStatus
     FROM `Order` o
     LEFT JOIN Payment p ON p.orderId = o.orderId
     WHERE o.orderId = ?
     LIMIT 1'
  );
  $stmt->execute([$orderId]);
  $order = $stmt->fetch();

  return is_array($order) ? $order : null;
}

function fetch_order_takeout_items(PDO $pdo, int $orderId): array {
  $stmt = $pdo->prepare(
    'SELECT
       lineLabel AS setName,
       unitPrice AS price,
       quantity,
       lineDescription,
       lineType
     FROM OrderItem
     WHERE orderId = ?
     ORDER BY orderItemId ASC'
  );
  $stmt->execute([$orderId]);
  return $stmt->fetchAll();
}

// =====================================================================
// Order creation
// =====================================================================

/** Get the next sequential order number for today (e.g. #001, #002, ...). */
function next_daily_order_number(PDO $pdo, string $createdTime): int {
  $stmt = $pdo->prepare(
    'SELECT IFNULL(MAX(dailyOrderNumber), 0) + 1
     FROM `Order`
     WHERE DATE(createdTime) = DATE(?)'
  );
  $stmt->execute([$createdTime]);
  return (int)$stmt->fetchColumn();
}

/**
 * Build an order from the current cart contents, insert it as Pending,
 * and return the new orderId. Runs inside a transaction.
 */
function create_takeout_order_from_cart(
  PDO $pdo,
  int $customerId,
  string $customerName,
  string $customerPhone,
  string $pickupTime,
  array $cartItems,
  string $specialInstructions = '',
  string $allergyNotes = ''
): int {
  if ($cartItems === []) {
    throw new InvalidArgumentException('Cart cannot be empty.');
  }

  $displayItems = build_cart_items_for_display($pdo, $cartItems);
  if ($displayItems === []) {
    throw new RuntimeException('One or more cart items are no longer available.');
  }

  $createdTime = date('Y-m-d H:i:s');
  $pickupDateTime = date('Y-m-d') . ' ' . $pickupTime . ':00';
  $totalAmount = calculate_cart_total_amount($pdo, $cartItems);
  $dailyOrderNumber = next_daily_order_number($pdo, $createdTime);

  $pdo->beginTransaction();

  try {
    $insertOrder = $pdo->prepare(
      'INSERT INTO `Order`
        (dailyOrderNumber, customerId, customerName, customerPhone, pickupTime, status, totalAmount, specialInstructions, allergyNotes, createdTime, updatedTime)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertOrder->execute([
      $dailyOrderNumber,
      $customerId,
      $customerName,
      $customerPhone,
      $pickupDateTime,
      ORDER_STATUS_PENDING,
      $totalAmount,
      trim($specialInstructions) ?: null,
      trim($allergyNotes) ?: null,
      $createdTime,
      $createdTime,
    ]);

    $orderId = (int)$pdo->lastInsertId();

    // Insert each cart line as an OrderItem row
    $insertItem = $pdo->prepare(
      'INSERT INTO OrderItem
        (orderId, setId, lineType, lineLabel, unitPrice, lineDescription, lineNotes, imageUrl, quantity)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($displayItems as $item) {
      $description = (string)$item['description'];
      if ((string)$item['lineType'] === ORDER_LINE_TYPE_CUSTOM && $item['selectedDishes'] !== '') {
        $description .= "\nSelected dishes: " . $item['selectedDishes'];
      }

      $insertItem->execute([
        $orderId,
        (int)$item['setId'] > 0 ? (int)$item['setId'] : null,
        (string)$item['lineType'],
        (string)$item['setName'],
        (float)$item['price'],
        $description,
        trim((string)$item['lineNotes']) ?: null,
        (string)$item['imageUrl'] !== '' ? (string)$item['imageUrl'] : null,
        (int)$item['qty'],
      ]);
    }

    $pdo->commit();
    return $orderId;
  } catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
  }
}

// =====================================================================
// Order status transitions
// =====================================================================

function update_order_status(PDO $pdo, int $orderId, string $status): void {
  $stmt = $pdo->prepare(
    'UPDATE `Order`
     SET status = ?, updatedTime = NOW()
     WHERE orderId = ?'
  );
  $stmt->execute([$status, $orderId]);
}

/** Let a customer cancel their own unpaid order. */
function cancel_pending_order_for_customer(PDO $pdo, int $orderId, int $customerId): bool {
  $stmt = $pdo->prepare(
    'UPDATE `Order`
     SET status = ?, updatedTime = NOW()
     WHERE orderId = ? AND customerId = ? AND status = ?'
  );
  $stmt->execute([ORDER_STATUS_CANCELLED, $orderId, $customerId, ORDER_STATUS_PENDING]);

  return $stmt->rowCount() > 0;
}

/** Auto-cancel orders that have been Pending (unpaid) for too long. */
function cancel_stale_pending_orders(PDO $pdo, int $olderThanMinutes = STALE_PENDING_ORDER_MINUTES): int {
  $thresholdMinutes = max(1, $olderThanMinutes);
  $stmt = $pdo->prepare(
    'UPDATE `Order`
     SET status = ?, updatedTime = NOW()
     WHERE status = ?
       AND createdTime < (NOW() - INTERVAL ? MINUTE)'
  );
  $stmt->execute([ORDER_STATUS_CANCELLED, ORDER_STATUS_PENDING, $thresholdMinutes]);

  return $stmt->rowCount();
}

/**
 * Mark an order as Paid and record the payment reference.
 * Only transitions from Pending to Paid (idempotent if already paid).
 */
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
    $updateOrder = $pdo->prepare(
      'UPDATE `Order`
       SET status = ?, updatedTime = NOW()
       WHERE orderId = ?'
    );
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
