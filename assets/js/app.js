/* =======================================================================
   Banheira de Leituras — front-end da área logada.
   Porta a lógica do protótipo (window.storage) pra API PHP real via fetch.
   Todos os caminhos são relativos ("api/..."), nunca começando com "/" —
   o app roda numa subpasta do domínio (ver CLAUDE.md, "Deploy").
   ======================================================================= */

const TAG_LABEL = { leve: 'Leve', medio: 'Médio', denso: 'Denso', 'muito-denso': 'Muito denso', pendente: 'A pesquisar' };

let books = [];
let sessions = [];
let monthGoals = {};
let currentYear = new Date().getFullYear();
let currentFilter = 'todos';
let currentSearch = '';

let timerRunning = false;
let timerSeconds = 0;
let timerInterval = null;
let timerBookId = null;

/* =======================================================================
   API — livros
   ======================================================================= */
async function apiBooksAction(body) {
  const res = await fetch('api/books.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || 'Erro ao salvar.');
  return data;
}

async function loadAll() {
  const res = await fetch('api/books.php');
  const data = await res.json().catch(() => ({}));
  books = (res.ok && data.books) ? data.books : [];
}

async function createBook(payload) {
  await apiBooksAction({ action: 'create', ...payload });
  await loadAll();
}

async function updateBook(id, payload) {
  await apiBooksAction({ action: 'update', id, ...payload });
  await loadAll();
}

async function toggleRead(id) {
  const b = books.find(x => x.id === id);
  if (!b || b.locked) return;
  try {
    await apiBooksAction({ action: 'toggle-read', id });
    await loadAll();
    renderLista();
    renderHome();
    const nowRead = books.find(x => x.id === id)?.read;
    showToast(nowRead ? '🦆 Marcado como lido!' : 'Desmarcado.');
  } catch (e) {
    showToast(e.message);
  }
}

async function moveBook(id, direction) {
  try {
    await apiBooksAction({ action: 'reorder', id, direction });
    await loadAll();
    renderLista();
  } catch (e) {
    showToast(e.message);
  }
}

async function deleteBook(id) {
  try {
    await apiBooksAction({ action: 'delete', id });
    await loadAll();
  } catch (e) {
    showToast(e.message);
  }
}

/* =======================================================================
   API — sessões, metas, placar
   ======================================================================= */
async function loadSessions() {
  const res = await fetch('api/sessions.php');
  const data = await res.json().catch(() => ({}));
  sessions = (res.ok && data.sessions) ? data.sessions : [];
}

async function saveSession(payload) {
  const res = await fetch('api/sessions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || 'Erro ao salvar sessão.');
  return data;
}

async function loadGoals() {
  const res = await fetch('api/month-goals.php');
  const data = await res.json().catch(() => ({}));
  monthGoals = (res.ok && data.goals) ? data.goals : {};
}

async function saveGoal(monthKeyVal, type, target) {
  const res = await fetch('api/month-goals.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ month: monthKeyVal, type, target }),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || 'Erro ao salvar meta.');
}

async function fetchScoreboard(year) {
  const res = await fetch('api/scoreboard.php?year=' + encodeURIComponent(year));
  const data = await res.json().catch(() => ({}));
  return (res.ok && data.scores) ? data.scores : [];
}

/* =======================================================================
   HELPERS
   ======================================================================= */
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 1800);
}

function yearsAvailable() {
  const ys = new Set(books.map(b => b.year));
  ys.add(currentYear);
  return [...ys].sort();
}

function booksOfYear(year) {
  return books.filter(b => b.year === year);
}

function formatDate(iso) {
  if (!iso) return '';
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
}

function escapeHtml(s) {
  return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function todayISO() { return new Date().toISOString().slice(0, 10); }
function monthKey(d) { return d.slice(0, 7); }

function calcStreak() {
  const days = new Set(sessions.map(s => s.date));
  let streak = 0;
  let cursor = new Date();
  while (true) {
    const iso = cursor.toISOString().slice(0, 10);
    if (days.has(iso)) {
      streak++;
      cursor.setDate(cursor.getDate() - 1);
    } else {
      break;
    }
  }
  return streak;
}

function minutesReadToday() {
  const today = todayISO();
  const secs = sessions.filter(s => s.date === today).reduce((sum, s) => sum + s.seconds, 0);
  return Math.round(secs / 60);
}

/* =======================================================================
   NAVEGAÇÃO
   ======================================================================= */
function goTo(screen) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById('screen-' + screen).classList.add('active');
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
  const navBtn = document.querySelector(`.nav-btn[data-go="${screen}"]`);
  if (navBtn) navBtn.classList.add('active');
  window.scrollTo({ top: 0 });

  if (screen === 'home') {
    renderHome();
    document.getElementById('streakNum').textContent = calcStreak();
    document.getElementById('totalTimeToday').textContent = minutesReadToday() + 'min';
    renderMonthGoal();
    populateTimerBookSelect();
  }
  if (screen === 'lista') renderLista();
  if (screen === 'stats') renderStats();
  if (screen === 'placar') renderPlacar();
}

document.querySelectorAll('[data-go]').forEach(el => {
  el.addEventListener('click', () => goTo(el.dataset.go));
});

/* =======================================================================
   RENDER: HOME (banheira)
   ======================================================================= */
function renderHome() {
  document.getElementById('tubYearLabel').textContent = currentYear;
  const yearBooks = booksOfYear(currentYear);
  const total = yearBooks.length;
  const read = yearBooks.filter(b => b.read).length;
  const pending = yearBooks.filter(b => !b.read).length; // fila de leitura, não só "a pesquisar"
  const pct = total ? Math.round((read / total) * 100) : 0;

  document.getElementById('readCount').firstChild.textContent = read;
  document.getElementById('totalCount').textContent = total;
  document.getElementById('tubFill').style.width = pct + '%';
  document.getElementById('chipRead').textContent = read;
  document.getElementById('chipPending').textContent = pending;

  const starred = yearBooks.filter(b => b.read && b.stars > 0);
  const avgStars = starred.length ? (starred.reduce((s, b) => s + b.stars, 0) / starred.length).toFixed(1) : '—';
  document.getElementById('chipStars').textContent = avgStars;

  const remaining = Math.max(0, total - read);
  document.getElementById('homeSub').textContent = remaining > 0
    ? `Faltam ${remaining} livro${remaining === 1 ? '' : 's'} pra encher a banheira de ${currentYear}.`
    : `Banheira cheia! Você leu tudo que planejou pra ${currentYear} 🎉`;
}

/* =======================================================================
   RENDER: LISTA
   ======================================================================= */
function renderLista() {
  document.getElementById('listYearLabel').textContent = currentYear;
  const container = document.getElementById('bookListContainer');
  container.innerHTML = '';

  let list = booksOfYear(currentYear);

  // "Pendentes" pro usuário = fila de leitura (tudo não lido ainda),
  // não só os "⏳ a pesquisar" (peso/páginas desconhecidos) — esse
  // subconjunto continua com o badge próprio no card.
  if (currentFilter === 'pendente') list = list.filter(b => !b.read);
  else if (currentFilter === 'lido') list = list.filter(b => b.read);
  else if (['leve', 'medio', 'denso'].includes(currentFilter)) list = list.filter(b => b.tag === currentFilter);

  if (currentSearch) {
    const q = currentSearch.toLowerCase();
    list = list.filter(b => b.title.toLowerCase().includes(q) || b.author.toLowerCase().includes(q));
  }

  if (list.length === 0) {
    const msg = currentSearch
      ? `<div class="empty-state"><span class="big">🔍</span>Nenhum livro encontrado pra "${escapeHtml(currentSearch)}".</div>`
      : `<div class="empty-state"><span class="big">🛁</span>Nada por aqui ainda.<br>Toque em "+ Adicionar" pra começar.</div>`;
    container.innerHTML = msg;
    return;
  }

  let lastGroup = null;
  const sameYearList = booksOfYear(currentYear);

  list.forEach((b) => {
    if (currentFilter === 'todos' && b.group !== lastGroup) {
      const g = document.createElement('div');
      g.className = 'group-label';
      g.innerHTML = `<h2>${escapeHtml(b.group)}</h2><span class="line"></span>`;
      container.appendChild(g);
      lastGroup = b.group;
    }

    const posInYear = sameYearList.indexOf(b);

    const card = document.createElement('div');
    card.className = 'book' + (b.read ? ' done' : '') + (b.locked ? ' locked' : '') + (b.pending ? ' pending' : '');

    let badgeHtml = b.pending
      ? `<span class="badge pendente">⏳ A pesquisar</span>`
      : `<span class="badge ${b.tag}">${TAG_LABEL[b.tag]}</span>`;

    let metaHtml = '';
    if (b.read && !b.pending) {
      const starsStr = '★'.repeat(b.stars) + '☆'.repeat(5 - b.stars);
      metaHtml = `<div class="book-meta-row">
        ${b.stars > 0 ? `<span class="stars">${starsStr}</span>` : ''}
        ${b.finishedOn ? `<span class="meta-date">📅 ${formatDate(b.finishedOn)}</span>` : ''}
      </div>`;
    }

    let progressHtml = '';
    if (b.progress !== null && b.progress !== undefined && !b.read) {
      progressHtml = `<div style="font-size:10.5px;color:#9a9082;margin-top:5px;">📍 ${b.progress}% lido</div>`;
    }

    card.innerHTML = `
      <div class="check">${b.read ? '✓' : ''}</div>
      <div class="book-main">
        <div class="book-top">
          <div>
            <p class="book-title">${escapeHtml(b.title)}</p>
            ${b.author ? `<p class="book-author">${escapeHtml(b.author)}</p>` : ''}
          </div>
          ${badgeHtml}
        </div>
        ${b.note ? `<p class="book-note">${escapeHtml(b.note)}</p>` : ''}
        ${progressHtml}
        ${metaHtml}
      </div>
      <div class="reorder-controls">
        <button class="up" ${posInYear <= 0 ? 'disabled' : ''}>▲</button>
        <button class="down" ${posInYear >= sameYearList.length - 1 ? 'disabled' : ''}>▼</button>
      </div>
    `;

    const checkEl = card.querySelector('.check');
    if (!b.locked) {
      checkEl.addEventListener('click', (e) => { e.stopPropagation(); toggleRead(b.id); });
    }
    card.querySelector('.book-main').addEventListener('click', () => openBookModal(b.id));
    card.querySelector('.up').addEventListener('click', (e) => { e.stopPropagation(); moveBook(b.id, -1); });
    card.querySelector('.down').addEventListener('click', (e) => { e.stopPropagation(); moveBook(b.id, 1); });

    container.appendChild(card);
  });
}

document.getElementById('filterRow').addEventListener('click', (e) => {
  const chip = e.target.closest('.filter-chip');
  if (!chip) return;
  document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
  currentFilter = chip.dataset.filter;
  renderLista();
});

document.getElementById('searchInput').addEventListener('input', (e) => {
  currentSearch = e.target.value.trim();
  renderLista();
});

/* =======================================================================
   MODAL: editar livro existente
   ======================================================================= */
function formatDuration(totalSeconds) {
  const h = Math.floor(totalSeconds / 3600);
  const m = Math.round((totalSeconds % 3600) / 60);
  if (h === 0) return `${m}min`;
  if (m === 0) return `${h}h`;
  return `${h}h ${m}min`;
}

function bookReadingStats(bookId) {
  const bookSessions = sessions.filter(s => s.bookId === bookId);
  return {
    count: bookSessions.length,
    totalSeconds: bookSessions.reduce((sum, s) => sum + s.seconds, 0),
    totalPages: bookSessions.reduce((sum, s) => sum + s.pages, 0),
  };
}

function openBookModal(id) {
  const b = books.find(x => x.id === id);
  if (!b) return;

  const overlay = document.getElementById('modalOverlay');
  const body = document.getElementById('modalBody');
  const stats = bookReadingStats(id);

  body.innerHTML = `
    <div class="modal-handle"></div>
    <h3>${b.locked ? '🔒 ' : ''}${escapeHtml(b.title)}</h3>
    ${b.pending ? `<div class="pending-note">⏳ Este livro está <strong>pendente de pesquisa</strong> (peso e páginas). Preencha manualmente abaixo.</div>` : ''}
    ${stats.count > 0 ? `<div class="note-box">⏱️ <strong>${formatDuration(stats.totalSeconds)}</strong> de leitura registrada · ${stats.totalPages} páginas · ${stats.count} sess${stats.count === 1 ? 'ão' : 'ões'} no cronômetro</div>` : ''}

    <div class="field">
      <label>Autor(a)</label>
      <input type="text" id="m-author" value="${escapeHtml(b.author)}">
    </div>

    <div class="field-row">
      <div class="field">
        <label>Peso de leitura</label>
        <select id="m-tag">
          <option value="leve" ${b.tag === 'leve' ? 'selected' : ''}>Leve</option>
          <option value="medio" ${b.tag === 'medio' ? 'selected' : ''}>Médio</option>
          <option value="denso" ${b.tag === 'denso' ? 'selected' : ''}>Denso</option>
          <option value="muito-denso" ${b.tag === 'muito-denso' ? 'selected' : ''}>Muito denso</option>
        </select>
      </div>
      <div class="field">
        <label>Páginas (opcional)</label>
        <input type="number" id="m-pages" value="${b.pages || ''}" placeholder="ex: 224">
      </div>
    </div>

    <div class="field">
      <label>Nota/descrição curta</label>
      <textarea id="m-desc" placeholder="Sobre o que é o livro...">${escapeHtml(b.note || '')}</textarea>
    </div>

    <div class="field">
      <label>Avaliação</label>
      <div class="star-picker" id="m-stars">
        ${[1, 2, 3, 4, 5].map(n => `<span data-n="${n}" class="${n <= b.stars ? 'on' : ''}">★</span>`).join('')}
      </div>
    </div>

    <div class="field">
      <label>Data que terminei</label>
      <input type="date" id="m-date" value="${b.finishedOn || ''}">
    </div>

    <div class="field">
      <label>Minha resenha (texto livre)</label>
      <textarea id="m-mynote" placeholder="O que achei, como me senti lendo...">${escapeHtml(b.myNote || '')}</textarea>
    </div>

    <div class="field">
      <label>💬 Citações/trechos favoritos</label>
      <div class="quote-list" id="m-quotes-list"></div>
      <div class="quote-add-row">
        <input type="text" id="m-quote-text" placeholder="Digite o trecho...">
        <input type="text" class="qpage-input" id="m-quote-page" placeholder="pág.">
        <button class="btn small primary" id="m-quote-add">+</button>
      </div>
    </div>

    <div class="modal-actions">
      <button class="btn ghost" id="m-cancel">Cancelar</button>
      ${!b.locked ? `<button class="btn danger small" id="m-delete">🗑️ Remover</button>` : ''}
      <button class="btn primary" id="m-save">Salvar</button>
    </div>
  `;

  let starVal = b.stars;
  body.querySelectorAll('#m-stars span').forEach(s => {
    s.addEventListener('click', () => {
      starVal = parseInt(s.dataset.n);
      body.querySelectorAll('#m-stars span').forEach(x => {
        x.classList.toggle('on', parseInt(x.dataset.n) <= starVal);
      });
    });
  });

  function renderQuotes() {
    const listEl = body.querySelector('#m-quotes-list');
    if (!b.quotes || b.quotes.length === 0) {
      listEl.innerHTML = `<p style="font-size:11.5px;color:#9a9082;">Nenhuma citação salva ainda.</p>`;
      return;
    }
    listEl.innerHTML = b.quotes.map((q) => `
      <div class="quote-item">
        <button class="qdel" data-id="${q.id}">✕</button>
        <div class="qtext">"${escapeHtml(q.text)}"</div>
        ${q.page ? `<div class="qpage">pág. ${escapeHtml(q.page)}</div>` : ''}
      </div>
    `).join('');
    listEl.querySelectorAll('.qdel').forEach(btn => {
      btn.addEventListener('click', async () => {
        try {
          await apiBooksAction({ action: 'delete-quote', quoteId: parseInt(btn.dataset.id) });
          b.quotes = b.quotes.filter(q => q.id !== parseInt(btn.dataset.id));
          renderQuotes();
        } catch (e) { showToast(e.message); }
      });
    });
  }
  renderQuotes();

  body.querySelector('#m-quote-add').addEventListener('click', async () => {
    const txt = body.querySelector('#m-quote-text').value.trim();
    if (!txt) return;
    const page = body.querySelector('#m-quote-page').value.trim();
    try {
      const res = await apiBooksAction({ action: 'add-quote', bookId: b.id, text: txt, page });
      if (!b.quotes) b.quotes = [];
      b.quotes.push({ id: res.id, text: txt, page });
      body.querySelector('#m-quote-text').value = '';
      body.querySelector('#m-quote-page').value = '';
      renderQuotes();
    } catch (e) { showToast(e.message); }
  });

  body.querySelector('#m-cancel').addEventListener('click', closeModal);
  if (!b.locked) {
    body.querySelector('#m-delete').addEventListener('click', async () => {
      if (confirm(`Remover "${b.title}" da lista?`)) {
        await deleteBook(id);
        closeModal();
        renderLista();
        renderHome();
        showToast('Livro removido.');
      }
    });
  }
  body.querySelector('#m-save').addEventListener('click', async () => {
    const pagesVal = body.querySelector('#m-pages').value;
    try {
      await updateBook(id, {
        author: body.querySelector('#m-author').value.trim(),
        tag: body.querySelector('#m-tag').value,
        pages: pagesVal,
        note: body.querySelector('#m-desc').value.trim(),
        stars: starVal,
        finishedOn: body.querySelector('#m-date').value,
        myNote: body.querySelector('#m-mynote').value.trim(),
      });
      closeModal();
      renderLista();
      renderHome();
      showToast('Salvo! 📖');
    } catch (e) { showToast(e.message); }
  });

  overlay.classList.add('active');
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('active');
}
document.getElementById('modalOverlay').addEventListener('click', (e) => {
  if (e.target.id === 'modalOverlay') closeModal();
});

/* =======================================================================
   MODAL: adicionar livro novo
   ======================================================================= */
const FIXED_GROUPS = ['Fila principal', 'Banco de respiros leves', 'Outros'];

function groupOptionsHtml() {
  const counts = {};
  books.forEach(b => { counts[b.group] = (counts[b.group] || 0) + 1; });
  const extraGroups = Object.keys(counts)
    .filter(g => !FIXED_GROUPS.includes(g))
    .sort((a, b) => counts[b] - counts[a]);

  let html = FIXED_GROUPS.map(g => `<option value="${escapeHtml(g)}" ${g === 'Outros' ? 'selected' : ''}>${escapeHtml(g)}</option>`).join('');
  if (extraGroups.length > 0) {
    html += extraGroups.map(g => `<option value="${escapeHtml(g)}">${escapeHtml(g)} (usado ${counts[g]}x)</option>`).join('');
  }
  html += `<option disabled>──────────</option>`;
  html += `<option value="__new__">+ Criar categoria nova...</option>`;
  return html;
}

function openAddBookModal(readNow) {
  const overlay = document.getElementById('modalOverlay');
  const body = document.getElementById('modalBody');

  body.innerHTML = `
    <div class="modal-handle"></div>
    <h3>${readNow ? '📅 Já li este' : '+ Adicionar livro'}</h3>
    <div class="pending-note">
      Preencha ao menos o título ou o autor e toque em <strong>"🔍 Buscar automaticamente"</strong> pra IA completar o resto (autor, peso, páginas e nota) — os campos vêm editáveis, revise antes de salvar. Se preferir, deixe peso em branco e o livro entra como <strong>"⏳ a pesquisar"</strong>.
    </div>
    <div class="field">
      <label>Título</label>
      <input type="text" id="n-title" placeholder="Nome do livro">
    </div>
    <div class="field">
      <label>Autor(a) (opcional)</label>
      <input type="text" id="n-author" placeholder="Nome do autor ou autora">
    </div>
    <button class="btn small ghost full" id="n-lookup" style="margin-bottom:14px;">🔍 Buscar automaticamente (IA)</button>
    <div class="field-row">
      <div class="field">
        <label>Peso (opcional)</label>
        <select id="n-tag">
          <option value="">— não sei ainda —</option>
          <option value="leve">Leve</option>
          <option value="medio">Médio</option>
          <option value="denso">Denso</option>
          <option value="muito-denso">Muito denso</option>
        </select>
      </div>
      <div class="field">
        <label>Páginas (opcional)</label>
        <input type="number" id="n-pages" placeholder="ex: 224">
      </div>
    </div>
    <div class="field">
      <label>Nota/descrição curta (opcional)</label>
      <textarea id="n-note" placeholder="Sobre o que é o livro..."></textarea>
    </div>
    <div class="field">
      <label>Grupo / categoria</label>
      <select id="n-group">${groupOptionsHtml()}</select>
    </div>
    <div class="field" id="n-new-group-field" style="display:none;">
      <label>Nome da categoria nova</label>
      <input type="text" id="n-new-group" placeholder="ex: Ficção científica">
    </div>
    ${readNow ? `
    <div class="field-row">
      <div class="field">
        <label>Data que terminei</label>
        <input type="date" id="n-date" value="${todayISO()}">
      </div>
      <div class="field">
        <label>Avaliação</label>
        <div class="star-picker" id="n-stars">
          ${[1, 2, 3, 4, 5].map(n => `<span data-n="${n}">★</span>`).join('')}
        </div>
      </div>
    </div>` : ''}
    <div class="modal-actions">
      <button class="btn ghost" id="n-cancel">Cancelar</button>
      <button class="btn primary" id="n-save">${readNow ? 'Lançar' : 'Adicionar'}</button>
    </div>
  `;

  let starVal = 0;
  if (readNow) {
    body.querySelectorAll('#n-stars span').forEach(s => {
      s.addEventListener('click', () => {
        starVal = parseInt(s.dataset.n);
        body.querySelectorAll('#n-stars span').forEach(x => x.classList.toggle('on', parseInt(x.dataset.n) <= starVal));
      });
    });
  }

  body.querySelector('#n-group').addEventListener('change', (e) => {
    const newGroupField = body.querySelector('#n-new-group-field');
    newGroupField.style.display = e.target.value === '__new__' ? 'block' : 'none';
    if (e.target.value === '__new__') body.querySelector('#n-new-group').focus();
  });

  body.querySelector('#n-lookup').addEventListener('click', async () => {
    const title = body.querySelector('#n-title').value.trim();
    const author = body.querySelector('#n-author').value.trim();
    if (!title && !author) {
      showToast('Preencha ao menos o título ou o autor antes de buscar.');
      return;
    }
    const btn = body.querySelector('#n-lookup');
    const originalLabel = btn.textContent;
    btn.textContent = '🔍 Buscando...';
    btn.disabled = true;
    try {
      const res = await fetch('api/lookup-metadata.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, author }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.error || 'Busca automática indisponível.');
      if (!title && data.title) body.querySelector('#n-title').value = data.title;
      if (!author && data.author) body.querySelector('#n-author').value = data.author;
      body.querySelector('#n-tag').value = data.tag || '';
      if (data.pages) body.querySelector('#n-pages').value = data.pages;
      if (data.note) body.querySelector('#n-note').value = data.note;
      showToast('Sugestão da IA preenchida — revise antes de salvar. ✨');
    } catch (e) {
      // Falha na IA nunca trava o cadastro — usuário sempre pode
      // preencher manualmente e salvar normalmente.
      showToast(e.message);
    } finally {
      btn.textContent = originalLabel;
      btn.disabled = false;
    }
  });

  body.querySelector('#n-cancel').addEventListener('click', closeModal);
  body.querySelector('#n-save').addEventListener('click', async () => {
    const title = body.querySelector('#n-title').value.trim();
    const author = body.querySelector('#n-author').value.trim();
    if (!title) {
      showToast('Preencha ao menos o título.');
      return;
    }
    const tag = body.querySelector('#n-tag').value;
    const pages = body.querySelector('#n-pages').value;
    const note = body.querySelector('#n-note').value.trim();
    let group = body.querySelector('#n-group').value;
    if (group === '__new__') {
      group = body.querySelector('#n-new-group').value.trim();
      if (!group) {
        showToast('Digite o nome da categoria nova.');
        return;
      }
    }

    try {
      await createBook({
        title, author, tag, pages, note, group,
        year: currentYear,
        read: !!readNow,
        finishedOn: readNow ? body.querySelector('#n-date').value : '',
        stars: readNow ? starVal : 0,
      });
      closeModal();
      renderLista();
      renderHome();
      showToast(tag ? 'Livro adicionado! ✅' : 'Adicionado como pendente ⏳');
    } catch (e) { showToast(e.message); }
  });

  overlay.classList.add('active');
}

document.getElementById('btnAddBook').addEventListener('click', () => openAddBookModal(false));
document.getElementById('btnRetroactive').addEventListener('click', () => openAddBookModal(true));

/* =======================================================================
   TROCAR / ADICIONAR ANO
   ======================================================================= */
document.getElementById('btnAddYear').addEventListener('click', () => {
  const overlay = document.getElementById('modalOverlay');
  const body = document.getElementById('modalBody');
  const years = yearsAvailable();

  body.innerHTML = `
    <div class="modal-handle"></div>
    <h3>📅 Trocar de ano</h3>
    <div class="field">
      <label>Ano atual</label>
      <select id="y-select">
        ${years.map(y => `<option value="${y}" ${y === currentYear ? 'selected' : ''}>${y}</option>`).join('')}
      </select>
    </div>
    <div class="field">
      <label>Ou criar um ano novo</label>
      <input type="number" id="y-new" placeholder="ex: ${currentYear + 1}">
    </div>
    <div class="modal-actions">
      <button class="btn ghost" id="y-cancel">Cancelar</button>
      <button class="btn primary" id="y-save">Confirmar</button>
    </div>
  `;
  body.querySelector('#y-cancel').addEventListener('click', closeModal);
  body.querySelector('#y-save').addEventListener('click', () => {
    const newYearVal = body.querySelector('#y-new').value;
    currentYear = newYearVal ? parseInt(newYearVal) : parseInt(body.querySelector('#y-select').value);
    closeModal();
    renderLista();
    renderHome();
    showToast(`Ano ${currentYear} selecionado.`);
  });
  overlay.classList.add('active');
});

/* =======================================================================
   RENDER: ESTATÍSTICAS
   ======================================================================= */
function renderStats() {
  const years = yearsAvailable();
  const picker = document.getElementById('statsYearPicker');
  picker.innerHTML = years.map(y => `<button class="year-chip ${y === currentYear ? 'active' : ''}" data-y="${y}">${y}</button>`).join('');
  picker.querySelectorAll('.year-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      currentYear = parseInt(chip.dataset.y);
      renderStats();
    });
  });

  const content = document.getElementById('statsContent');
  const yearBooks = booksOfYear(currentYear);
  const readBooks = yearBooks.filter(b => b.read);

  if (yearBooks.length === 0) {
    content.innerHTML = `<div class="empty-state"><span class="big">📊</span>Sem dados ainda para ${currentYear}.</div>`;
    return;
  }

  const monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
  const monthCounts = new Array(12).fill(0);
  readBooks.forEach(b => {
    if (b.finishedOn) {
      const m = parseInt(b.finishedOn.split('-')[1]) - 1;
      if (m >= 0 && m < 12) monthCounts[m]++;
    }
  });
  const maxMonth = Math.max(1, ...monthCounts);

  const tagCounts = { leve: 0, medio: 0, denso: 0, 'muito-denso': 0 };
  readBooks.forEach(b => { if (tagCounts[b.tag] !== undefined) tagCounts[b.tag]++; });
  const tagColors = { leve: 'var(--leaf)', medio: 'var(--duck)', denso: 'var(--tomato)', 'muito-denso': 'var(--ink)' };
  const totalRead = readBooks.length || 1;

  const starred = readBooks.filter(b => b.stars > 0).sort((a, b) => b.stars - a.stars).slice(0, 5);
  const avgStars = starred.length ? (starred.reduce((s, b) => s + b.stars, 0) / starred.length).toFixed(1) : '—';

  content.innerHTML = `
    <div class="chip-row">
      <div class="chip"><span class="num">${readBooks.length}</span><span class="lbl">lidos em ${currentYear}</span></div>
      <div class="chip"><span class="num">${yearBooks.length}</span><span class="lbl">na lista</span></div>
      <div class="chip"><span class="num">${avgStars}</span><span class="lbl">média ⭐</span></div>
    </div>

    <div class="stat-card">
      <h3>📅 Ritmo por mês</h3>
      <div class="bar-chart">
        ${monthCounts.map((c, i) => `
          <div class="bar-col">
            ${c > 0 ? `<div class="bar-val">${c}</div>` : ''}
            <div class="bar" style="height:${Math.max(2, (c / maxMonth) * 100)}%"></div>
            <div class="bar-lbl">${monthNames[i]}</div>
          </div>
        `).join('')}
      </div>
    </div>

    <div class="stat-card">
      <h3>⚖️ Distribuição por peso</h3>
      <div class="donut-row">
        <div class="donut" style="background:${buildConicGradient(tagCounts, totalRead, tagColors)}"></div>
        <div class="donut-legend">
          ${Object.keys(tagCounts).map(k => `
            <div class="li"><span class="sw" style="background:${tagColors[k]}"></span>${TAG_LABEL[k]}<span class="val">${tagCounts[k]}</span></div>
          `).join('')}
        </div>
      </div>
    </div>

    <div class="stat-card">
      <h3>⭐ Melhor avaliados</h3>
      ${starred.length === 0 ? '<div class="empty-state" style="padding:10px;">Avalie livros lidos pra ver o ranking aqui.</div>' :
      `<div class="top-authors">
          ${starred.map((b, i) => `
            <div class="row">
              <div class="rank">${i + 1}</div>
              <div class="name">${escapeHtml(b.title)}<br><span style="color:#9a9082;font-size:11px;">${escapeHtml(b.author)}</span></div>
              <div class="stars">${'★'.repeat(b.stars)}${'☆'.repeat(5 - b.stars)}</div>
            </div>
          `).join('')}
        </div>`
    }
    </div>

    ${years.length > 1 ? `
    <div class="stat-card">
      <h3>📈 Comparativo entre anos</h3>
      <div class="bar-chart" style="height:90px;">
        ${years.map(y => {
      const yb = booksOfYear(y);
      const yr = yb.filter(b => b.read).length;
      const maxY = Math.max(1, ...years.map(yy => booksOfYear(yy).filter(b => b.read).length));
      return `<div class="bar-col">
            <div class="bar-val">${yr}</div>
            <div class="bar" style="height:${Math.max(2, (yr / maxY) * 100)}%; background:${y === currentYear ? 'var(--tomato)' : 'var(--water-line)'}"></div>
            <div class="bar-lbl">${y}</div>
          </div>`;
    }).join('')}
      </div>
    </div>` : ''}
  `;
}

function buildConicGradient(counts, total, colors) {
  let acc = 0;
  const parts = [];
  Object.keys(counts).forEach(k => {
    if (counts[k] === 0) return;
    const start = (acc / total) * 360;
    acc += counts[k];
    const end = (acc / total) * 360;
    parts.push(`${colors[k]} ${start}deg ${end}deg`);
  });
  if (parts.length === 0) return '#eee';
  return `conic-gradient(${parts.join(', ')})`;
}

/* =======================================================================
   RENDER: PLACAR
   ======================================================================= */
async function renderPlacar() {
  const setupEl = document.getElementById('placarSetup');
  const contentEl = document.getElementById('placarContent');

  setupEl.innerHTML = `
    <div class="note-box">
      Você aparece no placar como <strong>${escapeHtml(window.CURRENT_USER.name)}</strong>.
      Só a contagem de livros (lidos/total) é compartilhada — notas e resenhas continuam privadas.
    </div>
  `;

  contentEl.innerHTML = `<div class="empty-state"><span class="big">⏳</span>Carregando placar...</div>`;

  const scores = await fetchScoreboard(currentYear);

  if (scores.length === 0) {
    contentEl.innerHTML = `<div class="empty-state"><span class="big">🛁</span>Ninguém tem livros cadastrados pra ${currentYear} ainda.</div>`;
    return;
  }

  contentEl.innerHTML = `
    <p style="font-size:12px; color:#9a9082; margin-bottom:10px;">Ranking de ${currentYear}:</p>
    ${scores.map((s, i) => {
    const pct = s.total ? Math.round((s.read / s.total) * 100) : 0;
    return `
        <div class="leaderboard-row">
          <div class="rank">${i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : (i + 1)}</div>
          <div class="info">
            <div class="nm">${escapeHtml(s.name)}${s.userId === window.CURRENT_USER.id ? ' (você)' : ''}</div>
            <div class="sub">${s.read} / ${s.total} livros</div>
            <div class="mini-tub"><div class="mini-tub-fill" style="width:${pct}%"></div></div>
          </div>
          <div class="count">${pct}%</div>
        </div>
      `;
  }).join('')}
  `;
}

/* =======================================================================
   HISTÓRICO DE SESSÕES (calendário/timeline)
   ======================================================================= */
function openHistoryModal() {
  const overlay = document.getElementById('modalOverlay');
  const body = document.getElementById('modalBody');

  const sorted = [...sessions].sort((a, b) => b.date.localeCompare(a.date));

  const rowsHtml = sorted.length === 0
    ? `<div class="empty-state"><span class="big">📜</span>Nenhuma sessão de leitura registrada ainda.<br>Use o cronômetro na Banheira pra começar.</div>`
    : (() => {
      let lastDate = null;
      let html = '';
      sorted.forEach(s => {
        const book = books.find(b => b.id === s.bookId);
        if (s.date !== lastDate) {
          html += `<div class="group-label"><h2>${formatDate(s.date)}</h2><span class="line"></span></div>`;
          lastDate = s.date;
        }
        html += `
          <div class="leaderboard-row">
            <div class="rank">📖</div>
            <div class="info">
              <div class="nm">${escapeHtml(book ? book.title : 'Livro removido')}</div>
              <div class="sub">${s.pages} página${s.pages === 1 ? '' : 's'}</div>
            </div>
            <div class="count">${formatDuration(s.seconds)}</div>
          </div>
        `;
      });
      return html;
    })();

  body.innerHTML = `
    <div class="modal-handle"></div>
    <h3>📜 Histórico de leitura</h3>
    <div style="max-height:60vh; overflow-y:auto;">${rowsHtml}</div>
    <div class="modal-actions">
      <button class="btn primary full" id="history-close">Fechar</button>
    </div>
  `;
  body.querySelector('#history-close').addEventListener('click', closeModal);
  overlay.classList.add('active');
}

document.getElementById('streakDaysCard').addEventListener('click', openHistoryModal);
document.getElementById('streakTodayCard').addEventListener('click', openHistoryModal);
document.getElementById('btnTimerHistory').addEventListener('click', openHistoryModal);

/* =======================================================================
   TIMER + STREAK
   ======================================================================= */
function populateTimerBookSelect() {
  const sel = document.getElementById('timerBookSelect');
  const unread = booksOfYear(currentYear).filter(b => !b.read && !b.locked);
  if (unread.length === 0) {
    sel.innerHTML = `<option value="">Nenhum livro pendente</option>`;
    timerBookId = null;
    return;
  }
  sel.innerHTML = unread.map(b => `<option value="${b.id}">${escapeHtml(b.title)}</option>`).join('');
  timerBookId = unread[0].id;
}

function formatTimer(totalSeconds) {
  const h = Math.floor(totalSeconds / 3600);
  const m = Math.floor((totalSeconds % 3600) / 60);
  const s = totalSeconds % 60;
  const pad = n => String(n).padStart(2, '0');
  return `${pad(h)}:${pad(m)}:${pad(s)}`;
}

document.getElementById('timerBookSelect').addEventListener('change', (e) => {
  timerBookId = e.target.value;
});

document.getElementById('timerPlayBtn').addEventListener('click', () => {
  if (!timerBookId) { showToast('Escolha um livro primeiro.'); return; }
  timerRunning = true;
  document.getElementById('timerPlayBtn').style.display = 'none';
  document.getElementById('timerPauseBtn').style.display = 'flex';
  document.getElementById('timerStopBtn').style.display = 'flex';
  timerInterval = setInterval(() => {
    timerSeconds++;
    document.getElementById('timerDisplay').textContent = formatTimer(timerSeconds);
  }, 1000);
});

document.getElementById('timerPauseBtn').addEventListener('click', () => {
  timerRunning = false;
  clearInterval(timerInterval);
  document.getElementById('timerPlayBtn').style.display = 'flex';
  document.getElementById('timerPauseBtn').style.display = 'none';
});

document.getElementById('timerStopBtn').addEventListener('click', async () => {
  clearInterval(timerInterval);
  const pages = parseInt(document.getElementById('timerPages').value) || 0;
  if (timerSeconds > 0 || pages > 0) {
    try {
      await saveSession({ bookId: timerBookId, date: todayISO(), seconds: timerSeconds, pages });
      await loadSessions();
      showToast(`Sessão salva: ${formatTimer(timerSeconds)} · ${pages} págs 📖`);
    } catch (e) { showToast(e.message); }
  }
  timerRunning = false;
  timerSeconds = 0;
  document.getElementById('timerDisplay').textContent = '00:00:00';
  document.getElementById('timerPages').value = '';
  document.getElementById('timerPlayBtn').style.display = 'flex';
  document.getElementById('timerPauseBtn').style.display = 'none';
  document.getElementById('timerStopBtn').style.display = 'none';
  document.getElementById('streakNum').textContent = calcStreak();
  document.getElementById('totalTimeToday').textContent = minutesReadToday() + 'min';
  renderMonthGoal();
});

/* ---------- Meta mensal ---------- */
function renderMonthGoal() {
  const key = monthKey(todayISO());
  const goal = monthGoals[key] || { target: 2, type: 'books' };
  const monthNamesPt = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  const monthIdx = parseInt(key.split('-')[1]) - 1;
  document.getElementById('monthLabel').textContent = monthNamesPt[monthIdx];

  let progress = 0;
  let unit = '';
  if (goal.type === 'books') {
    progress = books.filter(b => b.read && b.finishedOn && monthKey(b.finishedOn) === key).length;
    unit = 'livros';
  } else if (goal.type === 'minutes') {
    const secs = sessions.filter(s => monthKey(s.date) === key).reduce((sum, s) => sum + s.seconds, 0);
    progress = Math.round(secs / 60);
    unit = 'min';
  } else {
    progress = sessions.filter(s => monthKey(s.date) === key).reduce((sum, s) => sum + s.pages, 0);
    unit = 'págs';
  }

  const pct = goal.target ? Math.min(100, Math.round((progress / goal.target) * 100)) : 0;
  document.getElementById('monthGoalFill').style.width = pct + '%';
  document.getElementById('monthGoalStat').textContent = `${progress} / ${goal.target} ${unit} (${pct}%)`;
}

document.getElementById('editGoalBtn').addEventListener('click', () => {
  const overlay = document.getElementById('modalOverlay');
  const body = document.getElementById('modalBody');
  const key = monthKey(todayISO());
  const goal = monthGoals[key] || { target: 2, type: 'books' };

  body.innerHTML = `
    <div class="modal-handle"></div>
    <h3>🎯 Editar meta do mês</h3>
    <div class="field">
      <label>O que quer medir?</label>
      <select id="g-type">
        <option value="books" ${goal.type === 'books' ? 'selected' : ''}>Livros lidos</option>
        <option value="minutes" ${goal.type === 'minutes' ? 'selected' : ''}>Minutos de leitura</option>
        <option value="pages" ${goal.type === 'pages' ? 'selected' : ''}>Páginas lidas</option>
      </select>
    </div>
    <div class="field">
      <label>Meta (número)</label>
      <input type="number" id="g-target" value="${goal.target}">
    </div>
    <div class="modal-actions">
      <button class="btn ghost" id="g-cancel">Cancelar</button>
      <button class="btn primary" id="g-save">Salvar meta</button>
    </div>
  `;
  body.querySelector('#g-cancel').addEventListener('click', closeModal);
  body.querySelector('#g-save').addEventListener('click', async () => {
    const type = body.querySelector('#g-type').value;
    const target = parseInt(body.querySelector('#g-target').value) || 1;
    try {
      await saveGoal(key, type, target);
      monthGoals[key] = { type, target };
      closeModal();
      renderMonthGoal();
      showToast('Meta atualizada! 🎯');
    } catch (e) { showToast(e.message); }
  });
  overlay.classList.add('active');
});

/* ---------- O que eu leio hoje? (mood picker) ---------- */
const MOODS = [
  { id: 'triste', label: 'Triste / pesada(o)', icon: '😔', wants: ['leve'] },
  { id: 'contemplativa', label: 'Contemplativa(o)', icon: '🌙', wants: ['denso', 'muito-denso'] },
  { id: 'curiosa', label: 'Curiosa(o) / animada(o)', icon: '✨', wants: ['leve', 'medio'] },
  { id: 'cansada', label: 'Cansada(o), quero leveza', icon: '🛁', wants: ['leve'] },
  { id: 'forte', label: 'Disposta(o) pra algo denso', icon: '💪', wants: ['denso', 'muito-denso'] },
  { id: 'romance', label: 'Quero um romance gostoso', icon: '💛', wants: ['leve', 'medio'] },
];

document.getElementById('btnMood').addEventListener('click', () => {
  const overlay = document.getElementById('modalOverlay');
  const body = document.getElementById('modalBody');
  body.innerHTML = `
    <div class="modal-handle"></div>
    <h3>✨ Como você está hoje?</h3>
    <div class="mood-modal-grid">
      ${MOODS.map(m => `<button class="mood-btn" data-mood="${m.id}"><span class="ic">${m.icon}</span>${m.label}</button>`).join('')}
    </div>
    <div id="moodResultArea"></div>
  `;
  body.querySelectorAll('.mood-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const mood = MOODS.find(m => m.id === btn.dataset.mood);
      const candidates = booksOfYear(currentYear).filter(b => !b.read && !b.locked && mood.wants.includes(b.tag));
      const pool = candidates.length ? candidates : booksOfYear(currentYear).filter(b => !b.read && !b.locked);
      const pick = pool[Math.floor(Math.random() * pool.length)];
      const resultArea = document.getElementById('moodResultArea');
      if (!pick) {
        resultArea.innerHTML = `<div class="mood-result">Sem livros pendentes pra sugerir — sua lista está completa! 🎉</div>`;
        return;
      }
      resultArea.innerHTML = `
        <div class="mood-result">
          <p class="pick-title">${escapeHtml(pick.title)}</p>
          <p class="pick-author">${escapeHtml(pick.author)}</p>
          <p class="pick-why">Sugerido porque combina com "${mood.label.toLowerCase()}" — peso <strong>${TAG_LABEL[pick.tag]}</strong>.</p>
          <button class="btn primary full" id="moodGoToBook" style="margin-top:10px;">Ver este livro na lista</button>
        </div>
      `;
      document.getElementById('moodGoToBook').addEventListener('click', () => {
        closeModal();
        goTo('lista');
        setTimeout(() => openBookModal(pick.id), 200);
      });
    });
  });
  overlay.classList.add('active');
});

/* ---------- Exportar dados ---------- */
document.getElementById('btnExport').addEventListener('click', () => {
  window.location.href = 'api/export.php';
});

/* =======================================================================
   DECORAÇÃO + INIT
   ======================================================================= */
function decorateTiles() {
  const directColors = ['#e0703f', '#2f9b94', '#2c6a92', '#e0a72f'];
  const stripTop = document.getElementById('tileStripTop');
  for (let i = 0; i < 24; i++) {
    const s = document.createElement('span');
    s.style.background = directColors[i % 4];
    stripTop.appendChild(s);
  }
}

async function init() {
  decorateTiles();
  await loadAll();
  await loadSessions();
  await loadGoals();

  if (books.length > 0) {
    currentYear = Math.max(...books.map(b => b.year));
  }

  populateTimerBookSelect();
  document.getElementById('streakNum').textContent = calcStreak();
  document.getElementById('totalTimeToday').textContent = minutesReadToday() + 'min';
  renderMonthGoal();
  renderHome();
}

init();
