<?php
/**
 * Instalador — Banheira de Leituras
 *
 * Roda uma única vez após o deploy: cria o schema no MySQL, grava
 * config/db.php e config/openai.php a partir dos .example, e cria o
 * primeiro usuário admin. Se autobloqueia se config/db.php já existir,
 * pra ninguém rodar de novo por engano (ou por acidente, na internet).
 *
 * Depois de instalar com sucesso, apague este arquivo do servidor.
 */

declare(strict_types=1);

$dbConfigPath = __DIR__ . '/config/db.php';
$openaiConfigPath = __DIR__ . '/config/openai.php';
$dbExampleFile = __DIR__ . '/config/db.php.example';
$openaiExampleFile = __DIR__ . '/config/openai.php.example';
$schemaFile = __DIR__ . '/schema.sql';

$alreadyInstalled = file_exists($dbConfigPath);

$errors = [];
$success = false;

if ($alreadyInstalled) {
    http_response_code(403);
}

if (!$alreadyInstalled && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $openaiKey = trim($_POST['openai_key'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass = (string)($_POST['admin_pass'] ?? '');

    if ($dbHost === '') $errors[] = 'Host do MySQL é obrigatório.';
    if ($dbName === '') $errors[] = 'Nome do banco é obrigatório.';
    if ($dbUser === '') $errors[] = 'Usuário do MySQL é obrigatório.';
    if ($openaiKey === '') $errors[] = 'Chave da OpenAI é obrigatória.';
    if ($adminName === '') $errors[] = 'Nome do admin é obrigatório.';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail do admin inválido.';
    if (strlen($adminPass) < 6) $errors[] = 'Senha do admin precisa ter ao menos 6 caracteres.';
    if (!is_file($schemaFile)) $errors[] = 'schema.sql não encontrado na raiz do projeto.';
    if (!is_file($dbExampleFile)) $errors[] = 'config/db.php.example não encontrado.';
    if (!is_file($openaiExampleFile)) $errors[] = 'config/openai.php.example não encontrado.';
    if (!is_writable(__DIR__ . '/config')) $errors[] = 'Pasta config/ não é gravável pelo PHP (ajuste permissão).';

    if (empty($errors)) {
        try {
            $pdo = new PDO(
                "mysql:host={$dbHost};charset=utf8mb4",
                $dbUser,
                $dbPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            // Cria o banco se ainda não existir, depois seleciona ele.
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . str_replace('`', '', $dbName) . '`');

            $sql = file_get_contents($schemaFile);
            if ($sql === false) {
                throw new RuntimeException('Não foi possível ler schema.sql');
            }

            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                $pdo->exec($statement);
            }

            $adminPassHash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)');
            $stmt->execute([
                'name' => $adminName,
                'email' => $adminEmail,
                'hash' => $adminPassHash,
            ]);

            $success = true;
        } catch (Throwable $e) {
            $errors[] = 'Falha na instalação: ' . $e->getMessage();
        }
    }

    if ($success) {
        $now = date('Y-m-d H:i:s');

        $dbConfigContent = "<?php\n"
            . "// Gerado pelo install.php em {$now}. Nunca commitar este arquivo.\n\n"
            . "\$DB_HOST = " . var_export($dbHost, true) . ";\n"
            . "\$DB_NAME = " . var_export($dbName, true) . ";\n"
            . "\$DB_USER = " . var_export($dbUser, true) . ";\n"
            . "\$DB_PASS = " . var_export($dbPass, true) . ";\n\n"
            . "try {\n"
            . "    \$pdo = new PDO(\n"
            . "        \"mysql:host={\$DB_HOST};dbname={\$DB_NAME};charset=utf8mb4\",\n"
            . "        \$DB_USER,\n"
            . "        \$DB_PASS,\n"
            . "        [\n"
            . "            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n"
            . "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n"
            . "            PDO::ATTR_EMULATE_PREPARES => false,\n"
            . "        ]\n"
            . "    );\n"
            . "} catch (PDOException \$e) {\n"
            . "    http_response_code(500);\n"
            . "    exit(json_encode(['error' => 'database connection failed']));\n"
            . "}\n";

        $openaiConfigContent = "<?php\n"
            . "// Gerado pelo install.php em {$now}. Nunca commitar este arquivo, nunca\n"
            . "// expor esta chave em código JS/front-end.\n\n"
            . "define('OPENAI_API_KEY', " . var_export($openaiKey, true) . ");\n";

        file_put_contents($dbConfigPath, $dbConfigContent, LOCK_EX);
        file_put_contents($openaiConfigPath, $openaiConfigContent, LOCK_EX);
        @chmod($dbConfigPath, 0640);
        @chmod($openaiConfigPath, 0640);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalação — Banheira de Leituras</title>
<style>
  body{ font-family: sans-serif; max-width: 560px; margin: 40px auto; padding: 0 16px; color:#2a2422; }
  h1{ font-size: 20px; }
  fieldset{ border: 2px solid #2a2422; border-radius: 12px; margin-bottom: 16px; padding: 14px; }
  legend{ font-weight: bold; padding: 0 6px; }
  label{ display:block; font-size: 13px; font-weight:600; margin: 10px 0 4px; }
  input{ width:100%; padding:8px; border:2px solid #2a2422; border-radius:8px; font-size:14px; box-sizing:border-box; }
  button{ background:#5c8a4f; color:#fff; border:none; padding:12px 20px; border-radius:999px; font-weight:bold; font-size:14px; cursor:pointer; }
  .error{ background:#fbe2e0; border:2px solid #d6433f; border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:13px; }
  .success{ background:#e8f1e0; border:2px solid #5c8a4f; border-radius:8px; padding:14px; font-size:14px; }
  .blocked{ background:#fbe2e0; border:2px solid #d6433f; border-radius:8px; padding:14px; font-size:14px; }
</style>
</head>
<body>
<h1>🛁 Instalação — Banheira de Leituras</h1>

<?php if ($alreadyInstalled): ?>
  <div class="blocked">
    <strong>Instalação bloqueada.</strong> <code>config/db.php</code> já existe —
    o app já foi instalado. Apague este arquivo (<code>install.php</code>) do
    servidor. Se precisar reinstalar, remova <code>config/db.php</code> manualmente
    primeiro (isso apaga o acesso ao banco atual).
  </div>

<?php elseif ($success): ?>
  <div class="success">
    <strong>Instalação concluída!</strong><br>
    Tabelas criadas, <code>config/db.php</code> e <code>config/openai.php</code>
    gravados, usuário admin criado.<br><br>
    <strong>Apague este arquivo (<code>install.php</code>) do servidor agora.</strong>
    Depois acesse <code>index.php</code> pra fazer login.
  </div>

<?php else: ?>

  <?php if (!empty($errors)): ?>
    <div class="error">
      <strong>Corrija antes de continuar:</strong>
      <ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form method="POST">
    <fieldset>
      <legend>Banco de dados (MySQL / cPanel)</legend>
      <label for="db_host">Host</label>
      <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
      <label for="db_name">Nome do banco</label>
      <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
      <label for="db_user">Usuário</label>
      <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
      <label for="db_pass">Senha</label>
      <input type="password" id="db_pass" name="db_pass">
    </fieldset>

    <fieldset>
      <legend>OpenAI</legend>
      <label for="openai_key">Chave da API (sk-...)</label>
      <input type="text" id="openai_key" name="openai_key" value="<?= htmlspecialchars($_POST['openai_key'] ?? '') ?>" required>
    </fieldset>

    <fieldset>
      <legend>Primeiro usuário (admin)</legend>
      <label for="admin_name">Nome</label>
      <input type="text" id="admin_name" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required>
      <label for="admin_email">E-mail</label>
      <input type="email" id="admin_email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
      <label for="admin_pass">Senha (mín. 6 caracteres)</label>
      <input type="password" id="admin_pass" name="admin_pass" required>
    </fieldset>

    <button type="submit">Instalar</button>
  </form>

<?php endif; ?>

</body>
</html>
