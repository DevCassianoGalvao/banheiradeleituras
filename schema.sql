-- Banheira de Leituras — schema MySQL
-- Extraído de especificacao-tecnica.md, seção 10.1

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
