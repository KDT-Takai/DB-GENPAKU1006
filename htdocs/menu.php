<?php
// ホーム（ログインボーナス・各機能へのリンク・ユーザー作成）
require_once __DIR__ . '/common.php';

const LOGIN_BONUS_GEMS = 100;

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $userName = trim((string)($_POST['user_name'] ?? ''));
    if ($userName === '' || mb_strlen($userName) > 50) {
        flash('ユーザー名は1〜50文字で入力してください。', 'error');
    } else {
        db()->prepare('INSERT INTO users (name) VALUES (:name)')->execute([':name' => $userName]);
        flash('新しいユーザー「' . $userName . '」を作成しました。ユーザー選択から切り替えられます。');
    }
    redirect('menu.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_bonus']) && $user) {
    $pdo = db();
    $pdo->beginTransaction();
    // 主キー(user_id, login_date)が重複したら今日はもう受け取り済み
    $stmt = $pdo->prepare('INSERT IGNORE INTO user_login_bonuses (user_id, login_date) VALUES (:id, CURDATE())');
    $stmt->execute([':id' => $user['id']]);
    if ($stmt->rowCount() === 1) {
        $pdo->prepare('UPDATE users SET gems = gems + :gems WHERE id = :id')
            ->execute([':gems' => LOGIN_BONUS_GEMS, ':id' => $user['id']]);
        flash('ログインボーナスで石 ' . LOGIN_BONUS_GEMS . ' を受け取りました！');
    } else {
        flash('今日のログインボーナスは受け取り済みです。', 'error');
    }
    $pdo->commit();
    redirect('menu.php');
}

$info = null;
if ($user) {
    $stmt = db()->prepare(
        'SELECT
            (SELECT COUNT(*) FROM user_cards WHERE user_id = :id1)                                     AS cards,
            (SELECT COUNT(*) FROM user_login_bonuses WHERE user_id = :id2)                             AS login_days,
            (SELECT COUNT(*) FROM user_login_bonuses WHERE user_id = :id3 AND login_date = CURDATE())  AS claimed,
            (SELECT COUNT(*) FROM battle_histories WHERE user_id = :id4 AND result = \'win\')           AS wins'
    );
    $stmt->execute([':id1' => $user['id'], ':id2' => $user['id'], ':id3' => $user['id'], ':id4' => $user['id']]);
    $info = $stmt->fetch();
}

$menus = [
    ['gacha.php',          'Warp',        'ガチャ',         '石を使ってカードを手に入れます'],
    ['cards.php',          'Collection',  '所持カード',     '持っているカードを確認します'],
    ['team.php',           'Team',        'チーム編成',     'バトルに出す4枚を選びます'],
    ['forge_entrance.php', 'Limit Break', '限界突破',       '同じカード2枚で上位カードに強化します'],
    ['battle.php',         'Battle',      'バトル',         '他のユーザーのチームと対戦します'],
    ['ranking.php',        'Ranking',     'ランキング',     '対戦の勝利数ランキングを見ます'],
];

render_header('ホーム', 0, $user ? 'ようこそ、' . $user['name'] . ' さん。' : 'まずはユーザーを選択してください。');
render_flash();
?>
<?php if ($user): ?>
<div class="stat-row">
  <div class="stat"><span class="en">Gems</span><span class="gems" style="border:0;background:none;padding:0"><i class="gem-icon"></i><b><?= number_format(user_gems($user['id'])) ?></b></span></div>
  <div class="stat"><span class="en">Cards</span><b><?= h($info['cards']) ?></b> 枚</div>
  <div class="stat"><span class="en">Wins</span><b><?= h($info['wins']) ?></b> 勝</div>
  <div class="stat"><span class="en">Login Days</span><b><?= h($info['login_days']) ?></b> 日</div>
</div>

<section class="panel" style="display:flex; align-items:center; gap:16px; flex-wrap:wrap">
  <div style="flex:1; min-width:200px">
    <span class="en" style="color:var(--gold); font-size:12px">Daily Login Bonus</span>
    <div style="font-weight:700; font-size:17px">ログインボーナス：石 <?= LOGIN_BONUS_GEMS ?></div>
    <div class="muted">1日1回受け取れます。</div>
  </div>
<?php if ($info['claimed']): ?>
  <span class="badge badge-max" style="font-size:13px; padding:4px 14px">本日受け取り済み</span>
<?php else: ?>
  <form method="post" action="menu.php" style="margin:0"><button type="submit" name="login_bonus" class="btn">受け取る</button></form>
<?php endif; ?>
</section>
<?php else: ?>
<p><a class="btn" href="select_user.php">ユーザーを選択する</a></p>
<?php endif; ?>

<div class="menu-grid" style="margin-bottom:28px">
<?php foreach ($menus as [$href, $en, $title, $desc]): ?>
  <a class="menu-item" href="<?= h($href) ?>">
    <span class="en"><?= h($en) ?></span>
    <div class="t"><?= h($title) ?></div>
    <div class="d"><?= h($desc) ?></div>
  </a>
<?php endforeach; ?>
</div>

<div class="panel">
  <h2>ユーザーを作成</h2>
  <p class="muted" style="margin-top:-6px">新しいユーザーは石 1,600 からスタートします（10連1回分）。</p>
  <form action="menu.php" method="post" class="row">
    <input type="text" name="user_name" placeholder="新しいユーザー名" maxlength="50" required>
    <button type="submit" name="create_user" class="btn">作成</button>
  </form>
</div>
<?php render_footer(); ?>
