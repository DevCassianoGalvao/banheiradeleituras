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

$name = trim((string)($input['name'] ?? ''));
$email = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($name === '' || $email === '' || strlen($password) < 6) {
    http_response_code(422);
    exit(json_encode(['error' => 'preencha nome, e-mail e senha (mínimo 6 caracteres)']));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    exit(json_encode(['error' => 'e-mail inválido']));
}

$check = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$check->execute(['email' => $email]);
if ($check->fetch()) {
    http_response_code(409);
    exit(json_encode(['error' => 'já existe uma conta com este e-mail']));
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$insert = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)');
$insert->execute([
    'name' => $name,
    'email' => $email,
    'hash' => $hash,
]);

$userId = (int)$pdo->lastInsertId();

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['user_name'] = $name;

echo json_encode([
    'ok' => true,
    'user' => ['id' => $userId, 'name' => $name, 'email' => $email],
]);
