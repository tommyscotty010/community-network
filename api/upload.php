<?php
require_once __DIR__ . '/../auth.php';
$user = apiAuth();
$pdo  = getDB();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

if (empty($_FILES['file'])) {
    http_response_code(400); echo json_encode(['error' => 'No file uploaded']); exit;
}

$file    = $_FILES['file'];
$maxSize = 20 * 1024 * 1024; // 20MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400); echo json_encode(['error' => 'Upload error ' . $file['error']]); exit;
}
if ($file['size'] > $maxSize) {
    http_response_code(400); echo json_encode(['error' => 'File too large (max 20MB)']); exit;
}

// Detect MIME from actual content
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);

$allowed = [
    'image/jpeg','image/png','image/gif','image/webp',
    'application/pdf',
    'text/plain',
    'application/zip','application/x-zip-compressed',
    'video/mp4','video/webm',
    'audio/mpeg','audio/ogg','audio/wav',
];
if (!in_array($mime, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'File type not allowed: ' . $mime]);
    exit;
}

$original = basename($file['name']);
$ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));
$safeName = bin2hex(random_bytes(12)) . '.' . $ext;
$dest     = __DIR__ . '/../uploads/files/' . $safeName;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    http_response_code(500); echo json_encode(['error' => 'Could not save file']); exit;
}

$stmt = $pdo->prepare("
    INSERT INTO attachments (uploader_id, filename, original, mime, size)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$user['id'], $safeName, $original, $mime, $file['size']]);
$id = $pdo->lastInsertId();

echo json_encode([
    'id'       => (int)$id,
    'filename' => $safeName,
    'original' => $original,
    'mime'     => $mime,
    'size'     => $file['size'],
    'url'      => '../uploads/files/' . $safeName,
]);
