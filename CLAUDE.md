# Banheira de Leituras — Contexto do Projeto

App pessoal de acompanhamento de leitura anual. Metáfora central: a meta de
leitura do ano é uma banheira que enche conforme livros são marcados como
lidos. Identidade visual nasce da ilustração de referência (gato numa
banheira rosa cheia de patinhos) — estilo flat/gouache, sombras sólidas tipo
sticker, sem gradiente nem blur.

## Documentos-fonte (ler antes de codar qualquer coisa)

- `especificacao-tecnica.md` — fonte da verdade: modelo de dados, regras de
  negócio, pseudocódigo das lógicas (streak, progresso, donut CSS,
  reordenação), schema SQL completo (seção 10.1) e contrato do endpoint de
  IA (seção 10.2).
- `PRD-Banheira-de-Leituras.pdf` — visão de produto já aprovada pelo
  cliente. Qualquer funcionalidade fora dali é fora de escopo desta fase.
- `prototype-reference/leituras-app.html` — protótipo funcional feito no
  Claude.ai. Tem toda a linguagem visual (CSS, cores, componentes das 4
  telas) já resolvida — **reaproveitar o CSS/HTML de lá**, o trabalho aqui é
  trocar a persistência (`window.storage`) por PHP/MySQL real, não
  redesenhar a interface.

## Deploy — domínio e subpasta (crítico)

App vai rodar em **www.ojhongomes.com.br/banheiradeleituras** — SUBPASTA de
domínio existente, não raiz. Isso afeta código e config do dia 1:

1. **Nunca** caminho absoluto começando com `/` em asset, `fetch()` ou
   `action` de form (ex: `/api/login.php` quebra, vira
   `www.ojhongomes.com.br/api/login.php`). Usar caminho relativo
   (`api/login.php`) ou uma constante `BASE_PATH` central (PHP e/ou JS) fácil
   de trocar num lugar só se a subpasta mudar de nome no futuro.
2. cPanel Git Version Control: **Repository Path** = `public_html/ojhongomes.com.br/banheiradeleituras`.
   `ojhongomes.com.br` é domínio **addon** nessa conta cPanel — docroot dele
   é `public_html/ojhongomes.com.br/`, não `public_html/` raiz (que hospeda
   vários outros domínios da conta). Nunca apontar pra raiz do `public_html`
   da conta nem pra raiz de `public_html/ojhongomes.com.br/` — sobrescreve
   site (WordPress) já existente lá.
3. `config/.htaccess` (`Deny from all`) funciona igual dentro da subpasta,
   nenhuma mudança necessária.
4. `.htaccess` na raiz do repo bloqueia acesso HTTP a `.git` — necessário
   porque o Repository Path acima clona o repo (com `.git`) direto dentro
   da webroot.

## Stack confirmada com o cliente

- Back-end: PHP puro (sem framework), hospedado em cPanel.
- Banco: MySQL via PDO.
- Front-end: HTML/CSS/JS vanilla (sem SPA), reaproveitando o protótipo.
- IA: API da OpenAI (`gpt-4o-mini`), chamada só pelo servidor.

## Regras não-negociáveis (segurança)

1. **Nunca** expor chave da OpenAI (ou qualquer credencial) no JS/front-end.
   Fica em `config/openai.php`, fora da webroot pública ou protegido por
   `.htaccess` (`Deny from all`) — ver pasta `config/` deste pacote.
2. Toda query MySQL usa **prepared statements via PDO**. Nunca concatenar
   valor de usuário direto numa query.
3. Todo endpoint que lê/grava dado de usuário checa `$_SESSION['user_id']`
   antes de qualquer coisa. Nenhum endpoint de escrita é público.
4. Senha sempre com `password_hash()` / `password_verify()`, nunca em texto
   puro nem hash própria.
5. Notas pessoais, resenhas e citações **nunca** aparecem em endpoints
   públicos ou no Placar — lá só a contagem numérica (lidos/total) é
   exposta entre usuários.
6. Se a chamada à IA falhar (timeout, erro, JSON malformado), o cadastro do
   livro **não pode travar**: salva com `pending = true` e segue o fluxo
   normal (ver especificação, seção 7).
7. Cache de metadados (tabela `metadata_cache`) é consultado **antes** de
   chamar a OpenAI — nunca pular essa checagem.

## Estrutura de pastas esperada

Caminhos abaixo são relativos à raiz do app (`public_html/ojhongomes.com.br/banheiradeleituras/`
no cPanel, não à raiz do domínio — ver seção Deploy acima). No repositório
Git, a raiz do repo já É essa pasta (sem `public_html/` aninhado dentro).

```
/public_html/ojhongomes.com.br/banheiradeleituras/
  index.html (ou .php)          — shell do app, carrega o CSS/JS do protótipo
  /assets/                      — css, js, imagens (inclui a ilustração do gato)
  /api/
    login.php
    register.php
    books.php                  — CRUD de livros
    sessions.php                — cronômetro de leitura
    month-goals.php
    scoreboard.php
    lookup-metadata.php         — busca via IA (contrato na spec, seção 10.2)
    export.php
/config/                        — FORA da webroot pública, ou com .htaccess Deny from all
  db.php
  openai.php
/schema.sql
```

## Ordem de desenvolvimento sugerida

1. **Fundação** — schema MySQL rodado, `config/db.php` e conexão PDO
   testada, login/registro funcionando com sessão PHP.
2. **Núcleo** — CRUD de livros (tela Lista), plugar no CSS do protótipo,
   marcar como lido, cálculo da barra da Banheira.
3. **IA** — endpoint `lookup-metadata.php` completo (com cache), modal de
   cadastro passa a preencher os campos automaticamente e editáveis.
4. **Cronômetro & streak** — sessões de leitura, cálculo de dias
   consecutivos.
5. **Estatísticas & Placar** — gráficos e ranking agregados por query.
6. **Metas mensais, sugestão por humor, exportação** — funcionalidades de
   apoio, menor prioridade.
7. **Deploy** — subir pro cPanel em `public_html/ojhongomes.com.br/banheiradeleituras`
   (domínio final: www.ojhongomes.com.br/banheiradeleituras), checklist de
   segurança (ver regras acima) e checklist de caminho relativo (ver seção
   Deploy) revisados antes de ir ao ar.

## Fora de escopo (não implementar sem confirmar com o cliente)

App nativo, login social (Google/Facebook), notificações push, capa de
livro (imagem) no cadastro automático — ver PRD, seção 7.
