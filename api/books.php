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

function loadQuotes(PDO $pdo, array $bookIds): array
{
    if (empty($bookIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($bookIds), '?'));
    $stmt = $pdo->prepare("SELECT id, book_id, text, page FROM quotes WHERE book_id IN ($placeholders) ORDER BY id");
    $stmt->execute($bookIds);
    $byBook = [];
    foreach ($stmt->fetchAll() as $row) {
        $byBook[$row['book_id']][] = [
            'id' => (int)$row['id'],
            'text' => $row['text'],
            'page' => $row['page'],
        ];
    }
    return $byBook;
}

function bookBelongsToUser(PDO $pdo, string $bookId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM books WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $bookId, 'user_id' => $userId]);
    $book = $stmt->fetch();
    return $book ?: null;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Devolve todos os anos do usuário de uma vez — front-end filtra por
    // ano localmente (evita refetch a cada troca de ano na tela Lista).
    $stmt = $pdo->prepare('SELECT * FROM books WHERE user_id = :user_id ORDER BY year ASC, sort_order ASC');
    $stmt->execute(['user_id' => $userId]);
    $books = $stmt->fetchAll();

    $quotesByBook = loadQuotes($pdo, array_column($books, 'id'));

    $out = array_map(function ($b) use ($quotesByBook) {
        return [
            'id' => $b['id'],
            'year' => (int)$b['year'],
            'group' => $b['group'],
            'title' => $b['title'],
            'author' => $b['author'],
            'tag' => $b['tag'],
            'pages' => $b['pages'] !== null ? (int)$b['pages'] : null,
            'note' => $b['note'],
            'read' => (bool)$b['read_status'],
            'locked' => (bool)$b['locked'],
            'pending' => (bool)$b['pending'],
            'stars' => (int)$b['stars'],
            'finishedOn' => $b['finished_on'],
            'myNote' => $b['my_note'],
            'progress' => $b['progress'] !== null ? (int)$b['progress'] : null,
            'quotes' => $quotesByBook[$b['id']] ?? [],
        ];
    }, $books);

    echo json_encode(['ok' => true, 'books' => $out]);
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

$action = $input['action'] ?? '';
$validTags = ['leve', 'medio', 'denso', 'muito-denso'];

switch ($action) {

    case 'create':
        $title = trim((string)($input['title'] ?? ''));
        $author = trim((string)($input['author'] ?? ''));
        $year = (int)($input['year'] ?? date('Y'));
        $group = trim((string)($input['group'] ?? 'Outros'));
        $tag = $input['tag'] ?? '';
        $pages = isset($input['pages']) && $input['pages'] !== '' ? (int)$input['pages'] : null;
        $readNow = !empty($input['read']); // usado no registro retroativo ("Já li este")
        $finishedOn = $readNow ? (trim((string)($input['finishedOn'] ?? '')) ?: date('Y-m-d')) : null;
        $stars = isset($input['stars']) ? max(0, min(5, (int)$input['stars'])) : 0;

        if ($title === '' || $author === '') {
            http_response_code(422);
            exit(json_encode(['error' => 'título e autor são obrigatórios']));
        }

        $pending = !in_array($tag, $validTags, true);
        if ($pending) {
            $tag = 'leve';
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM books WHERE user_id = :user_id AND year = :year');
        $countStmt->execute(['user_id' => $userId, 'year' => $year]);
        $sortOrder = (int)$countStmt->fetchColumn();

        $id = uuid4();
        $insert = $pdo->prepare('
            INSERT INTO books (id, user_id, year, `group`, title, author, tag, pages, note, read_status, pending, stars, finished_on, sort_order)
            VALUES (:id, :user_id, :year, :group, :title, :author, :tag, :pages, :note, :read_status, :pending, :stars, :finished_on, :sort_order)
        ');
        $insert->execute([
            'id' => $id,
            'user_id' => $userId,
            'year' => $year,
            'group' => $group,
            'title' => $title,
            'author' => $author,
            'tag' => $tag,
            'pages' => $pages,
            'note' => '',
            'read_status' => $readNow ? 1 : 0,
            'pending' => $pending ? 1 : 0,
            'stars' => $stars,
            'finished_on' => $finishedOn,
            'sort_order' => $sortOrder,
        ]);

        echo json_encode(['ok' => true, 'id' => $id]);
        break;

    case 'update':
        $id = (string)($input['id'] ?? '');
        $book = bookBelongsToUser($pdo, $id, $userId);
        if (!$book) {
            http_response_code(404);
            exit(json_encode(['error' => 'livro não encontrado']));
        }

        $author = trim((string)($input['author'] ?? $book['author'])) ?: $book['author'];
        $tag = in_array($input['tag'] ?? '', $validTags, true) ? $input['tag'] : $book['tag'];
        $pages = isset($input['pages']) && $input['pages'] !== '' ? (int)$input['pages'] : null;
        $note = trim((string)($input['note'] ?? $book['note']));
        $stars = isset($input['stars']) ? max(0, min(5, (int)$input['stars'])) : (int)$book['stars'];
        $finishedOn = trim((string)($input['finishedOn'] ?? '')) ?: null;
        $myNote = trim((string)($input['myNote'] ?? $book['my_note']));
        $pending = $book['pending'] && in_array($input['tag'] ?? '', $validTags, true) ? 0 : $book['pending'];

        $update = $pdo->prepare('
            UPDATE books SET author = :author, tag = :tag, pages = :pages, note = :note,
                stars = :stars, finished_on = :finished_on, my_note = :my_note, pending = :pending
            WHERE id = :id AND user_id = :user_id
        ');
        $update->execute([
            'author' => $author,
            'tag' => $tag,
            'pages' => $pages,
            'note' => $note,
            'stars' => $stars,
            'finished_on' => $finishedOn,
            'my_note' => $myNote,
            'pending' => $pending,
            'id' => $id,
            'user_id' => $userId,
        ]);

        echo json_encode(['ok' => true]);
        break;

    case 'toggle-read':
        $id = (string)($input['id'] ?? '');
        $book = bookBelongsToUser($pdo, $id, $userId);
        if (!$book) {
            http_response_code(404);
            exit(json_encode(['error' => 'livro não encontrado']));
        }
        if ((bool)$book['locked']) {
            http_response_code(403);
            exit(json_encode(['error' => 'livro travado não pode ser desmarcado']));
        }

        $newRead = $book['read_status'] ? 0 : 1;
        $finishedOn = $book['finished_on'];
        if ($newRead && !$finishedOn) {
            $finishedOn = date('Y-m-d');
        }

        $update = $pdo->prepare('UPDATE books SET read_status = :read_status, finished_on = :finished_on WHERE id = :id AND user_id = :user_id');
        $update->execute([
            'read_status' => $newRead,
            'finished_on' => $finishedOn,
            'id' => $id,
            'user_id' => $userId,
        ]);

        echo json_encode(['ok' => true, 'read' => (bool)$newRead, 'finishedOn' => $finishedOn]);
        break;

    case 'reorder':
        $id = (string)($input['id'] ?? '');
        $direction = (int)($input['direction'] ?? 0);
        if (!in_array($direction, [-1, 1], true)) {
            http_response_code(422);
            exit(json_encode(['error' => 'direção inválida']));
        }

        $book = bookBelongsToUser($pdo, $id, $userId);
        if (!$book) {
            http_response_code(404);
            exit(json_encode(['error' => 'livro não encontrado']));
        }

        $listStmt = $pdo->prepare('SELECT id, sort_order FROM books WHERE user_id = :user_id AND year = :year ORDER BY sort_order ASC');
        $listStmt->execute(['user_id' => $userId, 'year' => $book['year']]);
        $yearList = $listStmt->fetchAll();

        $fromIndex = array_search($id, array_column($yearList, 'id'), true);
        $toIndex = $fromIndex + $direction;

        if ($fromIndex === false || $toIndex < 0 || $toIndex >= count($yearList)) {
            echo json_encode(['ok' => true]); // nos limites, não faz nada
            break;
        }

        $bookA = $yearList[$fromIndex];
        $bookB = $yearList[$toIndex];

        $pdo->beginTransaction();
        $swap = $pdo->prepare('UPDATE books SET sort_order = :sort_order WHERE id = :id AND user_id = :user_id');
        $swap->execute(['sort_order' => $bookB['sort_order'], 'id' => $bookA['id'], 'user_id' => $userId]);
        $swap->execute(['sort_order' => $bookA['sort_order'], 'id' => $bookB['id'], 'user_id' => $userId]);
        $pdo->commit();

        echo json_encode(['ok' => true]);
        break;

    case 'delete':
        $id = (string)($input['id'] ?? '');
        $book = bookBelongsToUser($pdo, $id, $userId);
        if (!$book) {
            http_response_code(404);
            exit(json_encode(['error' => 'livro não encontrado']));
        }
        if ((bool)$book['locked']) {
            http_response_code(403);
            exit(json_encode(['error' => 'livro travado não pode ser removido']));
        }

        $del = $pdo->prepare('DELETE FROM books WHERE id = :id AND user_id = :user_id');
        $del->execute(['id' => $id, 'user_id' => $userId]);

        echo json_encode(['ok' => true]);
        break;

    case 'add-quote':
        $bookId = (string)($input['bookId'] ?? '');
        $text = trim((string)($input['text'] ?? ''));
        $page = trim((string)($input['page'] ?? ''));

        if ($text === '') {
            http_response_code(422);
            exit(json_encode(['error' => 'texto da citação é obrigatório']));
        }

        $book = bookBelongsToUser($pdo, $bookId, $userId);
        if (!$book) {
            http_response_code(404);
            exit(json_encode(['error' => 'livro não encontrado']));
        }

        $insert = $pdo->prepare('INSERT INTO quotes (book_id, text, page) VALUES (:book_id, :text, :page)');
        $insert->execute(['book_id' => $bookId, 'text' => $text, 'page' => $page ?: null]);

        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        break;

    case 'delete-quote':
        $quoteId = (int)($input['quoteId'] ?? 0);

        $check = $pdo->prepare('SELECT q.id FROM quotes q INNER JOIN books b ON b.id = q.book_id WHERE q.id = :quote_id AND b.user_id = :user_id');
        $check->execute(['quote_id' => $quoteId, 'user_id' => $userId]);
        if (!$check->fetch()) {
            http_response_code(404);
            exit(json_encode(['error' => 'citação não encontrada']));
        }

        $del = $pdo->prepare('DELETE FROM quotes WHERE id = :id');
        $del->execute(['id' => $quoteId]);

        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'ação desconhecida']);
}
