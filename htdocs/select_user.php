<?php
// ユーザー選択（ログイン）・ログアウト
require_once __DIR__ . '/forge_common.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    $_SESSION = [];
    session_regenerate_id(true);
    $message = 'ログアウトしました。';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_user'])) {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $stmt = db()->prepare('SELECT id, name FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId ?: 0]);
    $selected = $stmt->fetch();

    if ($selected) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $selected['id'];
        $_SESSION['user_name'] = $selected['name'];
        header('Location: menu.php');
        exit;
    }
    $message = 'ユーザーの選択に失敗しました。';
}

// ユーザーごとの所持カード枚数も表示する
$users = db()->query(
    'SELECT u.id, u.name, COUNT(uc.id) AS card_num
       FROM users u
       LEFT JOIN user_cards uc ON uc.user_id = u.id
      GROUP BY u.id, u.name
      ORDER BY u.id'
)->fetchAll();

$me = current_user();
render_header('ユーザー選択', 0, '操作するユーザーを選んでください。');

if ($message !== '') {
    render_notice($message);
}
?>
<?php if (!$users): ?>
<?php render_notice('ユーザーが登録されていません。メニューから作成してください。'); ?>
<?php else: ?>
<div class="user-list">
<?php foreach ($users as $u): ?>
  <div class="user-item<?= $me && $me['id'] === (int)$u['id'] ? ' me' : '' ?>">
    <div class="avatar"><?= h(mb_substr($u['name'], 0, 1)) ?></div>
    <div class="info">
      <div class="name"><?= h($u['name']) ?></div>
      <div class="muted">ID: <?= h($u['id']) ?> ・ カード <?= h($u['card_num']) ?>枚</div>
    </div>
<?php if ($me && $me['id'] === (int)$u['id']): ?>
    <span class="badge badge-max">選択中</span>
<?php else: ?>
    <form action="select_user.php" method="post">
      <input type="hidden" name="user_id" value="<?= h($u['id']) ?>">
      <button type="submit" name="select_user" class="btn">選択</button>
    </form>
<?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<p style="margin-top:24px"><a class="btn btn-ghost" href="menu.php">メニューに戻る</a></p>
<?php render_footer(); ?>
