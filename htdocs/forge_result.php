<?php
// 画面③ 強化結果
// 強化対象のcard_idを突破後のIDに更新し、素材カードを削除する（トランザクション）
require_once __DIR__ . '/forge_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forge_entrance.php');
    exit;
}

$targetId   = filter_input(INPUT_POST, 'target_id', FILTER_VALIDATE_INT);
$materialId = filter_input(INPUT_POST, 'material_id', FILTER_VALIDATE_INT);

$error  = null;
$before = null;
$after  = null;

$pdo = db();
try {
    if (!$targetId || !$materialId || $targetId === $materialId) {
        throw new RuntimeException('強化対象と素材の指定が正しくありません。');
    }

    $pdo->beginTransaction();

    // 対象と素材をロックして取得（同時実行での二重消費を防ぐ）
    $stmt = $pdo->prepare(
        'SELECT uc.id AS user_card_id, uc.card_id, c.name, c.rarity, c.limit_break, c.attack, c.hp,
                c.next_card_id, (t.user_card_id IS NOT NULL) AS in_team
           FROM user_cards uc
           JOIN cards c ON c.id = uc.card_id
           LEFT JOIN user_team_cards t ON t.user_card_id = uc.id
          WHERE uc.id = :id AND uc.user_id = :user_id
          FOR UPDATE'
    );
    $stmt->execute([':id' => $targetId, ':user_id' => FORGE_USER_ID]);
    $before = $stmt->fetch();
    $stmt->execute([':id' => $materialId, ':user_id' => FORGE_USER_ID]);
    $material = $stmt->fetch();

    if (!$before || !$material) {
        throw new RuntimeException('カードが見つかりません。すでに強化済みの可能性があります。');
    }
    if ($before['card_id'] !== $material['card_id']) {
        throw new RuntimeException('素材には強化対象と同じカードを選んでください。');
    }
    if ($material['in_team']) {
        throw new RuntimeException('チーム編成中のカードは素材にできません。');
    }
    if ($before['next_card_id'] === null) {
        throw new RuntimeException('このカードは最大まで突破済みです。');
    }

    $pdo->prepare('UPDATE user_cards SET card_id = :next_card_id WHERE id = :id')
        ->execute([':next_card_id' => $before['next_card_id'], ':id' => $targetId]);
    $pdo->prepare('DELETE FROM user_cards WHERE id = :id')
        ->execute([':id' => $materialId]);

    $pdo->commit();

    $stmt = $pdo->prepare(
        'SELECT uc.id AS user_card_id, uc.card_id, c.name, c.rarity, c.limit_break, c.attack, c.hp
           FROM user_cards uc
           JOIN cards c ON c.id = uc.card_id
          WHERE uc.id = :id'
    );
    $stmt->execute([':id' => $targetId]);
    $after = $stmt->fetch();
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $e->getMessage();
}

render_header('強化結果', 3);

if ($error !== null): ?>
<p class="error"><?= h($error) ?></p>
<?php else: ?>
<div class="box">
  <p><strong>限界突破に成功しました！</strong>（素材の所持ID <?= h($materialId) ?> は消費されました）</p>
  <table>
    <tr><th></th><th class="num">所持ID</th><th class="num">カードID</th><th>カード名</th><th>レア</th><th class="num">突破</th><th class="num">攻撃</th><th class="num">HP</th></tr>
<?php foreach (['強化前' => $before, '強化後' => $after] as $label => $c): ?>
    <tr>
      <th><?= h($label) ?></th>
      <td class="num"><?= h($c['user_card_id']) ?></td>
      <td class="num"><strong><?= h($c['card_id']) ?></strong></td>
      <td><?= h($c['name']) ?></td>
      <td class="rarity"><?= stars((int)$c['rarity']) ?></td>
      <td class="num"><?= h($c['limit_break']) ?></td>
      <td class="num"><?= h($c['attack']) ?></td>
      <td class="num"><?= h($c['hp']) ?></td>
    </tr>
<?php endforeach; ?>
  </table>
  <p>カードID：<strong><?= h($before['card_id']) ?></strong> → <strong><?= h($after['card_id']) ?></strong></p>
</div>
<?php endif; ?>
<p><a href="forge_entrance.php">強化対象選択へ戻る</a></p>
<?php render_footer(); ?>
