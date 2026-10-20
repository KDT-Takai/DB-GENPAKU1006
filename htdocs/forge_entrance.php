<?php
// 画面① 強化対象カード選択
// 同じcard_idを2枚以上所持しているユーザーカードのみ表示する
require_once __DIR__ . '/forge_common.php';

$sql = <<<SQL
SELECT
    uc.id                       AS user_card_id,
    uc.card_id,
    c.name,
    c.rarity,
    c.limit_break,
    c.attack,
    c.hp,
    c.next_card_id,
    nc.name                     AS next_name,
    (t.user_card_id IS NOT NULL) AS in_team,
    dup.num                     AS same_num,
    -- 自分以外 かつ チーム編成されていない同一カード = 素材にできる枚数
    (SELECT COUNT(*)
       FROM user_cards m
       LEFT JOIN user_team_cards mt ON mt.user_card_id = m.id
      WHERE m.user_id = uc.user_id
        AND m.card_id = uc.card_id
        AND m.id <> uc.id
        AND mt.user_card_id IS NULL) AS material_num
FROM user_cards uc
JOIN cards c ON c.id = uc.card_id
LEFT JOIN cards nc ON nc.id = c.next_card_id
LEFT JOIN user_team_cards t ON t.user_card_id = uc.id
JOIN (
    SELECT card_id, COUNT(*) AS num
      FROM user_cards
     WHERE user_id = :user_id_sub
     GROUP BY card_id
    HAVING COUNT(*) >= 2
) dup ON dup.card_id = uc.card_id
WHERE uc.user_id = :user_id
ORDER BY uc.card_id, uc.id
SQL;

$stmt = db()->prepare($sql);
$stmt->execute([':user_id_sub' => FORGE_USER_ID, ':user_id' => FORGE_USER_ID]);
$rows = $stmt->fetchAll();

render_header('強化対象カードを選択', 1);
?>
<div class="box">
<?php if (!$rows): ?>
  <p>同じカードを2枚以上所持していないため、限界突破できるカードがありません。</p>
<?php else: ?>
  <table>
    <tr>
      <th class="num">所持ID</th><th>カード名</th><th>レア</th><th class="num">突破</th>
      <th class="num">攻撃</th><th class="num">HP</th><th class="num">所持数</th><th>突破後</th><th>状態</th>
    </tr>
<?php foreach ($rows as $r): ?>
<?php
    $canForge = $r['next_card_id'] !== null && $r['material_num'] > 0;
    if ($r['next_card_id'] === null) {
        $reason = '最大まで突破済み';
    } elseif ($r['material_num'] == 0) {
        $reason = '素材にできるカードがない（編成中）';
    } else {
        $reason = '';
    }
?>
    <tr>
      <td class="num"><?= h($r['user_card_id']) ?></td>
      <td>
<?php if ($canForge): ?>
        <a href="forge_material.php?target_id=<?= h($r['user_card_id']) ?>"><?= h($r['name']) ?></a>
<?php else: ?>
        <?= h($r['name']) ?>
<?php endif; ?>
      </td>
      <td class="rarity"><?= stars((int)$r['rarity']) ?></td>
      <td class="num"><?= h($r['limit_break']) ?></td>
      <td class="num"><?= h($r['attack']) ?></td>
      <td class="num"><?= h($r['hp']) ?></td>
      <td class="num"><?= h($r['same_num']) ?></td>
      <td><?= $r['next_name'] !== null ? h($r['next_name']) : '-' ?></td>
      <td>
        <?php if ($r['in_team']): ?><span class="tag">編成中</span><?php endif; ?>
        <?php if ($reason !== ''): ?><span class="muted"><?= h($reason) ?></span><?php endif; ?>
      </td>
    </tr>
<?php endforeach; ?>
  </table>
<?php endif; ?>
</div>
<p class="muted">カード名をクリックすると素材選択へ進みます。編成中のカードは強化対象にはできますが、素材にはできません。</p>
<?php render_footer(); ?>
