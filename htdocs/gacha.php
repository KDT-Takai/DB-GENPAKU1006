<?php
// ガチャ（単発・10連、天井、10連★4以上確定、提供割合、履歴）
require_once __DIR__ . '/common.php';

$user    = require_login();
$gachaId = 1;

$stmt = db()->prepare('SELECT * FROM gachas WHERE id = :id');
$stmt->execute([':id' => $gachaId]);
$gacha = $stmt->fetch();

// 排出対象カードと重み
$stmt = db()->prepare(
    'SELECT gi.card_id, gi.weight, c.name, c.rarity, c.limit_break, c.attack, c.hp
       FROM gacha_items gi
       JOIN cards c ON c.id = gi.card_id
      WHERE gi.gacha_id = :id
      ORDER BY c.rarity DESC, c.id'
);
$stmt->execute([':id' => $gachaId]);
$pool        = $stmt->fetchAll();
$totalWeight = array_sum(array_column($pool, 'weight'));

// 重み付き抽選
function pick_weighted(array $items): array
{
    $total = array_sum(array_column($items, 'weight'));
    $r = random_int(1, $total);
    foreach ($items as $item) {
        $r -= (int)$item['weight'];
        if ($r <= 0) {
            return $item;
        }
    }
    return end($items);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = (int)($_POST['count'] ?? 0) === 10 ? 10 : 1;
    $cost  = $gacha['cost'] * $count;
    $pdo   = db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT gems, pity_count FROM users WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $user['id']]);
        $me = $stmt->fetch();
        if ($me['gems'] < $cost) {
            throw new RuntimeException('石が足りません。（必要: ' . number_format($cost) . '）');
        }

        $stmt = $pdo->prepare('SELECT DISTINCT card_id FROM user_cards WHERE user_id = :id');
        $stmt->execute([':id' => $user['id']]);
        $owned = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

        $pool5   = array_values(array_filter($pool, fn($p) => $p['rarity'] == 5));
        $pool4up = array_values(array_filter($pool, fn($p) => $p['rarity'] >= 4));
        $pity    = (int)$me['pity_count'];
        $hasHigh = false;
        $results = [];

        $insCard = $pdo->prepare('INSERT INTO user_cards (user_id, card_id) VALUES (:user_id, :card_id)');
        $insHist = $pdo->prepare('INSERT INTO user_gacha_histories (user_id, gacha_id, card_id) VALUES (:user_id, :gacha_id, :card_id)');

        for ($i = 0; $i < $count; $i++) {
            $pity++;
            if ($pity >= $gacha['pity']) {
                $item = pick_weighted($pool5);                       // 天井：★5確定
            } elseif ($count === 10 && $i === 9 && !$hasHigh) {
                $item = pick_weighted($pool4up);                     // 10連の最後：★4以上確定
            } else {
                $item = pick_weighted($pool);
            }
            if ($item['rarity'] == 5) {
                $pity = 0;
            }
            if ($item['rarity'] >= 4) {
                $hasHigh = true;
            }

            $insCard->execute([':user_id' => $user['id'], ':card_id' => $item['card_id']]);
            $userCardId = (int)$pdo->lastInsertId();
            $insHist->execute([':user_id' => $user['id'], ':gacha_id' => $gachaId, ':card_id' => $item['card_id']]);

            $results[] = ['user_card_id' => $userCardId, 'new' => !isset($owned[$item['card_id']])];
            $owned[$item['card_id']] = true;
        }

        $pdo->prepare('UPDATE users SET gems = gems - :cost, pity_count = :pity WHERE id = :id')
            ->execute([':cost' => $cost, ':pity' => $pity, ':id' => $user['id']]);

        $pdo->commit();
        $_SESSION['gacha_result'] = $results;
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        flash($e->getMessage(), 'error');
    }
    redirect('gacha.php');
}

// 直前の結果（リロードで再抽選されないようにセッションから1回だけ表示）
$results = [];
if (!empty($_SESSION['gacha_result'])) {
    $ids = array_column($_SESSION['gacha_result'], 'user_card_id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT uc.id AS user_card_id, c.name, c.rarity, c.limit_break, c.attack, c.hp
           FROM user_cards uc JOIN cards c ON c.id = uc.card_id
          WHERE uc.id IN ($in)"
    );
    $stmt->execute($ids);
    $cards = array_column($stmt->fetchAll(), null, 'user_card_id');
    foreach ($_SESSION['gacha_result'] as $r) {
        if (isset($cards[$r['user_card_id']])) {
            $results[] = $cards[$r['user_card_id']] + ['new' => $r['new']];
        }
    }
    unset($_SESSION['gacha_result']);
}

$stmt = db()->prepare('SELECT gems, pity_count FROM users WHERE id = :id');
$stmt->execute([':id' => $user['id']]);
$me = $stmt->fetch();

$stmt = db()->prepare(
    'SELECT h.created_at, c.name, c.rarity
       FROM user_gacha_histories h JOIN cards c ON c.id = h.card_id
      WHERE h.user_id = :id AND h.gacha_id = :gacha_id
      ORDER BY h.id DESC LIMIT 20'
);
$stmt->execute([':id' => $user['id'], ':gacha_id' => $gachaId]);
$histories = $stmt->fetchAll();

$pickups = array_filter($pool, fn($p) => $p['rarity'] == 5);
$untilPity = $gacha['pity'] - $me['pity_count'];

render_header('ガチャ');
render_flash();
?>
<?php if ($results): ?>
<section class="panel gacha-result">
  <p class="celebrate"><span class="en">Warp Result</span><span class="ja"><?= count($results) ?>回 ワープの結果</span></p>
  <div class="grid">
<?php foreach ($results as $c): ?>
    <?php render_card($c, ['badges' => $c['new'] ? [['NEW', 'new']] : []]); ?>
<?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="banner">
  <span class="en">Event Warp</span>
  <h2><?= h($gacha['name']) ?></h2>
  <p><?= h($gacha['description']) ?></p>
  <div class="pickup">
<?php foreach ($pickups as $p): ?>
    <span>★5 <?= h($p['name']) ?></span>
<?php endforeach; ?>
  </div>
  <div class="pity">
    <span>★5確定まであと <b style="color:var(--gold)"><?= h($untilPity) ?></b> 回</span>
    <div class="pity-bar"><i style="width: <?= round($me['pity_count'] / $gacha['pity'] * 100) ?>%"></i></div>
  </div>
</section>

<div class="pull-row" style="margin-bottom: 32px">
<?php foreach ([1, 10] as $n): $cost = $gacha['cost'] * $n; ?>
  <form method="post" action="gacha.php">
    <input type="hidden" name="count" value="<?= $n ?>">
    <button type="submit" class="btn" <?= $me['gems'] < $cost ? 'disabled' : '' ?>>
      <?= $n ?>回ワープ<small><i class="gem-icon"></i><?= number_format($cost) ?></small>
    </button>
  </form>
<?php endforeach; ?>
  <span class="muted">10回ワープは★4以上が1枚以上確定</span>
</div>

<section class="panel">
  <h2>提供割合</h2>
  <div class="table-wrap">
  <table class="list">
    <tr><th>レア度</th><th>カード</th><th style="text-align:right">確率</th></tr>
<?php foreach ($pool as $p): ?>
    <tr>
      <td class="r-text-<?= (int)$p['rarity'] ?>"><?= stars((int)$p['rarity']) ?></td>
      <td><?= h($p['name']) ?></td>
      <td class="num"><?= number_format($p['weight'] / $totalWeight * 100, 2) ?>%</td>
    </tr>
<?php endforeach; ?>
  </table>
  </div>
</section>

<section class="panel">
  <h2>ガチャ履歴（直近20件）</h2>
<?php if (!$histories): ?>
  <p class="muted">まだガチャを引いていません。</p>
<?php else: ?>
  <div class="table-wrap">
  <table class="list">
    <tr><th>日時</th><th>レア度</th><th>カード</th></tr>
<?php foreach ($histories as $hst): ?>
    <tr>
      <td class="muted"><?= h($hst['created_at']) ?></td>
      <td class="r-text-<?= (int)$hst['rarity'] ?>"><?= stars((int)$hst['rarity']) ?></td>
      <td><?= h($hst['name']) ?></td>
    </tr>
<?php endforeach; ?>
  </table>
  </div>
<?php endif; ?>
</section>
<?php render_footer(); ?>
