<?php
declare(strict_types=1);
session_start();

$loggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Banheira de Leituras</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if ($loggedIn): ?>

  <div class="page-header">
    <h1>🛁 Banheira de Leituras</h1>
    <p>Bem-vinda(o), <?= htmlspecialchars($userName) ?>.</p>
  </div>
  <div class="wrap">
    <div class="tub-card">
      <p>Fundação instalada: login funcionando. As telas de banheira, lista,
      estatísticas e placar entram nas próximas fases.</p>
      <button class="btn full ghost" id="btnLogout" style="margin-top:10px;">Sair</button>
    </div>
  </div>

  <script>
    document.getElementById('btnLogout').addEventListener('click', async () => {
      await fetch('api/logout.php', { method: 'POST' });
      location.reload();
    });
  </script>

<?php else: ?>

  <div class="page-header">
    <h1>🛁 Banheira de Leituras</h1>
    <p>Entre ou crie sua conta pra começar a acompanhar suas leituras.</p>
  </div>

  <div class="wrap">
    <div class="filter-row" id="authTabs">
      <button class="filter-chip active" data-tab="login">Entrar</button>
      <button class="filter-chip" data-tab="register">Criar conta</button>
    </div>

    <div class="setup-name" id="loginForm">
      <div class="field">
        <label>E-mail</label>
        <input type="email" id="loginEmail" placeholder="voce@email.com">
      </div>
      <div class="field">
        <label>Senha</label>
        <input type="password" id="loginPassword" placeholder="••••••">
      </div>
      <button class="btn full primary" id="btnLogin">Entrar</button>
    </div>

    <div class="setup-name" id="registerForm" style="display:none;">
      <div class="field">
        <label>Nome</label>
        <input type="text" id="registerName" placeholder="Seu nome">
      </div>
      <div class="field">
        <label>E-mail</label>
        <input type="email" id="registerEmail" placeholder="voce@email.com">
      </div>
      <div class="field">
        <label>Senha</label>
        <input type="password" id="registerPassword" placeholder="mínimo 6 caracteres">
      </div>
      <button class="btn full primary" id="btnRegister">Criar conta</button>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script>
    // Caminhos sempre relativos (sem "/" na frente) — app roda numa
    // subpasta do domínio, não na raiz. Ver CLAUDE.md, "Deploy".
    const tabs = document.querySelectorAll('#authTabs .filter-chip');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        const isLogin = tab.dataset.tab === 'login';
        loginForm.style.display = isLogin ? 'block' : 'none';
        registerForm.style.display = isLogin ? 'none' : 'block';
      });
    });

    function showToast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      setTimeout(() => t.classList.remove('show'), 2200);
    }

    document.getElementById('btnLogin').addEventListener('click', async () => {
      const email = document.getElementById('loginEmail').value.trim();
      const password = document.getElementById('loginPassword').value;
      try {
        const res = await fetch('api/login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, password }),
        });
        const data = await res.json();
        if (!res.ok) { showToast(data.error || 'Erro ao entrar'); return; }
        location.reload();
      } catch (e) { showToast('Falha de conexão.'); }
    });

    document.getElementById('btnRegister').addEventListener('click', async () => {
      const name = document.getElementById('registerName').value.trim();
      const email = document.getElementById('registerEmail').value.trim();
      const password = document.getElementById('registerPassword').value;
      try {
        const res = await fetch('api/register.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ name, email, password }),
        });
        const data = await res.json();
        if (!res.ok) { showToast(data.error || 'Erro ao criar conta'); return; }
        location.reload();
      } catch (e) { showToast('Falha de conexão.'); }
    });
  </script>

<?php endif; ?>

</body>
</html>
