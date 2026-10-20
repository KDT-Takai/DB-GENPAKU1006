-- ==========================================================
-- ソシャゲ（ガチャ・編成・バトル・限界突破）用 初期化SQL
-- 実行: mysql -uroot -p < /root/sql/initialize.sql
-- ==========================================================
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS forge_db;
CREATE DATABASE forge_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE forge_db;

-- ==========================================================
-- マスタデータ（最初に準備しておくデータ）
-- ==========================================================

-- カードマスタ
--   next_card_id : 限界突破後に変化するカードID（NULL = これ以上突破できない）
CREATE TABLE cards (
    id           INT         NOT NULL PRIMARY KEY,
    name         VARCHAR(50) NOT NULL,
    rarity       TINYINT     NOT NULL,           -- ★の数（3〜5）
    limit_break  TINYINT     NOT NULL DEFAULT 0, -- 限界突破段階（0〜2）
    attack       INT         NOT NULL,
    hp           INT         NOT NULL,
    next_card_id INT         NULL,
    image        VARCHAR(255) NOT NULL DEFAULT '',  -- htdocs/resource/cards/ 内の画像ファイル名
    FOREIGN KEY (next_card_id) REFERENCES cards (id)
);

-- ガチャマスタ
CREATE TABLE gachas (
    id          INT          NOT NULL PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL,
    description VARCHAR(255) NOT NULL,
    cost        INT          NOT NULL,          -- 1回あたりの石
    pity        INT          NOT NULL           -- この回数で★5確定（天井）
);

-- ガチャの排出カードと重み（weightの合計に対する割合が排出率）
CREATE TABLE gacha_items (
    gacha_id INT NOT NULL,
    card_id  INT NOT NULL,
    weight   INT NOT NULL,
    PRIMARY KEY (gacha_id, card_id),
    FOREIGN KEY (gacha_id) REFERENCES gachas (id),
    FOREIGN KEY (card_id)  REFERENCES cards (id)
);

-- ==========================================================
-- トランザクションデータ（プレイヤーの行動で増えるデータ）
-- ==========================================================

-- ユーザー
CREATE TABLE users (
    id         INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50) NOT NULL,
    gems       INT         NOT NULL DEFAULT 1600,  -- 石（ガチャに使う通貨）
    pity_count INT         NOT NULL DEFAULT 0,     -- 前回★5からのガチャ回数
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ユーザー所持カード（同じcard_idを複数所持できる）
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

-- チーム編成（4枠。編成中のカードは素材にできない）
--   外部キーにより、編成中のuser_cardsは削除できない（二重の安全策）
CREATE TABLE user_team_cards (
    user_id      INT     NOT NULL,
    slot         TINYINT NOT NULL,            -- 編成枠 1〜4
    user_card_id INT     NOT NULL UNIQUE,
    PRIMARY KEY (user_id, slot),
    FOREIGN KEY (user_id)      REFERENCES users (id),
    FOREIGN KEY (user_card_id) REFERENCES user_cards (id)
);

-- ガチャ履歴
CREATE TABLE user_gacha_histories (
    id         INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT      NOT NULL,
    gacha_id   INT      NOT NULL,
    card_id    INT      NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_gacha (user_id, gacha_id),
    FOREIGN KEY (user_id)  REFERENCES users (id),
    FOREIGN KEY (gacha_id) REFERENCES gachas (id),
    FOREIGN KEY (card_id)  REFERENCES cards (id)
);

-- ログインボーナス受け取り履歴（1日1回）
CREATE TABLE user_login_bonuses (
    user_id    INT  NOT NULL,
    login_date DATE NOT NULL,
    PRIMARY KEY (user_id, login_date),
    FOREIGN KEY (user_id) REFERENCES users (id)
);

-- バトル履歴
CREATE TABLE battle_histories (
    id               INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id          INT      NOT NULL,
    opponent_user_id INT      NOT NULL,
    result           ENUM('win', 'lose') NOT NULL,
    user_power       INT      NOT NULL,
    opponent_power   INT      NOT NULL,
    reward_gems      INT      NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id)          REFERENCES users (id),
    FOREIGN KEY (opponent_user_id) REFERENCES users (id)
);

-- ==========================================================
-- マスタデータ投入
-- ==========================================================

-- 各カードは 通常 → +1 → +2 の3段階（突破後カードから先に登録）
INSERT INTO cards (id, name, rarity, limit_break, attack, hp, next_card_id) VALUES
-- ★3
( 3, 'スライム+2',         3, 2,  160,  480, NULL), ( 2, 'スライム+1',         3, 1,  130,  390,  3), ( 1, 'スライム',         3, 0,  100,  300,  2),
( 6, 'ゴブリン+2',         3, 2,  240,  560, NULL), ( 5, 'ゴブリン+1',         3, 1,  195,  455,  6), ( 4, 'ゴブリン',         3, 0,  150,  350,  5),
( 9, 'ナイト+2',           3, 2,  288,  800, NULL), ( 8, 'ナイト+1',           3, 1,  234,  650,  9), ( 7, 'ナイト',           3, 0,  180,  500,  8),
(12, '見習い魔法使い+2',   3, 2,  352,  480, NULL), (11, '見習い魔法使い+1',   3, 1,  286,  390, 12), (10, '見習い魔法使い',   3, 0,  220,  300, 11),
(15, '宇宙ネコ+2',         3, 2,  256,  672, NULL), (14, '宇宙ネコ+1',         3, 1,  208,  546, 15), (13, '宇宙ネコ',         3, 0,  160,  420, 14),
-- ★4
(18, '流星の射手+2',       4, 2,  672, 1280, NULL), (17, '流星の射手+1',       4, 1,  546, 1040, 18), (16, '流星の射手',       4, 0,  420,  800, 17),
(21, '氷晶の魔導士+2',     4, 2,  768, 1120, NULL), (20, '氷晶の魔導士+1',     4, 1,  624,  910, 21), (19, '氷晶の魔導士',     4, 0,  480,  700, 20),
(24, '鉄壁の守護者+2',     4, 2,  480, 2080, NULL), (23, '鉄壁の守護者+1',     4, 1,  390, 1690, 24), (22, '鉄壁の守護者',     4, 0,  300, 1300, 23),
-- ★5
(27, 'ドラゴン+2',         5, 2, 1280, 2560, NULL), (26, 'ドラゴン+1',         5, 1, 1040, 2080, 27), (25, 'ドラゴン',         5, 0,  800, 1600, 26),
(30, '銀河の剣聖+2',       5, 2, 1520, 2240, NULL), (29, '銀河の剣聖+1',       5, 1, 1235, 1820, 30), (28, '銀河の剣聖',       5, 0,  950, 1400, 29);

-- カード画像（同じシリーズは突破段階が違っても同じ画像）
UPDATE cards SET image = 'slime.svg' WHERE name LIKE 'スライム%';
UPDATE cards SET image = 'goblin.svg' WHERE name LIKE 'ゴブリン%';
UPDATE cards SET image = 'knight.svg' WHERE name LIKE 'ナイト%';
UPDATE cards SET image = 'mage.svg' WHERE name LIKE '見習い魔法使い%';
UPDATE cards SET image = 'cat.svg' WHERE name LIKE '宇宙ネコ%';
UPDATE cards SET image = 'archer.svg' WHERE name LIKE '流星の射手%';
UPDATE cards SET image = 'ice.svg' WHERE name LIKE '氷晶の魔導士%';
UPDATE cards SET image = 'guardian.svg' WHERE name LIKE '鉄壁の守護者%';
UPDATE cards SET image = 'dragon.svg' WHERE name LIKE 'ドラゴン%';
UPDATE cards SET image = 'swordsman.svg' WHERE name LIKE '銀河の剣聖%';

INSERT INTO gachas (id, name, description, cost, pity) VALUES
(1, '星海ワープ', '★5「ドラゴン」「銀河の剣聖」が登場！90回以内に★5が必ず1枚出現します。', 160, 90);

-- 排出率：★5 0.6% / ★4 5.1% / ★3 94.3%（weightの合計 10000）
INSERT INTO gacha_items (gacha_id, card_id, weight) VALUES
(1, 25,   30), (1, 28,   30),                     -- ★5 各0.3%
(1, 16,  170), (1, 19,  170), (1, 22, 170),       -- ★4 各1.7%
(1,  1, 1886), (1,  4, 1886), (1,  7, 1886), (1, 10, 1886), (1, 13, 1886);  -- ★3 各18.86%

-- ==========================================================
-- サンプルデータ
-- ==========================================================
INSERT INTO users (id, name, gems) VALUES
(1, 'サンプルユーザー', 3200),
(2, 'テストユーザー',   1600),
(3, 'ライバル',         1600);

-- ユーザー1の所持カード
INSERT INTO user_cards (id, user_id, card_id) VALUES
(1,  1,  1),   -- スライム（チーム編成中）
(2,  1,  1),   -- スライム
(3,  1,  1),   -- スライム
(4,  1,  4),   -- ゴブリン（1枚のみ → 限界突破の画面①に出ない）
(5,  1,  7),   -- ナイト（チーム編成中）
(6,  1,  7),   -- ナイト
(7,  1, 25),   -- ドラゴン（チーム編成中）
(8,  1, 25),   -- ドラゴン（チーム編成中 → 素材がない）
(9,  1,  3),   -- スライム+2（最大突破済み）
(10, 1,  3),   -- スライム+2（最大突破済み）
(11, 1, 16),   -- 流星の射手
-- ユーザー2
(12, 2,  1),
(13, 2,  1),
(14, 2, 19),   -- 氷晶の魔導士
(15, 2, 10),   -- 見習い魔法使い
-- ユーザー3
(16, 3, 28),   -- 銀河の剣聖
(17, 3, 22),   -- 鉄壁の守護者
(18, 3, 13),   -- 宇宙ネコ
(19, 3,  4);   -- ゴブリン

-- チーム編成
INSERT INTO user_team_cards (user_id, slot, user_card_id) VALUES
(1, 1, 1), (1, 2, 5), (1, 3, 7), (1, 4, 8),
(2, 1, 14), (2, 2, 12), (2, 3, 15),
(3, 1, 16), (3, 2, 17), (3, 3, 18), (3, 4, 19);

-- バトル履歴（ランキング表示用）
INSERT INTO battle_histories (user_id, opponent_user_id, result, user_power, opponent_power, reward_gems, created_at) VALUES
(2, 3, 'lose', 1700, 3400, 20, NOW() - INTERVAL 2 DAY),
(3, 2, 'win',  3500, 1650, 60, NOW() - INTERVAL 2 DAY),
(3, 1, 'win',  3300, 3100, 60, NOW() - INTERVAL 1 DAY),
(1, 2, 'win',  3200, 1800, 60, NOW() - INTERVAL 1 DAY);
