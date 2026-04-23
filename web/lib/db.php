<?php
declare(strict_types=1);

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) {
    return $pdo;
  }

  $host = getenv('DB_HOST') !== false ? (string)getenv('DB_HOST') : '127.0.0.1';
  $port = getenv('DB_PORT') !== false ? (string)getenv('DB_PORT') : '3306';
  $name = getenv('DB_NAME') !== false ? (string)getenv('DB_NAME') : 'vegbuffet';
  $user = getenv('DB_USER') !== false ? (string)getenv('DB_USER') : 'root';
  $pass = getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '';
  $sslCaPath = getenv('DB_SSL_CA_PATH') !== false ? trim((string)getenv('DB_SSL_CA_PATH')) : '';
  $sslVerify = getenv('DB_SSL_VERIFY_SERVER_CERT') !== false
    ? filter_var(getenv('DB_SSL_VERIFY_SERVER_CERT'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) !== false
    : true;

  $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];

  if (defined('PDO::MYSQL_ATTR_SSL_CA') && $sslCaPath !== '') {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCaPath;
  }

  if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $sslVerify;
  }

  $pdo = new PDO($dsn, $user, $pass, $options);

  return $pdo;
}

