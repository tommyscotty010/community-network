<?php
require_once __DIR__ . '/../auth.php';
$user = apiAuth();
$pdo  = getDB();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $after  = (int)($_GET['after'] ?? 0);
    $peerId = isset($_GET['peer']) ? (int)$_GET['peer'] : null;

    if ($peerId) {
        $stmt = $pdo->prepare("
            SELECT m.id, m.sender_id, m.receiver_id, m.content, m.created_at,
                   u.username, pr.avatar AS sender_avatar,
                   a.original AS attach_name, a.mime AS attach_mime,
                   a.filename AS attach_file, a.size AS attach_size
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            LEFT JOIN user_profiles pr ON pr.user_id = m.sender_id
            LEFT JOIN attachments a ON a.id = m.attachment_id
            WHERE m.id > ? AND m.group_id IS NULL
              AND ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
            ORDER BY m.created_at ASC LIMIT 100
        ");
        $stmt->execute([$after, $user['id'], $peerId, $peerId, $user['id']]);
    } else {
        $stmt = $pdo->prepare("
            SELECT m.id, m.sender_id, m.receiver_id, m.content, m.created_at,
                   u.username, pr.avatar AS sender_avatar,
                   a.original AS attach_name, a.mime AS attach_mime,
                   a.filename AS attach_file, a.size AS attach_size
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            LEFT JOIN user_profiles pr ON pr.user_id = m.sender_id
            LEFT JOIN attachments a ON a.id = m.attachment_id
            WHERE m.id > ? AND m.receiver_id IS NULL AND m.group_id IS NULL
            ORDER BY m.created_at ASC LIMIT 100
        ");
        $stmt->execute([$after]);
    }
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data         = json_decode(file_get_contents('php://input'), true) ?? [];
    $content      = isset($data['content']) ? substr(trim($data['content']), 0, 2000) : null;
    $receiverId   = isset($data['receiver_id'])   ? (int)$data['receiver_id']   : null;
    $attachmentId = isset($data['attachment_id']) ? (int)$data['attachment_id'] : null;
    if (!$content && !$attachmentId) { http_response_code(400); echo json_encode(['error'=>'Messaggio vuoto']); exit; }
    $pdo->prepare("INSERT INTO messages (sender_id,receiver_id,content,attachment_id) VALUES (?,?,?,?)")
        ->execute([$user['id'], $receiverId, $content ?: null, $attachmentId]);
    echo json_encode(['id'=>(int)$pdo->lastInsertId()]);
    exit;
}

http_response_code(405);
echo json_encode(['error'=>'Method not allowed']);
