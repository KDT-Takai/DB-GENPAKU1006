<?php
// 画面① 強化対象カード選択
// 同じcard_idを2枚以上所持しているユーザーカードのみ表示する
require_once __DIR__ . '/forge_common.php';

$user = require_login();

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
$stmt->execute([':user_id_sub' => $user['id'], ':user_id' => $user['id']]);
$rows = $stmt->fetchAll();


// 同じカードごとにまとめる
$groups = [];
foreach ($rows as $r) {
    $groups[$r['card_id']][] = $r;
}

render_header('強化するカードを選ぶ', 1, '同じカードを2枚以上持っていると限界突破できます。強化したいカードをクリックしてください。');

if (!$groups) {
    render_notice('同じカードを2枚以上持っていないため、限界突破できるカードがありません。');
}

foreach ($groups as $cards):
    $first = $cards[0];
?>
<section class="group">
  <div class="group-head">
    <h2><?= h($first['name']) ?></h2>
    <span class="count">×<?= h($first['same_num']) ?></span>
    <span class="to"><?= $first['next_name'] !== null ? '→ 突破後：' . h($first['next_name']) : 'これ以上突破できません' ?></span>
  </div>
  <div class="grid">
<?php foreach ($cards as $r):
    $badges = [];
    if ($r['in_team']) {
        $badges[] = ['編成中', 'team'];
    }
    if ($r['next_card_id'] === null) {
        $badges[] = ['最大突破', 'max'];
        $note = '';
    } elseif ($r['material_num'] == 0) {
        $badges[] = ['素材なし', 'warn'];
        $note = '残りのカードが編成中のため素材にできません';
    } else {
        $note = '';
    }
    $canForge = $r['next_card_id'] !== null && $r['material_num'] > 0;
    render_card($r, [
        'href'     => 'forge_material.php?target_id=' . $r['user_card_id'],
        'disabled' => !$canForge,
        'badges'   => $badges,
        'note'     => $note,
    ]);
endforeach; ?>
  </div>
</section>
<?php endforeach; ?>
<p class="muted">※ 編成中のカードも強化はできますが、素材にはできません。</p>
<p><a class="btn btn-ghost" href="menu.php">メニューに戻る</a></p>
<?php render_footer(); ?>