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
  $payload = build_stripe_checkout_payload($orderId, $items);
  $decoded = stripe_api_post('https://api.stripe.com/v1/checkout/sessions', $payload);

  if (!is_array($decoded) || !isset($decoded['url']) || !is_string($decoded['url'])) {
    throw new RuntimeException('Stripe API error: missing checkout URL.');
  }

  return $decoded['url'];
}

function build_stripe_checkout_payload(int $orderId, array $items): array {
  return array_merge(
    build_stripe_line_items($items),
    [
      'mode' => 'payment',
      'client_reference_id' => (string)$orderId,
      'metadata[orderId]' => (string)$orderId,
      'success_url' => app_base_url() . '/success.php?session_id={CHECKOUT_SESSION_ID}&order_id=' . $orderId,
      'cancel_url' => app_base_url() . '/cancel.php?order_id=' . $orderId,
    ]
  );
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

function stripe_api_post(string $url, array $payload): array {
  $ch = curl_init($url);
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
  if (!is_array($decoded)) {
    throw new RuntimeException('Stripe API returned an invalid response.');
  }

  return $decoded;
}

function stripe_api_get(string $url): array {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERPWD, stripe_secret_key() . ':');

  $response = curl_exec($ch);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($response === false) {
    throw new RuntimeException('Stripe request failed: ' . $curlError);
  }

  $decoded = json_decode((string)$response, true);
  if (!is_array($decoded)) {
    throw new RuntimeException('Stripe API returned an invalid response.');
  }

  return $decoded;
}

function retrieve_stripe_checkout_session(string $sessionId): array {
  if ($sessionId === '') {
    throw new InvalidArgumentException('Stripe session id is required.');
  }

  return stripe_api_get('https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId));
}

function verify_checkout_session_for_order(array $session, int $expectedOrderId): void {
  $paymentStatus = (string)($session['payment_status'] ?? '');
  if ($paymentStatus !== 'paid') {
    throw new RuntimeException('Stripe has not marked this checkout session as paid.');
  }

  $sessionOrderId = (string)($session['metadata']['orderId'] ?? $session['client_reference_id'] ?? '');
  if ($sessionOrderId === '' || (int)$sessionOrderId !== $expectedOrderId) {
    throw new RuntimeException('Stripe checkout session does not match the local order.');
  }
}

function finalize_payment_success(PDO $pdo, int $orderId, string $sessionId): bool {
  if (str_starts_with($sessionId, 'mock_stripe_session_')) {
    return mark_order_paid($pdo, $orderId, $sessionId);
  }

  $session = retrieve_stripe_checkout_session($sessionId);
  verify_checkout_session_for_order($session, $orderId);

  return mark_order_paid($pdo, $orderId, $sessionId);
}

function payment_cancel_message(int $orderId): string {
  if ($orderId > 0) {
    return 'You cancelled the checkout process. Your order is still unpaid and has not been sent to the kitchen.';
  }

  return 'You cancelled the checkout process before payment could be completed.';
}
