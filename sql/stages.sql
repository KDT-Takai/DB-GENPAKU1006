-- ==========================================================
-- クエスト（ステージ攻略）用 テーブルとマスタデータ
-- initialize.sql の最後で読み込まれる。
-- 既存のDBに追加するだけなら（ユーザーデータは消えない）:
--   mysql -uroot -p < /root/sql/stages.sql
-- ==========================================================
SET NAMES utf8mb4;
USE forge_db;

-- 章マスタ
CREATE TABLE IF NOT EXISTS chapters (
    id          INT          NOT NULL PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL,
    description VARCHAR(255) NOT NULL
);

-- ステージマスタ（idの順に攻略。前のステージをクリアすると次が解放される）
CREATE TABLE IF NOT EXISTS stages (
    id               INT          NOT NULL PRIMARY KEY,
    chapter_id       INT          NOT NULL,
    number           TINYINT      NOT NULL,          -- 章内の番号（1-1 の右側）
    name             VARCHAR(50)  NOT NULL,
    description      VARCHAR(255) NOT NULL,
    is_boss          TINYINT(1)   NOT NULL DEFAULT 0,
    star_turns       TINYINT      NOT NULL,          -- ★3条件：このターン数以内にクリア
    first_clear_gems INT          NOT NULL,          -- 初回クリア報酬
    clear_gems       INT          NOT NULL,          -- 2回目以降のクリア報酬
    FOREIGN KEY (chapter_id) REFERENCES chapters (id)
);

-- ステージに出てくる敵（カードではなくステージ専用のユニット）
CREATE TABLE IF NOT EXISTS stage_enemies (
    stage_id INT          NOT NULL,
    slot     TINYINT      NOT NULL,
    name     VARCHAR(50)  NOT NULL,
    image    VARCHAR(255) NOT NULL DEFAULT '',
    attack   INT          NOT NULL,
    hp       INT          NOT NULL,
    is_boss  TINYINT(1)   NOT NULL DEFAULT 0,       -- ボスは3ターンごとに全体攻撃
    PRIMARY KEY (stage_id, slot),
    FOREIGN KEY (stage_id) REFERENCES stages (id)
);

-- ユーザーのステージ進行状況
CREATE TABLE IF NOT EXISTS user_stage_progress (
    user_id     INT      NOT NULL,
    stage_id    INT      NOT NULL,
    best_stars  TINYINT  NOT NULL DEFAULT 0,  -- 獲得した★の最高数（0〜3）
    clear_count INT      NOT NULL DEFAULT 0,
    play_count  INT      NOT NULL DEFAULT 0,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, stage_id),
    FOREIGN KEY (user_id)  REFERENCES users (id),
    FOREIGN KEY (stage_id) REFERENCES stages (id)
);

-- ==========================================================
-- マスタデータ投入（何度実行しても同じ内容になる）
-- ==========================================================
INSERT INTO chapters (id, name, description) VALUES
(1, '星の海辺',   '旅のはじまり。弱いモンスターで戦い方を覚えよう。'),
(2, '氷晶の回廊', '守りの固い敵が増える。★4カードや限界突破で戦力を上げよう。'),
(3, '銀河の玉座', '最強の敵が待つ最終章。限界突破を重ねた★5で挑め。')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO stages (id, chapter_id, number, name, description, is_boss, star_turns, first_clear_gems, clear_gems) VALUES
( 1, 1, 1, 'はじまりの浜辺',     'スライムがのんびり跳ねている。',                     0, 2,  50, 10),
( 2, 1, 2, 'ゴブリンの見張り台', 'ゴブリンたちが行く手をふさぐ。',                     0, 2,  50, 10),
( 3, 1, 3, '夜光の森',           '宇宙ネコの群れが光る目でこちらを見ている。',         0, 3,  60, 10),
( 4, 1, 4, '巨大スライム',       '浜辺のぬし。3ターンごとに全体攻撃をしてくる。',     1, 3, 120, 20),
( 5, 2, 1, '凍てつく入口',       '魔法使いとナイトの見張り部隊。',                     0, 3,  80, 15),
( 6, 2, 2, '射手の待ち伏せ',     '流星の射手が高所から狙ってくる。',                   0, 3,  80, 15),
( 7, 2, 3, '氷の衛兵',           '鉄壁の守護者が後ろの魔導士を守る。',                 0, 4,  90, 15),
( 8, 2, 4, '氷晶の女王',         '回廊の支配者。3ターンごとに全体攻撃をしてくる。',   1, 5, 200, 30),
( 9, 3, 1, '星屑の門',           '銀河騎士団の精鋭4人が門を守る。',                   0, 4, 120, 20),
(10, 3, 2, '竜の巣',             'ドラゴンと射手の連携に注意。',                       0, 5, 120, 20),
(11, 3, 3, '剣聖の試練',         '銀河の剣聖と守護者が立ちはだかる。',                 0, 5, 150, 25),
(12, 3, 4, '星喰いの竜王',       '全ての元凶。3ターンごとに全体攻撃をしてくる。',     1, 7, 400, 50)
ON DUPLICATE KEY UPDATE chapter_id = VALUES(chapter_id), number = VALUES(number), name = VALUES(name),
    description = VALUES(description), is_boss = VALUES(is_boss), star_turns = VALUES(star_turns),
    first_clear_gems = VALUES(first_clear_gems), clear_gems = VALUES(clear_gems);

DELETE FROM stage_enemies;
INSERT INTO stage_enemies (stage_id, slot, name, image, attack, hp, is_boss) VALUES
-- 第1章
( 1, 1, 'スライム',           'slime.svg',      40,   250, 0),
( 1, 2, 'スライム',           'slime.svg',      40,   250, 0),
( 2, 1, 'ゴブリン',           'goblin.svg',     70,   350, 0),
( 2, 2, 'ゴブリン',           'goblin.svg',     70,   350, 0),
( 2, 3, 'スライム',           'slime.svg',      40,   250, 0),
( 3, 1, '宇宙ネコ',           'cat.svg',        90,   450, 0),
( 3, 2, '宇宙ネコ',           'cat.svg',        90,   450, 0),
( 3, 3, 'ゴブリン',           'goblin.svg',     70,   350, 0),
( 4, 1, '巨大スライム',       'slime.svg',     150,  2500, 1),
( 4, 2, 'スライム',           'slime.svg',      40,   250, 0),
( 4, 3, 'スライム',           'slime.svg',      40,   250, 0),
-- 第2章
( 5, 1, '見習い魔法使い',     'mage.svg',      160,   500, 0),
( 5, 2, '見習い魔法使い',     'mage.svg',      160,   500, 0),
( 5, 3, 'ナイト',             'knight.svg',    130,   800, 0),
( 6, 1, '流星の射手',         'archer.svg',    250,   900, 0),
( 6, 2, '流星の射手',         'archer.svg',    250,   900, 0),
( 7, 1, '鉄壁の守護者',       'guardian.svg',  180,  1600, 0),
( 7, 2, '鉄壁の守護者',       'guardian.svg',  180,  1600, 0),
( 7, 3, '氷晶の魔導士',       'ice.svg',       320,  1000, 0),
( 8, 1, '氷晶の女王',         'ice.svg',       450,  8000, 1),
( 8, 2, '鉄壁の守護者',       'guardian.svg',  180,  2000, 0),
-- 第3章
( 9, 1, '銀河騎士',           'knight.svg',    350,  2000, 0),
( 9, 2, '銀河騎士',           'knight.svg',    350,  2000, 0),
( 9, 3, '銀河騎士',           'knight.svg',    350,  2000, 0),
( 9, 4, '銀河騎士',           'knight.svg',    350,  2000, 0),
(10, 1, 'ドラゴン',           'dragon.svg',    900,  9000, 0),
(10, 2, '流星の射手',         'archer.svg',    500,  2500, 0),
(10, 3, '流星の射手',         'archer.svg',    500,  2500, 0),
(11, 1, '銀河の剣聖',         'swordsman.svg',1000, 10000, 0),
(11, 2, '鉄壁の守護者',       'guardian.svg',  500,  8000, 0),
(12, 1, '星喰いの竜王',       'dragon.svg',   1000, 26000, 1),
(12, 2, '銀河の剣聖',         'swordsman.svg', 600,  4000, 0);
