<?php
// 画面② 素材カード選択
// 強化対象と同じcard_idのユーザーカード（強化対象自身を除く）を1枚1行で表示する
require_once __DIR__ . '/common.php';

$user = require_login();

$targetId = filter_input(INPUT_GET, 'target_id', FILTER_VALIDATE_INT);

$target = false;
if ($targetId) {
    $stmt = db()->prepare(
        'SELECT uc.id AS user_card_id, uc.card_id, c.name, c.rarity, c.limit_break, c.attack, c.hp,
                c.next_card_id, nc.name AS next_name, nc.rarity AS next_rarity,
                nc.limit_break AS next_limit_break, nc.attack AS next_attack, nc.hp AS next_hp
           FROM user_cards uc
           JOIN cards c ON c.id = uc.card_id
           LEFT JOIN cards nc ON nc.id = c.next_card_id
          WHERE uc.id = :id AND uc.user_id = :user_id'
    );
    $stmt->execute([':id' => $targetId, ':user_id' => $user['id']]);
    $target = $stmt->fetch();
}

if (!$target || $target['next_card_id'] === null) {
    render_header('素材を選ぶ', 2);
    render_notice($target ? $target['name'] . ' は最大まで突破済みです。' : '強化するカードが見つかりません。', 'error');
    echo '<p><a class="btn btn-ghost" href="forge_entrance.php">強化するカードを選び直す</a></p>';
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

$next = [
    'name'        => $target['next_name'],
    'rarity'      => $target['next_rarity'],
    'limit_break' => $target['next_limit_break'],
    'attack'      => $target['next_attack'],
    'hp'          => $target['next_hp'],
];

render_header('素材を選ぶ', 2, '素材にするカードをクリックすると、すぐに強化が実行されます。素材にしたカードはなくなります。');
?>
<div class="panel">
  <div class="compare">
    <div>
      <div class="label">強化するカード</div>
      <?php render_card($target, ['large' => true]); ?>
    </div>
    <div class="arrow">→</div>
    <div>
      <div class="label">強化後</div>
      <?php render_card($next, ['large' => true, 'diff' => $target]); ?>
    </div>
  </div>
</div>

<h2>素材にするカード</h2>
<?php if (!$materials): ?>
<?php render_notice('素材にできるカードがありません。'); ?>
<?php else: ?>
<div class="grid">
<?php foreach ($materials as $m):
    render_card($m, [
        'post'        => ['target_id' => $target['user_card_id'], 'material_id' => $m['user_card_id']],
        'post_action' => 'forge_result.php',
        'disabled'    => (bool)$m['in_team'],
        'badges'      => $m['in_team'] ? [['編成中', 'team']] : [],
        'note'        => $m['in_team'] ? '編成中のため素材にできません' : 'クリックで素材にする',
    ]);
endforeach; ?>
</div>
<?php endif; ?>
<p style="margin-top:24px"><a class="btn btn-ghost" href="forge_entrance.php">強化するカードを選び直す</a></p>
<?php render_footer(); ?>
