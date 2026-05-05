<?php
declare(strict_types=1);

/**
 * Handle image uploads for dishes and takeout sets.
 * Validates file type and size, generates a unique filename,
 * and moves the file into the /uploads/ directory.
 */

function store_uploaded_image(string $fieldName, string $subdirectory, ?string $existingPath = null): ?string {
  // No file uploaded — keep whatever path we already have
  if (
    !isset($_FILES[$fieldName]) ||
    !is_array($_FILES[$fieldName]) ||
    (int)($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
  ) {
    return $existingPath;
  }

  $file = $_FILES[$fieldName];
  $errorCode = (int)($file['error'] ?? UPLOAD_ERR_OK);
  if ($errorCode !== UPLOAD_ERR_OK) {
    throw new RuntimeException('Image upload failed.');
  }

  $tmpPath = (string)($file['tmp_name'] ?? '');
  $size = (int)($file['size'] ?? 0);
  if ($tmpPath === '' || $size <= 0) {
    throw new RuntimeException('Uploaded image is invalid.');
  }

  // 5 MB limit
  if ($size > 5 * 1024 * 1024) {
    throw new RuntimeException('Image must be 5MB or smaller.');
  }

  // Only allow common image formats
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mimeType = (string)$finfo->file($tmpPath);
  $extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
  ];
  $extension = $extensions[$mimeType] ?? null;
  if ($extension === null) {
    throw new RuntimeException('Only JPG, PNG, WEBP, or GIF images are allowed.');
  }

  // Build a safe destination path
  $safeSubdirectory = trim(str_replace(['..', '\\'], ['', '/'], $subdirectory), '/');
  $relativeDirectory = '/uploads/' . $safeSubdirectory;
  $absoluteDirectory = dirname(__DIR__) . $relativeDirectory;
  if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
    throw new RuntimeException('Unable to prepare upload directory.');
  }

  // Generate a unique filename to avoid collisions
  $filename = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
  $targetPath = $absoluteDirectory . DIRECTORY_SEPARATOR . $filename;
  if (!move_uploaded_file($tmpPath, $targetPath)) {
    throw new RuntimeException('Failed to save uploaded image.');
  }

  return $relativeDirectory . '/' . $filename;
}
