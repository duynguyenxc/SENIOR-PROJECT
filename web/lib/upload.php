<?php
declare(strict_types=1);

function store_uploaded_image(string $fieldName, string $subdirectory, ?string $existingPath = null): ?string {
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

  if ($size > 5 * 1024 * 1024) {
    throw new RuntimeException('Image must be 5MB or smaller.');
  }

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

  $safeSubdirectory = trim(str_replace(['..', '\\'], ['', '/'], $subdirectory), '/');
  $relativeDirectory = '/uploads/' . $safeSubdirectory;
  $absoluteDirectory = dirname(__DIR__) . $relativeDirectory;
  if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
    throw new RuntimeException('Unable to prepare upload directory.');
  }

  $filename = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
  $targetPath = $absoluteDirectory . DIRECTORY_SEPARATOR . $filename;
  if (!move_uploaded_file($tmpPath, $targetPath)) {
    throw new RuntimeException('Failed to save uploaded image.');
  }

  return $relativeDirectory . '/' . $filename;
}
