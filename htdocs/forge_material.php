<?php
// 画面② 素材カード選択
// 強化対象と同じcard_idのユーザーカード（強化対象自身を除く）を1枚1行で表示する
require_once __DIR__ . '/forge_common.php';

$user = require_login();

$targetId = filter_input(INPUT_GET, 'target_id', FILTER_VALIDATE_INT);

$target = false;
if ($targetId) {
    $stmt = db()->prepare(
        'SELECT uc.id AS user_card_id, uc.card_id, c.name, c.rarity, c.limit_break, c.attack, c.hp,
                c.next_card_id, nc.name AS next_name
           FROM user_cards uc
           JOIN cards c ON c.id = uc.card_id
           LEFT JOIN cards nc ON nc.id = c.next_card_id
          WHERE uc.id = :id AND uc.user_id = :user_id'
    );
    $stmt->execute([':id' => $targetId, ':user_id' => $user['id']]);
    $target = $stmt->fetch();
}

render_header('素材カードを選択', 2);

if (!$target) {
    echo '<p class="error">強化対象のカードが見つかりません。</p>';
    echo '<p><a href="forge_entrance.php">強化対象選択へ戻る</a></p>';
    render_footer();
    exit;
}
if ($target['next_card_id'] === null) {
    echo '<p class="error">' . h($target['name']) . ' は最大まで突破済みです。</p>';
    echo '<p><a href="forge_entrance.php">強化対象選択へ戻る</a></p>';
    render_footer();
    exit;
}

$stmt = db()->prepare(
    'SELECT uc.id AS user_card_id, c.name, c.rarity, c.limit_break, c.attack, c.hp,
            (t.user_card_id IS NOT NULL) AS in_team
       FROM user_cards uc
       JOIN cards c ON c.id = uc.card_id
       LEFT JOIN user_team_cards t ON t.user_card_id = uc.id
      WHERE uc.user_id = :user_id
        AND uc.card_id = :card_id
        AND uc.id <> :target_id
      ORDER BY uc.id'
);
$stmt->execute([
    ':user_id'   => $user['id'],
    ':card_id'   => $target['card_id'],
    ':target_id' => $target['user_card_id'],
]);
$materials = $stmt->fetchAll();
?>
<div class="box">
  <strong>強化対象：</strong>
  所持ID <?= h($target['user_card_id']) ?> / <?= h($target['name']) ?>
  <span class="rarity"><?= stars((int)$target['rarity']) ?></span>
  → <strong><?= h($target['next_name']) ?></strong>
</div>

<div class="box">
<?php if (!$materials): ?>
  <p>素材にできるカードがありません。</p>
<?php else: ?>
  <table>
    <tr>
      <th class="num">所持ID</th><th>カード名</th><th>レア</th><th class="num">突破</th>
      <th class="num">攻撃</th><th class="num">HP</th><th>状態</th>
    </tr>
<?php foreach ($materials as $m): ?>
    <tr>
      <td class="num"><?= h($m['user_card_id']) ?></td>
      <td>
<?php if ($m['in_team']): ?>
        <?= h($m['name']) ?>
<?php else: ?>
        <form method="post" action="forge_result.php" style="margin:0">
          <input type="hidden" name="target_id" value="<?= h($target['user_card_id']) ?>">
          <input type="hidden" name="material_id" value="<?= h($m['user_card_id']) ?>">
          <button type="submit" class="link"><?= h($m['name']) ?></button>
        </form>
<?php endif; ?>
      </td>
      <td class="rarity"><?= stars((int)$m['rarity']) ?></td>
      <td class="num"><?= h($m['limit_break']) ?></td>
      <td class="num"><?= h($m['attack']) ?></td>
      <td class="num"><?= h($m['hp']) ?></td>
      <td><?php if ($m['in_team']): ?><span class="tag">編成中のため素材にできません</span><?php endif; ?></td>
    </tr>
<?php endforeach; ?>
  </table>
<?php endif; ?>
</div>
<p class="muted">素材にしたカードは消費されます。</p>
<p><a href="forge_entrance.php">強化対象選択へ戻る</a></p>
<?php render_footer(); ?>
