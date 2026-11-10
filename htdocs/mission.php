<?php
// ミッション（デイリー：毎日0時にリセット／実績：1回だけ）
require_once __DIR__ . '/common.php';

$user = require_login();
$tab  = ($_GET['tab'] ?? 'daily') === 'achievements' ? 'achievements' : 'daily';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = mission_status($user['id']);
    $list   = $status['daily'] + $status['achievements'];
    // 受け取るミッション（all = 受け取れるものすべて）
    $keys = ($_POST['mission'] ?? '') === 'all' ? array_keys($list) : [(string)($_POST['mission'] ?? '')];
    $got  = [];
    $pdo  = db();
    $pdo->beginTransaction();
    foreach ($keys as $key) {
        $m = $list[$key] ?? null;
        if (!$m || $m['claimed'] || $m['now'] < $m['goal']) continue;
        $period = isset($status['daily'][$key]) ? date('Y-m-d') : 'once';
        // 主キーの重複で二重受け取りを防ぐ
        $stmt = $pdo->prepare('INSERT IGNORE INTO user_mission_claims (user_id, mission, period) VALUES (:u, :m, :p)');
        $stmt->execute([':u' => $user['id'], ':m' => $key, ':p' => $period]);
        if ($stmt->rowCount() === 1) {
            grant($user['id'], $m['item'], $m['amount']);
            $got[$m['item']] = ($got[$m['item']] ?? 0) + $m['amount'];
        }
    }
    $pdo->commit();
    if ($got) {
        $parts = [];
        foreach ($got as $item => $amount) $parts[] = PRESENT_ITEMS[$item] . ' ' . number_format($amount);
        flash('報酬を受け取りました：' . implode('、', $parts));
    } else {
        flash('受け取れる報酬がありません。', 'error');
    }
    redirect('mission.php?tab=' . $tab);
}

$status = mission_status($user['id']);
$list   = $status[$tab];
$readyIn = fn(array $ms) => count(array_filter($ms, fn($m) => !$m['claimed'] && $m['now'] >= $m['goal']));

function reward_html(string $item, int $amount): string
{
    $icon = ['gems' => '<i class="gem-icon"></i>', 'gold' => '<i class="gold-icon"></i>', 'stamina' => '<i class="stamina-icon"></i>'][$item];
    return '<span class="reward">' . $icon . number_format($amount) . '</span>';
}

render_header('ミッション', 0, 'デイリーは毎日0時にリセット。');
render_flash();
?>
<div class="tabs">
  <a href="mission.php?tab=daily" class="<?= $tab === 'daily' ? 'active' : '' ?>">デイリー<?= $readyIn($status['daily']) ? '（' . $readyIn($status['daily']) . '）' : '' ?></a>
  <a href="mission.php?tab=achievements" class="<?= $tab === 'achievements' ? 'active' : '' ?>">実績<?= $readyIn($status['achievements']) ? '（' . $readyIn($status['achievements']) . '）' : '' ?></a>
<?php if ($readyIn($list)): ?>
  <form method="post" action="mission.php?tab=<?= h($tab) ?>" style="margin:0 0 0 auto">
    <button type="submit" name="mission" value="all" class="btn">まとめて受け取る</button>
  </form>
<?php endif; ?>
</div>

<div class="mission-list">
<?php foreach ($list as $key => $m):
    $ready = !$m['claimed'] && $m['now'] >= $m['goal'];
    $done  = $m['now'] >= $m['goal']; ?>
  <div class="mission<?= $ready ? ' ready' : '' ?><?= $m['claimed'] ? ' claimed' : '' ?>">
    <div>
      <div class="t"><?= h($m['title']) ?></div>
      <div class="meter-bar<?= $done ? ' done' : '' ?>"><i style="width:<?= round($m['now'] / $m['goal'] * 100) ?>%"></i></div>
      <div class="p"><?= number_format($m['now']) ?> / <?= number_format($m['goal']) ?></div>
    </div>
    <?= reward_html($m['item'], $m['amount']) ?>
<?php if ($m['claimed']): ?>
    <span class="badge badge-max">受取済</span>
<?php elseif ($ready): ?>
    <form method="post" action="mission.php?tab=<?= h($tab) ?>"><button type="submit" name="mission" value="<?= h($key) ?>" class="btn">受け取る</button></form>
<?php else: ?>
    <span class="muted" style="font-size:13px">未達成</span>
<?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php render_footer(); ?>
