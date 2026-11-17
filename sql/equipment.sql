-- ==========================================================
-- 装備（武器・防具・アクセサリー）用 テーブルとマスタデータ
-- initialize.sql の最後で読み込まれる。
-- 既存のDBに追加するだけなら（ユーザーデータは消えない・何度実行しても大丈夫）:
--   mysql -uroot -p < /root/sql/equipment.sql
-- ==========================================================
SET NAMES utf8mb4;
USE forge_db;

-- 装備マスタ
--   slot   : weapon（武器・ATK）/ armor（防具・HP）/ accessory（アクセサリー・両方少し）
--   rarity : 1=N 2=R 3=SR 4=SSR
--   attack / hp : レベル1のときの上昇値。レベルが1上がるごとに +10%
CREATE TABLE IF NOT EXISTS equipments (
    id     INT         NOT NULL PRIMARY KEY,
    name   VARCHAR(50) NOT NULL,
    slot   VARCHAR(10) NOT NULL,
    rarity TINYINT     NOT NULL,
    attack INT         NOT NULL DEFAULT 0,
    hp     INT         NOT NULL DEFAULT 0
);

-- 所持している装備。user_card_id が装備しているカード（NULL = 未装備）
-- カードが限界突破の素材で消えたら、装備は自動で外れる（ON DELETE SET NULL）
CREATE TABLE IF NOT EXISTS user_equipments (
    id           INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      INT      NOT NULL,
    equipment_id INT      NOT NULL,
    level        INT      NOT NULL DEFAULT 1,
    user_card_id INT      NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)      REFERENCES users (id),
    FOREIGN KEY (equipment_id) REFERENCES equipments (id),
    FOREIGN KEY (user_card_id) REFERENCES user_cards (id) ON DELETE SET NULL,
    INDEX idx_user_card (user_card_id)
);

INSERT INTO equipments (id, name, slot, rarity, attack, hp) VALUES
    (1,  '木の剣',       'weapon',    1, 30,  0),
    (2,  '鋼の剣',       'weapon',    2, 70,  0),
    (3,  '星鉄の大剣',   'weapon',    3, 150, 0),
    (4,  '流星剣',       'weapon',    4, 300, 0),
    (5,  '布の服',       'armor',     1, 0,   120),
    (6,  '革の鎧',       'armor',     2, 0,   280),
    (7,  '鋼の鎧',       'armor',     3, 0,   600),
    (8,  '星屑の鎧',     'armor',     4, 0,   1200),
    (9,  'お守り',       'accessory', 1, 15,  60),
    (10, '銀の指輪',     'accessory', 2, 35,  140),
    (11, '月の首飾り',   'accessory', 3, 75,  300),
    (12, '星の冠',       'accessory', 4, 150, 600)
ON DUPLICATE KEY UPDATE name = VALUES(name), slot = VALUES(slot), rarity = VALUES(rarity),
                        attack = VALUES(attack), hp = VALUES(hp);

-- 既存ユーザーに初期装備（Nの3種）を1セットずつ配る（まだ装備を持っていない人だけ）
INSERT INTO user_equipments (user_id, equipment_id)
SELECT u.id, e.id FROM users u CROSS JOIN equipments e
 WHERE e.rarity = 1 AND NOT EXISTS (SELECT 1 FROM user_equipments ue WHERE ue.user_id = u.id);
