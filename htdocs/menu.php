<?php
// ホーム（リーダー表示・ログインボーナス・次にやること・各機能へのリンク・ユーザー作成）
require_once __DIR__ . '/common.php';

const LOGIN_BONUS_GEMS = 100;
const LOGIN_BONUS_GOLD = 2000;

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $userName = trim((string)($_POST['user_name'] ?? ''));
    if ($userName === '' || mb_strlen($userName) > 50) {
        flash('ユーザー名は1〜50文字で入力してください。', 'error');
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO users (name) VALUES (:name)')->execute([':name' => $userName]);
        $newId = (int)$pdo->lastInsertId();
        // スタートダッシュ支援はプレゼントボックスへ
        send_present($newId, 'gold', 10000, 'ようこそ！スタートダッシュ支援');
        send_present($newId, 'gems', 800, 'ようこそ！ガチャ5回分の石');
        $pdo->commit();
        flash('新しいユーザー「' . $userName . '」を作成しました。ユーザー選択から切り替えられます。');
    }
    redirect('menu.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_bonus']) && $user) {
    $pdo = db();
    $pdo->beginTransaction();
    // 主キー(user_id, login_date)が重複したら今日はもう受け取り済み
    $stmt = $pdo->prepare('INSERT IGNORE INTO user_login_bonuses (user_id, login_date) VALUES (:id, CURDATE())');
    $stmt->execute([':id' => $user['id']]);
    if ($stmt->rowCount() === 1) {
        $pdo->prepare('UPDATE users SET gems = gems + :gems, gold = gold + :gold WHERE id = :id')
            ->execute([':gems' => LOGIN_BONUS_GEMS, ':gold' => LOGIN_BONUS_GOLD, ':id' => $user['id']]);
        count_up($user['id'], 'login');
        flash('ログインボーナスで石 ' . LOGIN_BONUS_GEMS . ' とゴールド ' . number_format(LOGIN_BONUS_GOLD) . ' を受け取りました！');
    } else {
        flash('今日のログインボーナスは受け取り済みです。', 'error');
    }
    $pdo->commit();
    redirect('menu.php');
}

$info = $status = $leader = $nextStage = null;
$badges = [];
if ($user) {
    $status = user_status($user['id']);
    $stmt = db()->prepare(
        'SELECT
            (SELECT COUNT(*) FROM user_cards WHERE user_id = :id1)                                     AS cards,
            (SELECT COUNT(*) FROM user_login_bonuses WHERE user_id = :id2)                             AS login_days,
            (SELECT COUNT(*) FROM user_login_bonuses WHERE user_id = :id3 AND login_date = CURDATE())  AS claimed,
            (SELECT COUNT(*) FROM battle_histories WHERE user_id = :id4 AND result = \'win\')           AS wins'
    );
    $stmt->execute([':id1' => $user['id'], ':id2' => $user['id'], ':id3' => $user['id'], ':id4' => $user['id']]);
    $info = $stmt->fetch();

    // リーダー：編成の先頭。いなければ一番レア度の高いカード
    $team   = team_cards($user['id']);
    $leader = $team ? reset($team) : null;
    if (!$leader) {
        $stmt = db()->prepare('SELECT ' . CARD_COLS . ' FROM user_cards uc JOIN cards c ON c.id = uc.card_id WHERE uc.user_id = :u ORDER BY c.rarity DESC, uc.level DESC LIMIT 1');
        $stmt->execute([':u' => $user['id']]);
        $leader = $stmt->fetch() ?: null;
    }

    // まだクリアしていない最初のステージ
    $stmt = db()->prepare(
        'SELECT s.id, s.chapter_id, s.number, s.name, s.stamina FROM stages s
           LEFT JOIN user_stage_progress p ON p.stage_id = s.id AND p.user_id = :u
          WHERE COALESCE(p.clear_count, 0) = 0 ORDER BY s.id LIMIT 1'
    );
    $stmt->execute([':u' => $user['id']]);
    $nextStage = $stmt->fetch() ?: null;

    $badges = ['mission' => mission_status($user['id'])['claimable'], 'present' => present_count($user['id'])];
}

// リーダーのひとこと（時間帯と状況で変わる）
function leader_line(array $status, array $info, ?array $nextStage): string
{
    $hour = (int)date('G');
    if (!$info['claimed'])                                   return 'おかえり！ まずはログインボーナスを受け取ろう。';
    if ((int)$status['stamina'] >= (int)$status['stamina_max']) return 'スタミナが満タンだよ。クエストに出かけよう！';
    if ($nextStage && $hour >= 5 && $hour < 11)              return 'おはよう。今日は ' . $nextStage['chapter_id'] . '-' . $nextStage['number'] . ' に挑戦してみない？';
    if ($hour >= 22 || $hour < 5)                            return '夜ふかしは程々にね。デイリーミッションは0時にリセットだよ。';
    if ((int)$status['gold'] >= 5000)                        return 'ゴールドが貯まってるね。カードのレベルを上げておこう。';
    return '属性の相性を考えて編成すると、格上の相手にも勝てるよ。';
}

$tiles = [
    ['gacha.php',          'Warp',        'ガチャ',       '★5 ドラゴン出るかも',         '', true],
    ['quest.php',          'Quest',       'クエスト',     '冒険に出る',   '', false],
    ['cards.php',          'Collection',  '所持カード',   '育てる',       '', false],
    ['team.php',           'Team',        '編成',         '4枚えらぶ',          '', false],
    ['forge_entrance.php', 'Limit Break', '限界突破',     '2枚で1枚に',     '', false],
    ['battle.php',         'Battle',      '対戦',         'ほかの人と勝負',     '', false],
    ['mission.php',        'Mission',     'ミッション',   '毎日やること',           'mission', false],
    ['present.php',        'Present',     'プレゼント',   '届いたもの',       'present', false],
    ['ranking.php',        'Ranking',     'ランキング',   'だれが強い？',         '', false],
];

render_header('ホーム', 0, $user ? '' : 'まずはユーザーを選択してください。');
render_flash();
?>
<?php if ($user): ?>
<section class="home-hero">
  <div class="leader">
<?php if ($leader && $leader['image'] !== ''): ?>
    <img src="resource/cards/<?= h($leader['image']) ?>" alt="<?= h($leader['name']) ?>">
<?php endif; ?>
  </div>
  <div>
    <div class="hello">ようこそ、<?= h($user['name']) ?> さん ・ ログイン <?= h($info['login_days']) ?> 日目</div>
<?php if ($leader): ?>
    <div class="lname"><?= element_chip($leader['element']) ?><?= h($leader['name']) ?> <small class="muted" style="font-size:14px">Lv.<?= h($leader['level']) ?></small></div>
<?php else: ?>
    <div class="lname">リーダー未設定</div>
<?php endif; ?>
    <div class="say"><?= h(leader_line($status, $info, $nextStage)) ?></div>
    <div class="home-actions">
<?php if (!$info['claimed']): ?>
      <form method="post" action="menu.php"><button type="submit" name="login_bonus" class="btn">ログインボーナスを受け取る</button></form>
<?php endif; ?>
<?php if ($nextStage): ?>
      <a class="btn<?= $info['claimed'] ? '' : ' btn-ghost' ?>" href="quest.php#stage-<?= h($nextStage['id']) ?>">次のクエスト <?= h($nextStage['chapter_id'] . '-' . $nextStage['number']) ?></a>
<?php else: ?>
      <a class="btn btn-ghost" href="quest.php">クエスト</a>
<?php endif; ?>
      <a class="btn btn-ghost" href="gacha.php">ガチャ</a>
    </div>
  </div>
</section>

<div class="stat-row">
  <div class="stat"><span class="label">ランク</span><b><?= h($status['player_rank']) ?></b>
    <div class="meter-bar" style="margin-top:6px"><i style="width:<?= round($status['exp'] / $status['exp_next'] * 100) ?>%"></i></div>
    <span class="muted" style="font-size:12px">次まで <?= number_format($status['exp_next'] - $status['exp']) ?> EXP</span></div>
  <div class="stat"><span class="label">所持カード</span><b><?= h($info['cards']) ?></b> 枚</div>
  <div class="stat"><span class="label">勝利数</span><b><?= h($info['wins']) ?></b> 勝</div>
  <div class="stat"><span class="label">チーム戦闘力</span><b><?= number_format(team_power(team_cards($user['id']))) ?></b></div>
</div>

<div class="home-tiles">
<?php foreach ($tiles as [$href, $en, $title, $desc, $badgeKey, $big]): ?>
  <a class="tile<?= $big ? ' big' : '' ?>" href="<?= h($href) ?>">

    <span class="t"><?= h($title) ?></span>
    <span class="d"><?= h($desc) ?></span>
<?php if ($badgeKey && !empty($badges[$badgeKey])): ?>
    <span class="dot"><?= h($badges[$badgeKey]) ?></span>
<?php endif; ?>
  </a>
<?php endforeach; ?>
</div>
<?php else: ?>
<p><a class="btn" href="select_user.php">ユーザーを選択する</a></p>
<?php endif; ?>

<div class="panel">
  <h2>ユーザーを作成</h2>
  <p class="muted" style="margin-top:-6px">新しいユーザーは石 1,600 から始まり、プレゼントボックスにスタートダッシュ支援（ゴールド 10,000・石 800）が届きます。</p>
  <form action="menu.php" method="post" class="row">
    <input type="text" name="user_name" placeholder="新しいユーザー名" maxlength="50" required>
    <button type="submit" name="create_user" class="btn">作成</button>
  </form>
</div>
<?php render_footer(); ?>
