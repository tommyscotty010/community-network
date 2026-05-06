<?php
require_once __DIR__ . '/../auth.php';
$user = apiAuth();
if (!$user['is_admin']) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
$pdo  = getDB();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("
        SELECT t.*, u.username AS used_by_name
        FROM invite_tokens t
        LEFT JOIN users u ON u.id = t.used_by
        ORDER BY t.created_at DESC
    ");
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = bin2hex(random_bytes(16));
    $token = implode('-', str_split($token, 4));
    $pdo->prepare("INSERT INTO invite_tokens (token, created_by) VALUES (?, ?)")
        ->execute([$token, $user['id']]);
    echo json_encode(['token' => $token, 'id' => $pdo->lastInsertId()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($data['id'] ?? 0);
    $pdo->prepare("DELETE FROM invite_tokens WHERE id = ? AND used_by IS NULL")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
