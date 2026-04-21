<?php
declare(strict_types=1);

require_once __DIR__ . '/orders.php';

function normalized_report_range(?string $range): string {
  $allowedRanges = ['today', 'week', 'month', 'custom'];
  return in_array($range, $allowedRanges, true) ? $range : 'week';
}

function report_date_bounds(string $range, ?string $startDate = null, ?string $endDate = null): array {
  $today = new DateTimeImmutable('today');

  return match ($range) {
    'today' => [$today, $today],
    'week' => [$today->modify('monday this week'), $today],
    'month' => [$today->modify('first day of this month'), $today],
    'custom' => [
      report_input_date($startDate) ?? $today->modify('monday this week'),
      report_input_date($endDate) ?? $today,
    ],
    default => [$today->modify('monday this week'), $today],
  };
}

function report_input_date(?string $date): ?DateTimeImmutable {
  if (!is_string($date) || $date === '') {
    return null;
  }

  $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
  return $parsed instanceof DateTimeImmutable ? $parsed : null;
}

function report_date_filters(DateTimeImmutable $startDate, DateTimeImmutable $endDate): array {
  $normalizedStart = $startDate->setTime(0, 0, 0);
  $normalizedEnd = $endDate->setTime(0, 0, 0);

  if ($normalizedStart > $normalizedEnd) {
    [$normalizedStart, $normalizedEnd] = [$normalizedEnd, $normalizedStart];
  }

  return [
    $normalizedStart->format('Y-m-d 00:00:00'),
    $normalizedEnd->modify('+1 day')->format('Y-m-d 00:00:00'),
  ];
}

function fetch_sales_kpi(PDO $pdo, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array {
  [$start, $endExclusive] = report_date_filters($startDate, $endDate);
  $countedStatuses = order_counted_revenue_statuses();

  $stmt = $pdo->prepare(
    'SELECT
       COUNT(*) AS orderCount,
       COALESCE(SUM(totalAmount), 0) AS totalRevenue,
       COALESCE(AVG(totalAmount), 0) AS averageOrderValue
     FROM `Order`
     WHERE status IN (' . sql_placeholders($countedStatuses) . ')
       AND createdTime >= ?
       AND createdTime < ?'
  );
  $stmt->execute([...$countedStatuses, $start, $endExclusive]);

  $row = $stmt->fetch() ?: [];
  return [
    'orderCount' => (int)($row['orderCount'] ?? 0),
    'totalRevenue' => (float)($row['totalRevenue'] ?? 0),
    'averageOrderValue' => (float)($row['averageOrderValue'] ?? 0),
  ];
}

function fetch_status_breakdown(PDO $pdo, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array {
  [$start, $endExclusive] = report_date_filters($startDate, $endDate);

  $stmt = $pdo->prepare(
    'SELECT status, COUNT(*) AS orderCount
     FROM `Order`
     WHERE createdTime >= ?
       AND createdTime < ?
     GROUP BY status
     ORDER BY orderCount DESC, status ASC'
  );
  $stmt->execute([$start, $endExclusive]);

  $breakdown = [];
  foreach ($stmt->fetchAll() as $row) {
    $breakdown[(string)$row['status']] = (int)$row['orderCount'];
  }

  return $breakdown;
}

function fetch_best_selling_sets(PDO $pdo, DateTimeImmutable $startDate, DateTimeImmutable $endDate, int $limit = 5): array {
  [$start, $endExclusive] = report_date_filters($startDate, $endDate);
  $countedStatuses = order_counted_revenue_statuses();

  $query = '
    SELECT
      ts.setId,
      ts.setName,
      SUM(oi.quantity) AS totalQuantity,
      SUM(oi.quantity * ts.price) AS totalRevenue
    FROM `Order` o
    JOIN OrderItem oi ON oi.orderId = o.orderId
    JOIN TakeoutSet ts ON ts.setId = oi.setId
    WHERE o.status IN (' . sql_placeholders($countedStatuses) . ')
      AND o.createdTime >= ?
      AND o.createdTime < ?
    GROUP BY ts.setId, ts.setName
    ORDER BY totalQuantity DESC, totalRevenue DESC, ts.setName ASC
    LIMIT ' . max(1, $limit);

  $stmt = $pdo->prepare($query);
  $stmt->execute([...$countedStatuses, $start, $endExclusive]);
  return $stmt->fetchAll();
}

function fetch_low_selling_available_sets(PDO $pdo, DateTimeImmutable $startDate, DateTimeImmutable $endDate, int $limit = 3): array {
  [$start, $endExclusive] = report_date_filters($startDate, $endDate);
  $countedStatuses = order_counted_revenue_statuses();

  $query = '
    SELECT
      ts.setId,
      ts.setName,
      COALESCE(SUM(CASE WHEN o.orderId IS NOT NULL THEN oi.quantity ELSE 0 END), 0) AS totalQuantity
    FROM TakeoutSet ts
    LEFT JOIN OrderItem oi ON oi.setId = ts.setId
    LEFT JOIN `Order` o
      ON o.orderId = oi.orderId
      AND o.status IN (' . sql_placeholders($countedStatuses) . ')
      AND o.createdTime >= ?
      AND o.createdTime < ?
    WHERE ts.isAvailable = 1
    GROUP BY ts.setId, ts.setName
    ORDER BY totalQuantity ASC, ts.setName ASC
    LIMIT ' . max(1, $limit);

  $stmt = $pdo->prepare($query);
  $stmt->execute([...$countedStatuses, $start, $endExclusive]);
  return $stmt->fetchAll();
}

function fetch_peak_pickup_windows(PDO $pdo, DateTimeImmutable $startDate, DateTimeImmutable $endDate, int $limit = 3): array {
  [$start, $endExclusive] = report_date_filters($startDate, $endDate);
  $countedStatuses = order_counted_revenue_statuses();

  $query = '
    SELECT
      DATE_FORMAT(pickupTime, "%h:00 %p") AS pickupWindow,
      COUNT(*) AS orderCount
    FROM `Order`
    WHERE status IN (' . sql_placeholders($countedStatuses) . ')
      AND createdTime >= ?
      AND createdTime < ?
    GROUP BY HOUR(pickupTime), DATE_FORMAT(pickupTime, "%h:00 %p")
    ORDER BY orderCount DESC, HOUR(pickupTime) ASC
    LIMIT ' . max(1, $limit);

  $stmt = $pdo->prepare($query);
  $stmt->execute([...$countedStatuses, $start, $endExclusive]);
  return $stmt->fetchAll();
}

function fetch_report_overview(PDO $pdo): array {
  $today = new DateTimeImmutable('today');

  return [
    'today' => fetch_sales_kpi($pdo, $today, $today),
    'week' => fetch_sales_kpi($pdo, $today->modify('monday this week'), $today),
    'month' => fetch_sales_kpi($pdo, $today->modify('first day of this month'), $today),
  ];
}

function build_report_insights(array $selectedKpi, array $bestSellers, array $lowSellingSets, array $pickupWindows): array {
  $insights = [];

  if ($bestSellers !== []) {
    $topSeller = $bestSellers[0];
    $insights[] = sprintf(
      '%s is the current top seller with %d orders in the selected period.',
      (string)$topSeller['setName'],
      (int)$topSeller['totalQuantity']
    );
  }

  if ($lowSellingSets !== []) {
    $slowSeller = $lowSellingSets[0];
    $insights[] = sprintf(
      '%s is underperforming with only %d orders while still available.',
      (string)$slowSeller['setName'],
      (int)$slowSeller['totalQuantity']
    );
  }

  if ($pickupWindows !== []) {
    $peakWindow = $pickupWindows[0];
    $insights[] = sprintf(
      'Peak pickup demand is around %s with %d orders.',
      (string)$peakWindow['pickupWindow'],
      (int)$peakWindow['orderCount']
    );
  }

  if ($selectedKpi['orderCount'] === 0) {
    $insights[] = 'No completed revenue activity was recorded in the selected date range yet.';
  }

  return array_slice($insights, 0, 3);
}
