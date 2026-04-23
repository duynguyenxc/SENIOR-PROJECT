<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function fetch_days(PDO $pdo): array {
  return $pdo->query('SELECT dayId, dayName, sortOrder FROM Day ORDER BY sortOrder')->fetchAll();
}

function fetch_day_by_id(PDO $pdo, int $dayId): ?array {
  $stmt = $pdo->prepare('SELECT dayId, dayName, sortOrder FROM Day WHERE dayId = ? LIMIT 1');
  $stmt->execute([$dayId]);
  $day = $stmt->fetch();

  return is_array($day) ? $day : null;
}

function fetch_all_dishes(PDO $pdo, bool $activeOnly = false): array {
  $sql = 'SELECT dishId, dishName, description, imageUrl, isActive
          FROM Dish';
  if ($activeOnly) {
    $sql .= ' WHERE isActive = 1';
  }
  $sql .= ' ORDER BY dishName ASC';

  return $pdo->query($sql)->fetchAll();
}

function fetch_dish_by_id(PDO $pdo, int $dishId): ?array {
  $stmt = $pdo->prepare(
    'SELECT dishId, dishName, description, imageUrl, isActive
     FROM Dish
     WHERE dishId = ?
     LIMIT 1'
  );
  $stmt->execute([$dishId]);
  $dish = $stmt->fetch();

  return is_array($dish) ? $dish : null;
}

function fetch_dish_by_name(PDO $pdo, string $dishName): ?array {
  $stmt = $pdo->prepare(
    'SELECT dishId, dishName, description, imageUrl, isActive
     FROM Dish
     WHERE dishName = ?
     LIMIT 1'
  );
  $stmt->execute([trim($dishName)]);
  $dish = $stmt->fetch();

  return is_array($dish) ? $dish : null;
}

function fetch_day_menu_dishes(PDO $pdo, int $dayId): array {
  $stmt = $pdo->prepare(
    'SELECT
       dmi.dayMenuItemId,
       d.dishId,
       d.dishName,
       d.description,
       d.imageUrl
     FROM DayMenuItem dmi
     JOIN Dish d ON dmi.dishId = d.dishId
     WHERE dmi.dayId = ? AND d.isActive = 1
     ORDER BY d.dishName ASC'
  );
  $stmt->execute([$dayId]);

  return $stmt->fetchAll();
}

function create_dish(PDO $pdo, string $name, string $description, ?string $imageUrl, bool $isActive): void {
  $stmt = $pdo->prepare(
    'INSERT INTO Dish (dishName, description, imageUrl, isActive)
     VALUES (?, ?, ?, ?)'
  );
  $stmt->execute([
    trim($name),
    trim($description) ?: null,
    $imageUrl ?: null,
    $isActive ? 1 : 0,
  ]);
}

function create_or_update_dish_for_day(PDO $pdo, int $dayId, string $name, string $description, ?string $imageUrl): void {
  $name = trim($name);
  if ($name === '') {
    throw new InvalidArgumentException('Dish name is required.');
  }

  $existingDish = fetch_dish_by_name($pdo, $name);
  if ($existingDish) {
    update_dish(
      $pdo,
      (int)$existingDish['dishId'],
      $name,
      $description !== '' ? $description : (string)($existingDish['description'] ?? ''),
      $imageUrl ?: (string)($existingDish['imageUrl'] ?? ''),
      true
    );
    add_dish_to_day($pdo, $dayId, (int)$existingDish['dishId']);
    return;
  }

  create_dish($pdo, $name, $description, $imageUrl, true);
  add_dish_to_day($pdo, $dayId, (int)$pdo->lastInsertId());
}

function update_dish(PDO $pdo, int $dishId, string $name, string $description, ?string $imageUrl, bool $isActive): void {
  $stmt = $pdo->prepare(
    'UPDATE Dish
     SET dishName = ?, description = ?, imageUrl = ?, isActive = ?
     WHERE dishId = ?'
  );
  $stmt->execute([
    trim($name),
    trim($description) ?: null,
    $imageUrl ?: null,
    $isActive ? 1 : 0,
    $dishId,
  ]);
}

function add_dish_to_day(PDO $pdo, int $dayId, int $dishId): void {
  $stmt = $pdo->prepare('INSERT IGNORE INTO DayMenuItem (dayId, dishId) VALUES (?, ?)');
  $stmt->execute([$dayId, $dishId]);
}

function remove_day_menu_item(PDO $pdo, int $dayMenuItemId): void {
  $stmt = $pdo->prepare('DELETE FROM DayMenuItem WHERE dayMenuItemId = ?');
  $stmt->execute([$dayMenuItemId]);
}
