<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/openai.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'não autenticado']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);
$title = trim((string)($input['title'] ?? ''));
$author = trim((string)($input['author'] ?? ''));

if ($title === '' && $author === '') {
    http_response_code(422);
    exit(json_encode(['error' => 'informe ao menos o título ou o autor']));
}

$validTags = ['leve', 'medio', 'denso', 'muito-denso'];
$hasBoth = $title !== '' && $author !== '';

// Cache só se aplica quando título e autor já vieram definidos — com
// só um dos dois a IA precisa "adivinhar" o outro, então o resultado
// não é reaproveitável de forma confiável pra outra consulta.
if ($hasBoth) {
    // Normalização igual à documentada no schema (metadata_cache.cache_key):
    // lower(trim(title)) + '|' + lower(trim(author))
    $cacheKey = mb_strtolower($title) . '|' . mb_strtolower($author);

    $stmt = $pdo->prepare('SELECT tag, pages, note FROM metadata_cache WHERE cache_key = :cache_key');
    $stmt->execute(['cache_key' => $cacheKey]);
    if ($cached = $stmt->fetch()) {
        echo json_encode([
            'ok' => true,
            'tag' => $cached['tag'],
            'pages' => $cached['pages'] !== null ? (int)$cached['pages'] : null,
            'note' => $cached['note'],
            'cached' => true,
        ]);
        exit;
    }

    $prompt = "Livro: \"$title\" de $author.\n"
        . "Responda APENAS um JSON válido, sem markdown, com este formato exato:\n"
        . '{"tag":"leve|medio|denso|muito-denso","pages":NUMBER_OR_NULL,"note":"1-2 frases descrevendo o livro"}' . "\n"
        . "Classifique 'tag' pelo peso emocional/intelectual de leitura, não pelo tamanho.";
} elseif ($title !== '') {
    $prompt = "Livro: \"$title\" (autor não informado — identifique pelo título).\n"
        . "Responda APENAS um JSON válido, sem markdown, com este formato exato:\n"
        . '{"author":"nome do autor ou autora","tag":"leve|medio|denso|muito-denso","pages":NUMBER_OR_NULL,"note":"1-2 frases descrevendo o livro"}' . "\n"
        . "Classifique 'tag' pelo peso emocional/intelectual de leitura, não pelo tamanho. "
        . "Se não conseguir identificar o livro com confiança, responda com \"author\":null.";
} else {
    $prompt = "Autor(a): \"$author\" (título não informado — sugira a obra mais conhecida dessa pessoa).\n"
        . "Responda APENAS um JSON válido, sem markdown, com este formato exato:\n"
        . '{"title":"título do livro","tag":"leve|medio|denso|muito-denso","pages":NUMBER_OR_NULL,"note":"1-2 frases descrevendo o livro"}' . "\n"
        . "Classifique 'tag' pelo peso emocional/intelectual de leitura, não pelo tamanho. "
        . "Se não conseguir identificar uma obra com confiança, responda com \"title\":null.";
}

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY,
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.3,
        'response_format' => ['type' => 'json_object'],
    ]),
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    // Falha na IA nunca trava o cadastro (regra do CLAUDE.md) — o
    // front-end trata este erro deixando os campos em branco/editáveis.
    http_response_code(502);
    exit(json_encode(['error' => 'busca automática indisponível no momento', 'detail' => $curlError ?: null]));
}

$body = json_decode($response, true);
$content = $body['choices'][0]['message']['content'] ?? null;
$metadata = $content ? json_decode($content, true) : null;

if (!is_array($metadata) || !isset($metadata['tag']) || !in_array($metadata['tag'], $validTags, true)) {
    http_response_code(502);
    exit(json_encode(['error' => 'resposta da IA veio em formato inesperado']));
}

$resolvedTitle = $title !== '' ? $title : trim((string)($metadata['title'] ?? ''));
$resolvedAuthor = $author !== '' ? $author : trim((string)($metadata['author'] ?? ''));

if ($resolvedTitle === '' || $resolvedAuthor === '') {
    http_response_code(404);
    exit(json_encode(['error' => 'não consegui identificar o livro com confiança — preencha manualmente']));
}

$tag = $metadata['tag'];
$pages = isset($metadata['pages']) && is_numeric($metadata['pages']) ? (int)$metadata['pages'] : null;
$note = trim((string)($metadata['note'] ?? ''));

if ($hasBoth) {
    // grava no cache pra próxima consulta (de qualquer usuário) não pagar de novo
    $upsert = $pdo->prepare('
        INSERT INTO metadata_cache (cache_key, tag, pages, note)
        VALUES (:cache_key, :tag, :pages, :note)
        ON DUPLICATE KEY UPDATE tag = VALUES(tag), pages = VALUES(pages), note = VALUES(note)
    ');
    $upsert->execute(['cache_key' => $cacheKey, 'tag' => $tag, 'pages' => $pages, 'note' => $note]);
}

echo json_encode([
    'ok' => true,
    'title' => $title === '' ? $resolvedTitle : null,
    'author' => $author === '' ? $resolvedAuthor : null,
    'tag' => $tag,
    'pages' => $pages,
    'note' => $note,
    'cached' => false,
]);
