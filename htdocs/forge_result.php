<?php
// 画面③ 強化結果
// 強化対象のcard_idを突破後のIDに更新し、素材カードを削除する（トランザクション）
require_once __DIR__ . '/common.php';

$user = require_login();

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
        'SELECT uc.id AS user_card_id, uc.card_id, c.name, c.image, c.rarity, c.limit_break, c.attack, c.hp,
                c.next_card_id, (t.user_card_id IS NOT NULL) AS in_team
           FROM user_cards uc
           JOIN cards c ON c.id = uc.card_id
           LEFT JOIN user_team_cards t ON t.user_card_id = uc.id
          WHERE uc.id = :id AND uc.user_id = :user_id
          FOR UPDATE'
    );
    $stmt->execute([':id' => $targetId, ':user_id' => $user['id']]);
    $before = $stmt->fetch();
    $stmt->execute([':id' => $materialId, ':user_id' => $user['id']]);
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
        'SELECT uc.id AS user_card_id, uc.card_id, c.name, c.image, c.rarity, c.limit_break, c.attack, c.hp
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


if ($error !== null) {
    render_header('強化できませんでした', 3);
    render_notice($error, 'error');
} else {
    render_header('強化結果', 3);
?>
<div class="panel">
  <p class="celebrate"><span class="en">Limit Break Success</span><span class="ja">限界突破に成功しました</span></p>
  <div class="compare">
    <div>
      <div class="label">強化前</div>
      <?php render_card($before, ['large' => true]); ?>
    </div>
    <div class="arrow">→</div>
    <div>
      <div class="label">強化後</div>
      <?php render_card($after, ['large' => true, 'diff' => $before]); ?>
    </div>
  </div>
  <p class="idchange">カードID <strong><?= h($before['card_id']) ?></strong> → <strong><?= h($after['card_id']) ?></strong></p>
  <p class="muted" style="text-align:center">素材にしたカード（#<?= h($materialId) ?>）はなくなりました。</p>
</div>
<?php } ?>
<div class="actions">
  <a class="btn" href="forge_entrance.php">続けて強化する</a>
  <a class="btn btn-ghost" href="menu.php">メニューに戻る</a>
</div>
<?php render_footer(); ?>