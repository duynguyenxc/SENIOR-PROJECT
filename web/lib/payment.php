<?php
declare(strict_types=1);

require_once __DIR__ . '/orders.php';

function app_base_url(): string {
  $baseUrl = getenv('APP_URL') ?: 'http://localhost:8080';
  return rtrim($baseUrl, '/');
}

function stripe_secret_key(): string {
  $secretKey = getenv('STRIPE_SECRET_KEY') ?: '';
  return trim($secretKey);
}

function stripe_publishable_key(): string {
  $publishableKey = getenv('STRIPE_PUBLISHABLE_KEY') ?: '';
  return trim($publishableKey);
}

function uses_mock_payment_mode(): bool {
  return stripe_secret_key() === '';
}

function create_payment_redirect_url(PDO $pdo, int $orderId, int $customerId): string {
  $order = fetch_pending_order_for_customer($pdo, $orderId, $customerId);
  if ($order === null) {
    throw new RuntimeException('Order not found or already paid.');
  }

  if (uses_mock_payment_mode()) {
    $mockSessionId = 'mock_stripe_session_' . uniqid('', true);
    return app_base_url() . '/success.php?session_id=' . urlencode($mockSessionId) . '&order_id=' . $orderId;
  }

  $items = fetch_order_takeout_items($pdo, $orderId);
  if ($items === []) {
    throw new RuntimeException('No order items found for payment.');
  }

  return create_stripe_checkout_url($orderId, $items);
}

function create_stripe_checkout_url(int $orderId, array $items): string {
  $payload = array_merge(
    build_stripe_line_items($items),
    [
      'mode' => 'payment',
      'success_url' => app_base_url() . '/success.php?session_id={CHECKOUT_SESSION_ID}&order_id=' . $orderId,
      'cancel_url' => app_base_url() . '/cancel.php?order_id=' . $orderId,
    ]
  );

  $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERPWD, stripe_secret_key() . ':');
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

  $response = curl_exec($ch);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($response === false) {
    throw new RuntimeException('Stripe request failed: ' . $curlError);
  }

  $decoded = json_decode((string)$response, true);
  if (!is_array($decoded) || !isset($decoded['url']) || !is_string($decoded['url'])) {
    throw new RuntimeException('Stripe API error: ' . (string)$response);
  }

  return $decoded['url'];
}

function build_stripe_line_items(array $items): array {
  $lineItems = [];

  foreach ($items as $index => $item) {
    $lineItems["line_items[$index][price_data][currency]"] = 'usd';
    $lineItems["line_items[$index][price_data][product_data][name]"] = (string)$item['setName'];
    $lineItems["line_items[$index][price_data][unit_amount]"] = (int)round(((float)$item['price']) * 100);
    $lineItems["line_items[$index][quantity]"] = (int)$item['quantity'];
  }

  return $lineItems;
}

function finalize_payment_success(PDO $pdo, int $orderId, string $sessionId): bool {
  return mark_order_paid($pdo, $orderId, $sessionId);
}
