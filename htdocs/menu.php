<?php
// メニュー（ユーザー作成・各機能へのリンク）
require_once __DIR__ . '/forge_common.php';

$message = '';
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $userName = trim((string)($_POST['user_name'] ?? ''));
    if ($userName === '' || mb_strlen($userName) > 50) {
        $message = 'ユーザー名は1〜50文字で入力してください。';
        $messageType = 'error';
    } else {
        db()->prepare('INSERT INTO users (name) VALUES (:name)')->execute([':name' => $userName]);
        $message = '新しいユーザー「' . $userName . '」を作成しました。';
    }
}

$user = current_user();
render_header('メニュー', 0, $user ? 'やりたいことを選んでください。' : 'まずはユーザーを選択してください。');

if ($message !== '') {
    render_notice($message, $messageType);
}
?>
<div class="menu-grid" style="margin-bottom:28px">
  <a class="menu-item" href="forge_entrance.php">
    <span class="en">Limit Break</span>
    <div class="t">カード強化（限界突破）</div>
    <div class="d">同じカードを2枚使って、上位のカードに強化します</div>
  </a>
  <a class="menu-item" href="select_user.php">
    <span class="en">Switch User</span>
    <div class="t">ユーザー選択</div>
    <div class="d">操作するユーザーを切り替えます</div>
  </a>
</div>

<div class="panel">
  <h2>ユーザーを作成</h2>
  <form action="menu.php" method="post" class="row">
    <input type="text" name="user_name" placeholder="新しいユーザー名" maxlength="50" required>
    <button type="submit" name="create_user" class="btn">作成</button>
  </form>
</div>
<?php render_footer(); ?>
