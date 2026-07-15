<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/helpers.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'não autenticado']));
}

$userId = (int)$_SESSION['user_id'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT id, book_id, date, seconds, pages FROM sessions WHERE user_id = :user_id ORDER BY date DESC');
    $stmt->execute(['user_id' => $userId]);
    $sessions = array_map(function ($s) {
        return [
            'id' => $s['id'],
            'bookId' => $s['book_id'],
            'date' => $s['date'],
            'seconds' => (int)$s['seconds'],
            'pages' => (int)$s['pages'],
        ];
    }, $stmt->fetchAll());

    echo json_encode(['ok' => true, 'sessions' => $sessions]);
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

$bookId = (string)($input['bookId'] ?? '');
$seconds = max(0, (int)($input['seconds'] ?? 0));
$pages = max(0, (int)($input['pages'] ?? 0));
$date = trim((string)($input['date'] ?? '')) ?: date('Y-m-d');

if ($bookId === '' || ($seconds === 0 && $pages === 0)) {
    http_response_code(422);
    exit(json_encode(['error' => 'informe o livro e ao menos tempo ou páginas lidas']));
}

$check = $pdo->prepare('SELECT id FROM books WHERE id = :id AND user_id = :user_id');
$check->execute(['id' => $bookId, 'user_id' => $userId]);
if (!$check->fetch()) {
    http_response_code(404);
    exit(json_encode(['error' => 'livro não encontrado']));
}

$id = uuid4();
$insert = $pdo->prepare('INSERT INTO sessions (id, book_id, user_id, date, seconds, pages) VALUES (:id, :book_id, :user_id, :date, :seconds, :pages)');
$insert->execute([
    'id' => $id,
    'book_id' => $bookId,
    'user_id' => $userId,
    'date' => $date,
    'seconds' => $seconds,
    'pages' => $pages,
]);

echo json_encode(['ok' => true, 'id' => $id]);
