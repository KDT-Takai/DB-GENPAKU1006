<?php
// 装備（カードに武器・防具・アクセサリーを付けてATK・HPを上げる。ゴールドで強化、いらない装備は売却）
require_once __DIR__ . '/common.php';

$user = require_login();
$pdo  = db();

// 自分の装備（$id を指定するとその1つだけ）
function my_equipments(int $userId, ?int $id = null): array
{
    $stmt = db()->prepare(
        'SELECT ue.id, ue.level, ue.user_card_id, e.name, e.slot, e.rarity, e.attack AS base_attack, e.hp AS base_hp, c.name AS card_name
           FROM user_equipments ue
           JOIN equipments e ON e.id = ue.equipment_id
           LEFT JOIN user_cards uc ON uc.id = ue.user_card_id
           LEFT JOIN cards c ON c.id = uc.card_id
          WHERE ue.user_id = :u' . ($id !== null ? ' AND ue.id = :id' : '') . '
          ORDER BY e.rarity DESC, ue.level DESC, e.slot, ue.id'
    );
    $stmt->execute([':u' => $userId] + ($id !== null ? [':id' => $id] : []));
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['attack'] = equip_stat((int)$r['base_attack'], (int)$r['level']);
        $r['hp']     = equip_stat((int)$r['base_hp'], (int)$r['level']);
        $r['score']  = $r['attack'] + intdiv($r['hp'], 4);   // 戦闘力への寄与（team_power と同じ式）
    }
    return $rows;
}

function owns_card(int $userId, int $cardId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM user_cards WHERE id = :c AND user_id = :u');
    $stmt->execute([':c' => $cardId, ':u' => $userId]);
    return (bool)$stmt->fetchColumn();
}

$cardId = filter_input(INPUT_GET, 'card', FILTER_VALIDATE_INT) ?: null;
$slotFilter = array_key_exists((string)($_GET['slot'] ?? ''), EQUIP_SLOTS) ? (string)$_GET['slot'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT) ?: 0;
    $cardId = filter_input(INPUT_POST, 'card_id', FILTER_VALIDATE_INT) ?: $cardId;
    $pdo->beginTransaction();
    $item = $itemId ? (my_equipments($user['id'], $itemId)[0] ?? null) : null;

    if (in_array($action, ['equip', 'unequip', 'upgrade', 'sell'], true) && !$item) {
        flash('その装備は持っていません。', 'error');
    } elseif ($action === 'equip') {
        if (!$cardId || !owns_card($user['id'], $cardId)) {
            flash('装備するカードを選んでください。', 'error');
        } else {
            // 同じ部位に付いている装備は外してから付ける
            $pdo->prepare('UPDATE user_equipments ue JOIN equipments e ON e.id = ue.equipment_id
                              SET ue.user_card_id = NULL WHERE ue.user_card_id = :c AND e.slot = :s')
                ->execute([':c' => $cardId, ':s' => $item['slot']]);
            $pdo->prepare('UPDATE user_equipments SET user_card_id = :c WHERE id = :id')->execute([':c' => $cardId, ':id' => $itemId]);
            flash($item['name'] . ' を装備しました。' . ($item['card_name'] ? '（' . $item['card_name'] . ' から付け替え）' : ''));
        }
    } elseif ($action === 'unequip') {
        $pdo->prepare('UPDATE user_equipments SET user_card_id = NULL WHERE id = :id')->execute([':id' => $itemId]);
        flash($item['name'] . ' を外しました。');
    } elseif ($action === 'upgrade') {
        $lv   = (int)$item['level'];
        $cost = equip_up_cost($lv, (int)$item['rarity']);
        if ($lv >= equip_max_level((int)$item['rarity'])) {
            flash('これ以上強化できません（最大 Lv.' . equip_max_level((int)$item['rarity']) . '）。', 'error');
        } else {
            $stmt = $pdo->prepare('UPDATE users SET gold = gold - :c WHERE id = :id AND gold >= :c2');
            $stmt->execute([':c' => $cost, ':c2' => $cost, ':id' => $user['id']]);
            if ($stmt->rowCount() === 1) {
                $pdo->prepare('UPDATE user_equipments SET level = level + 1 WHERE id = :id')->execute([':id' => $itemId]);
                count_up($user['id'], 'levelup');
                flash($item['name'] . ' を Lv.' . ($lv + 1) . ' に強化しました（ゴールド ' . number_format($cost) . '）。');
            } else {
                flash('ゴールドが足りません（必要 ' . number_format($cost) . '）。放置報酬で貯めよう。', 'error');
            }
        }
    } elseif ($action === 'sell') {
        if ($item['user_card_id']) {
            flash('装備中の装備は売却できません。先に外してください。', 'error');
        } else {
            $price = equip_sell_price((int)$item['level'], (int)$item['rarity']);
            $pdo->prepare('DELETE FROM user_equipments WHERE id = :id')->execute([':id' => $itemId]);
            $pdo->prepare('UPDATE users SET gold = gold + :p WHERE id = :id')->execute([':p' => $price, ':id' => $user['id']]);
            flash($item['name'] . ' を売却しました（ゴールド +' . number_format($price) . '）。');
        }
    } elseif ($action === 'sell_n') {
        // 装備していない N の装備をまとめて売る
        $sold = 0;
        $price = 0;
        foreach (my_equipments($user['id']) as $e) {
            if ((int)$e['rarity'] === 1 && !$e['user_card_id']) {
                $pdo->prepare('DELETE FROM user_equipments WHERE id = :id')->execute([':id' => $e['id']]);
                $price += equip_sell_price((int)$e['level'], 1);
                $sold++;
            }
        }
        $pdo->prepare('UPDATE users SET gold = gold + :p WHERE id = :id')->execute([':p' => $price, ':id' => $user['id']]);
        flash($sold ? '未装備の N 装備を ' . $sold . ' 個売却しました（ゴールド +' . number_format($price) . '）。' : '売却できる N 装備はありません。', $sold ? 'ok' : 'error');
    } elseif ($action === 'auto') {
        // おまかせ：チームのカードに、強い装備から順に配る（チーム外のカードが付けている装備はそのまま）
        $team = team_cards($user['id']);
        if (!$team) {
            flash('チームが編成されていません。', 'error');
        } else {
            $teamIds = array_map('intval', array_column($team, 'user_card_id'));
            $in = implode(',', $teamIds);
            $pdo->exec("UPDATE user_equipments SET user_card_id = NULL WHERE user_card_id IN ($in)");
            $free = array_filter(my_equipments($user['id']), fn($e) => !$e['user_card_id']);
            $changed = 0;
            foreach (array_keys(EQUIP_SLOTS) as $slot) {
                $list = array_values(array_filter($free, fn($e) => $e['slot'] === $slot));
                usort($list, fn($a, $b) => $b['score'] <=> $a['score']);
                foreach ($teamIds as $i => $cid) {
                    if (!isset($list[$i])) break;
                    $pdo->prepare('UPDATE user_equipments SET user_card_id = :c WHERE id = :id')->execute([':c' => $cid, ':id' => $list[$i]['id']]);
                    $changed++;
                }
            }
            flash('チームのカードに装備を ' . $changed . ' 個おまかせで付けました。');
        }
    }
    $pdo->commit();
    redirect('equip.php?' . http_build_query(array_filter(['card' => $cardId, 'slot' => $slotFilter])));
}

// カード一覧（チームのカードを先に）
$stmt = $pdo->prepare(
    'SELECT ' . CARD_COLS . ', t.slot AS team_slot
       FROM user_cards uc
       JOIN cards c ON c.id = uc.card_id
       LEFT JOIN user_team_cards t ON t.user_card_id = uc.id
      WHERE uc.user_id = :u
      ORDER BY t.slot IS NULL, t.slot, c.rarity DESC, uc.level DESC, uc.id'
);
$stmt->execute([':u' => $user['id']]);
$cards = $stmt->fetchAll();
$cardById = array_column($cards, null, 'user_card_id');
if (!$cardId || !isset($cardById[$cardId])) {
    $cardId = $cards ? (int)$cards[0]['user_card_id'] : null;
}
$card = $cardId ? $cardById[$cardId] : null;

$items = my_equipments($user['id']);
$worn = [];   // 選択中のカードが付けている装備（部位 => 装備）
foreach ($items as $e) {
    if ($card && (int)$e['user_card_id'] === $cardId) $worn[$e['slot']] = $e;
}
$shown = array_values(array_filter($items, fn($e) => $slotFilter === '' || $e['slot'] === $slotFilter));
$gold  = (int)user_status($user['id'])['gold'];
$nCount = count(array_filter($items, fn($e) => (int)$e['rarity'] === 1 && !$e['user_card_id']));

function equip_icon(array $e): string
{
    [$label, $color] = EQUIP_RARITY[(int)$e['rarity']];
    return '<span class="eq-icon r' . (int)$e['rarity'] . '" style="--rc:' . h($color) . '">' . EQUIP_SLOTS[$e['slot']][1] . '<i>' . h($label) . '</i></span>';
}

function equip_stats(array $e): string
{
    $s = [];
    if ($e['attack']) $s[] = 'ATK +' . number_format($e['attack']);
    if ($e['hp'])     $s[] = 'HP +' . number_format($e['hp']);
    return implode(' / ', $s);
}

render_header('装備', 0, '装備でカードを強くしよう。装備は放置報酬とボス討伐で手に入ります。');
render_flash();
?>
<style>
  .eq-cards { display: flex; gap: 8px; overflow-x: auto; padding: 4px 2px 10px; }
  .eq-cards a { flex: none; position: relative; width: 66px; text-align: center; text-decoration: none; font-size: 11px; font-weight: 800; line-height: 1.3; }
  .eq-cards img { width: 62px; height: 62px; object-fit: contain; background: var(--paper-2); border: 3px solid var(--ink); border-radius: 12px; }
  .eq-cards a.on img { background: var(--sun); box-shadow: var(--shadow-sm); transform: translateY(-2px); }
  .eq-cards .ts { position: absolute; top: -4px; left: -2px; background: var(--tomato); color: #fff; border: 2px solid var(--ink); border-radius: 999px;
                  font-size: 10px; padding: 0 5px; }
  .eq-cards .nm { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .eq-main { display: grid; grid-template-columns: 170px 1fr; gap: 18px; align-items: start; }
  .eq-main .portrait img { width: 100%; aspect-ratio: 1; object-fit: contain; background: var(--paper-2); border: 3px solid var(--ink); border-radius: 16px; box-shadow: var(--shadow); }
  .eq-slots { display: grid; gap: 10px; }
  .eq-slot { display: flex; align-items: center; gap: 12px; border: 3px solid var(--ink); border-radius: 14px; background: var(--paper-2); padding: 8px 12px; }
  .eq-slot.empty { border-style: dashed; background: transparent; color: var(--ink-soft); }
  .eq-slot .what { flex: 1; min-width: 0; }
  .eq-slot form, .eq-item form { margin: 0; }
  .eq-icon { position: relative; flex: none; display: inline-grid; place-items: center; width: 46px; height: 46px; font-size: 24px;
             background: var(--rc); border: 3px solid var(--ink); border-radius: 12px; box-shadow: var(--shadow-sm); }
  .eq-icon i { position: absolute; bottom: -8px; right: -8px; font-style: normal; font-size: 10px; font-weight: 800; background: var(--ink); color: #fff;
               padding: 0 5px; border-radius: 999px; }
  .eq-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
  .eq-tabs a { text-decoration: none; padding: 3px 12px; border: 2px solid var(--ink); border-radius: 999px; font-weight: 800; font-size: 13px; background: var(--paper-2); }
  .eq-tabs a.on { background: var(--ink); color: var(--paper); }
  .eq-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px; }
  .eq-item { display: grid; grid-template-columns: auto 1fr; gap: 6px 12px; border: 3px solid var(--ink); border-radius: 14px; background: var(--paper-2); padding: 10px 12px; }
  .eq-item.worn { background: #fff4cf; }
  .eq-item .btns { grid-column: 1 / -1; display: flex; gap: 6px; flex-wrap: wrap; }
  .eq-item .btns .btn { padding: 4px 12px; font-size: 13px; }
  .eq-name { font-weight: 800; }
  .eq-total { display: flex; gap: 16px; flex-wrap: wrap; margin: 4px 0 12px; font-weight: 800; }
  .eq-total b { font-family: var(--display); font-weight: 400; font-size: 22px; }
  @media (max-width: 560px) { .eq-main { grid-template-columns: 1fr; } .eq-main .portrait { max-width: 160px; } }

  /* ---- 作り込み：レア度で光る枠・ホバーで浮く・付け替えたときの増減 ---- */
  .eq-main .portrait { position: relative; isolation: isolate; }
  .eq-main .portrait::before { content: ""; position: absolute; inset: -8%; z-index: -1; border-radius: 50%;
                               background: repeating-conic-gradient(rgba(255,194,46,.55) 0 10deg, transparent 10deg 20deg); animation: heroSpin 18s linear infinite; }
  @keyframes heroSpin { to { transform: rotate(360deg); } }
  .eq-main .portrait img { animation: bob 2.6s ease-in-out infinite; }
  .eq-icon { overflow: visible; background: linear-gradient(160deg, rgba(255,255,255,.55), rgba(255,255,255,0) 55%), var(--rc); }
  .eq-icon.r3 { box-shadow: var(--shadow-sm), 0 0 0 3px rgba(139,103,255,.35), 0 0 12px rgba(139,103,255,.6); }
  .eq-icon.r4 { box-shadow: var(--shadow-sm), 0 0 0 3px rgba(255,194,46,.45), 0 0 16px rgba(255,194,46,.85); animation: ssrGlow 2s ease-in-out infinite; }
  .eq-icon.r4::after { content: ""; position: absolute; inset: 0; border-radius: 9px; background: var(--shine); background-size: 250% 100%; animation: ssrShine 2.4s linear infinite; }
  @keyframes ssrGlow { 50% { box-shadow: var(--shadow-sm), 0 0 0 4px rgba(255,194,46,.6), 0 0 24px rgba(255,194,46,1); } }
  @keyframes ssrShine { from { background-position: 150% 0; } to { background-position: -150% 0; } }
  .eq-item { position: relative; box-shadow: var(--shadow-sm); transition: transform .15s, box-shadow .15s; }
  .eq-item:hover { transform: translate(-1px, -3px); box-shadow: 4px 6px 0 var(--ink); }
  .eq-item.r4 { background: linear-gradient(135deg, #fff8dc, #fff 60%); }
  .eq-item.r3 { background: linear-gradient(135deg, #f1ebff, #fff 60%); }
  .eq-item.worn::after { content: "装備中"; position: absolute; top: -10px; right: 10px; font-size: 11px; font-weight: 800; padding: 0 8px; color: #fff; background: var(--mint);
                         border: 2px solid var(--ink); border-radius: 999px; transform: rotate(3deg); }
  .eq-delta { display: inline-block; margin-top: 2px; font-size: 12px; font-weight: 800; }
  .eq-delta .up { color: var(--ok); } .eq-delta .down { color: var(--danger); }
  .eq-slot { transition: transform .15s; }
  .eq-slot:not(.empty) { box-shadow: var(--shadow-sm); }
  .eq-slot.empty:hover { background: #fff3c4; transform: translateX(3px); }
  .eq-total b { color: var(--tomato); -webkit-text-stroke: 1px var(--ink); }
</style>

<?php if (!$cards): ?>
<?php render_notice('カードを持っていません。ガチャでカードを手に入れよう。', 'error'); ?>
<?php else: ?>
<section class="panel">
  <div class="actions" style="justify-content:space-between; align-items:center; margin:0 0 8px">
    <h2 style="margin:0">カードを選ぶ</h2>
    <form method="post" action="equip.php" style="margin:0"><input type="hidden" name="card_id" value="<?= h($cardId) ?>">
      <button type="submit" name="action" value="auto" class="btn">チームにおまかせ装備</button></form>
  </div>
  <div class="eq-cards">
<?php foreach ($cards as $c): ?>
    <a href="equip.php?<?= h(http_build_query(array_filter(['card' => $c['user_card_id'], 'slot' => $slotFilter]))) ?>" class="<?= (int)$c['user_card_id'] === $cardId ? 'on' : '' ?>">
      <?php if ($c['team_slot']): ?><span class="ts">編成<?= h($c['team_slot']) ?></span><?php endif; ?>
      <img src="resource/cards/<?= h($c['image']) ?>" alt=""><span class="nm"><?= h($c['name']) ?></span><span class="muted">Lv.<?= h($c['level']) ?></span>
    </a>
<?php endforeach; ?>
  </div>

  <div class="eq-main">
    <div class="portrait"><img src="resource/cards/<?= h($card['image']) ?>" alt="<?= h($card['name']) ?>"></div>
    <div>
      <h2 style="margin:0"><?= element_chip($card['element']) ?><?= h($card['name']) ?> <small class="muted">Lv.<?= h($card['level']) ?> <?= stars((int)$card['rarity']) ?></small></h2>
      <div class="eq-total"><span>ATK <b><?= number_format($card['attack']) ?></b></span><span>HP <b><?= number_format($card['hp']) ?></b></span>
        <span class="muted" style="align-self:center">装備込みの値</span></div>
      <div class="eq-slots">
<?php foreach (EQUIP_SLOTS as $slot => [$label, $icon]): $e = $worn[$slot] ?? null; ?>
<?php if ($e): ?>
        <div class="eq-slot"><?= equip_icon($e) ?>
          <div class="what"><span class="muted" style="font-size:12px"><?= h($label) ?></span><br><span class="eq-name"><?= h($e['name']) ?> Lv.<?= h($e['level']) ?></span>
            <span class="muted"><?= h(equip_stats($e)) ?></span></div>
          <form method="post" action="equip.php"><input type="hidden" name="item_id" value="<?= h($e['id']) ?>"><input type="hidden" name="card_id" value="<?= h($cardId) ?>">
            <button type="submit" name="action" value="unequip" class="btn btn-ghost">外す</button></form>
        </div>
<?php else: ?>
        <a class="eq-slot empty" href="equip.php?<?= h(http_build_query(['card' => $cardId, 'slot' => $slot])) ?>#inventory" style="text-decoration:none">
          <span class="eq-icon" style="--rc:transparent; box-shadow:none; border-style:dashed"><?= $icon ?></span>
          <span class="what"><?= h($label) ?>：なし（下の一覧から選んで装備）</span></a>
<?php endif; ?>
<?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="panel" id="inventory">
  <div class="actions" style="justify-content:space-between; align-items:center; margin:0 0 8px">
    <h2 style="margin:0">所持装備 <small class="muted"><?= count($items) ?> 個 ・ ゴールド <?= number_format($gold) ?></small></h2>
<?php if ($nCount): ?>
    <form method="post" action="equip.php" style="margin:0" onsubmit="return confirm('未装備の N 装備 <?= $nCount ?> 個を売却しますか？')">
      <input type="hidden" name="card_id" value="<?= h($cardId) ?>">
      <button type="submit" name="action" value="sell_n" class="btn btn-ghost">未装備の N をまとめて売却（<?= $nCount ?>）</button></form>
<?php endif; ?>
  </div>
  <div class="eq-tabs">
    <a href="equip.php?<?= h(http_build_query(array_filter(['card' => $cardId]))) ?>#inventory" class="<?= $slotFilter === '' ? 'on' : '' ?>">すべて</a>
<?php foreach (EQUIP_SLOTS as $slot => [$label, $icon]): ?>
    <a href="equip.php?<?= h(http_build_query(array_filter(['card' => $cardId, 'slot' => $slot]))) ?>#inventory" class="<?= $slotFilter === $slot ? 'on' : '' ?>"><?= $icon ?> <?= h($label) ?></a>
<?php endforeach; ?>
  </div>
<?php if (!$shown): ?>
  <p class="muted">装備がありません。<a href="idle.php">放置</a>で報酬を受け取るか、ボスを倒すと手に入ります。</p>
<?php endif; ?>
  <div class="eq-list">
<?php foreach ($shown as $e):
    $r = (int)$e['rarity'];
    $lv = (int)$e['level'];
    $isMax = $lv >= equip_max_level($r);
    $mine = $card && (int)$e['user_card_id'] === $cardId; ?>
    <div class="eq-item r<?= $r ?><?= $e['user_card_id'] ? ' worn' : '' ?>">
      <?= equip_icon($e) ?>
      <div><span class="eq-name"><?= h($e['name']) ?></span> <span class="muted">Lv.<?= $lv ?> / <?= equip_max_level($r) ?></span><br>
        <span><?= h(equip_stats($e)) ?></span>
<?php if ($card && !$mine):
    // 選択中のカードの同じ部位と比べた増減
    $cur = $worn[$e['slot']] ?? ['attack' => 0, 'hp' => 0];
    $parts = [];
    foreach (['attack' => 'ATK', 'hp' => 'HP'] as $k => $label) {
        $d = $e[$k] - $cur[$k];
        if ($d !== 0) $parts[] = '<span class="' . ($d > 0 ? 'up' : 'down') . '">' . $label . ($d > 0 ? ' ▲' : ' ▼') . number_format(abs($d)) . '</span>';
    }
    if ($parts): ?>
        <br><span class="eq-delta"><?= implode(' ', $parts) ?></span>
<?php endif; endif; ?>
<?php if ($e['user_card_id']): ?>
        <br><span class="muted" style="font-size:12px">装備中：<?= h($e['card_name']) ?></span>
<?php endif; ?>
      </div>
      <div class="btns">
<?php if ($card && !$mine): ?>
        <form method="post" action="equip.php"><input type="hidden" name="item_id" value="<?= h($e['id']) ?>"><input type="hidden" name="card_id" value="<?= h($cardId) ?>">
          <button type="submit" name="action" value="equip" class="btn"><?= h($card['name']) ?>に装備</button></form>
<?php endif; ?>
        <form method="post" action="equip.php"><input type="hidden" name="item_id" value="<?= h($e['id']) ?>"><input type="hidden" name="card_id" value="<?= h($cardId) ?>">
          <button type="submit" name="action" value="upgrade" class="btn btn-ghost" <?= $isMax || $gold < equip_up_cost($lv, $r) ? 'disabled' : '' ?>>
            <?= $isMax ? '最大レベル' : '強化 <small style="opacity:.75">ゴールド' . number_format(equip_up_cost($lv, $r)) . '</small>' ?></button></form>
<?php if (!$e['user_card_id']): ?>
        <form method="post" action="equip.php" onsubmit="return confirm('<?= h($e['name']) ?> を売却しますか？')"><input type="hidden" name="item_id" value="<?= h($e['id']) ?>"><input type="hidden" name="card_id" value="<?= h($cardId) ?>">
          <button type="submit" name="action" value="sell" class="btn btn-ghost">売却 <small style="opacity:.75">+<?= number_format(equip_sell_price($lv, $r)) ?></small></button></form>
<?php endif; ?>
      </div>
    </div>
<?php endforeach; ?>
  </div>
</section>
<?php render_footer(); ?>
