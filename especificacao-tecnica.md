# Banheira de Leituras — Especificação Técnica
### Briefing completo para desenvolvimento

> Este documento descreve tudo que foi definido em um protótipo funcional (HTML/CSS/JS single-file) para que possa ser reconstruído como aplicação web hospedada, com banco de dados real, login, e compartilhamento entre usuários.
>
> O protótipo de referência (`leituras-app.html`) está funcional e pode ser aberto em qualquer navegador — use-o como fonte de verdade visual e de comportamento sempre que este documento for ambíguo.

---

## 1. Visão geral do produto

Um app pessoal de **acompanhamento de leitura anual**, com identidade visual baseada numa ilustração de um gato preto relaxando numa banheira rosa cheia de patinhos de borracha. A metáfora central do produto: **a meta de leitura do ano é uma banheira que vai enchendo conforme livros são marcados como lidos.**

Funcionalidades centrais:
1. Lista de livros com metadados (autor, peso de leitura, páginas, notas, citações)
2. Progresso visual em forma de barra "banheira" com nível de água
3. Estatísticas (mês a mês, distribuição por peso de leitura, ranking de avaliações)
4. Cronômetro de sessões de leitura (tempo + páginas) vinculado a um livro
5. Sequência de dias consecutivos lendo (streak)
6. Metas mensais configuráveis (livros, minutos ou páginas)
7. Sugestão de leitura por humor ("o que eu leio hoje?")
8. Placar comparativo entre usuários (compartilhado)
9. Exportação de dados em JSON

---

## 2. Identidade visual

### 2.1 Paleta de cores (extraída da ilustração de referência)

A ilustração é em **estilo flat gouache**: cores chapadas e saturadas, sem gradientes, contornos pretos finos definindo formas.

| Token | Hex | Uso |
|---|---|---|
| `--wall-pink` | `#f0c4cb` | Fundo do cabeçalho/hero |
| `--water` | `#aee0ea` | Preenchimento da barra de progresso ("água") |
| `--water-line` | `#6cc3d6` | Elementos de gráfico (barras, linhas) |
| `--duck` | `#f5c542` | Acento "médio" / destaque amarelo |
| `--ink` | `#2a2422` | Texto principal, bordas, contornos |
| `--wood` | `#b97a4a` | Madeira (detalhes decorativos) |
| `--wood-dark` | `#8a5630` | Texto de destaque secundário |
| `--leaf` | `#5c8a4f` | Acento "leve" (verde) |
| `--leaf-dark` | `#3c6334` | Borda de itens concluídos |
| `--tile-orange` | `#e0703f` | Azulejo decorativo 1 |
| `--tile-teal` | `#2f9b94` | Azulejo decorativo 2 |
| `--tile-blue` | `#2c6a92` | Azulejo decorativo 3 |
| `--tile-mustard` | `#e0a72f` | Azulejo decorativo 4 |
| `--tomato` | `#d6433f` | Acento "denso" / destaque vermelho |
| `--cream` | `#fbf1de` | Fundo geral da página |
| `--paper` | `#fffaf0` | Fundo de cards |
| `--towel-pink` | `#f6dbe2` | Fundo de caixas de aviso/nota |

**Regra de ouro:** nunca usar `linear-gradient` ou `box-shadow` com blur suave. Toda sombra é sólida e deslocada (`box-shadow: 0 3px 0 var(--ink)` — sem blur, simulando um contorno de ilustração 2D/sticker).

### 2.2 Tipografia

- **Display / títulos / labels / números:** [Baloo 2](https://fonts.google.com/specimen/Baloo+2) (Google Fonts), peso 500–800. Fonte arredondada e brincalhona, usada em `h1`, `h2`, `h3`, botões, badges, valores numéricos.
- **Corpo / texto corrido:** [Nunito](https://fonts.google.com/specimen/Nunito) (Google Fonts), peso 400–700.
- Import via `<link>` do Google Fonts (ver código-fonte do protótipo, `<head>`).

### 2.3 Princípios de layout

- **Bordas:** todo elemento "card" (botões, cards de livro, modais) tem borda sólida de 2–4px na cor `--ink`, com cantos arredondados (`border-radius: 14–24px`).
- **Sombra estilo sticker:** `box-shadow: 0 3px 0 var(--ink)` (ou `0 4–5px 0` em elementos maiores) — nunca blur, sempre um deslocamento vertical sólido que simula espessura de papel/adesivo.
- **Feedback de toque:** ao clicar/tocar (`:active`), o elemento desce (`transform: translateY(2px)`) e a sombra "encolhe" (`box-shadow: 0 1px 0 var(--ink)`), simulando um botão sendo pressionado.
- **Decoração de azulejos:** uma faixa fina (`14–16px` de altura) no topo do cabeçalho, dividida em retângulos repetindo as 4 cores de `--tile-*` em sequência — referência direta aos azulejos da banheira na ilustração.
- **Imagem hero:** a ilustração do gato aparece recortada (não inteira) numa moldura de proporção 4:3, `object-fit: cover`, `object-position: 50% 62%` (foco no gato, cortando o topo da imagem onde estão as plantas/velas).

### 2.4 Asset visual

A imagem de referência (`cat-bath.jpg`, 736×920px, JPEG) deve ser fornecida pelo cliente e hospedada como asset estático (ex: `/assets/cat-bath.jpg`). No protótipo atual ela está embutida em base64 dentro do HTML — **isso deve ser trocado por um arquivo de imagem real servido pela hospedagem**, por performance.

---

## 3. Arquitetura de dados

### 3.1 Modelo de dados: Livro (`Book`)

```ts
interface Book {
  id: string;              // identificador único (slug ou UUID)
  year: number;             // ano de leitura (ex: 2026) — permite múltiplos anos
  group: string;            // categoria/grupo de exibição (ex: "Fila principal · luto & psicologia")
  title: string;
  author: string;
  tag: 'leve' | 'medio' | 'denso' | 'muito-denso';  // peso de leitura
  pages?: number;           // opcional
  note: string;             // descrição curta do livro (editorial, não pessoal)
  read: boolean;            // se já foi concluído
  locked?: boolean;         // true = não pode ser desmarcado (ex: livros lidos antes do app existir)
  pending?: boolean;        // true = aguardando preenchimento de peso/páginas
  stars: number;            // 0–5, avaliação pessoal
  finishedOn: string;       // data ISO "YYYY-MM-DD", preenchida automaticamente ao marcar como lido
  myNote: string;           // resenha/nota pessoal, texto livre
  quotes: Quote[];          // citações favoritas
  progress?: number;        // 0–100, % lido (usado para livros "em pausa", não concluídos)
}

interface Quote {
  text: string;
  page?: string;            // string, não number — permite "12-15" ou "cap. 3"
}
```

### 3.2 Modelo de dados: Sessão de leitura (`Session`)

```ts
interface Session {
  id: string;
  bookId: string;           // referencia Book.id
  date: string;              // ISO "YYYY-MM-DD"
  seconds: number;           // duração total da sessão em segundos
  pages: number;              // páginas lidas nessa sessão específica
}
```

### 3.3 Modelo de dados: Meta mensal (`MonthGoal`)

```ts
// chave do mapa = "YYYY-MM", ex: "2026-06"
interface MonthGoal {
  type: 'books' | 'minutes' | 'pages';
  target: number;
}
type MonthGoals = Record<string, MonthGoal>;
```

### 3.4 Modelo de dados: Placar compartilhado (`ScoreEntry`)

```ts
interface ScoreEntry {
  name: string;             // nome/apelido escolhido pelo usuário
  year: number;
  read: number;              // contagem de livros lidos naquele ano
  total: number;             // total de livros na lista daquele ano
  updatedAt: number;         // timestamp epoch ms
}
```

### 3.5 Persistência atual vs. recomendada para produção

**No protótipo (Claude.ai artifact):** os dados são salvos via uma API de key-value storage exclusiva do ambiente Claude (`window.storage`), com dois escopos:
- **Privado** (`shared: false`): visível só para o próprio usuário — usado para `books`, `sessions`, `monthGoals`, `username`.
- **Compartilhado** (`shared: true`): visível para qualquer um com o link — usado **apenas** para o placar (`score-{username}`), nunca para notas/resenhas pessoais.

**Decisão de stack confirmada com o cliente:** PHP + MySQL, hospedado em cPanel, front-end permanece HTML/CSS/JS (sem framework SPA). Isso substitui a sugestão genérica de seção 9; ver seção 9 atualizada.

**Para a hospedagem real, isso se torna:**

| Dado | Recomendação |
|---|---|
| `books`, `sessions`, `monthGoals` | Tabelas MySQL (`books`, `sessions`, `month_goals`), cada uma com `user_id` (FK) vinculado ao usuário autenticado. Ver schema SQL completo na seção 10.1 |
| `username` / autenticação | Login real via PHP: tabela `users` (email + senha com `password_hash`/`password_verify`), sessão PHP nativa (`$_SESSION`) para manter o usuário logado — **o protótipo não tem login de verdade**, só pede um nome em texto livre na primeira visita ao Placar |
| Placar (`ScoreEntry`) | Tabela `score_entries` (ou uma view calculada a partir de `books`), com leitura pública via endpoint PHP dedicado, escrita restrita ao próprio usuário autenticado |
| Exportação | Mantém o mesmo formato JSON; endpoint PHP gera o JSON a partir do banco e força download real (`Content-Disposition: attachment`) em vez de copiar texto de uma `<textarea>` |
| Metadados de livro (peso/páginas/sinopse) | **Busca automática via API da OpenAI**, disparada a cada novo cadastro — ver seção 7 atualizada e seção 10.2 para o contrato do endpoint |

> ⚠️ Importante: a privacidade observada no protótipo (notas/resenhas nunca compartilhadas, só a contagem numérica do placar é pública) é uma decisão de produto deliberada e deve ser preservada na implementação real.

---

## 4. Estrutura de navegação

App de página única com **navegação inferior fixa de 4 abas** (estilo app mobile), sempre visível:

```
┌─────────────────────────────────┐
│         (conteúdo da aba)        │
│                                   │
├─────────────────────────────────┤
│  🛁        📖        📊      🏆  │
│ Banheira  Lista  Estatísticas Placar │
└─────────────────────────────────┘
```

- **🛁 Banheira (Home):** tela inicial. Streak, cronômetro de leitura, meta do mês, barra de progresso do ano, chips de resumo.
- **📖 Lista:** lista completa de livros do ano selecionado, com filtros, busca, edição e reordenação.
- **📊 Estatísticas:** gráficos derivados de `books` e `sessions`, com seletor de ano.
- **🏆 Placar:** comparação de progresso entre usuários que compartilharam o link.

Cada aba é uma `<div class="screen">` que alterna visibilidade via JS (`display: none/block`), sem reload de página. Em uma implementação real com framework (React/Vue), isso é simplesmente roteamento client-side ou troca de componente.

---

## 5. Especificação tela a tela

### 5.1 Tela Banheira (Home)

**Componentes, de cima para baixo:**

1. **Hero/cabeçalho:** faixa de azulejos decorativos → imagem recortada do gato (4:3) → eyebrow badge ("🕯️ projeto pessoal") → título `Minha banheira de leituras` (com a palavra "leituras" na cor `--tomato`) → subtítulo dinâmico.

2. **Card de streak/tempo (2 colunas):**
   - Coluna 1: 🔥 + número de **dias consecutivos lendo**
   - Coluna 2: ⏱️ + **minutos lidos hoje**
   - *Lógica do streak:* conta dias consecutivos (incluindo hoje, se houver sessão) com pelo menos 1 sessão de leitura registrada. Ver pseudocódigo na seção 6.1.

3. **Card de cronômetro de leitura:**
   - Seletor (`<select>`) com os livros **não lidos** do ano atual
   - Display grande do tempo no formato `HH:MM:SS`
   - 3 botões circulares: ▶ Play, ⏸ Pausa, ⏹ Parar
   - Campo numérico "Páginas lidas" (preenchido manualmente pelo usuário ao final da sessão)
   - Ao clicar em **Parar**: salva uma nova `Session` com `bookId`, `date` (hoje), `seconds` (tempo acumulado) e `pages` (valor do campo); zera o cronômetro e o campo.
   - Play inicia/retoma um `setInterval` de 1s que incrementa o contador; Pausa interrompe sem zerar; Parar persiste e zera.

4. **Card de meta mensal:**
   - Título: "🎯 Meta do mês — {nome do mês em português}"
   - Link "editar meta" → abre modal para escolher tipo (livros / minutos / páginas) e valor numérico alvo
   - Barra de progresso fina mostrando `{progresso atual} / {meta} {unidade} ({percentual}%)`
   - *Cálculo do progresso* depende do `type` da meta (ver seção 6.2)

5. **Card "banheira" (progresso anual):**
   - Título: "🛁 nível da banheira — {ano}"
   - Contador grande: `{lidos} / {total}` do ano selecionado
   - Barra de progresso horizontal com decoração de azulejos de fundo (sutil, opacidade ~50%) e preenchimento azul-água (`--water`) que cresce de largura conforme a % de livros lidos
   - Um emoji 🦆 fixo na borda direita do preenchimento (decoração, sempre visível enquanto há algum progresso)
   - Legenda de cores abaixo (Leve = verde, Médio = amarelo, Denso = vermelho, Muito denso = preto)

6. **Chips de resumo (3 colunas):** lidos · pendentes · média de estrelas (calculada só sobre livros lidos com `stars > 0`)

7. **Botões de ação:** "📖 Ver minha lista completa" (navega pra aba Lista) e "⬇️ Exportar meus dados" (abre modal de exportação)

### 5.2 Tela Lista

**Toolbar superior:**
- Botão **"+ Adicionar"** → abre modal de criação de livro novo
- Botão **"📅 Já li este"** → (registro retroativo — ver seção 5.2.1)
- Botão **"📅 {ano}"** → abre modal de seleção/criação de ano

**Botão de largura total:** "✨ O que eu leio hoje?" (mood picker — ver seção 5.2.2)

**Filtros (chips horizontais roláveis):** Todos · ⏳ Pendentes · ✓ Lidos · Leve · Médio · Denso

**Lista de livros**, agrupada por `group` (quando o filtro é "Todos") com um separador visual entre grupos (linha pontilhada + nome do grupo). Cada item é um card com:
- Círculo de checkbox à esquerda (clicável, exceto se `locked`)
- Título (com `text-decoration: line-through` se já lido) + autor em itálico
- Badge de peso (cor conforme `tag`) ou badge "⏳ A pesquisar" se `pending`
- Nota/descrição curta
- Se houver `progress` definido e o livro não estiver lido: mini barra de "% lido"
- Se já lido: estrelas (★/☆) e data formatada (dd/mm/aaaa)
- Setas ▲▼ à direita para reordenar o livro dentro da lista do mesmo ano (desabilitadas nos extremos)

**Clique no corpo do card** (não no checkbox, não nas setas) → abre modal de edição completo do livro.

#### 5.2.1 Registro retroativo ("Já li este")
Modal simplificado pra lançar rapidamente um livro **já concluído no passado**, sem passar pelo fluxo de "pendente": título, autor, ano, mês/data de conclusão, estrelas, nota. Ao salvar, cria o `Book` já com `read: true` e `finishedOn` preenchido. Essencial para reconstruir histórico de anos anteriores e alimentar as Estatísticas com dados reais.

> No protótipo atual, esse botão existe na toolbar mas a implementação completa do modal ficou pendente — o comportamento esperado é idêntico ao modal de "+ Adicionar livro" (seção 5.2.3), exceto que o livro nasce com `read: true` em vez de `false`.

#### 5.2.2 Mood picker ("O que eu leio hoje?")
Modal com grade 2 colunas de botões de humor:

| Humor | Ícone | Pesos sugeridos |
|---|---|---|
| Triste / pesada(o) | 😔 | leve |
| Contemplativa(o) | 🌙 | denso, muito-denso |
| Curiosa(o) / animada(o) | ✨ | leve, medio |
| Cansada(o), quero leveza | 🛁 | leve |
| Disposta(o) pra algo denso | 💪 | denso, muito-denso |
| Quero um romance gostoso | 💛 | leve, medio |

Ao escolher um humor: filtra livros não lidos do ano atual cujo `tag` esteja na lista de pesos sugeridos; se não houver nenhum, usa qualquer livro não lido como fallback; sorteia um aleatoriamente; exibe título, autor e justificativa ("Sugerido porque combina com X — peso Y"); botão para ir direto ao modal de edição daquele livro.

#### 5.2.3 Modal "+ Adicionar livro"
Campos: Título* · Autor* · Peso (select, opcional — "não sei ainda" disponível) · Páginas (opcional) · Grupo/categoria (select com 3 opções fixas: "Fila principal", "Banco de respiros leves", "Outros").

Se o peso for deixado em branco, o livro nasce com `pending: true` e `tag: 'leve'` (valor padrão até ser corrigido). O texto de ajuda no modal explica que, se o peso/páginas não forem conhecidos, o usuário deve avisar título+autor "no chat" para pesquisa — **essa instrução deve ser adaptada ou removida na versão sem Claude**, já que a pesquisa automática dependia da IA na conversa. Numa implementação real, isso pode ser substituído por uma integração com uma API de livros (Google Books API, Open Library API) para autocompletar páginas/sinopse a partir do título.

#### 5.2.4 Modal de edição de livro (clique no card)
Campos: Autor (editável mesmo que já preenchido) · Peso (select) · Páginas · Nota/descrição curta (textarea) · Avaliação (seletor de 1–5 estrelas clicáveis) · Data que terminei (date picker) · Minha resenha (textarea livre, sem estrutura imposta) · Lista de citações (cada uma com texto + página opcional, adicionável/removível) · Botões: Cancelar / Remover (exceto se `locked`) / Salvar.

Ao salvar, se o livro estava `pending` e um `tag` válido foi escolhido, remove a flag `pending`.

### 5.3 Tela Estatísticas

**Seletor de ano:** chips horizontais, um por ano presente nos dados + o ano atual.

**Cards de estatística (todos recalculados ao trocar de ano):**

1. **Chips de resumo:** lidos no ano · total na lista · média de estrelas
2. **Gráfico de barras "Ritmo por mês":** 12 colunas (Jan–Dez), altura proporcional ao número de livros concluídos naquele mês (`finishedOn`), valor numérico acima de cada barra quando > 0
3. **Gráfico donut "Distribuição por peso":** proporção de livros lidos em cada categoria de peso, implementado como `conic-gradient` puro em CSS (sem biblioteca de gráficos) + legenda lateral com contagem
4. **Lista "Melhor avaliados":** top 5 livros lidos com `stars > 0`, ordenados decrescente, com ranking numerado e estrelas exibidas
5. **Gráfico comparativo entre anos** (só aparece se houver mais de 1 ano com dados): barras lado a lado, uma por ano, com o ano atualmente selecionado destacado em vermelho (`--tomato`) e os demais em azul-água

### 5.4 Tela Placar

**Primeira visita (sem nome definido):** formulário simples pedindo nome/apelido, com aviso explícito de que **só a contagem de progresso é compartilhada, nunca notas ou resenhas**.

**Com nome definido:**
- Linha de status mostrando o nome atual + botão "Trocar nome"
- Publica automaticamente o progresso do usuário (contagem lida/total do ano atual) no armazenamento compartilhado a cada visita à tela e a cada alteração de status de leitura
- Busca todos os registros de progresso publicados por qualquer usuário, filtra pelo ano selecionado, ordena decrescente por livros lidos
- Renderiza como ranking: 🥇🥈🥉 para os 3 primeiros, posição numérica para os demais; nome (com "(você)" anexado quando aplicável); contagem `lido/total`; mini barra de progresso; percentual em destaque

---

## 6. Lógica de cálculo — pseudocódigo de referência

### 6.1 Streak (sequência de dias)

```js
function calcStreak(sessions, today) {
  const daysWithSession = new Set(sessions.map(s => s.date));
  let streak = 0;
  let cursor = today;
  while (daysWithSession.has(toISODate(cursor))) {
    streak++;
    cursor = subtractOneDay(cursor);
  }
  return streak;
}
```
Se não houver sessão registrada **hoje**, o streak retorna 0 imediatamente (a sequência "quebrou").

### 6.2 Progresso da meta mensal

```js
function calcGoalProgress(books, sessions, goal, monthKey) {
  if (goal.type === 'books') {
    return books.filter(b => b.read && b.finishedOn && monthKey(b.finishedOn) === monthKey).length;
  }
  if (goal.type === 'minutes') {
    const totalSeconds = sessions
      .filter(s => monthKey(s.date) === monthKey)
      .reduce((sum, s) => sum + s.seconds, 0);
    return Math.round(totalSeconds / 60);
  }
  // type === 'pages'
  return sessions
    .filter(s => monthKey(s.date) === monthKey)
    .reduce((sum, s) => sum + s.pages, 0);
}
// monthKey(dateISO) = dateISO.slice(0, 7) → "2026-06"
```

### 6.3 Progresso da banheira (barra anual)

```js
function tubProgress(books, year) {
  const yearBooks = books.filter(b => b.year === year);
  const total = yearBooks.length;
  const read = yearBooks.filter(b => b.read).length;
  const pct = total ? Math.round((read / total) * 100) : 0;
  return { total, read, pct };
}
```

### 6.4 Reordenação de livros (drag-like via setas)

A reordenação opera **dentro do subconjunto de livros do ano selecionado**, mas troca posições no array global subjacente (que pode misturar anos). Pseudocódigo:

```js
function moveBook(allBooks, year, bookId, direction) { // direction: -1 ou +1
  const yearList = allBooks.filter(b => b.year === year);
  const fromIndex = yearList.findIndex(b => b.id === bookId);
  const toIndex = fromIndex + direction;
  if (toIndex < 0 || toIndex >= yearList.length) return allBooks; // bloqueia nos limites

  const bookA = yearList[fromIndex];
  const bookB = yearList[toIndex];
  const globalIndexA = allBooks.indexOf(bookA);
  const globalIndexB = allBooks.indexOf(bookB);
  // troca as posições no array global
  [allBooks[globalIndexA], allBooks[globalIndexB]] = [allBooks[globalIndexB], allBooks[globalIndexA]];
  return allBooks;
}
```

### 6.5 Gráfico donut sem biblioteca (CSS puro)

```js
function buildConicGradient(counts, total, colorMap) {
  let accumulated = 0;
  const segments = [];
  for (const key of Object.keys(counts)) {
    if (counts[key] === 0) continue;
    const startDeg = (accumulated / total) * 360;
    accumulated += counts[key];
    const endDeg = (accumulated / total) * 360;
    segments.push(`${colorMap[key]} ${startDeg}deg ${endDeg}deg`);
  }
  if (segments.length === 0) return '#eee'; // fallback sem dados
  return `conic-gradient(${segments.join(', ')})`;
}
// aplicado via style.background no elemento .donut (div circular)
```

---

## 7. Comportamentos e regras de negócio importantes

- **Livro `locked`:** representa um livro lido **antes** da existência do app (ex: histórico inicial). Não pode ser desmarcado, não tem botão de remover, mas pode ter peso/nota/data editados normalmente.
- **Ao marcar um livro como lido** (clique no checkbox): se `finishedOn` estava vazio, preenche automaticamente com a data de hoje. Atualiza imediatamente a barra da banheira e republica o placar.
- **Privacidade:** notas pessoais (`myNote`), citações (`quotes`) e detalhes individuais de livros **nunca** devem ser expostos no Placar — só a dupla `(read, total)` por ano e por usuário.
- **Múltiplos anos:** a estrutura suporta qualquer número de anos. O seletor de ano na tela Lista e Estatísticas deve sempre incluir, no mínimo, o ano atual mesmo que vazio.
- **Busca automática de metadados via IA (requisito do cliente):** o protótipo dependia de pedir ajuda "no chat" do Claude.ai pra descobrir peso/páginas de um livro pendente. Na versão real, isso é substituído por uma chamada automática à **API da OpenAI**, disparada pelo back-end (PHP) a cada novo cadastro de livro:
  - O usuário cadastra só **Título + Autor** (Peso e Páginas deixam de ser preenchidos manualmente no modal "+ Adicionar").
  - Ao salvar, o front-end chama um endpoint PHP próprio (`api/lookup-metadata.php`), que por sua vez chama a API da OpenAI **do servidor**, nunca do navegador (a chave da OpenAI não pode ser exposta no JS).
  - A IA retorna peso de leitura (`leve`/`medio`/`denso`/`muito-denso`), estimativa de páginas e uma nota/sinopse curta (1–2 frases), em JSON estruturado.
  - Se a chamada falhar (erro de rede, timeout, resposta malformada), o livro é salvo normalmente com `pending: true` e `tag: 'leve'` como já previsto — a IA é um enriquecimento automático, não uma dependência bloqueante do cadastro.
  - Contrato completo do endpoint, prompt usado e considerações de custo/segurança: ver seção 10.2.

---

## 8. Responsividade

O protótipo foi desenhado **mobile-first** (largura de referência ~380–420px), mas usa `max-width` nos containers principais (`640–700px`) para funcionar bem também em desktop, centralizado. A navegação inferior fixa é um padrão mobile; em desktop, pode ser convertida em navegação lateral ou superior conforme preferência do designer, mas a hierarquia de telas (Banheira / Lista / Estatísticas / Placar) deve ser preservada.

---

## 9. Resumo de tecnologias usadas no protótipo (para referência, não prescritivo)

- HTML/CSS/JS vanilla, single-file, sem framework
- Fontes: Google Fonts (Baloo 2, Nunito)
- Gráficos: CSS puro (barras com `<div>` de altura proporcional; donut com `conic-gradient`) — nenhuma biblioteca externa
- Persistência: API de key-value específica do ambiente Claude.ai (`window.storage`), que **não existe fora dele** — deve ser substituída por backend real

**Stack confirmada para a versão hospedada:**
- **Front-end:** HTML/CSS/JS vanilla (mesma base do protótipo, sem migrar para framework SPA) — troca-se apenas a persistência local (`window.storage`) por chamadas `fetch` aos endpoints PHP.
- **Back-end:** PHP puro (sem framework), hospedado em cPanel — mesmo padrão de deploy já usado em outros projetos (FTP/GitHub Actions).
- **Banco de dados:** MySQL, acessado via PDO com prepared statements (proteção contra SQL injection, obrigatório já que o front-end vanilla vai mandar dados direto pros endpoints).
- **Autenticação:** sessão PHP nativa (`$_SESSION`) + `password_hash`/`password_verify`. Sem OAuth por ora, a menos que o cliente peça login social depois.
- **Enriquecimento de metadados:** API da OpenAI, chamada server-side via `cURL` dentro de um endpoint PHP dedicado (ver 10.2).
- A lógica de cálculo (seção 6) é framework-agnostic e pode ser portada quase 1:1 para PHP ou mantida em JS puro no front-end, dependendo de onde os dados já estiverem carregados.

---

## 10. Implementação — back-end PHP/MySQL

### 10.1 Schema MySQL

```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE books (
  id VARCHAR(36) PRIMARY KEY,          -- UUID
  user_id INT NOT NULL,
  year INT NOT NULL,
  `group` VARCHAR(120) NOT NULL,
  title VARCHAR(255) NOT NULL,
  author VARCHAR(255) NOT NULL,
  tag ENUM('leve','medio','denso','muito-denso') NOT NULL DEFAULT 'leve',
  pages INT NULL,
  note TEXT,
  read_status TINYINT(1) NOT NULL DEFAULT 0,   -- `read` é palavra reservada em SQL
  locked TINYINT(1) NOT NULL DEFAULT 0,
  pending TINYINT(1) NOT NULL DEFAULT 0,
  stars TINYINT NOT NULL DEFAULT 0,
  finished_on DATE NULL,
  my_note TEXT,
  progress TINYINT NULL,                -- 0-100
  sort_order INT NOT NULL DEFAULT 0,     -- suporta a reordenação por setas (seção 6.4)
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_year (user_id, year)
);

CREATE TABLE quotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id VARCHAR(36) NOT NULL,
  text TEXT NOT NULL,
  page VARCHAR(20) NULL,               -- string: aceita "12-15", "cap. 3"
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
);

CREATE TABLE sessions (
  id VARCHAR(36) PRIMARY KEY,
  book_id VARCHAR(36) NOT NULL,
  user_id INT NOT NULL,
  date DATE NOT NULL,
  seconds INT NOT NULL,
  pages INT NOT NULL DEFAULT 0,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE month_goals (
  user_id INT NOT NULL,
  month_key CHAR(7) NOT NULL,          -- "YYYY-MM"
  type ENUM('books','minutes','pages') NOT NULL,
  target INT NOT NULL,
  PRIMARY KEY (user_id, month_key),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE metadata_cache (
  cache_key VARCHAR(255) PRIMARY KEY,   -- normalizado: lower(trim(title)) + '|' + lower(trim(author))
  tag ENUM('leve','medio','denso','muito-denso') NOT NULL,
  pages INT NULL,
  note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

O Placar (`ScoreEntry`) não precisa de tabela própria — é uma **query agregada** sobre `books` (`COUNT(*) WHERE read_status=1` vs `COUNT(*)` por `user_id` + `year`), evitando duplicar dado que já existe.

### 10.2 Endpoint de busca automática de metadados (OpenAI)

**Fluxo:** front-end cadastra livro com `title` + `author` → chama `POST /api/lookup-metadata.php` → PHP chama OpenAI → retorna JSON → **front-end preenche os campos (Peso, Páginas, Nota) no próprio modal de cadastro, deixando-os editáveis** — o usuário revisa/corrige antes de confirmar o salvamento. O livro só é persistido quando o usuário clica em "Salvar", nunca automaticamente a partir da resposta da IA.

**Requisição (front-end → PHP):**
```json
{ "title": "A Sutil Arte de Ligar o F*da-se", "author": "Mark Manson" }
```

**PHP (`api/lookup-metadata.php`), pontos de segurança obrigatórios:**
- Chave da OpenAI fica num arquivo de config **fora da webroot** ou protegido por `.htaccess` (`Deny from all`), nunca hardcoded no JS.
- Endpoint exige sessão PHP válida (`$_SESSION['user_id']`) — não pode ser público, senão qualquer um gasta a cota da API.
- Timeout curto no `cURL` (ex: 8s) com fallback gracioso (seção 7).
- **Cache obrigatório** via tabela `metadata_cache` (seção 10.1): antes de chamar a OpenAI, o endpoint verifica se já existe uma entrada pra `title+author` normalizado; se sim, retorna do cache sem gastar uma nova chamada. Isso evita pagar a mesma consulta duas vezes quando usuários diferentes cadastram o mesmo livro.

```php
<?php
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); exit(json_encode(['error' => 'not authenticated'])); }

require_once __DIR__ . '/../config/openai.php'; // define OPENAI_API_KEY, fora da webroot
require_once __DIR__ . '/../config/db.php';      // define $pdo (PDO com prepared statements)

$input = json_decode(file_get_contents('php://input'), true);
$title = trim($input['title'] ?? '');
$author = trim($input['author'] ?? '');
if (!$title || !$author) { http_response_code(400); exit(json_encode(['error' => 'title and author required'])); }

$cacheKey = mb_strtolower($title) . '|' . mb_strtolower($author);

// 1) tenta o cache primeiro
$stmt = $pdo->prepare('SELECT tag, pages, note FROM metadata_cache WHERE cache_key = ?');
$stmt->execute([$cacheKey]);
if ($cached = $stmt->fetch(PDO::FETCH_ASSOC)) {
  echo json_encode($cached);
  exit;
}

$prompt = "Livro: \"$title\" de $author.\n" .
  "Responda APENAS um JSON válido, sem markdown, com este formato exato:\n" .
  '{"tag":"leve|medio|denso|muito-denso","pages":NUMBER_OR_NULL,"note":"1-2 frases descrevendo o livro"}' . "\n" .
  "Classifique 'tag' pelo peso emocional/intelectual de leitura, não pelo tamanho.";

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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) { http_response_code(502); exit(json_encode(['error' => 'openai request failed'])); }

$body = json_decode($response, true);
$content = $body['choices'][0]['message']['content'] ?? null;
$metadata = json_decode($content, true);

if (!$metadata || !isset($metadata['tag'])) {
  http_response_code(502);
  exit(json_encode(['error' => 'invalid openai response']));
}

// 2) grava no cache pra próxima consulta não pagar de novo
$stmt = $pdo->prepare('INSERT INTO metadata_cache (cache_key, tag, pages, note) VALUES (?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE tag = VALUES(tag), pages = VALUES(pages), note = VALUES(note)');
$stmt->execute([$cacheKey, $metadata['tag'], $metadata['pages'] ?? null, $metadata['note'] ?? '']);

echo json_encode($metadata); // { tag, pages, note }
```

**Resposta esperada:**
```json
{ "tag": "medio", "pages": 224, "note": "Um antídoto irônico à cultura da positividade tóxica, defendendo aceitar limites em vez de perseguir tudo." }
```

> Nota de custo: `gpt-4o-mini` é o modelo recomendado pra essa tarefa — é barato o suficiente pra rodar a cada cadastro sem preocupação relevante de custo, e a tarefa (classificação + resumo curto) não exige um modelo mais caro.

---

## Anexo: arquivo de referência

O arquivo `leituras-app.html` (entregue junto) é o protótipo navegável completo. Abra em qualquer navegador para ver e testar todo o comportamento descrito acima antes de iniciar a implementação.
