-- ==========================================================
-- 限界突破（重複カード強化）用 初期化SQL
-- 実行: mysql -uroot -p < /root/sql/initialize.sql
-- ==========================================================
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS forge_db;
CREATE DATABASE forge_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE forge_db;

-- ----------------------------------------------------------
-- ユーザー
-- ----------------------------------------------------------
CREATE TABLE users (
    id         INT          NOT NULL PRIMARY KEY,
    name       VARCHAR(50)  NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------
-- カードマスタ
--   next_card_id : 限界突破後に変化するカードID（NULL = これ以上突破できない）
-- ----------------------------------------------------------
CREATE TABLE cards (
    id           INT         NOT NULL PRIMARY KEY,
    name         VARCHAR(50) NOT NULL,
    rarity       TINYINT     NOT NULL,           -- ★の数
    limit_break  TINYINT     NOT NULL DEFAULT 0, -- 限界突破段階
    attack       INT         NOT NULL,
    hp           INT         NOT NULL,
    next_card_id INT         NULL,
    FOREIGN KEY (next_card_id) REFERENCES cards (id)
);

-- ----------------------------------------------------------
-- ユーザー所持カード（同じcard_idを複数所持できる）
-- ----------------------------------------------------------
CREATE TABLE user_cards (
    id         INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT      NOT NULL,
    card_id    INT      NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_card (user_id, card_id),
    FOREIGN KEY (user_id) REFERENCES users (id),
    FOREIGN KEY (card_id) REFERENCES cards (id)
);

-- ----------------------------------------------------------
-- チーム編成（編成中のカードは素材にできない）
--   外部キーにより、編成中のuser_cardsは削除できない（二重の安全策）
-- ----------------------------------------------------------
CREATE TABLE user_team_cards (
    user_id      INT     NOT NULL,
    slot         TINYINT NOT NULL,            -- 編成枠 1〜5
    user_card_id INT     NOT NULL UNIQUE,
    PRIMARY KEY (user_id, slot),
    FOREIGN KEY (user_id)      REFERENCES users (id),
    FOREIGN KEY (user_card_id) REFERENCES user_cards (id)
);

-- ==========================================================
-- サンプルデータ
-- ==========================================================
INSERT INTO users (id, name) VALUES
(1, 'たかい'),
(2, 'テストユーザー');

-- 突破後カードを先に登録（next_card_idの外部キーのため、最終段階から順に）
INSERT INTO cards (id, name, rarity, limit_break, attack, hp, next_card_id) VALUES
(3,  'スライム+2',   1, 2,  180,  600, NULL),
(2,  'スライム+1',   1, 1,  140,  450, 3),
(1,  'スライム',     1, 0,  100,  300, 2),
(5,  'ゴブリン+1',   2, 1,  260,  520, NULL),
(4,  'ゴブリン',     2, 0,  200,  400, 5),
(8,  'ナイト+2',     3, 2,  520, 1100, NULL),
(7,  'ナイト+1',     3, 1,  420,  900, 8),
(6,  'ナイト',       3, 0,  320,  700, 7),
(11, 'ドラゴン+2',   5, 2, 1300, 2600, NULL),
(10, 'ドラゴン+1',   5, 1, 1050, 2100, 11),
(9,  'ドラゴン',     5, 0,  800, 1600, 10);

-- ユーザー1の所持カード
INSERT INTO user_cards (id, user_id, card_id) VALUES
(1,  1, 1),   -- スライム（チーム編成中）
(2,  1, 1),   -- スライム
(3,  1, 1),   -- スライム
(4,  1, 4),   -- ゴブリン（1枚のみ → 画面①に出ない）
(5,  1, 6),   -- ナイト（チーム編成中）
(6,  1, 6),   -- ナイト
(7,  1, 9),   -- ドラゴン（チーム編成中）
(8,  1, 9),   -- ドラゴン（チーム編成中 → 素材がない）
(9,  1, 3),   -- スライム+2（最大突破済み）
(10, 1, 3),   -- スライム+2（最大突破済み）
(11, 2, 1),   -- ユーザー2のカード（ユーザー1の画面には出ない）
(12, 2, 1);

-- ユーザー1のチーム編成
INSERT INTO user_team_cards (user_id, slot, user_card_id) VALUES
(1, 1, 1),
(1, 2, 5),
(1, 3, 7),
(1, 4, 8);
