-- ==========================================================
-- 放置（オート周回）用 テーブル
-- initialize.sql の最後で読み込まれる。
-- 既存のDBに追加するだけなら（ユーザーデータは消えない・何度実行しても大丈夫）:
--   mysql -uroot -p < /root/sql/idle.sql
-- ==========================================================
SET NAMES utf8mb4;
USE forge_db;

-- ユーザーごとの放置状態
--   stage      : いま周回しているステージ（ボスに勝つと +1。高いほど放置報酬が増える）
--   claimed_at : 最後に放置報酬を受け取った時刻（ここからの経過時間で報酬が貯まる）
--   wins       : ステージボスに勝った回数（ランキング・統計用）
CREATE TABLE IF NOT EXISTS user_idle (
    user_id    INT      NOT NULL PRIMARY KEY,
    stage      INT      NOT NULL DEFAULT 1,
    claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    wins       INT      NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users (id)
);

-- 既存ユーザーは「2時間前から放置していた」状態で始める（すぐに受け取りを試せるように）
INSERT IGNORE INTO user_idle (user_id, stage, claimed_at)
SELECT id, 1, NOW() - INTERVAL 2 HOUR FROM users;
