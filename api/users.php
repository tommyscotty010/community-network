<?php
require_once __DIR__ . '/../auth.php';
$user = apiAuth();
$pdo  = getDB();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$stmt = $pdo->prepare("
    SELECT u.id, u.username, p.avatar
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE u.last_seen >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
    ORDER BY u.username ASC
");
$stmt->execute();
echo json_encode($stmt->fetchAll());
