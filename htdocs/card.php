<?php
// カード詳細（ステータス・属性・スキル・ゴールドでレベル強化）
require_once __DIR__ . '/common.php';

$user = require_login();
$id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

function load_card(int $userId, int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT ' . CARD_COLS . ', c.flavor, c.attack AS base_attack, c.hp AS base_hp, t.slot AS team_slot
           FROM user_cards uc
           JOIN cards c ON c.id = uc.card_id
           LEFT JOIN user_team_cards t ON t.user_card_id = uc.id
          WHERE uc.id = :id AND uc.user_id = :u'
    );
    $stmt->execute([':id' => $id, ':u' => $userId]);
    return $stmt->fetch() ?: null;
}

// レベルを $times 回上げたときの費用（上限で止める）
function levelup_plan(array $c, int $times): array
{
    $max   = max_level((int)$c['rarity'], (int)$c['limit_break']);
    $level = (int)$c['level'];
    $cost  = 0;
    $n     = 0;
    while ($n < $times && $level + $n < $max) {
        $cost += levelup_cost($level + $n, (int)$c['rarity']);
        $n++;
    }
    return [$n, $cost];
}

$card = $id ? load_card($user['id'], $id) : null;
if (!$card) {
    render_header('カード詳細');
    render_notice('カードが見つかりません。', 'error');
    echo '<p><a class="btn" href="cards.php">所持カードへ</a></p>';
    render_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $want = $_POST['times'] ?? '1';
    $pdo  = db();
    $pdo->beginTransaction();
    $card = load_card($user['id'], $id);
    $gold = (int)$pdo->query('SELECT gold FROM users WHERE id = ' . (int)$user['id'] . ' FOR UPDATE')->fetchColumn();
    if ($want === 'max') {
        // 持っているゴールドで上げられるところまで
        $n = 0; $cost = 0;
        [$cap] = levelup_plan($card, PHP_INT_MAX);
        for ($k = 1; $k <= $cap; $k++) {
            [, $c] = levelup_plan($card, $k);
            if ($c > $gold) break;
            [$n, $cost] = [$k, $c];
        }
    } else {
        [$n, $cost] = levelup_plan($card, max(1, min(10, (int)$want)));
    }
    if ($n === 0) {
        $pdo->rollBack();
        flash('これ以上レベルを上げられません。限界突破で上限が10上がります。', 'error');
    } elseif ($cost > $gold) {
        $pdo->rollBack();
        flash('ゴールドが足りません（必要 ' . number_format($cost) . '）。', 'error');
    } else {
        $pdo->prepare('UPDATE users SET gold = gold - :c WHERE id = :u')->execute([':c' => $cost, ':u' => $user['id']]);
        $pdo->prepare('UPDATE user_cards SET level = level + :n WHERE id = :id')->execute([':n' => $n, ':id' => $id]);
        count_up($user['id'], 'levelup');
        $pdo->commit();
        flash('「' . $card['name'] . '」が Lv.' . ((int)$card['level'] + $n) . ' になりました！（ゴールド -' . number_format($cost) . '）');
    }
    redirect('card.php?id=' . $id);
}

$status  = user_status($user['id']);
$max     = max_level((int)$card['rarity'], (int)$card['limit_break']);
[$n1, $c1]   = levelup_plan($card, 1);
[$n10, $c10] = levelup_plan($card, 10);
$isMax   = (int)$card['level'] >= $max;
[$skillName, $skillDesc] = SKILLS[$card['skill']] ?? ['-', ''];
[$elemName] = ELEMENTS[$card['element']] ?? ELEMENTS['none'];
$weakTo = ['fire' => '水', 'water' => '風', 'wind' => '火', 'light' => '闇', 'dark' => '光'][$card['element']] ?? 'なし';
$strongTo = ['fire' => '風', 'water' => '火', 'wind' => '水', 'light' => '闇', 'dark' => '光'][$card['element']] ?? 'なし';
// 次のレベルでの値
$next = fn(string $key) => (int)round((int)$card['base_' . $key] * (1 + LEVEL_RATE * (int)$card['level']));

render_header($card['name']);
render_flash();
?>
<div class="detail">
  <div class="card-wrap">
    <?php render_card($card, ['large' => true, 'badges' => $card['team_slot'] ? [['枠' . $card['team_slot'] . 'に編成中', 'team']] : []]); ?>
  </div>
  <div class="spec">
<?php if ($card['flavor'] !== ''): ?>
    <p class="flavor"><?= h($card['flavor']) ?></p>
<?php endif; ?>
    <dl>
      <dt>レベル</dt><dd><?= h($card['level']) ?> / <?= h($max) ?><?= $isMax ? ' <span class="badge badge-max">MAX</span>' : '' ?></dd>
      <dt>ATK</dt><dd><?= number_format($card['attack']) ?><?= $isMax ? '' : ' <span class="up" style="color:var(--ok)">→ ' . number_format($next('attack')) . '</span>' ?></dd>
      <dt>HP</dt><dd><?= number_format($card['hp']) ?><?= $isMax ? '' : ' <span class="up" style="color:var(--ok)">→ ' . number_format($next('hp')) . '</span>' ?></dd>
      <dt>属性</dt><dd><?= element_chip($card['element']) ?><?= h($elemName) ?>（<?= h($strongTo) ?>に強い・<?= h($weakTo) ?>に弱い）</dd>
      <dt>スキル</dt><dd><?= h($skillName) ?><div class="muted" style="font-weight:400; font-size:13px"><?= h($skillDesc) ?>（2・5・8ターン目に発動）</div></dd>
      <dt>限界突破</dt><dd><?= h($card['limit_break']) ?> / 2<?= (int)$card['limit_break'] < 2 ? '　<a href="forge_entrance.php" class="mini">限界突破へ</a>' : '' ?></dd>
    </dl>

    <section class="panel" style="padding:18px">
      <h2 style="margin-bottom:8px">レベル強化</h2>
      <p class="muted" style="margin:0 0 12px; font-size:13px">ゴールドを使ってレベルを上げます。1レベルごとに ATK・HP が基本値の <?= LEVEL_RATE * 100 ?>% ずつ上がります。所持ゴールド：<span class="coin" style="padding:1px 10px 1px 4px"><i class="gold-icon"></i><?= number_format($status['gold']) ?></span></p>
<?php if ($isMax): ?>
      <p class="muted" style="margin:0">レベルが上限です。限界突破すると上限が10上がります。</p>
<?php else: ?>
      <div class="lvup">
        <form method="post" action="card.php"><input type="hidden" name="id" value="<?= h($id) ?>"><button type="submit" name="times" value="1" class="btn" <?= $c1 > $status['gold'] ? 'disabled' : '' ?>>+1　<i class="gold-icon"></i><?= number_format($c1) ?></button></form>
        <form method="post" action="card.php"><input type="hidden" name="id" value="<?= h($id) ?>"><button type="submit" name="times" value="10" class="btn btn-ghost" <?= $c10 > $status['gold'] ? 'disabled' : '' ?>>+<?= $n10 ?>　<i class="gold-icon"></i><?= number_format($c10) ?></button></form>
        <form method="post" action="card.php"><input type="hidden" name="id" value="<?= h($id) ?>"><button type="submit" name="times" value="max" class="btn btn-ghost" <?= $c1 > $status['gold'] ? 'disabled' : '' ?>>上げられるだけ</button></form>
      </div>
<?php endif; ?>
    </section>
    <p><a class="mini" href="cards.php">← 所持カードへ</a>　<a class="mini" href="team.php">編成へ</a></p>
  </div>
</div>
<?php render_footer(); ?>
