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

$users = db()->query('SELECT id, name FROM users ORDER BY id')->fetchAll();

render_header('ユーザー選択');
?>
<?php if ($message !== ''): ?>
<p class="message"><?= h($message) ?></p>
<?php endif; ?>
<div class="box">
<?php if (!$users): ?>
  <p>ユーザーが登録されていません。<a href="menu.php">メニュー</a>から作成してください。</p>
<?php else: ?>
  <table>
    <tr><th class="num">ユーザーID</th><th>ユーザー名</th><th>操作</th></tr>
<?php foreach ($users as $u): ?>
    <tr>
      <td class="num"><?= h($u['id']) ?></td>
      <td><?= h($u['name']) ?></td>
      <td>
        <form action="select_user.php" method="post">
          <input type="hidden" name="user_id" value="<?= h($u['id']) ?>">
          <button type="submit" name="select_user">このユーザーを使用する</button>
        </form>
      </td>
    </tr>
<?php endforeach; ?>
  </table>
<?php endif; ?>
</div>
<p><a href="menu.php">メニューに戻る</a></p>
<?php render_footer(); ?>
