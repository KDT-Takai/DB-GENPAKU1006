<?php
// 対戦（他ユーザーのチームとターン制バトル。勝つと石がもらえる）
require_once __DIR__ . '/common.php';

$user = require_login();

const REWARD_WIN  = 60;
const REWARD_LOSE = 20;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opponentId = filter_input(INPUT_POST, 'opponent_id', FILTER_VALIDATE_INT);
    $myTeam  = team_cards($user['id']);
    $oppTeam = $opponentId && $opponentId !== $user['id'] ? team_cards($opponentId) : [];

    if (!$myTeam) {
        flash('チームが編成されていません。先に編成してください。', 'error');
    } elseif (!$oppTeam) {
        flash('その相手とは対戦できません。', 'error');
    } else {
        $battle   = simulate_battle(card_units($myTeam), card_units($oppTeam));
        $myPower  = team_power($myTeam);
        $oppPower = team_power($oppTeam);
        $result   = $battle['result'];
        $reward   = $result === 'win' ? REWARD_WIN : REWARD_LOSE;

        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO battle_histories (user_id, opponent_user_id, result, user_power, opponent_power, reward_gems)
             VALUES (:user_id, :opp, :result, :mp, :op, :reward)'
        )->execute([':user_id' => $user['id'], ':opp' => $opponentId, ':result' => $result,
                    ':mp' => $myPower, ':op' => $oppPower, ':reward' => $reward]);
        $pdo->prepare('UPDATE users SET gems = gems + :reward WHERE id = :id')
            ->execute([':reward' => $reward, ':id' => $user['id']]);
        $pdo->commit();

        $_SESSION['battle_result'] = [
            'opponent_id' => $opponentId, 'result' => $result,
            'my_power' => $myPower, 'opp_power' => $oppPower, 'reward' => $reward, 'battle' => $battle,
        ];
    }
    redirect('battle.php');
}

$myTeam = team_cards($user['id']);

// 直前の結果
$last = $_SESSION['battle_result'] ?? null;
unset($_SESSION['battle_result']);
$lastOpp = null;
if ($last) {
    $stmt = db()->prepare('SELECT name FROM users WHERE id = :id');
    $stmt->execute([':id' => $last['opponent_id']]);
    $lastOpp = $stmt->fetchColumn();
}

// 対戦相手（チームを編成している他のユーザー）
$stmt = db()->prepare(
    'SELECT u.id, u.name, COUNT(t.slot) AS members
       FROM users u
       JOIN user_team_cards t ON t.user_id = u.id
      WHERE u.id <> :id
      GROUP BY u.id, u.name
      ORDER BY u.id'
);
$stmt->execute([':id' => $user['id']]);
$opponents = $stmt->fetchAll();

$stmt = db()->prepare(
    'SELECT b.created_at, b.result, b.user_power, b.opponent_power, b.reward_gems, u.name AS opponent
       FROM battle_histories b JOIN users u ON u.id = b.opponent_user_id
      WHERE b.user_id = :id
      ORDER BY b.id DESC LIMIT 10'
);
$stmt->execute([':id' => $user['id']]);
$histories = $stmt->fetchAll();

render_header('対戦', 0,'自分のチームで他のユーザーと対戦します。勝利で石 ' . REWARD_WIN . '、敗北でも石 ' . REWARD_LOSE . ' がもらえます。');
render_flash();
?>
<?php if ($last): ?>
<section class="panel battle" data-battle>
  <div class="vs" style="margin: 0 0 4px">
    <div class="side"><div class="muted">あなた</div><div class="name"><?= h($user['name']) ?></div><div class="num" style="text-align:center"><?= number_format($last['my_power']) ?></div></div>
    <div class="vs-mark">VS</div>
    <div class="side"><div class="muted">相手</div><div class="name"><?= h($lastOpp) ?></div><div class="num" style="text-align:center"><?= number_format($last['opp_power']) ?></div></div>
  </div>
<?php render_battle($last['battle'], $user['name'], (string)$lastOpp); ?>
  <div class="battle-after">
    <p class="result-word <?= h($last['result']) ?>"><?= $last['result'] === 'win' ? 'VICTORY' : 'DEFEAT' ?></p>
    <p style="text-align:center; margin:0">報酬：<span class="gems"><i class="gem-icon"></i><?= h($last['reward']) ?></span></p>
    <form method="post" action="battle.php" class="actions" style="justify-content:center; margin-top:16px">
      <input type="hidden" name="opponent_id" value="<?= h($last['opponent_id']) ?>">
      <button type="submit" class="btn">もう一度対戦</button>
    </form>
  </div>
</section>
<?php endif; ?>

<div class="panel">
  <div class="power"><span class="en">My Team Power</span><b><?= number_format(team_power($myTeam)) ?></b>
    <span class="muted"><?= count($myTeam) ?> / <?= TEAM_SLOTS ?> 枚</span>
    <a class="mini" href="team.php" style="margin-left:auto">編成する</a></div>
</div>

<h2>対戦相手</h2>
<?php if (!$myTeam): ?>
<?php render_notice('チームが編成されていないため対戦できません。', 'error'); ?>
<?php endif; ?>
<?php if (!$opponents): ?>
<?php render_notice('対戦できる相手がいません。'); ?>
<?php else: ?>
<div class="user-list" style="margin-bottom:28px">
<?php foreach ($opponents as $o): $power = team_power(team_cards((int)$o['id'])); ?>
  <div class="user-item">
    <div class="avatar"><?= h(mb_substr($o['name'], 0, 1)) ?></div>
    <div class="info">
      <div class="name"><?= h($o['name']) ?></div>
      <div class="muted">戦闘力 <?= number_format($power) ?> ・ <?= h($o['members']) ?>枚</div>
    </div>
    <form method="post" action="battle.php">
      <input type="hidden" name="opponent_id" value="<?= h($o['id']) ?>">
      <button type="submit" class="btn" <?= $myTeam ? '' : 'disabled' ?>>対戦</button>
    </form>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<section class="panel">
  <h2>対戦履歴（直近10件）</h2>
<?php if (!$histories): ?>
  <p class="muted">まだ対戦していません。</p>
<?php else: ?>
  <div class="table-wrap">
  <table class="list">
    <tr><th>日時</th><th>相手</th><th>結果</th><th style="text-align:right">自分</th><th style="text-align:right">相手</th><th style="text-align:right">報酬</th></tr>
<?php foreach ($histories as $b): ?>
    <tr>
      <td class="muted"><?= h($b['created_at']) ?></td>
      <td><?= h($b['opponent']) ?></td>
      <td class="<?= h($b['result']) ?>"><?= $b['result'] === 'win' ? '勝利' : '敗北' ?></td>
      <td class="num"><?= number_format($b['user_power']) ?></td>
      <td class="num"><?= number_format($b['opponent_power']) ?></td>
      <td class="num"><?= h($b['reward_gems']) ?></td>
    </tr>
<?php endforeach; ?>
  </table>
  </div>
<?php endif; ?>
</section>
<?php render_footer(); ?>
