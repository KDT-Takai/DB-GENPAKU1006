<?php
// メニュー（ユーザー作成・各機能へのリンク）
require_once __DIR__ . '/forge_common.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $userName = trim((string)($_POST['user_name'] ?? ''));
    if ($userName === '' || mb_strlen($userName) > 50) {
        $message = 'ユーザー名は1〜50文字で入力してください。';
    } else {
        db()->prepare('INSERT INTO users (name) VALUES (:name)')->execute([':name' => $userName]);
        $message = '新しいユーザー「' . $userName . '」を作成しました。';
    }
}

render_header('メニュー');
?>
<div class="box">
  <h2 style="margin-top:0; font-size:18px;">ユーザー作成</h2>
<?php if ($message !== ''): ?>
  <p class="message"><?= h($message) ?></p>
<?php endif; ?>
  <form action="menu.php" method="post">
    <input type="text" name="user_name" placeholder="新しいユーザー名" maxlength="50" required>
    <button type="submit" name="create_user">作成</button>
  </form>
</div>

<div class="box">
  <p style="margin-top:0;">実行したい操作を選択してください。</p>
  <ul class="menu">
    <li><a href="select_user.php">ユーザー選択</a></li>
    <li><a href="forge_entrance.php">カード強化（限界突破）</a></li>
  </ul>
</div>
<?php render_footer(); ?>
