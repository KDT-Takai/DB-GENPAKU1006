<?php
// チーム編成（4枠）
require_once __DIR__ . '/common.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slot   = filter_input(INPUT_POST, 'slot', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    $pdo    = db();

    if ($slot >= 1 && $slot <= TEAM_SLOTS && $action === 'remove') {
        $pdo->prepare('DELETE FROM user_team_cards WHERE user_id = :user_id AND slot = :slot')
            ->execute([':user_id' => $user['id'], ':slot' => $slot]);
        flash('枠' . $slot . 'を外しました。');
    } elseif ($slot >= 1 && $slot <= TEAM_SLOTS && $action === 'set') {
        $userCardId = filter_input(INPUT_POST, 'user_card_id', FILTER_VALIDATE_INT);
        try {
            $pdo->beginTransaction();
            // 自分のカードで、まだ編成されていないものだけセットできる
            $stmt = $pdo->prepare(
                'SELECT c.name FROM user_cards uc
                   JOIN cards c ON c.id = uc.card_id
                   LEFT JOIN user_team_cards t ON t.user_card_id = uc.id
                  WHERE uc.id = :id AND uc.user_id = :user_id AND t.user_card_id IS NULL
                  FOR UPDATE'
            );
            $stmt->execute([':id' => $userCardId ?: 0, ':user_id' => $user['id']]);
            $name = $stmt->fetchColumn();
            if ($name === false) {
                throw new RuntimeException('そのカードは編成できません。');
            }
            $pdo->prepare('DELETE FROM user_team_cards WHERE user_id = :user_id AND slot = :slot')
                ->execute([':user_id' => $user['id'], ':slot' => $slot]);
            $pdo->prepare('INSERT INTO user_team_cards (user_id, slot, user_card_id) VALUES (:user_id, :slot, :uc)')
                ->execute([':user_id' => $user['id'], ':slot' => $slot, ':uc' => $userCardId]);
            $pdo->commit();
            flash('枠' . $slot . 'に「' . $name . '」を編成しました。');
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            flash($e->getMessage(), 'error');
        }
    }
    redirect('team.php');
}

$team = team_cards($user['id']);
$editSlot = filter_input(INPUT_GET, 'slot', FILTER_VALIDATE_INT);
if ($editSlot < 1 || $editSlot > TEAM_SLOTS) {
    $editSlot = 0;
}

$candidates = [];
if ($editSlot) {
    $stmt = db()->prepare(
        'SELECT uc.id AS user_card_id, c.name, c.image, c.rarity, c.limit_break, c.attack, c.hp, t.slot AS team_slot
           FROM user_cards uc
           JOIN cards c ON c.id = uc.card_id
           LEFT JOIN user_team_cards t ON t.user_card_id = uc.id
          WHERE uc.user_id = :user_id
          ORDER BY c.rarity DESC, c.limit_break DESC, c.attack DESC, uc.id'
    );
    $stmt->execute([':user_id' => $user['id']]);
    $candidates = $stmt->fetchAll();
}

render_header('チーム編成', 0, 'バトルに出す4枚を選びます。編成中のカードは限界突破の素材にできません。');
render_flash();
?>
<div class="panel">
  <div class="power" style="margin-bottom:16px"><span class="en">Team Power</span><b><?= number_format(team_power($team)) ?></b>
    <span class="muted">（ATK + HP÷4 の合計）</span></div>
  <div class="slots">
<?php for ($s = 1; $s <= TEAM_SLOTS; $s++): ?>
    <div class="slot-wrap<?= $editSlot === $s ? ' selected' : '' ?>">
      <span class="slot-label">SLOT <?= $s ?></span>
<?php if (isset($team[$s])): ?>
      <?php render_card($team[$s], ['href' => 'team.php?slot=' . $s]); ?>
      <div class="slot-actions">
        <a class="mini" href="team.php?slot=<?= $s ?>">入れ替え</a>
        <form method="post" action="team.php">
          <input type="hidden" name="slot" value="<?= $s ?>">
          <button type="submit" name="action" value="remove" class="mini">外す</button>
        </form>
      </div>
<?php else: ?>
      <a class="slot-empty" href="team.php?slot=<?= $s ?>">カードを編成</a>
<?php endif; ?>
    </div>
<?php endfor; ?>
  </div>
</div>

<?php if ($editSlot): ?>
<h2>枠<?= $editSlot ?>に編成するカードを選択</h2>
<?php if (!$candidates): ?>
<?php render_notice('カードを持っていません。ガチャで手に入れましょう。'); ?>
<?php else: ?>
<div class="grid">
<?php foreach ($candidates as $c):
    $inTeam = $c['team_slot'] !== null;
    render_card($c, [
        'post'        => ['slot' => $editSlot, 'user_card_id' => $c['user_card_id'], 'action' => 'set'],
        'post_action' => 'team.php',
        'disabled'    => $inTeam,
        'badges'      => $inTeam ? [['枠' . $c['team_slot'] . 'に編成中', 'team']] : [],
    ]);
endforeach; ?>
</div>
<?php endif; ?>
<p style="margin-top:20px"><a class="btn btn-ghost" href="team.php">キャンセル</a></p>
<?php endif; ?>
<?php render_footer(); ?>
