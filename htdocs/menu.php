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
        // 初期装備（N の武器・防具・アクセサリー）
        $pdo->prepare('INSERT INTO user_equipments (user_id, equipment_id) SELECT :u, id FROM equipments WHERE rarity = 1')->execute([':u' => $newId]);
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

    $idle   = idle_state($user['id']);
    $badges = ['mission' => mission_status($user['id'])['claimable'], 'present' => present_count($user['id']),
               'idle' => $idle['elapsed'] >= 3600 ? intdiv($idle['elapsed'], 3600) . 'h' : 0];
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
    ['idle.php',           'Idle',        '放置',         '放っておくだけで報酬',     'idle', true],
    ['gacha.php',          'Warp',        'ガチャ',       '★5 ドラゴン出るかも',         '', true],
    ['quest.php',          'Quest',       'クエスト',     '冒険に出る',   '', false],
    ['cards.php',          'Collection',  '所持カード',   '育てる',       '', false],
    ['team.php',           'Team',        '編成',         '4枚えらぶ',          '', false],
    ['equip.php',          'Equip',       '装備',         '武器・防具で強化',    '', false],
    ['forge_entrance.php', 'Limit Break', '限界突破',     '2枚で1枚に',     '', false],
    ['battle.php',         'Battle',      '対戦',         'ほかの人と勝負',     '', false],
    ['mission.php',        'Mission',     'ミッション',   '毎日やること',           'mission', false],
    ['present.php',        'Present',     'プレゼント',   '届いたもの',       'present', false],
    ['ranking.php',        'Ranking',     'ランキング',   'だれが強い？',         '', false],
];

render_header('ホーム', 0, $user ? '' : 'まずはユーザーを選択してください。');
render_flash();
?>
<style>
  /* ホームの作り込み：リーダーの後ろで回る後光・きらめき・今日やることチップ・アイコン付きタイル */
  .home-hero { isolation: isolate; }
  .home-hero::before { content: ""; position: absolute; inset: -40%; z-index: -1; pointer-events: none;
                       background: repeating-conic-gradient(from 0deg at 25% 55%, rgba(255,255,255,.35) 0 6deg, transparent 6deg 20deg); animation: heroSpin 60s linear infinite; }
  .home-hero.r5 { background: repeating-conic-gradient(from 0deg at 25% 55%, #ffe08a 0 10deg, #ffd062 10deg 20deg); }
  .home-hero.r4 { background: repeating-conic-gradient(from 0deg at 25% 55%, #dccfff 0 10deg, #cbb8ff 10deg 20deg); }
  @keyframes heroSpin { to { transform: rotate(360deg); } }
  .home-hero .leader .halo { position: absolute; inset: 4%; border-radius: 50%; z-index: 0;
                             background: repeating-conic-gradient(var(--sun) 0 12deg, #fff 12deg 24deg); border: 3px solid var(--ink); animation: heroSpin 14s linear infinite; }
  .home-hero .leader::before { inset: 16%; z-index: 1; }
  .home-hero .leader img { z-index: 2; filter: drop-shadow(4px 5px 0 rgba(29,26,34,.35)); }
  .leader-stars { position: absolute; z-index: 3; bottom: 6%; left: 50%; transform: translateX(-50%) rotate(-3deg); padding: 0 12px; font-size: 16px; letter-spacing: 2px;
                  color: var(--sun); -webkit-text-stroke: 1px var(--ink); background: var(--ink); border-radius: 999px; box-shadow: var(--shadow-sm); white-space: nowrap; }
  .spark { position: absolute; z-index: 3; font-style: normal; color: #fff; -webkit-text-stroke: 1.5px var(--ink); font-size: 26px; animation: twinkle 2.2s ease-in-out infinite; }
  .spark.s1 { top: 8%; right: 12%; } .spark.s2 { bottom: 24%; left: 4%; font-size: 18px; animation-delay: -.8s; }
  .spark.s3 { top: 22%; left: 10%; font-size: 20px; color: var(--sun); animation-delay: -1.5s; }
  .home-hero .say { animation: sayIn .5s .2s cubic-bezier(.2,1.5,.4,1) both; transform-origin: left center; }
  @keyframes sayIn { from { opacity: 0; transform: scale(.7) rotate(-3deg); } }
  .today { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 14px; }
  .today > * { display: inline-flex; align-items: center; gap: 4px; padding: 1px 10px; font-size: 12px; font-weight: 800; text-decoration: none;
               border: 2px solid var(--ink); border-radius: 999px; background: #fff; }
  .today .todo { background: var(--tomato); color: #fff; box-shadow: var(--shadow-sm); animation: dotPulse 1.8s ease-in-out infinite; }
  .today .done { opacity: .65; }

  .home-tiles .tile:nth-child(n) { min-height: 112px; background-color: var(--paper-2);
                      background-image: radial-gradient(rgba(255,255,255,.35) 1.5px, transparent 2px); background-size: 12px 12px; }
  .home-tiles .tile:nth-child(1) { background-color: var(--tomato); } .home-tiles .tile:nth-child(2) { background-color: var(--grape); }
  .home-tiles .tile:nth-child(3) { background-color: var(--mint); }   .home-tiles .tile:nth-child(4) { background-color: var(--sky); }
  .home-tiles .tile:nth-child(5) { background-color: var(--sun); }    .home-tiles .tile:nth-child(6) { background-color: var(--rose); }
  .home-tiles .tile:nth-child(-n+4) .t, .home-tiles .tile:nth-child(6) .t
    { color: #fff; -webkit-text-stroke: 1px var(--ink); paint-order: stroke; text-shadow: 2px 2px 0 var(--ink); }
  .home-tiles .tile:nth-child(-n+4) .d, .home-tiles .tile:nth-child(6) .d { color: #fff; }
  .home-tiles .tile:nth-child(5) .t { color: var(--ink); -webkit-text-stroke: 0; text-shadow: none; }
  .home-tiles .tile:nth-child(5) .d { color: var(--ink-soft); }
  .tile .ti { position: absolute; top: 8px; right: 10px; font-size: 34px; line-height: 1; filter: drop-shadow(2px 2px 0 var(--ink)); transform: rotate(8deg);
              transition: transform .25s cubic-bezier(.2,1.6,.4,1); }
  .tile.big .ti { font-size: 52px; top: 10px; right: 16px; }
  .tile:hover .ti { transform: rotate(-10deg) scale(1.2); }
  .tile .dot { top: 8px; right: auto; left: 10px; }
  .tile.big::after { content: ""; position: absolute; inset: 0; z-index: -1; background: var(--shine); animation: tileShine 3.6s ease-in-out infinite; }
  .tile.big:nth-child(2)::after { animation-delay: 1.8s; }
  @keyframes tileShine { 0%, 60% { transform: translateX(-120%); } 100% { transform: translateX(120%); } }
</style>
<?php if ($user): ?>
<section class="home-hero r<?= h($leader['rarity'] ?? 3) ?>">
  <div class="leader">
    <i class="halo" aria-hidden="true"></i>
    <i class="spark s1" aria-hidden="true">✦</i><i class="spark s2" aria-hidden="true">✦</i><i class="spark s3" aria-hidden="true">★</i>
<?php if ($leader && $leader['image'] !== ''): ?>
    <img src="resource/cards/<?= h($leader['image']) ?>" alt="<?= h($leader['name']) ?>">
    <span class="leader-stars"><?= stars((int)$leader['rarity']) ?></span>
<?php endif; ?>
  </div>
  <div class="hero-text">
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
      <a class="btn btn-ghost" href="idle.php">放置報酬 <small style="opacity:.75">ゴールド <?= number_format($idle['pending']['gold']) ?></small></a>
      <a class="btn btn-ghost" href="gacha.php">ガチャ</a>
    </div>
    <div class="today" aria-label="今日やること">
      <span class="<?= $info['claimed'] ? 'done' : 'todo' ?>">🎫 ログボ<?= $info['claimed'] ? ' 受取済' : '' ?></span>
      <a href="mission.php" class="<?= $badges['mission'] ? 'todo' : 'done' ?>">📋 ミッション<?= $badges['mission'] ? ' ' . $badges['mission'] . '件' : '' ?></a>
      <a href="present.php" class="<?= $badges['present'] ? 'todo' : 'done' ?>">🎁 プレゼント<?= $badges['present'] ? ' ' . $badges['present'] . '件' : '' ?></a>
      <a href="idle.php" class="<?= $idle['elapsed'] >= 3600 ? 'todo' : 'done' ?>">⏳ 放置 <?= sprintf('%d:%02d', intdiv($idle['elapsed'], 3600), intdiv($idle['elapsed'] % 3600, 60)) ?></a>
    </div>
  </div>
</section>

<div class="stat-row">
  <div class="stat"><span class="label">ランク</span><b><?= h($status['player_rank']) ?></b>
    <div class="meter-bar" style="margin-top:6px"><i style="width:<?= round($status['exp'] / $status['exp_next'] * 100) ?>%"></i></div>
    <span class="muted" style="font-size:12px">次まで <?= number_format($status['exp_next'] - $status['exp']) ?> EXP</span></div>
  <div class="stat"><span class="label">所持カード</span><b><?= h($info['cards']) ?></b> 枚</div>
  <div class="stat"><span class="label">放置ステージ</span><b><?= h($idle['stage']) ?></b></div>
  <div class="stat"><span class="label">勝利数</span><b><?= h($info['wins']) ?></b> 勝</div>
  <div class="stat"><span class="label">チーム戦闘力</span><b><?= number_format(team_power(team_cards($user['id']))) ?></b></div>
</div>

<div class="home-tiles">
<?php
$tileIcons = ['Idle' => '⏳', 'Warp' => '✨', 'Quest' => '🗺️', 'Collection' => '🃏', 'Team' => '👥', 'Equip' => '⚔️',
              'Limit Break' => '🔥', 'Battle' => '🥊', 'Mission' => '📋', 'Present' => '🎁', 'Ranking' => '👑'];
foreach ($tiles as [$href, $en, $title, $desc, $badgeKey, $big]): ?>
  <a class="tile<?= $big ? ' big' : '' ?>" href="<?= h($href) ?>">
    <span class="ti" aria-hidden="true"><?= $tileIcons[$en] ?? '★' ?></span>
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
