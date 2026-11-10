-- ==========================================================
-- 育成・デイリー要素（ランク・スタミナ・ゴールド・カードレベル・属性とスキル・
-- ミッション・プレゼントボックス）用 テーブルとマスタデータ
-- initialize.sql の最後で読み込まれる。
-- 既存のDBに追加するだけなら（ユーザーデータは消えない・何度実行しても大丈夫）:
--   mysql -uroot -p < /root/sql/social.sql
-- ==========================================================
SET NAMES utf8mb4;
USE forge_db;

-- 列がなければ追加する（MySQL 8.0 には ADD COLUMN IF NOT EXISTS がないため）
DROP PROCEDURE IF EXISTS add_column_if_missing;
DELIMITER //
CREATE PROCEDURE add_column_if_missing(IN tbl VARCHAR(64), IN col VARCHAR(64), IN def VARCHAR(255))
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
        SET @ddl = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', def);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- ユーザー：ランク・経験値・ゴールド・スタミナ
CALL add_column_if_missing('users', 'player_rank', 'INT NOT NULL DEFAULT 1');
CALL add_column_if_missing('users', 'exp',         'INT NOT NULL DEFAULT 0');
CALL add_column_if_missing('users', 'gold',        'INT NOT NULL DEFAULT 5000');
CALL add_column_if_missing('users', 'stamina',     'INT NOT NULL DEFAULT 60');
-- stamina_at：スタミナを最後に計算した時刻（ここからの経過時間ぶん回復する）
CALL add_column_if_missing('users', 'stamina_at',  'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

-- 所持カード：レベル（ゴールドで上げる。ステータスが 1レベルにつき +6%）
CALL add_column_if_missing('user_cards', 'level', 'INT NOT NULL DEFAULT 1');

-- カード：属性・スキル・説明文
--   element: fire / water / wind / light / dark / none
--   skill  : strike（単体強攻撃）/ aoe（全体攻撃）/ heal（回復）/ guard（被ダメージ軽減）
CALL add_column_if_missing('cards', 'element', "VARCHAR(10) NOT NULL DEFAULT 'none'");
CALL add_column_if_missing('cards', 'skill',   "VARCHAR(10) NOT NULL DEFAULT 'strike'");
CALL add_column_if_missing('cards', 'flavor',  "VARCHAR(255) NOT NULL DEFAULT ''");

-- ステージ：消費スタミナ ／ 敵：属性
CALL add_column_if_missing('stages',        'stamina', 'INT NOT NULL DEFAULT 8');
CALL add_column_if_missing('stage_enemies', 'element', "VARCHAR(10) NOT NULL DEFAULT 'none'");

DROP PROCEDURE add_column_if_missing;

-- デイリーミッションの日別カウント（mkey ごとに今日何回やったか）
CREATE TABLE IF NOT EXISTS user_daily_counts (
    user_id INT         NOT NULL,
    day     DATE        NOT NULL,
    mkey    VARCHAR(20) NOT NULL,
    cnt     INT         NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, day, mkey),
    FOREIGN KEY (user_id) REFERENCES users (id)
);

-- ミッション報酬の受け取り記録（period：デイリーは日付、実績は 'once'）
CREATE TABLE IF NOT EXISTS user_mission_claims (
    user_id    INT         NOT NULL,
    mission    VARCHAR(30) NOT NULL,
    period     VARCHAR(10) NOT NULL,
    claimed_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, mission, period),
    FOREIGN KEY (user_id) REFERENCES users (id)
);

-- プレゼントボックス（received_at が NULL のものが未受け取り）
CREATE TABLE IF NOT EXISTS user_presents (
    id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    item        ENUM('gems', 'gold', 'stamina') NOT NULL,
    amount      INT          NOT NULL,
    message     VARCHAR(100) NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    received_at DATETIME     NULL,
    INDEX idx_user_open (user_id, received_at),
    FOREIGN KEY (user_id) REFERENCES users (id)
);

-- ==========================================================
-- マスタデータ（何度実行しても同じ内容になる）
-- ==========================================================
UPDATE cards SET element = 'water', skill = 'heal',   flavor = 'ぷるぷるの体で仲間の傷をふさぐ。見た目より頼れる。'                 WHERE image = 'slime.svg';
UPDATE cards SET element = 'wind',  skill = 'strike', flavor = '素早さだけは一流。隙を見つけると一気に飛びかかる。'               WHERE image = 'goblin.svg';
UPDATE cards SET element = 'light', skill = 'guard',  flavor = '盾を構えれば仲間の前から一歩も退かない、誓いの騎士。'             WHERE image = 'knight.svg';
UPDATE cards SET element = 'fire',  skill = 'aoe',    flavor = '火の玉はまだ小さいけれど、数で押すのが得意。'                     WHERE image = 'mage.svg';
UPDATE cards SET element = 'dark',  skill = 'strike', flavor = '星の海を渡ってきたネコ。気まぐれな一撃が意外と重い。'             WHERE image = 'cat.svg';
UPDATE cards SET element = 'wind',  skill = 'aoe',    flavor = '流れ星を矢にして放つ。降りそそぐ光の雨から逃げ場はない。'         WHERE image = 'archer.svg';
UPDATE cards SET element = 'water', skill = 'aoe',    flavor = '氷の結晶で戦場を包み込む魔導士。静かで冷たい怒りを持つ。'         WHERE image = 'ice.svg';
UPDATE cards SET element = 'light', skill = 'guard',  flavor = '星の加護を受けた大盾は、どんな攻撃も受け流す。'                   WHERE image = 'guardian.svg';
UPDATE cards SET element = 'fire',  skill = 'aoe',    flavor = '星を食らう古き竜。その吐息は戦場を焼き尽くす。'                   WHERE image = 'dragon.svg';
UPDATE cards SET element = 'dark',  skill = 'strike', flavor = '銀河の果てで剣を磨き続けた剣聖。その一閃は光より速い。'           WHERE image = 'swordsman.svg';

-- 消費スタミナ：章が進むほど多く、ボスはさらに多い
UPDATE stages SET stamina = 6 + chapter_id * 2 + is_boss * 4;

-- 敵の属性（同じ画像の敵は同じ属性）
UPDATE stage_enemies e JOIN (SELECT DISTINCT image, element FROM cards) c ON c.image = e.image SET e.element = c.element;
