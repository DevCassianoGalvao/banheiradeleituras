<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'não autenticado']));
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT month_key, type, target FROM month_goals WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);
    $goals = [];
    foreach ($stmt->fetchAll() as $row) {
        $goals[$row['month_key']] = ['type' => $row['type'], 'target' => (int)$row['target']];
    }

    echo json_encode(['ok' => true, 'goals' => $goals]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    exit(json_encode(['error' => 'corpo inválido']));
}

$monthKey = trim((string)($input['month'] ?? ''));
$type = $input['type'] ?? '';
$target = max(1, (int)($input['target'] ?? 1));

if (!preg_match('/^\d{4}-\d{2}$/', $monthKey) || !in_array($type, ['books', 'minutes', 'pages'], true)) {
    http_response_code(422);
    exit(json_encode(['error' => 'mês (YYYY-MM) e tipo válidos são obrigatórios']));
}

$upsert = $pdo->prepare('
    INSERT INTO month_goals (user_id, month_key, type, target)
    VALUES (:user_id, :month_key, :type, :target)
    ON DUPLICATE KEY UPDATE type = VALUES(type), target = VALUES(target)
');
$upsert->execute([
    'user_id' => $userId,
    'month_key' => $monthKey,
    'type' => $type,
    'target' => $target,
]);

echo json_encode(['ok' => true]);
