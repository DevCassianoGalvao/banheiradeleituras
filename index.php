<?php
declare(strict_types=1);
session_start();

$loggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
$userId = $_SESSION['user_id'] ?? null;
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

<!-- ===================== TELA: BANHEIRA (home) ===================== -->
<div class="screen active" id="screen-home">
  <div class="hero">
    <div class="tile-strip" id="tileStripTop"></div>
    <div class="hero-text">
      <span class="eyebrow">🕯️ projeto pessoal</span>
      <h1 class="hero-title">Minha banheira<br>de <em>leituras</em></h1>
      <p class="hero-sub" id="homeSub">Marcando cada livro conforme termino, rumo à meta do ano.</p>
    </div>
  </div>

  <div class="wrap">

    <div class="streak-row">
      <div class="streak-card">
        <span class="flame">🔥</span>
        <span class="num" id="streakNum">0</span>
        <span class="lbl">dias seguidos lendo</span>
      </div>
      <div class="streak-card">
        <span class="flame">⏱️</span>
        <span class="num" id="totalTimeToday">0min</span>
        <span class="lbl">lido hoje</span>
      </div>
    </div>

    <div class="timer-card">
      <h3>⏱️ Sessão de leitura</h3>
      <select class="timer-book-select" id="timerBookSelect"></select>
      <div class="timer-display" id="timerDisplay">00:00:00</div>
      <div class="timer-controls">
        <button class="timer-btn play" id="timerPlayBtn">▶</button>
        <button class="timer-btn pause" id="timerPauseBtn" style="display:none;">⏸</button>
        <button class="timer-btn stop" id="timerStopBtn" style="display:none;">⏹</button>
      </div>
      <div class="timer-pages">
        <label for="timerPages">Páginas lidas:</label>
        <input type="number" id="timerPages" placeholder="0">
      </div>
    </div>

    <div class="month-goal-card">
      <div class="top-row">
        <h3>🎯 Meta do mês — <span id="monthLabel"></span></h3>
        <button class="edit-link" id="editGoalBtn">editar meta</button>
      </div>
      <div class="mini-tub2"><div class="mini-tub2-fill" id="monthGoalFill" style="width:0%"></div></div>
      <p class="stat-line" id="monthGoalStat"></p>
    </div>

    <div class="tub-card">
      <div class="tub-header">
        <p class="tub-title">🛁 nível da banheira — <span id="tubYearLabel"></span></p>
        <p class="tub-count" id="readCount">0<span> / <span id="totalCount">0</span></span></p>
      </div>
      <div class="tub">
        <div class="tub-bg-tiles">
          <span style="background:var(--tile-orange)"></span><span style="background:var(--tile-teal)"></span>
          <span style="background:var(--tile-blue)"></span><span style="background:var(--tile-mustard)"></span>
          <span style="background:var(--tile-orange)"></span><span style="background:var(--tile-teal)"></span>
          <span style="background:var(--tile-blue)"></span><span style="background:var(--tile-mustard)"></span>
        </div>
        <div class="tub-fill" id="tubFill" style="width:0%"><span class="ducks">🦆</span></div>
      </div>
      <p class="tub-caption">Toque em qualquer livro na lista pra marcar como lido</p>
      <div class="legend">
        <span><i class="dot" style="background:var(--leaf)"></i>Leve</span>
        <span><i class="dot" style="background:var(--duck)"></i>Médio</span>
        <span><i class="dot" style="background:var(--tomato)"></i>Denso</span>
        <span><i class="dot" style="background:var(--ink)"></i>Muito denso</span>
      </div>
    </div>

    <div class="chip-row">
      <div class="chip"><span class="num" id="chipRead">0</span><span class="lbl">lidos</span></div>
      <div class="chip"><span class="num" id="chipPending">0</span><span class="lbl">pendentes</span></div>
      <div class="chip"><span class="num" id="chipStars">—</span><span class="lbl">média ⭐</span></div>
    </div>

    <button class="btn full primary" data-go="lista">📖 Ver minha lista completa</button>
    <button class="btn full ghost" id="btnExport" style="margin-top:8px;">⬇️ Exportar meus dados (backup)</button>
    <button class="btn full ghost" id="btnLogout" style="margin-top:8px;">Sair (<?= htmlspecialchars($userName) ?>)</button>
  </div>
</div>

<!-- ===================== TELA: LISTA ===================== -->
<div class="screen" id="screen-lista">
  <div class="page-header">
    <h1>📖 Minha lista</h1>
    <p>Toque no círculo pra marcar como lido · toque no livro pra editar nota e estrelas</p>
  </div>
  <main class="list">
    <div class="toolbar">
      <button class="btn primary" id="btnAddBook">+ Adicionar</button>
      <button class="btn ghost small" id="btnRetroactive">📅 Já li este</button>
      <button class="btn ghost small" id="btnAddYear" title="Trocar/adicionar ano">📅 <span id="listYearLabel"></span></button>
    </div>
    <button class="btn full" id="btnMood" style="margin-bottom:14px; background:var(--duck);">✨ O que eu leio hoje?</button>
    <div class="filter-row" id="filterRow">
      <button class="filter-chip active" data-filter="todos">Todos</button>
      <button class="filter-chip" data-filter="pendente">⏳ Pendentes</button>
      <button class="filter-chip" data-filter="lido">✓ Lidos</button>
      <button class="filter-chip" data-filter="leve">Leve</button>
      <button class="filter-chip" data-filter="medio">Médio</button>
      <button class="filter-chip" data-filter="denso">Denso</button>
    </div>
    <div id="bookListContainer"></div>
  </main>
</div>

<!-- ===================== TELA: ESTATÍSTICAS ===================== -->
<div class="screen" id="screen-stats">
  <div class="page-header">
    <h1>📊 Estatísticas</h1>
    <p>Seu ritmo de leitura, ano a ano</p>
  </div>
  <main class="list">
    <div class="stats-year-picker" id="statsYearPicker"></div>
    <div id="statsContent"></div>
  </main>
</div>

<!-- ===================== TELA: PLACAR ===================== -->
<div class="screen" id="screen-placar">
  <div class="page-header">
    <h1>🏆 Placar</h1>
    <p>Compare seu progresso com outras contas deste app</p>
  </div>
  <main class="list">
    <div id="placarSetup"></div>
    <div id="placarContent"></div>
  </main>
</div>

<!-- ===================== NAV INFERIOR ===================== -->
<nav class="bottom-nav">
  <button class="nav-btn active" data-go="home"><span class="ic">🛁</span>Banheira</button>
  <button class="nav-btn" data-go="lista"><span class="ic">📖</span>Lista</button>
  <button class="nav-btn" data-go="stats"><span class="ic">📊</span>Estatísticas</button>
  <button class="nav-btn" data-go="placar"><span class="ic">🏆</span>Placar</button>
</nav>

<!-- ===================== MODAL ===================== -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal" id="modalBody"></div>
</div>

<div class="toast" id="toast"></div>

<script>
  // Identidade do usuário logado, injetada pelo PHP — usada no Placar
  // pra destacar "(você)" e nos fetch()s, sempre com caminho relativo.
  window.CURRENT_USER = { id: <?= (int)$userId ?>, name: <?= json_encode($userName, JSON_UNESCAPED_UNICODE) ?> };
</script>
<script src="assets/js/app.js"></script>
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
