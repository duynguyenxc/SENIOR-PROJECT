<?php
declare(strict_types=1);

/**
 * CRUD for takeout sets (the pre-built meal boxes customers can order).
 * Includes support for the "custom box" option where customers pick their own dishes.
 */

require_once __DIR__ . '/db.php';

/** Look up a takeout set by name (used to enforce unique names). */
function fetch_takeout_set_by_name(PDO $pdo, string $setName, ?int $excludeSetId = null): ?array {
  $sql = 'SELECT
            setId,
            setName,
            description,
            price,
            imageUrl,
            isAvailable,
            sortOrder,
            allowsCustomSelection,
            selectionLimit
          FROM TakeoutSet
          WHERE setName = ?';
  $params = [trim($setName)];

  // Exclude a specific set (used during updates to allow the set to keep its own name)
  if ($excludeSetId !== null && $excludeSetId > 0) {
    $sql .= ' AND setId <> ?';
    $params[] = $excludeSetId;
  }

  $sql .= ' LIMIT 1';

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $set = $stmt->fetch();

  return is_array($set) ? $set : null;
}

/** Throw if the name is already taken by another set. */
function assert_takeout_set_name_is_available(PDO $pdo, string $setName, ?int $excludeSetId = null): void {
  if (fetch_takeout_set_by_name($pdo, $setName, $excludeSetId) !== null) {
    throw new RuntimeException('A takeout set with that name already exists. Please choose a different name.');
  }
}

/** Get all takeout sets (optionally only visible/available ones for the public page). */
function fetch_all_takeout_sets(PDO $pdo, bool $visibleOnly = false): array {
  $sql = 'SELECT
            setId,
            setName,
            description,
            price,
            imageUrl,
            isAvailable,
            sortOrder,
            allowsCustomSelection,
            selectionLimit
          FROM TakeoutSet';
  if ($visibleOnly) {
    $sql .= ' WHERE isAvailable = 1';
  }
  $sql .= ' ORDER BY sortOrder ASC, setName ASC';

  return $pdo->query($sql)->fetchAll();
}

function fetch_takeout_set_by_id(PDO $pdo, int $setId): ?array {
  $stmt = $pdo->prepare(
    'SELECT
       setId,
       setName,
       description,
       price,
       imageUrl,
       isAvailable,
       sortOrder,
       allowsCustomSelection,
       selectionLimit
     FROM TakeoutSet
     WHERE setId = ?
     LIMIT 1'
  );
  $stmt->execute([$setId]);
  $set = $stmt->fetch();

  return is_array($set) ? $set : null;
}

function create_takeout_set(PDO $pdo, array $data): void {
  assert_takeout_set_name_is_available($pdo, (string)$data['setName']);

  $stmt = $pdo->prepare(
    'INSERT INTO TakeoutSet
      (setName, description, price, imageUrl, isAvailable, sortOrder, allowsCustomSelection, selectionLimit)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
  );
  $stmt->execute([
    trim((string)$data['setName']),
    trim((string)$data['description']),
    (float)$data['price'],
    $data['imageUrl'] ?: null,
    !empty($data['isAvailable']) ? 1 : 0,
    (int)($data['sortOrder'] ?? 0),
    !empty($data['allowsCustomSelection']) ? 1 : 0,
    (int)($data['selectionLimit'] ?? 0),
  ]);
}

function update_takeout_set(PDO $pdo, int $setId, array $data): void {
  assert_takeout_set_name_is_available($pdo, (string)$data['setName'], $setId);

  $stmt = $pdo->prepare(
    'UPDATE TakeoutSet
     SET
       setName = ?,
       description = ?,
       price = ?,
       imageUrl = ?,
       isAvailable = ?,
       sortOrder = ?,
       allowsCustomSelection = ?,
       selectionLimit = ?
     WHERE setId = ?'
  );
  $stmt->execute([
    trim((string)$data['setName']),
    trim((string)$data['description']),
    (float)$data['price'],
    $data['imageUrl'] ?: null,
    !empty($data['isAvailable']) ? 1 : 0,
    (int)($data['sortOrder'] ?? 0),
    !empty($data['allowsCustomSelection']) ? 1 : 0,
    (int)($data['selectionLimit'] ?? 0),
    $setId,
  ]);
}

function delete_takeout_set(PDO $pdo, int $setId): void {
  $stmt = $pdo->prepare('DELETE FROM TakeoutSet WHERE setId = ?');
  $stmt->execute([$setId]);
}

/** Find the first takeout set that allows custom dish selection (the "custom box"). */
function fetch_custom_takeout_set(PDO $pdo): ?array {
  $stmt = $pdo->query(
    'SELECT
       setId,
       setName,
       description,
       price,
       imageUrl,
       isAvailable,
       sortOrder,
       allowsCustomSelection,
       selectionLimit
     FROM TakeoutSet
     WHERE allowsCustomSelection = 1
     ORDER BY sortOrder ASC, setId ASC
     LIMIT 1'
  );
  $set = $stmt->fetch();

  return is_array($set) ? $set : null;
}
