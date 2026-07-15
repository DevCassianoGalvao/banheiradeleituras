<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'não autenticado']));
}

$userId = (int)$_SESSION['user_id'];

$booksStmt = $pdo->prepare('SELECT * FROM books WHERE user_id = :user_id ORDER BY year, sort_order');
$booksStmt->execute(['user_id' => $userId]);
$books = $booksStmt->fetchAll();

$bookIds = array_column($books, 'id');
$quotesByBook = [];
if (!empty($bookIds)) {
    $placeholders = implode(',', array_fill(0, count($bookIds), '?'));
    $quotesStmt = $pdo->prepare("SELECT book_id, text, page FROM quotes WHERE book_id IN ($placeholders)");
    $quotesStmt->execute($bookIds);
    foreach ($quotesStmt->fetchAll() as $q) {
        $quotesByBook[$q['book_id']][] = ['text' => $q['text'], 'page' => $q['page']];
    }
}

$booksOut = array_map(function ($b) use ($quotesByBook) {
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

$sessionsStmt = $pdo->prepare('SELECT id, book_id, date, seconds, pages FROM sessions WHERE user_id = :user_id ORDER BY date');
$sessionsStmt->execute(['user_id' => $userId]);
$sessions = array_map(function ($s) {
    return [
        'id' => $s['id'],
        'bookId' => $s['book_id'],
        'date' => $s['date'],
        'seconds' => (int)$s['seconds'],
        'pages' => (int)$s['pages'],
    ];
}, $sessionsStmt->fetchAll());

$goalsStmt = $pdo->prepare('SELECT month_key, type, target FROM month_goals WHERE user_id = :user_id');
$goalsStmt->execute(['user_id' => $userId]);
$goals = [];
foreach ($goalsStmt->fetchAll() as $g) {
    $goals[$g['month_key']] = ['type' => $g['type'], 'target' => (int)$g['target']];
}

$data = [
    'exportedAt' => date('c'),
    'books' => $booksOut,
    'sessions' => $sessions,
    'monthGoals' => $goals,
];

$filename = 'banheira-de-leituras-backup-' . date('Y-m-d') . '.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
