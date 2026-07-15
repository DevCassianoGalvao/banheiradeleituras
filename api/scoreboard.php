<?php
declare(strict_types=1);

// Placar: expõe só a contagem numérica (lidos/total) por usuário/ano.
// Nunca selecionar note, my_note ou quotes aqui — regra de privacidade
// do CLAUDE.md (notas e resenhas nunca aparecem entre usuários).

require __DIR__ . '/../config/db.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'não autenticado']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit(json_encode(['error' => 'method not allowed']));
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$stmt = $pdo->prepare('
    SELECT u.id AS user_id, u.name,
           COUNT(*) AS total,
           SUM(b.read_status) AS read_count
    FROM books b
    INNER JOIN users u ON u.id = b.user_id
    WHERE b.year = :year
    GROUP BY u.id, u.name
    ORDER BY read_count DESC, total DESC
');
$stmt->execute(['year' => $year]);

$scores = array_map(function ($row) {
    return [
        'userId' => (int)$row['user_id'],
        'name' => $row['name'],
        'read' => (int)$row['read_count'],
        'total' => (int)$row['total'],
    ];
}, $stmt->fetchAll());

echo json_encode(['ok' => true, 'year' => $year, 'scores' => $scores]);
