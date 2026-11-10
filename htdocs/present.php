<?php
// プレゼントボックス（ランク報酬・スタートダッシュ支援などを受け取る）
require_once __DIR__ . '/common.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = $_POST['present_id'] ?? '';
    $pdo = db();
    $pdo->beginTransaction();
    $sql = 'SELECT id, item, amount FROM user_presents WHERE user_id = :u AND received_at IS NULL'
         . ($id === 'all' ? '' : ' AND id = :id') . ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($id === 'all' ? [':u' => $user['id']] : [':u' => $user['id'], ':id' => (int)$id]);
    $got = [];
    foreach ($stmt->fetchAll() as $p) {
        grant($user['id'], $p['item'], (int)$p['amount']);
        $pdo->prepare('UPDATE user_presents SET received_at = NOW() WHERE id = :id')->execute([':id' => $p['id']]);
        $got[$p['item']] = ($got[$p['item']] ?? 0) + (int)$p['amount'];
    }
    $pdo->commit();
    if ($got) {
        $parts = [];
        foreach ($got as $item => $amount) $parts[] = PRESENT_ITEMS[$item] . ' ' . number_format($amount);
        flash('受け取りました：' . implode('、', $parts));
    } else {
        flash('受け取れるプレゼントがありません。', 'error');
    }
    redirect('present.php');
}

$stmt = db()->prepare('SELECT * FROM user_presents WHERE user_id = :u AND received_at IS NULL ORDER BY id DESC');
$stmt->execute([':u' => $user['id']]);
$open = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM user_presents WHERE user_id = :u AND received_at IS NOT NULL ORDER BY received_at DESC, id DESC LIMIT 20');
$stmt->execute([':u' => $user['id']]);
$done = $stmt->fetchAll();

$icon = ['gems' => '<i class="gem-icon"></i>', 'gold' => '<i class="gold-icon"></i>', 'stamina' => '<i class="stamina-icon"></i>'];

render_header('プレゼント', 0, '');
render_flash();
?>
<?php if (!$open): ?>
<?php render_notice('未受け取りのプレゼントはありません。'); ?>
<?php else: ?>
<form method="post" action="present.php" style="margin-bottom:14px">
  <button type="submit" name="present_id" value="all" class="btn">すべて受け取る（<?= count($open) ?>件）</button>
</form>
<div class="present-list" style="margin-bottom:28px">
<?php foreach ($open as $p): ?>
  <div class="present">
    <span class="icon"><?= $icon[$p['item']] ?></span>
    <div>
      <div><b><?= h(PRESENT_ITEMS[$p['item']]) ?> ×<?= number_format($p['amount']) ?></b></div>
      <div class="msg"><?= h($p['message']) ?> ・ <?= h(date('n/j H:i', strtotime($p['created_at']))) ?></div>
    </div>
    <form method="post" action="present.php"><button type="submit" name="present_id" value="<?= h($p['id']) ?>" class="mini">受け取る</button></form>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($done): ?>
<section class="panel">
  <h2>受け取り履歴（直近20件）</h2>
  <div class="table-wrap">
  <table class="list">
    <tr><th>受け取り日時</th><th>内容</th><th style="text-align:right">数</th></tr>
<?php foreach ($done as $p): ?>
    <tr><td class="muted"><?= h($p['received_at']) ?></td><td><?= h($p['message']) ?></td><td class="num"><?= h(PRESENT_ITEMS[$p['item']]) ?> ×<?= number_format($p['amount']) ?></td></tr>
<?php endforeach; ?>
  </table>
  </div>
</section>
<?php endif; ?>
<?php render_footer(); ?>
