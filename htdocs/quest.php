<?php
// クエスト（ステージを順番に攻略する。クリアで石、条件を満たすと★がもらえる）
require_once __DIR__ . '/common.php';

$user = require_login();

$stages = db()->query(
    'SELECT s.*, c.name AS chapter_name, c.description AS chapter_description
       FROM stages s JOIN chapters c ON c.id = s.chapter_id
      ORDER BY s.id'
)->fetchAll();

$enemies = [];
foreach (db()->query('SELECT * FROM stage_enemies ORDER BY stage_id, slot')->fetchAll() as $e) {
    $enemies[(int)$e['stage_id']][] = $e;
}

$stmt = db()->prepare('SELECT * FROM user_stage_progress WHERE user_id = :id');
$stmt->execute([':id' => $user['id']]);
$progress = [];
foreach ($stmt->fetchAll() as $p) {
    $progress[(int)$p['stage_id']] = $p;
}

// 最初のステージ、または1つ前のステージをクリア済みなら挑戦できる
$unlocked = [];
$prevCleared = true;
foreach ($stages as $s) {
    $unlocked[(int)$s['id']] = $prevCleared;
    $prevCleared = ($progress[(int)$s['id']]['clear_count'] ?? 0) > 0;
}

// 敵（stage_enemies の行）をバトル用のユニットに変換する
function enemy_units(array $rows): array
{
    $units = [];
    foreach ($rows as $e) {
        $units[] = ['name' => $e['name'], 'image' => $e['image'], 'attack' => (int)$e['attack'],
                    'hp' => (int)$e['hp'], 'max_hp' => (int)$e['hp'], 'boss' => (bool)$e['is_boss']];
    }
    return $units;
}

function stage_label(array $s): string
{
    return $s['chapter_id'] . '-' . $s['number'];
}

function render_stage_stars(int $stars): string
{
    return '<span class="stage-stars">' . str_repeat('<span class="on">★</span>', $stars) . str_repeat('★', 3 - $stars) . '</span>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stageId = filter_input(INPUT_POST, 'stage_id', FILTER_VALIDATE_INT);
    $stage   = null;
    foreach ($stages as $s) {
        if ((int)$s['id'] === $stageId) $stage = $s;
    }
    $myTeam = team_cards($user['id']);

    if (!$stage || empty($unlocked[$stageId])) {
        flash('そのステージにはまだ挑戦できません。', 'error');
    } elseif (!$myTeam) {
        flash('チームが編成されていません。先に編成してください。', 'error');
    } else {
        $battle = simulate_battle(card_units($myTeam), enemy_units($enemies[$stageId] ?? []));
        $win    = $battle['result'] === 'win';
        $goals  = [
            'クリアする'                               => $win,
            '味方が1人も倒れない'                      => $win && $battle['ko'] === 0,
            $stage['star_turns'] . ' ターン以内にクリア' => $win && $battle['turns'] <= (int)$stage['star_turns'],
        ];
        $stars      = count(array_filter($goals));
        $firstClear = $win && ($progress[$stageId]['clear_count'] ?? 0) === 0;
        $reward     = $win ? (int)($firstClear ? $stage['first_clear_gems'] : $stage['clear_gems']) : 0;

        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO user_stage_progress (user_id, stage_id, best_stars, clear_count, play_count)
             VALUES (:user_id, :stage_id, :stars, :clear, 1)
             ON DUPLICATE KEY UPDATE best_stars  = GREATEST(best_stars, VALUES(best_stars)),
                                     clear_count = clear_count + VALUES(clear_count),
                                     play_count  = play_count + 1'
        )->execute([':user_id' => $user['id'], ':stage_id' => $stageId, ':stars' => $stars, ':clear' => $win ? 1 : 0]);
        if ($reward > 0) {
            $pdo->prepare('UPDATE users SET gems = gems + :reward WHERE id = :id')
                ->execute([':reward' => $reward, ':id' => $user['id']]);
        }
        $pdo->commit();

        $_SESSION['quest_result'] = [
            'stage_id' => $stageId, 'battle' => $battle, 'goals' => $goals,
            'reward' => $reward, 'first_clear' => $firstClear,
        ];
    }
    redirect('quest.php');
}

$myTeam = team_cards($user['id']);
$last   = $_SESSION['quest_result'] ?? null;
unset($_SESSION['quest_result']);

$stageById = array_column($stages, null, 'id');
$chapters  = [];
foreach ($stages as $s) {
    $chapters[(int)$s['chapter_id']][] = $s;
}
// まだクリアしていない最初のステージ（「次はここ」と目立たせる）
$nextStageId = null;
foreach ($stages as $s) {
    if ($unlocked[(int)$s['id']] && ($progress[(int)$s['id']]['clear_count'] ?? 0) === 0) {
        $nextStageId = (int)$s['id'];
        break;
    }
}
$totalStars = array_sum(array_column($progress, 'best_stars'));

render_header('クエスト', 0, 'ステージを順番に攻略します。初回クリアで多めの石、★3 を目指して編成を工夫しましょう。');
render_flash();
?>
<?php if ($last): $ls = $stageById[$last['stage_id']]; $lb = $last['battle']; ?>
<section class="panel battle" id="result" data-battle>
  <p class="muted" style="text-align:center; margin:0"><?= h(stage_label($ls)) ?>　<?= h($ls['name']) ?></p>
<?php render_battle($lb, 'Your Team', 'Enemy'); ?>
  <div class="battle-after">
  <p class="result-word <?= h($lb['result']) ?>"><?= $lb['result'] === 'win' ? 'CLEAR' : ($lb['timeout'] ? 'TIME UP' : 'DEFEAT') ?></p>
  <ul class="star-goals">
<?php foreach ($last['goals'] as $text => $ok): ?>
    <li class="<?= $ok ? 'ok' : '' ?>"><?= h($text) ?></li>
<?php endforeach; ?>
  </ul>
  <p style="text-align:center; margin:12px 0 0">
<?php if ($last['reward'] > 0): ?>
    <?= $last['first_clear'] ? '<span class="badge badge-new">初回クリア</span> ' : '' ?>報酬：<span class="gems"><i class="gem-icon"></i><?= h($last['reward']) ?></span>
<?php else: ?>
    <span class="muted">チームを強化して再挑戦しよう（ガチャ・限界突破・編成の見直し）</span>
<?php endif; ?>
  </p>
  <div class="actions" style="justify-content:center; margin-top:16px">
    <form method="post" action="quest.php" style="margin:0"><input type="hidden" name="stage_id" value="<?= h($ls['id']) ?>"><button type="submit" class="btn">もう一度挑戦</button></form>
<?php if ($lb['result'] === 'win' && isset($stageById[$ls['id'] + 1])): ?>
    <form method="post" action="quest.php" style="margin:0"><input type="hidden" name="stage_id" value="<?= h($ls['id'] + 1) ?>"><button type="submit" class="btn btn-ghost">次のステージへ（<?= h(stage_label($stageById[$ls['id'] + 1])) ?>）</button></form>
<?php endif; ?>
  </div>
  </div>
</section>
<?php endif; ?>

<div class="panel">
  <div class="power"><span class="en">My Team Power</span><b><?= number_format(team_power($myTeam)) ?></b>
    <span class="muted"><?= count($myTeam) ?> / <?= TEAM_SLOTS ?> 枚 ・ 獲得★ <?= h($totalStars) ?> / <?= count($stages) * 3 ?></span>
    <a class="mini" href="team.php" style="margin-left:auto">編成する</a></div>
</div>
<?php if (!$myTeam): ?>
<?php render_notice('チームが編成されていないため挑戦できません。', 'error'); ?>
<?php endif; ?>

<?php foreach ($chapters as $chapterId => $list): $first = $list[0];
    $chapterStars = 0;
    foreach ($list as $s) $chapterStars += (int)($progress[(int)$s['id']]['best_stars'] ?? 0); ?>
<section class="chapter">
  <div class="chapter-head">
    <span class="en">Chapter <?= h($chapterId) ?></span>
    <h2><?= h($first['chapter_name']) ?></h2>
    <span class="star-total">★ <?= $chapterStars ?> / <?= count($list) * 3 ?></span>
    <div class="muted"><?= h($first['chapter_description']) ?></div>
  </div>
  <div class="stage-list">
<?php foreach ($list as $s):
    $id   = (int)$s['id'];
    $p    = $progress[$id] ?? null;
    $open = $unlocked[$id];
    $foes = $enemies[$id] ?? [];
    $classes = ['stage'];
    if ($s['is_boss']) $classes[] = 'boss';
    if (!$open) $classes[] = 'locked';
    if ($id === $nextStageId) $classes[] = 'next'; ?>
    <div class="<?= implode(' ', $classes) ?>">
      <div class="stage-top">
        <span class="stage-no"><?= h(stage_label($s)) ?></span>
        <span class="stage-name"><?= h($s['name']) ?></span>
        <?= render_stage_stars((int)($p['best_stars'] ?? 0)) ?>
      </div>
      <div class="muted"><?= $s['is_boss'] ? '<span class="boss-tag">BOSS</span>' : '' ?><?= h($s['description']) ?></div>
      <div class="stage-enemies">
<?php foreach ($foes as $e): ?>
        <img src="resource/cards/<?= h($e['image']) ?>" alt="<?= h($e['name']) ?>" title="<?= h($e['name']) ?>（ATK <?= h($e['attack']) ?> / HP <?= h($e['hp']) ?>）">
<?php endforeach; ?>
      </div>
      <div class="muted">敵の戦闘力 <?= number_format(team_power($foes)) ?> ・ ★3条件 <?= h($s['star_turns']) ?>ターン以内</div>
      <div class="stage-foot">
<?php if (!$p || (int)$p['clear_count'] === 0): ?>
        <span class="gems" title="初回クリア報酬"><i class="gem-icon"></i><?= h($s['first_clear_gems']) ?></span><span class="muted">初回</span>
<?php else: ?>
        <span class="gems" title="クリア報酬"><i class="gem-icon"></i><?= h($s['clear_gems']) ?></span><span class="muted">クリア <?= h($p['clear_count']) ?> 回</span>
<?php endif; ?>
<?php if ($open): ?>
        <form method="post" action="quest.php"><input type="hidden" name="stage_id" value="<?= $id ?>">
          <button type="submit" class="btn" <?= $myTeam ? '' : 'disabled' ?>><?= $p ? '再挑戦' : '挑戦' ?></button></form>
<?php else: ?>
        <span class="muted" style="margin-left:auto">🔒 前のステージをクリアで解放</span>
<?php endif; ?>
      </div>
    </div>
<?php endforeach; ?>
  </div>
</section>
<?php endforeach; ?>
<?php render_footer(); ?>
