<?php
declare(strict_types=1);

/**
 * CSRF token generation and validation.
 * A random token is stored in the session and embedded in every form.
 */

require_once __DIR__ . '/auth.php';

const CSRF_SESSION_KEY = '_csrf_token';

/** Get or create a CSRF token for the current session. */
function csrf_token(): string {
  ensure_session_started();

  $token = $_SESSION[CSRF_SESSION_KEY] ?? null;
  if (is_string($token) && $token !== '') {
    return $token;
  }

  $token = bin2hex(random_bytes(32));
  $_SESSION[CSRF_SESSION_KEY] = $token;
  return $token;
}

/** Return a hidden <input> element containing the CSRF token (ready to drop into a form). */
function csrf_input(): string {
  return '<input type="hidden" name="_csrf" value="'
    . htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '" />';
}

/** Check if a given token matches the one stored in the session. */
function is_valid_csrf_token(?string $token): bool {
  ensure_session_started();

  $sessionToken = $_SESSION[CSRF_SESSION_KEY] ?? '';
  if (!is_string($token) || $token === '') {
    return false;
  }

  return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

/** Halt the request with a 400 error if the CSRF token is missing or invalid. */
function require_valid_csrf_token(): void {
  $token = $_POST['_csrf'] ?? null;
  if (is_string($token) && is_valid_csrf_token($token)) {
    return;
  }

  http_response_code(400);
  exit('Invalid request token.');
}
