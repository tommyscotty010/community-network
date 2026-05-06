<?php
require_once __DIR__ . '/../auth.php';
$user = apiAuth();
$pdo  = getDB();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query("
        SELECT p.id, p.user_id, p.lat+0 AS lat, p.lng+0 AS lng,
               p.message, p.created_at, u.username,
               pr.avatar
        FROM map_pins p
        JOIN users u ON u.id = p.user_id
        LEFT JOIN user_profiles pr ON pr.user_id = p.user_id
        ORDER BY p.created_at DESC
        LIMIT 500
    ")->fetchAll();
    echo json_encode($rows);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON non valido', 'raw' => $raw]);
        exit;
    }

    $lat = isset($data['lat']) ? (float)$data['lat'] : null;
    $lng = isset($data['lng']) ? (float)$data['lng'] : null;
    $msg = isset($data['message']) ? trim($data['message']) : null;
    if ($msg === '') $msg = null;

    if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        http_response_code(400);
        echo json_encode(['error' => 'Coordinate non valide', 'lat' => $lat, 'lng' => $lng]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO map_pins (user_id, lat, lng, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user['id'], $lat, $lng, $msg]);
    echo json_encode(['id' => (int)$pdo->lastInsertId(), 'ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($data['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID mancante']); exit; }
    if ($user['is_admin']) {
        $pdo->prepare("DELETE FROM map_pins WHERE id = ?")->execute([$id]);
    } else {
        $pdo->prepare("DELETE FROM map_pins WHERE id = ? AND user_id = ?")->execute([$id, $user['id']]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
