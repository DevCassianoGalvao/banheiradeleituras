<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$email = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(422);
    exit(json_encode(['error' => 'informe e-mail e senha']));
}

$stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = :email');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'e-mail ou senha inválidos']));
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_name'] = $user['name'];

echo json_encode([
    'ok' => true,
    'user' => ['id' => (int)$user['id'], 'name' => $user['name'], 'email' => $user['email']],
]);
