<?php
// 共通処理（セッション・DB接続・ログイン確認・表示ヘルパー）

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// MySQL（TZ=Asia/Tokyo）と時刻をそろえる。スタミナ回復の計算に使う
date_default_timezone_set('Asia/Tokyo');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=mysql;dbname=forge_db;charset=utf8mb4',
            'root',
            'p@ssword',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function stars(int $rarity): string
{
    return str_repeat('★', $rarity);
}

// ログイン中のユーザー（未ログインならnull）
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return ['id' => (int)$_SESSION['user_id'], 'name' => (string)$_SESSION['user_name']];
}

// 未ログインならエラー画面を出して終了する
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        render_header('ログインが必要です');
        render_notice('この機能を使うには、まずユーザーを選択してください。', 'error');
        echo '<p><a class="btn" href="select_user.php">ユーザーを選択する</a></p>';
        render_footer();
        exit;
    }
    return $user;
}

function render_notice(string $text, string $type = 'info'): void
{
    echo '<p class="notice notice-' . h($type) . '">' . h($text) . '</p>';
}

// 画面をまたいで1回だけ表示するメッセージ（POST→リダイレクト後に表示する用）
function flash(string $text, string $type = 'ok'): void
{
    $_SESSION['flash'] = [$text, $type];
}

function render_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        [$text, $type] = $_SESSION['flash'];
        unset($_SESSION['flash']);
        render_notice($text, $type);
    }
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function user_gems(int $userId): int
{
    $stmt = db()->prepare('SELECT gems FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    return (int)$stmt->fetchColumn();
}

// ---------- 育成要素（カードレベル・属性・スキル・ランク・スタミナ） ----------
const LEVEL_RATE        = 0.06;   // カードのレベルが1上がるごとのステータス上昇率
const STAMINA_REGEN_SEC = 60;     // スタミナが1回復するまでの秒数

// 所持カードを表示・計算するときの列（ATK・HP はレベルを反映した値にする）
const CARD_COLS = 'uc.id AS user_card_id, uc.card_id, uc.level, c.name, c.image, c.rarity, c.limit_break, c.element, c.skill,
                   CAST(ROUND(c.attack * (1 + 0.06 * (uc.level - 1))) AS SIGNED) AS attack,
                   CAST(ROUND(c.hp * (1 + 0.06 * (uc.level - 1))) AS SIGNED) AS hp';

// 属性：[表示名, 色]。火→風→水→火 の三すくみ、光と闇はお互いに弱点
const ELEMENTS = [
    'fire'  => ['火', '#ff7a4a'], 'water' => ['水', '#4ab0ff'], 'wind' => ['風', '#5ee08a'],
    'light' => ['光', '#ffe27a'], 'dark'  => ['闇', '#b67aff'], 'none' => ['無', '#a0a4b8'],
];
// スキル：[名前, 説明]。3ターンに1回（2・5・8ターン目）発動する
const SKILLS = [
    'strike' => ['渾身の一撃', '敵1体に攻撃力の220%のダメージ'],
    'aoe'    => ['全体攻撃',   '敵全体に攻撃力の70%のダメージ'],
    'heal'   => ['ヒール',     'HPの割合が一番低い味方を攻撃力の150%回復'],
    'guard'  => ['守りの構え', '次のターンが終わるまで、味方全員の受けるダメージを35%軽減'],
];

function element_rate(string $attacker, string $defender): float
{
    $strong = ['fire' => 'wind', 'wind' => 'water', 'water' => 'fire', 'light' => 'dark', 'dark' => 'light'];
    if (($strong[$attacker] ?? null) === $defender) return 1.3;
    if (($strong[$defender] ?? null) === $attacker && !in_array($attacker, ['light', 'dark'], true)) return 0.8;
    return 1.0;
}

function max_level(int $rarity, int $limitBreak): int { return 10 + $rarity * 5 + $limitBreak * 10; }
function levelup_cost(int $level, int $rarity): int   { return 30 * $level * $rarity; }
function stamina_max(int $rank): int                   { return min(120, 40 + $rank * 2); }
function exp_next(int $rank): int                      { return 50 + $rank * 50; }

// スタミナを経過時間ぶん回復させてから、ユーザーの状態（ランク・通貨・スタミナ）を返す
function user_status(int $userId): array
{
    $stmt = db()->prepare('SELECT id, name, gems, gold, player_rank, exp, stamina, stamina_at, pity_count FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $u = $stmt->fetch();
    if (!$u) {
        return [];
    }
    $max = stamina_max((int)$u['player_rank']);
    $now = time();
    $at  = min($now, strtotime($u['stamina_at']));
    if ((int)$u['stamina'] < $max) {
        $gain = intdiv($now - $at, STAMINA_REGEN_SEC);
        if ($gain > 0) {
            $u['stamina'] = min($max, (int)$u['stamina'] + $gain);
            $at = $u['stamina'] >= $max ? $now : $at + $gain * STAMINA_REGEN_SEC;
            db()->prepare('UPDATE users SET stamina = :s, stamina_at = :at WHERE id = :id')
                ->execute([':s' => $u['stamina'], ':at' => date('Y-m-d H:i:s', $at), ':id' => $userId]);
        }
    }
    $u['stamina_max']  = $max;
    $u['stamina_next'] = (int)$u['stamina'] >= $max ? 0 : STAMINA_REGEN_SEC - ($now - $at) % STAMINA_REGEN_SEC;
    $u['exp_next']     = exp_next((int)$u['player_rank']);
    return $u;
}

// スタミナを使う（足りなければ false）。満タンから使ったときは、そこから回復の時計を動かす
function spend_stamina(int $userId, int $cost): bool
{
    $u = user_status($userId);
    if ((int)$u['stamina'] < $cost) {
        return false;
    }
    $sql = 'UPDATE users SET stamina = stamina - :cost' . ((int)$u['stamina'] >= $u['stamina_max'] ? ', stamina_at = NOW()' : '')
         . ' WHERE id = :id AND stamina >= :cost2';
    $stmt = db()->prepare($sql);
    $stmt->execute([':cost' => $cost, ':cost2' => $cost, ':id' => $userId]);
    return $stmt->rowCount() === 1;
}

// 経験値を加算し、ランクアップしたらスタミナ全回復＋ランク報酬をプレゼントボックスへ
// 戻り値: 上がったランク数
function gain_exp(int $userId, int $exp): int
{
    $stmt = db()->prepare('SELECT player_rank, exp FROM users WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $userId]);
    $row  = $stmt->fetch();
    $rank = (int)$row['player_rank'];
    $total = (int)$row['exp'] + $exp;
    $up = 0;
    while ($total >= exp_next($rank)) {
        $total -= exp_next($rank);
        $rank++;
        $up++;
        send_present($userId, 'gems', 50, 'ランク' . $rank . ' 到達ボーナス');
    }
    if ($up) {
        db()->prepare('UPDATE users SET player_rank = :r, exp = :e, stamina = GREATEST(stamina, :max), stamina_at = NOW() WHERE id = :id')
            ->execute([':r' => $rank, ':e' => $total, ':max' => stamina_max($rank), ':id' => $userId]);
    } else {
        db()->prepare('UPDATE users SET exp = :e WHERE id = :id')->execute([':e' => $total, ':id' => $userId]);
    }
    return $up;
}

// ---------- プレゼントボックス・デイリーミッション ----------
const PRESENT_ITEMS = ['gems' => '石', 'gold' => 'ゴールド', 'stamina' => 'スタミナ'];

function send_present(int $userId, string $item, int $amount, string $message): void
{
    db()->prepare('INSERT INTO user_presents (user_id, item, amount, message) VALUES (:u, :i, :a, :m)')
        ->execute([':u' => $userId, ':i' => $item, ':a' => $amount, ':m' => $message]);
}

// 報酬を直接付与する（ミッション・プレゼント受け取りで共通）
function grant(int $userId, string $item, int $amount): void
{
    $col = ['gems' => 'gems', 'gold' => 'gold', 'stamina' => 'stamina'][$item];
    db()->prepare("UPDATE users SET {$col} = {$col} + :a WHERE id = :id")->execute([':a' => $amount, ':id' => $userId]);
}

// 今日の行動回数を数える（デイリーミッション用）
function count_up(int $userId, string $key, int $n = 1): void
{
    db()->prepare('INSERT INTO user_daily_counts (user_id, day, mkey, cnt) VALUES (:u, CURDATE(), :k, :n)
                   ON DUPLICATE KEY UPDATE cnt = cnt + VALUES(cnt)')
        ->execute([':u' => $userId, ':k' => $key, ':n' => $n]);
}

// [タイトル, 目標回数, 報酬の種類, 報酬量]
const DAILY_MISSIONS = [
    'login'   => ['ログインボーナスを受け取る', 1, 'gems', 30],
    'gacha'   => ['ガチャを1回引く',           1, 'gold', 1000],
    'quest'   => ['クエストを3回クリアする',     3, 'gold', 1500],
    'battle'  => ['対戦を1回する',             1, 'gems', 30],
    'levelup' => ['カードを強化する',           1, 'gold', 800],
];
const DAILY_ALL = ['デイリーミッションをすべて達成', 'gems', 100];
// [タイトル, 何の値で判定するか, 目標, 報酬の種類, 報酬量]
const ACHIEVEMENTS = [
    'rank5'  => ['プレイヤーランク5に到達',      'rank',  5,  'gems', 100],
    'rank10' => ['プレイヤーランク10に到達',     'rank',  10, 'gems', 200],
    'rank20' => ['プレイヤーランク20に到達',     'rank',  20, 'gems', 300],
    'book5'  => ['カードを5シリーズ集める',      'book',  5,  'gems', 100],
    'book10' => ['全10シリーズのカードを集める', 'book',  10, 'gems', 300],
    'star12' => ['クエストの★を12個集める',      'stars', 12, 'gems', 100],
    'star36' => ['クエストの★をすべて集める',    'stars', 36, 'gems', 300],
    'win5'   => ['対戦で5回勝つ',               'wins',  5,  'gold', 3000],
    'win20'  => ['対戦で20回勝つ',              'wins',  20, 'gems', 200],
    'lv30'   => ['カードをレベル30まで育てる',   'maxlv', 30, 'gold', 5000],
];

// ミッションの進み具合と受け取り状況。claimable は受け取れる報酬の数
function mission_status(int $userId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT mkey, cnt FROM user_daily_counts WHERE user_id = :u AND day = CURDATE()');
    $stmt->execute([':u' => $userId]);
    $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmt = $pdo->prepare("SELECT mission FROM user_mission_claims WHERE user_id = :u AND (period = :today OR period = 'once')");
    $stmt->execute([':u' => $userId, ':today' => date('Y-m-d')]);
    $claimed = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

    $stmt = $pdo->prepare(
        "SELECT u.player_rank AS `rank`,
                (SELECT COUNT(DISTINCT c.image) FROM user_cards uc JOIN cards c ON c.id = uc.card_id WHERE uc.user_id = u.id) AS book,
                (SELECT COALESCE(SUM(best_stars), 0) FROM user_stage_progress WHERE user_id = u.id) AS stars,
                (SELECT COUNT(*) FROM battle_histories WHERE user_id = u.id AND result = 'win') AS wins,
                (SELECT COALESCE(MAX(level), 1) FROM user_cards WHERE user_id = u.id) AS maxlv
           FROM users u WHERE u.id = :u"
    );
    $stmt->execute([':u' => $userId]);
    $values = $stmt->fetch();

    $daily = [];
    $done = 0;
    foreach (DAILY_MISSIONS as $key => [$title, $goal, $item, $amount]) {
        $now = min($goal, (int)($counts[$key] ?? 0));
        $daily[$key] = compact('title', 'goal', 'item', 'amount', 'now') + ['claimed' => isset($claimed[$key])];
        if (isset($claimed[$key])) $done++;
    }
    [$title, $item, $amount] = DAILY_ALL;
    $daily['all'] = ['title' => $title, 'goal' => count(DAILY_MISSIONS), 'item' => $item, 'amount' => $amount,
                     'now' => $done, 'claimed' => isset($claimed['all'])];

    $achievements = [];
    foreach (ACHIEVEMENTS as $key => [$title, $by, $goal, $item, $amount]) {
        $now = min($goal, (int)$values[$by]);
        $achievements[$key] = compact('title', 'goal', 'item', 'amount', 'now') + ['claimed' => isset($claimed[$key])];
    }

    $claimable = 0;
    foreach ($daily + $achievements as $m) {
        if (!$m['claimed'] && $m['now'] >= $m['goal']) $claimable++;
    }
    return ['daily' => $daily, 'achievements' => $achievements, 'claimable' => $claimable];
}

function present_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM user_presents WHERE user_id = :u AND received_at IS NULL');
    $stmt->execute([':u' => $userId]);
    return (int)$stmt->fetchColumn();
}

// チーム編成（slot => カード情報）
function team_cards(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT t.slot, ' . CARD_COLS . '
           FROM user_team_cards t
           JOIN user_cards uc ON uc.id = t.user_card_id
           JOIN cards c ON c.id = uc.card_id
          WHERE t.user_id = :user_id
          ORDER BY t.slot'
    );
    $stmt->execute([':user_id' => $userId]);
    $team = [];
    foreach ($stmt->fetchAll() as $row) {
        $team[(int)$row['slot']] = $row;
    }
    return $team;
}

// 戦闘力 = 各カードの ATK + HP/4 の合計
function team_power(array $team): int
{
    $power = 0;
    foreach ($team as $c) {
        $power += (int)$c['attack'] + intdiv((int)$c['hp'], 4);
    }
    return $power;
}

const TEAM_SLOTS = 4;

// ---------- ターン制バトル（クエスト・対戦で共通） ----------
const BATTLE_MAX_TURNS = 10;

// チーム（team_cards の戻り値）をバトル用のユニットに変換する
function card_units(array $team): array
{
    $units = [];
    foreach ($team as $c) {
        $units[] = ['name' => $c['name'], 'image' => $c['image'], 'attack' => (int)$c['attack'],
                    'hp' => (int)$c['hp'], 'max_hp' => (int)$c['hp'], 'boss' => false,
                    'element' => $c['element'] ?? 'none', 'skill' => $c['skill'] ?? null];
    }
    return $units;
}

/**
 * 味方 → 敵 の順に1体ずつ攻撃するターン制バトル
 * - 攻撃する相手は、生き残っている相手からランダム
 * - ダメージは ATK の 85〜115%。15% の確率で会心（1.5倍）。属性が有利なら1.3倍、不利なら0.8倍
 * - カードは3ターンに1回（2・5・8ターン目）スキルを使う（SKILLS を参照）
 * - ボスは3ターンごとに全体攻撃（全員に ATK の 60%）
 * - BATTLE_MAX_TURNS ターンで敵を全滅できなければ時間切れで敗北
 * 戻り値: result（win/lose）, turns, ko（倒された味方の数）, allies, enemies（戦闘後のHP）, log
 */
function simulate_battle(array $allies, array $enemies): array
{
    $units = ['ally' => array_values($allies), 'enemy' => array_values($enemies)];
    $log   = [];
    $guard = ['ally' => 0, 'enemy' => 0];   // このターンまで被ダメージ軽減
    $alive = function (string $side) use (&$units): array {
        return array_keys(array_filter($units[$side], fn($u) => $u['hp'] > 0));
    };

    $hit = function (string $side, int $i, int $t, float $rate, array $tags, ?string $skill = null) use (&$units, &$log, &$turn, &$guard): void {
        $foe    = $side === 'ally' ? 'enemy' : 'ally';
        $crit   = random_int(1, 100) <= 15;
        $elem   = element_rate($units[$side][$i]['element'] ?? 'none', $units[$foe][$t]['element'] ?? 'none');
        $damage = $units[$side][$i]['attack'] * $rate * random_int(85, 115) / 100 * ($crit ? 1.5 : 1) * $elem;
        if ($guard[$foe] >= $turn) {
            $damage *= 0.65;
            $tags[] = 'guarded';
        }
        $damage = (int)round($damage);
        $units[$foe][$t]['hp'] = max(0, $units[$foe][$t]['hp'] - $damage);
        $ko = $units[$foe][$t]['hp'] === 0;
        if ($crit)       $tags[] = 'crit';
        if ($elem > 1)   $tags[] = 'weak';
        if ($elem < 1)   $tags[] = 'resist';
        if ($ko)         $tags[] = 'ko';
        // a / t：攻撃した側・された側のユニット番号、ts：された側、hp：攻撃を受けた後の残りHP（戦闘シーンの再生に使う）
        $log[] = ['turn' => $turn, 'side' => $side, 'tags' => $tags, 'a' => $i, 't' => $t, 'ts' => $foe, 'hp' => $units[$foe][$t]['hp'],
                  'actor' => $units[$side][$i]['name'], 'target' => $units[$foe][$t]['name'], 'damage' => $damage, 'skill' => $skill];
    };

    $result = 'lose';
    for ($turn = 1; $turn <= BATTLE_MAX_TURNS; $turn++) {
        foreach (['ally' => 'enemy', 'enemy' => 'ally'] as $side => $foe) {
            foreach (array_keys($units[$side]) as $i) {
                $u = $units[$side][$i];
                if ($u['hp'] <= 0) continue;
                $targets = $alive($foe);
                if (!$targets) break 2;
                $skill = empty($u['boss']) && !empty($u['skill']) && $turn % 3 === 2 ? $u['skill'] : null;
                $name  = $skill ? SKILLS[$skill][0] : null;
                if (!empty($u['boss']) && $turn % 3 === 0) {
                    foreach ($targets as $t) $hit($side, $i, $t, 0.6, ['aoe']);
                } elseif ($skill === 'strike') {
                    $hit($side, $i, $targets[array_rand($targets)], 2.2, ['skill'], $name);
                } elseif ($skill === 'aoe') {
                    foreach ($targets as $t) $hit($side, $i, $t, 0.7, ['skill', 'aoe'], $name);
                } elseif ($skill === 'heal') {
                    // HPの割合が一番低い味方を回復
                    $mates = $alive($side);
                    usort($mates, fn($a, $b) => $units[$side][$a]['hp'] / $units[$side][$a]['max_hp'] <=> $units[$side][$b]['hp'] / $units[$side][$b]['max_hp']);
                    $t = $mates[0];
                    $amount = min((int)round($u['attack'] * 1.5), $units[$side][$t]['max_hp'] - $units[$side][$t]['hp']);
                    $units[$side][$t]['hp'] += $amount;
                    $log[] = ['turn' => $turn, 'side' => $side, 'tags' => ['skill', 'heal'], 'a' => $i, 't' => $t, 'ts' => $side,
                              'hp' => $units[$side][$t]['hp'], 'actor' => $u['name'], 'target' => $units[$side][$t]['name'],
                              'damage' => $amount, 'skill' => $name];
                } elseif ($skill === 'guard') {
                    $guard[$side] = $turn + 1;
                    $log[] = ['turn' => $turn, 'side' => $side, 'tags' => ['skill', 'guard'], 'a' => $i, 't' => $i, 'ts' => $side,
                              'hp' => $u['hp'], 'actor' => $u['name'], 'target' => $u['name'], 'damage' => 0, 'skill' => $name];
                } else {
                    $hit($side, $i, $targets[array_rand($targets)], 1.0, []);
                }
            }
        }
        if (!$alive('enemy')) { $result = 'win'; break; }
        if (!$alive('ally'))  break;
    }

    return [
        'result'  => $result,
        'turns'   => min($turn, BATTLE_MAX_TURNS),
        'timeout' => $result === 'lose' && $alive('ally') !== [],
        'ko'      => count($units['ally']) - count($alive('ally')),
        'allies'  => $units['ally'],
        'enemies' => $units['enemy'],
        'log'     => $log,
    ];
}

/**
 * 戦闘シーン（敵が上・味方が下）と戦闘ログを表示する
 * 呼び出し側は data-battle 属性を付けた要素の中でこの関数を呼ぶ。
 * 同じ要素の中の .battle-after（勝敗・報酬など）は、戦闘シーンの再生が終わってから表示される。
 * JavaScript が無効なときは、戦闘後の状態とログをそのまま表示する。
 */
function render_battle(array $battle, string $allyLabel, string $enemyLabel): void
{
    $side = function (array $units, string $label, string $cls): void { ?>
  <div class="unit-side <?= $cls ?>">
    <div class="side-label en"><?= h($label) ?></div>
    <div class="unit-row">
<?php foreach ($units as $u): $rate = $u['max_hp'] > 0 ? $u['hp'] / $u['max_hp'] * 100 : 0; ?>
      <div class="unit<?= $u['hp'] <= 0 ? ' down' : '' ?><?= !empty($u['boss']) ? ' boss' : '' ?>" data-max="<?= h($u['max_hp']) ?>" data-final="<?= h($u['hp']) ?>">
        <?php if ($u['image'] !== ''): ?><img src="resource/cards/<?= h($u['image']) ?>" alt=""><?php endif; ?>
        <div class="unit-name"><?= element_chip($u['element'] ?? 'none') ?><?= !empty($u['boss']) ? '<span class="boss-tag">BOSS</span>' : '' ?><?= h($u['name']) ?></div>
        <div class="hpbar"><i style="width:<?= round($rate, 1) ?>%"></i></div>
        <div class="unit-hp"><?= number_format($u['hp']) ?> / <?= number_format($u['max_hp']) ?></div>
      </div>
<?php endforeach; ?>
    </div>
  </div>
<?php };
?>
  <div class="arena" data-log="<?= h(json_encode($battle['log'], JSON_UNESCAPED_UNICODE)) ?>">
    <div class="battle-controls">
      <button type="button" class="mini" data-speed>×1</button>
      <button type="button" class="mini" data-skip>スキップ</button>
    </div>
<?php $side($battle['enemies'], $enemyLabel, 'enemy'); ?>
    <div class="turn-banner">バトル開始</div>
<?php $side($battle['allies'], $allyLabel, 'ally'); ?>
  </div>
  <details class="battle-log" open>
    <summary>戦闘ログ（<?= h($battle['turns']) ?> ターン）</summary>
    <ol>
<?php $prevTurn = 0; foreach ($battle['log'] as $n => $l): ?>
<?php if ($l['turn'] !== $prevTurn): $prevTurn = $l['turn']; ?>
      <li class="turn" data-turn="<?= h($l['turn']) ?>">ターン <?= h($l['turn']) ?></li>
<?php endif; ?>
      <li class="<?= h($l['side']) ?> <?= h(implode(' ', $l['tags'])) ?>" data-n="<?= $n ?>">
<?php $tags = $l['tags']; $skill = $l['skill'] ?? null; ?>
<?php if (in_array('heal', $tags, true)): ?>
        <b><?= h($l['actor']) ?></b> の<span class="skill-tag"><?= h($skill) ?></span> → <?= h($l['target']) ?> のHPが <span class="heal-num"><?= number_format($l['damage']) ?></span> 回復
<?php elseif (in_array('guard', $tags, true)): ?>
        <b><?= h($l['actor']) ?></b> の<span class="skill-tag"><?= h($skill) ?></span> 味方全員の守りが固くなった
<?php else: ?>
        <b><?= h($l['actor']) ?></b><?= $skill ? ' の<span class="skill-tag">' . h($skill) . '</span>' : (in_array('aoe', $tags, true) ? ' の全体攻撃！' : ' の攻撃') ?>
        → <?= h($l['target']) ?> に <span class="dmg"><?= number_format($l['damage']) ?></span><?= in_array('crit', $tags, true) ? ' <span class="crit-tag">会心！</span>' : '' ?>
        <?= in_array('weak', $tags, true) ? '<span class="weak-tag">弱点！</span>' : '' ?><?= in_array('resist', $tags, true) ? '<span class="resist-tag">いまひとつ</span>' : '' ?><?= in_array('guarded', $tags, true) ? '<span class="resist-tag">軽減</span>' : '' ?>
        <?= in_array('ko', $tags, true) ? '<span class="ko-tag">' . h($l['target']) . ' は倒れた</span>' : '' ?>
<?php endif; ?>
      </li>
<?php endforeach; ?>
<?php if ($battle['timeout']): ?>
      <li class="turn">時間切れ（<?= BATTLE_MAX_TURNS ?> ターン以内に倒せなかった）</li>
<?php endif; ?>
    </ol>
  </details>
<script>
// 戦闘シーンの再生：サーバーで決まった戦闘ログを1手ずつアニメーションで見せる
(function () {
  var root = document.currentScript.closest('[data-battle]');
  if (!root) return;
  var arena  = root.querySelector('.arena');
  var log    = JSON.parse(arena.dataset.log || '[]');
  if (!log.length || log[0].a === undefined) return;
  var units  = { ally: arena.querySelectorAll('.unit-side.ally .unit'), enemy: arena.querySelectorAll('.unit-side.enemy .unit') };
  var banner = arena.querySelector('.turn-banner');
  var logBox = root.querySelector('.battle-log ol');
  var items  = root.querySelectorAll('.battle-log li');
  var speedBtn = arena.querySelector('[data-speed]');
  var speeds = [1, 2, 4], speed = 1, timer = null, done = false;
  try { speed = speeds.indexOf(+localStorage.getItem('battleSpeed')) >= 0 ? +localStorage.getItem('battleSpeed') : 1; } catch (e) {}
  speedBtn.textContent = '×' + speed;

  function setHp(u, hp) {
    var max = +u.dataset.max;
    u.querySelector('.hpbar i').style.width = (max > 0 ? hp / max * 100 : 0) + '%';
    u.querySelector('.unit-hp').textContent = hp.toLocaleString() + ' / ' + max.toLocaleString();
    u.classList.toggle('down', hp <= 0);
  }
  function replay(el, cls) { el.classList.remove(cls); void el.offsetWidth; el.classList.add(cls); }
  function popDamage(u, e) {
    var pop = document.createElement('span');
    var has = function (t) { return e.tags.indexOf(t) >= 0; };
    pop.className = 'dmg-pop' + (has('crit') ? ' crit' : '') + (has('heal') ? ' heal' : '') + (has('guard') ? ' guard' : '') + (has('weak') ? ' weak' : '');
    pop.textContent = has('heal') ? '+' + e.damage.toLocaleString() : has('guard') ? 'GUARD' :
      (has('crit') ? '会心 ' : '') + (has('weak') ? '弱点 ' : '') + e.damage.toLocaleString();
    u.appendChild(pop);
    setTimeout(function () { pop.remove(); }, 900);
  }
  function show(li) { if (li) { li.classList.remove('pending'); logBox.scrollTop = logBox.scrollHeight; } }
  function wait(fn, ms) { timer = setTimeout(fn, ms / speed); }

  // 同じボスの全体攻撃はまとめて1手として再生する
  var steps = [];
  log.forEach(function (e, n) {
    e.n = n;
    var last = steps[steps.length - 1];
    if (e.tags.indexOf('aoe') >= 0 && last && last[0].tags.indexOf('aoe') >= 0 &&
        last[0].a === e.a && last[0].side === e.side && last[0].turn === e.turn) last.push(e);
    else steps.push([e]);
  });

  root.classList.add('playing');
  ['ally', 'enemy'].forEach(function (s) { units[s].forEach(function (u) { setHp(u, +u.dataset.max); }); });
  items.forEach(function (li) { li.classList.add('pending'); });

  var i = 0, turn = 0;
  function next() {
    if (i >= steps.length) return wait(finish, 500);
    var step = steps[i++], e0 = step[0];
    var foe = e0.side === 'ally' ? 'enemy' : 'ally';
    var actor = units[e0.side][e0.a];
    if (e0.turn !== turn) {
      turn = e0.turn;
      banner.textContent = 'ターン ' + turn;
      replay(banner, 'pop');
      show(root.querySelector('.battle-log li[data-turn="' + turn + '"]'));
    }
    actor.classList.add('acting');
    var aoe = e0.tags.indexOf('aoe') >= 0;
    var big = aoe || !!e0.skill;
    if (big) { banner.textContent = e0.skill ? e0.skill + '！' : '全体攻撃！'; replay(banner, 'pop'); if (aoe) replay(arena, 'flash'); }
    wait(function () {
      step.forEach(function (e) {
        var target = units[e.ts || foe][e.t];
        setHp(target, e.hp);
        var support = e.tags.indexOf('heal') >= 0 || e.tags.indexOf('guard') >= 0;
        replay(target, support ? 'buff' : e.hp <= 0 ? 'ko' : 'hit');
        popDamage(target, e);
        show(root.querySelector('.battle-log li[data-n="' + e.n + '"]'));
      });
      wait(function () {
        actor.classList.remove('acting');
        if (big) banner.textContent = 'ターン ' + turn;
        wait(next, 160);
      }, big ? 600 : 380);
    }, big ? 500 : 260);
  }

  function finish() {
    if (done) return;
    done = true;
    clearTimeout(timer);
    ['ally', 'enemy'].forEach(function (s) { units[s].forEach(function (u) { u.classList.remove('acting'); setHp(u, +u.dataset.final); }); });
    items.forEach(show);
    banner.textContent = '決着！';
    replay(banner, 'pop');
    root.classList.remove('playing');
    root.querySelectorAll('.battle-after').forEach(function (el) { replay(el, 'show'); });
  }

  speedBtn.addEventListener('click', function () {
    speed = speeds[(speeds.indexOf(speed) + 1) % speeds.length];
    speedBtn.textContent = '×' + speed;
    try { localStorage.setItem('battleSpeed', speed); } catch (e) {}
  });
  arena.querySelector('[data-skip]').addEventListener('click', finish);
  wait(next, 700);
})();
</script>
<?php
}

// 属性マーク（丸に一文字）
function element_chip(string $element): string
{
    [$label, $color] = ELEMENTS[$element] ?? ELEMENTS['none'];
    return '<span class="elem" style="--ec:' . h($color) . '" title="' . h($label) . '属性">' . h($label) . '</span>';
}

/**
 * カード1枚をタイル表示する
 * $c    : name, rarity, limit_break, attack, hp を含む配列（user_card_id があれば所持IDも表示）
 * $opts : href（リンク先）/ post（POST送信する値の配列。action は post_action）/
 *         badges（表示するバッジ [文字, 種類]）/ note（下部の補足文）/ disabled（選択不可で薄く表示）/
 *         diff（強化前カード。ステータスの増加量を表示）/ large（大きく表示）
 */
function render_card(array $c, array $opts = []): void
{
    $classes = ['card', 'r' . (int)$c['rarity'], 'lb' . (int)$c['limit_break']];
    if (!empty($opts['disabled'])) $classes[] = 'disabled';
    if (!empty($opts['large']))    $classes[] = 'large';
    $clickable = empty($opts['disabled']) && (isset($opts['href']) || isset($opts['post']));
    if ($clickable) $classes[] = 'clickable';

    $diff = function (string $key) use ($c, $opts): string {
        if (empty($opts['diff'])) return '';
        $d = (int)$c[$key] - (int)$opts['diff'][$key];
        return $d > 0 ? ' <span class="up">+' . $d . '</span>' : '';
    };

    ob_start(); ?>
  <div class="card-band"><span class="stars"><?= stars((int)$c['rarity']) ?></span><?php if (isset($c['element'])): ?><?= element_chip($c['element']) ?><?php endif; ?><?php if (isset($c['level'])): ?><span class="lv">Lv.<b><?= h($c['level']) ?></b></span><?php elseif (isset($c['user_card_id'])): ?><span class="uid">#<?= h($c['user_card_id']) ?></span><?php endif; ?></div>
<?php if (!empty($c['image'])): ?>
  <img class="card-art" src="resource/cards/<?= h($c['image']) ?>" alt="" loading="lazy">
<?php else: ?>
  <span class="emblem"><?= h(mb_substr($c['name'], 0, 1)) ?></span>
<?php endif; ?>
<?php if (!empty($opts['badges'])): ?>
  <div class="badges"><?php foreach ($opts['badges'] as [$text, $type]): ?><span class="badge badge-<?= h($type) ?>"><?= h($text) ?></span><?php endforeach; ?></div>
<?php endif; ?>
  <div class="card-body">
    <div class="card-name"><?= h($c['name']) ?></div>
    <div class="lb">限界突破 <?= str_repeat('<i class="on"></i>', (int)$c['limit_break']) ?><?= str_repeat('<i></i>', max(0, 2 - (int)$c['limit_break'])) ?></div>
<?php if (!empty($c['skill']) && isset(SKILLS[$c['skill']])): ?>
    <div class="skill-line" title="<?= h(SKILLS[$c['skill']][1]) ?>">SKILL <?= h(SKILLS[$c['skill']][0]) ?></div>
<?php endif; ?>
    <dl class="stats">
      <div><dt>ATK</dt><dd><?= h($c['attack']) ?><?= $diff('attack') ?></dd></div>
      <div><dt>HP</dt><dd><?= h($c['hp']) ?><?= $diff('hp') ?></dd></div>
    </dl>
<?php if (!empty($opts['note'])): ?>
    <div class="card-note"><?= h($opts['note']) ?></div>
<?php endif; ?>
  </div>
<?php
    $inner = ob_get_clean();
    $class = h(implode(' ', $classes));

    if ($clickable && isset($opts['post'])) {
        echo '<form method="post" action="' . h($opts['post_action']) . '" class="card-form">';
        foreach ($opts['post'] as $name => $value) {
            echo '<input type="hidden" name="' . h($name) . '" value="' . h($value) . '">';
        }
        echo '<button type="submit" class="' . $class . '">' . $inner . '</button></form>';
    } elseif ($clickable) {
        echo '<a href="' . h($opts['href']) . '" class="' . $class . '">' . $inner . '</a>';
    } else {
        echo '<div class="' . $class . '">' . $inner . '</div>';
    }
}

// $step: 限界突破の何画面目か（0 = 限界突破以外の画面）
function render_header(string $title, int $step = 0, string $lead = ''): void
{
    $steps = ['強化するカードを選ぶ', '素材を選ぶ', '強化結果'];
    $user   = current_user();
    $status = $user ? user_status($user['id']) : [];
    $badges = $user ? ['mission.php' => mission_status($user['id'])['claimable'], 'present.php' => present_count($user['id'])] : [];

    // ナビゲーション（ファイル名の先頭が一致したら選択中）
    $nav = [
        'menu.php'           => ['ホーム', 'menu'],
        'gacha.php'          => ['ガチャ', 'gacha'],
        'cards.php'          => ['カード', 'cards'],
        'team.php'           => ['編成', 'team'],
        'forge_entrance.php' => ['限界突破', 'forge'],
        'quest.php'          => ['クエスト', 'quest'],
        'battle.php'         => ['対戦', 'battle'],
        'ranking.php'        => ['ランキング', 'ranking'],
        'mission.php'        => ['ミッション', 'mission'],
        'present.php'        => ['プレゼント', 'present'],
    ];
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> | STAR CARDS</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Dela+Gothic+One&family=M+PLUS+Rounded+1c:wght@500;700;800&display=swap" rel="stylesheet">
<style>
  /* ステッカー風テーマ：生成り色の紙・黒いインクの太線・ずらした影・はっきりした色 */
  :root {
    --paper: #f4ecdc; --paper-2: #fffaf0; --ink: #1d1a22; --ink-soft: #4a4552;
    --tomato: #ff5b3a; --sun: #ffc22e; --mint: #23c49a; --sky: #3d9bff; --grape: #8b67ff; --rose: #ff7aa8;
    /* 旧テーマの変数名も残す（各ページのインラインスタイルが参照している） */
    --text: var(--ink); --muted: #7a7266; --line: var(--ink); --surface: var(--paper-2); --surface-2: #fff;
    --gold: #e0a100; --gold-deep: #b37d00; --primary: var(--tomato); --primary-soft: #ffe3c2;
    --danger: #d8361b; --ok: #13936f;
    --r3: var(--sky); --r4: var(--grape); --r5: var(--sun);
    --shadow: 4px 4px 0 var(--ink); --shadow-sm: 2px 2px 0 var(--ink);
    --round: "M PLUS Rounded 1c", "Hiragino Maru Gothic ProN", "Yu Gothic UI", sans-serif;
    --display: "Dela Gothic One", "M PLUS Rounded 1c", sans-serif;
  }
  * { box-sizing: border-box; }
  body { margin: 0; color: var(--ink); font-family: var(--round); font-weight: 500; font-size: 15px; line-height: 1.7; min-height: 100vh;
         background-color: var(--paper);
         background-image: radial-gradient(rgba(29,26,34,.09) 1px, transparent 1.2px);
         background-size: 14px 14px; }
  a { color: var(--ink); }
  .en { display: none; }   /* 英語の飾りラベルは使わない */
  b, strong { font-weight: 800; }

  /* ===== ヘッダー ===== */
  .appbar { position: sticky; top: 0; z-index: 10; background: var(--ink); color: var(--paper); padding: 10px 16px 0; border-bottom: 3px solid var(--ink); }
  .appbar-inner { max-width: 1040px; margin: 0 auto; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
  .appbar .brand { margin-right: auto; text-decoration: none; font-family: var(--display); font-size: 22px; letter-spacing: .04em; color: var(--ink);
                   background: var(--sun); padding: 0 12px; border: 2.5px solid var(--paper); border-radius: 10px; transform: rotate(-2deg); box-shadow: 3px 3px 0 var(--tomato); }
  .appbar .who { font-size: 13px; color: #cfc6b4; }
  .appbar .who strong { color: var(--paper); }
  .appbar form { margin: 0; }
  .appbar a.plain, .appbar button { color: var(--paper); background: transparent; border: 2px solid #5a5462; border-radius: 999px;
       padding: 2px 12px; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; }
  .appbar a.plain:hover, .appbar button:hover { border-color: var(--sun); color: var(--sun); }
  .gnav { max-width: 1040px; margin: 10px auto 0; display: flex; gap: 4px; overflow-x: auto; scrollbar-width: none; }
  .gnav::-webkit-scrollbar { display: none; }
  .gnav a { position: relative; flex: none; color: #cfc6b4; text-decoration: none; padding: 6px 14px 8px; font-size: 14px; font-weight: 800; border-radius: 10px 10px 0 0; }
  .gnav a:hover { color: var(--paper); }
  .gnav a.active { color: var(--ink); background: var(--paper); }
  .gnav .dot { position: absolute; top: 0; right: 0; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: var(--tomato); color: #fff;
               font-size: 11px; line-height: 16px; text-align: center; border: 1.5px solid var(--ink); }

  /* ステータス（ランク・スタミナ・通貨） */
  .status { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .status .rank, .status .stamina { position: relative; display: inline-flex; align-items: baseline; gap: 5px; padding: 0 10px 6px; border-radius: 8px; background: #2c2832; }
  .status small { font-size: 11px; color: #cfc6b4; }
  .status .rank small::before { content: "ランク"; } .status .stamina small::before { content: "スタミナ"; }
  .status small.en { display: inline; font-size: 0; }
  .status small.en::before { font-size: 11px; }
  .status b { font-family: var(--display); font-weight: 400; font-size: 17px; color: var(--paper); }
  .status .bar { position: absolute; left: 8px; right: 8px; bottom: 3px; height: 3px; border-radius: 2px; background: #4a4552; overflow: hidden; }
  .status .bar i { display: block; height: 100%; background: var(--sun); }
  .status .stamina .bar i { background: var(--mint); }
  .status .stamina em { font-style: normal; font-size: 11px; color: var(--mint); min-width: 2.4em; }
  .gems, .coin { display: inline-flex; align-items: center; gap: 6px; padding: 1px 12px 1px 6px; border-radius: 999px; font-family: var(--display); font-weight: 400; font-size: 14px;
                 background: var(--paper-2); color: var(--ink); border: 2px solid var(--ink); }
  .appbar .gems, .appbar .coin { border-color: var(--paper); }
  .gem-icon { display: inline-block; width: 13px; height: 13px; transform: rotate(45deg); flex: none; background: var(--sky); border: 2px solid var(--ink); border-radius: 2px; }
  .gold-icon { display: inline-block; width: 15px; height: 15px; border-radius: 50%; flex: none; background: var(--sun); border: 2px solid var(--ink); box-shadow: inset -2px -2px 0 #e09a00; }
  .stamina-icon { display: inline-block; width: 13px; height: 13px; border-radius: 3px; transform: rotate(45deg); background: var(--mint); border: 2px solid var(--ink); }

  /* ===== ページ ===== */
  main { max-width: 1040px; margin: 0 auto; padding: 26px 16px 64px; }
  h1 { font-family: var(--display); font-weight: 400; font-size: 30px; line-height: 1.2; margin: 0 0 18px; display: inline-block; position: relative; }
  h1::after { content: ""; position: absolute; left: -4px; right: -8px; bottom: 2px; height: 12px; background: var(--sun); z-index: -1; transform: skewX(-12deg); }
  .lead { color: var(--ink-soft); margin: -8px 0 22px; font-size: 14px; }
  h2 { font-family: var(--display); font-weight: 400; font-size: 18px; margin: 0 0 12px; }

  .panel { position: relative; background: var(--paper-2); border: 2.5px solid var(--ink); border-radius: 16px; padding: 20px; margin-bottom: 24px; box-shadow: var(--shadow); }
  .notice { padding: 10px 16px; margin: 0 0 20px; background: #fff3c4; border: 2.5px solid var(--ink); border-radius: 12px; font-weight: 700; box-shadow: var(--shadow-sm); }
  .notice-error { background: #ffd2c7; }
  .notice-ok { background: #c9f2df; }
  .muted { color: var(--muted); font-size: 13px; }
  .row { display: flex; gap: 10px; flex-wrap: wrap; }
  .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; }

  /* ボタン：押すと影の分だけ沈む */
  .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 8px 20px; font: inherit; font-weight: 800; font-size: 15px;
         color: var(--ink); background: var(--sun); border: 2.5px solid var(--ink); border-radius: 12px; box-shadow: var(--shadow);
         text-decoration: none; cursor: pointer; transition: transform .08s, box-shadow .08s; }
  .btn:hover { background: #ffd04f; }
  .btn:active { transform: translate(3px, 3px); box-shadow: 1px 1px 0 var(--ink); }
  .btn-ghost { background: var(--paper-2); }
  .btn-ghost:hover { background: #fff; }
  .btn[disabled] { opacity: .45; cursor: not-allowed; transform: none; box-shadow: var(--shadow-sm); }
  .mini { display: inline-block; font: inherit; font-size: 12px; font-weight: 700; color: var(--ink); background: var(--paper-2); border: 2px solid var(--ink);
          border-radius: 999px; padding: 1px 12px; cursor: pointer; text-decoration: none; box-shadow: var(--shadow-sm); }
  .mini:hover { background: var(--sun); }
  input[type=text] { font: inherit; color: var(--ink); padding: 8px 14px; background: #fff; border: 2.5px solid var(--ink); border-radius: 12px; min-width: 0; flex: 1; max-width: 340px; outline: none; }
  input[type=text]:focus { box-shadow: var(--shadow-sm); }
  .badge { display: inline-block; font-size: 11px; font-weight: 800; padding: 0 8px; border-radius: 6px; background: #fff; border: 2px solid var(--ink); color: var(--ink); line-height: 1.6; }
  .badge-new { background: var(--tomato); color: #fff; }
  .badge-team { background: var(--sun); }
  .badge-max { background: var(--mint); color: #fff; }
  .badge-warn { background: #ffd2c7; }
  .num { text-align: right; font-family: var(--display); font-weight: 400; font-variant-numeric: tabular-nums; }

  /* ステップ表示 */
  .steps { display: flex; list-style: none; padding: 0; margin: 0 0 24px; gap: 8px; counter-reset: step; }
  .steps li { flex: 1; counter-increment: step; padding: 6px 10px; font-size: 13px; font-weight: 800; color: var(--muted); background: var(--paper-2); border: 2px dashed var(--muted); border-radius: 12px; }
  .steps li::before { content: counter(step); display: inline-grid; place-items: center; width: 20px; height: 20px; margin-right: 6px; border-radius: 50%; background: var(--muted); color: #fff; font-size: 12px; }
  .steps li.done { color: var(--ink); border: 2px solid var(--ink); }
  .steps li.done::before { background: var(--mint); }
  .steps li.current { color: var(--ink); background: var(--sun); border: 2.5px solid var(--ink); box-shadow: var(--shadow-sm); }
  .steps li.current::before { background: var(--ink); }

  /* ===== カード ===== */
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(168px, 1fr)); gap: 18px; }
  .card-form { margin: 0; display: contents; }
  .card { --rc: var(--sky); position: relative; display: flex; flex-direction: column; width: 100%; min-height: 290px; padding: 0; text-align: left;
          font: inherit; color: var(--ink); text-decoration: none; background: #fff; border: 2.5px solid var(--ink); border-radius: 14px; overflow: hidden;
          box-shadow: var(--shadow); transition: transform .12s, box-shadow .12s; }
  .card.r3 { --rc: var(--sky); } .card.r4 { --rc: var(--grape); } .card.r5 { --rc: var(--sun); }
  .card.clickable { cursor: pointer; }
  .card.clickable:hover, .card.clickable:focus-visible { transform: translate(-2px, -3px) rotate(-.6deg); box-shadow: 6px 7px 0 var(--ink); outline: none; }
  .card.disabled { filter: grayscale(.9); opacity: .55; }
  /* イラスト部分：レア度の色＋斜めのストライプ */
  .card::before { content: ""; position: absolute; left: 0; right: 0; top: 0; height: 64%; z-index: 0;
                  background: repeating-linear-gradient(-45deg, rgba(255,255,255,.22) 0 8px, transparent 8px 16px), var(--rc); border-bottom: 2.5px solid var(--ink); }
  .card.r5::before { background: repeating-linear-gradient(-45deg, rgba(255,255,255,.35) 0 8px, transparent 8px 16px), linear-gradient(135deg, #ffd85e, #ff9f43); }
  .card-band { position: relative; z-index: 2; display: flex; align-items: center; gap: 4px; padding: 8px 10px 0; font-size: 12px; }
  .card-band .stars { color: #fff; letter-spacing: 1px; font-size: 14px; -webkit-text-stroke: 1px var(--ink); paint-order: stroke; text-shadow: 1px 1px 0 var(--ink); }
  .card-band .uid { margin-left: auto; font-family: var(--display); color: var(--ink); background: #fff; border: 2px solid var(--ink); border-radius: 6px; padding: 0 5px; font-size: 11px; }
  .card-band .lv { margin-left: auto; font-size: 11px; font-weight: 800; background: var(--ink); color: var(--paper); border-radius: 6px; padding: 0 6px; }
  .card-band .lv b { font-family: var(--display); font-weight: 400; font-size: 14px; color: #fff; }
  .card-band .elem + .lv, .card-band .elem + .uid { margin-left: 0; }
  .card-art { position: absolute; left: 0; right: 0; top: 18px; z-index: 1; width: 100%; height: 54%; object-fit: contain; pointer-events: none; transition: transform .2s; }
  .card.clickable:hover .card-art { transform: scale(1.06) rotate(2deg); }
  .emblem { position: absolute; right: 6px; top: 30px; z-index: 1; font-family: var(--display); font-size: 90px; line-height: 1; color: rgba(255,255,255,.5); }
  .card-body { position: relative; z-index: 2; margin-top: auto; padding: 8px 10px 10px; background: #fff; }
  .card-name { font-size: 15px; font-weight: 800; line-height: 1.3; }
  .lb { display: flex; align-items: center; gap: 3px; font-size: 11px; color: var(--muted); }
  .lb i { display: inline-block; width: 9px; height: 9px; transform: rotate(45deg); border: 1.5px solid var(--ink); margin-left: 3px; background: #fff; }
  .lb i.on { background: var(--tomato); }
  .skill-line { display: inline-block; margin-top: 3px; font-size: 10px; font-weight: 800; padding: 0 6px; border-radius: 5px; background: var(--ink); color: var(--paper); }
  .stats { display: flex; gap: 12px; margin: 6px 0 0; padding-top: 6px; border-top: 2px dashed #d8cfbf; }
  .stats div { display: flex; align-items: baseline; gap: 4px; flex-wrap: wrap; }
  .stats dt { font-size: 11px; font-weight: 800; color: var(--muted); }
  .stats dd { margin: 0; font-family: var(--display); font-size: 16px; font-variant-numeric: tabular-nums; }
  .stats .up { color: var(--ok); font-size: 12px; }
  .badges { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
  .card .badges { position: absolute; top: 34px; left: 8px; right: 8px; margin: 0; z-index: 3; flex-direction: column; align-items: flex-start; gap: 3px; }
  .card-note { margin-top: 4px; font-size: 11px; color: var(--muted); }
  .card.large { min-height: 340px; }
  .card.large .card-name { font-size: 19px; }
  .card.lb2 { box-shadow: 4px 4px 0 var(--ink), 0 0 0 4px var(--sun) inset; }
  .elem { display: inline-grid; place-items: center; width: 20px; height: 20px; border-radius: 50%; flex: none; font-size: 11px; font-weight: 800;
          color: var(--ink); background: var(--ec); border: 2px solid var(--ink); margin-right: 3px; }

  /* 強化前→強化後（限界突破） */
  .compare { display: grid; grid-template-columns: minmax(0,1fr) auto minmax(0,1fr); gap: 18px; align-items: center; max-width: 520px; margin: 0 auto; }
  .compare .arrow { font-size: 0; width: 40px; height: 40px; border-radius: 50%; background: var(--tomato); border: 2.5px solid var(--ink); position: relative; box-shadow: var(--shadow-sm); }
  .compare .arrow::before { content: ""; position: absolute; left: 9px; top: 12px; width: 10px; height: 10px; border-top: 3px solid #fff; border-right: 3px solid #fff; transform: rotate(45deg); }
  .compare .label { font-size: 13px; font-weight: 800; text-align: center; margin-bottom: 8px; }
  .idchange { text-align: center; font-size: 15px; margin: 22px 0 4px; }
  .idchange strong { font-family: var(--display); font-weight: 400; font-size: 28px; margin: 0 6px; }
  .celebrate { text-align: center; margin: 0 0 18px; }
  .celebrate .ja { display: inline-block; font-family: var(--display); font-size: 26px; padding: 2px 18px; background: var(--sun); border: 2.5px solid var(--ink); border-radius: 12px; transform: rotate(-2deg); box-shadow: var(--shadow); }
  @media (max-width: 600px) {
    .compare { grid-template-columns: 1fr; justify-items: stretch; }
    .compare .arrow { transform: rotate(90deg); justify-self: center; }
    .steps li { font-size: 11px; padding: 4px 6px; }
  }

  /* グループ見出し（限界突破の一覧など） */
  .group { margin-bottom: 30px; }
  .group-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
  .group-head h2 { margin: 0; }
  .group-head .count { font-family: var(--display); background: var(--ink); color: var(--paper); border-radius: 8px; padding: 0 8px; }
  .group-head .to { margin-left: auto; font-size: 13px; color: var(--muted); }

  /* ===== 一覧・表 ===== */
  .user-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 14px; }
  .user-item { display: flex; align-items: center; gap: 12px; padding: 12px 14px; background: var(--paper-2); border: 2.5px solid var(--ink); border-radius: 14px; box-shadow: var(--shadow-sm); }
  .user-item.me { background: #fff3c4; }
  .avatar { width: 44px; height: 44px; flex: none; display: grid; place-items: center; border-radius: 12px; font-family: var(--display); font-size: 20px;
            background: var(--rose); color: #fff; border: 2.5px solid var(--ink); -webkit-text-stroke: 1px var(--ink); }
  .user-item:nth-child(3n+2) .avatar { background: var(--sky); } .user-item:nth-child(3n) .avatar { background: var(--mint); }
  .user-item .info { flex: 1; min-width: 0; }
  .user-item .name { font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .user-item form { margin: 0; }
  .user-item .btn { padding: 4px 14px; font-size: 13px; }
  .table-wrap { overflow-x: auto; }
  table.list { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; }
  table.list th { text-align: left; font-size: 12px; font-weight: 800; padding: 6px 10px; border-bottom: 2.5px solid var(--ink); white-space: nowrap; }
  table.list td { padding: 8px 10px; border-bottom: 1.5px dashed #d8cfbf; white-space: nowrap; }
  table.list tr.me td { background: #fff3c4; }
  .r-text-3 { color: var(--sky); } .r-text-4 { color: var(--grape); } .r-text-5 { color: #e08a00; }
  .win { color: var(--ok); font-weight: 800; } .lose { color: var(--danger); font-weight: 800; }
  .filters { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; align-items: center; }
  .filters a { text-decoration: none; padding: 2px 14px; font-size: 13px; font-weight: 800; background: var(--paper-2); border: 2px solid var(--ink); border-radius: 999px; }
  .filters a.active { background: var(--ink); color: var(--paper); }
  .filters .sep { width: 2px; height: 20px; background: var(--ink); margin: 0 4px; opacity: .3; }
  .stat-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; margin-bottom: 24px; }
  .stat { padding: 12px 16px; background: var(--paper-2); border: 2.5px solid var(--ink); border-radius: 14px; box-shadow: var(--shadow-sm); }
  .stat .label { display: block; font-size: 12px; font-weight: 800; color: var(--muted); }
  .stat b { font-family: var(--display); font-weight: 400; font-size: 26px; }
  .power { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
  .power .label { font-size: 13px; font-weight: 800; }
  .power b { font-family: var(--display); font-weight: 400; font-size: 30px; color: var(--tomato); -webkit-text-stroke: 1px var(--ink); }

  /* ===== ガチャ ===== */
  .banner { position: relative; overflow: hidden; padding: 26px 24px; margin-bottom: 24px; border: 2.5px solid var(--ink); border-radius: 18px; box-shadow: var(--shadow);
            background: repeating-conic-gradient(from 0deg at 80% 50%, #ffcf4a 0 10deg, #ffb52e 10deg 20deg); }
  .banner h2 { display: inline-block; font-size: 30px; margin: 0 0 10px; padding: 0 12px; background: #fff; border: 2.5px solid var(--ink); border-radius: 10px; transform: rotate(-1.5deg); box-shadow: var(--shadow-sm); }
  .banner p { margin: 0 0 14px; max-width: 520px; font-weight: 700; }
  .banner .pickup { display: flex; gap: 8px; flex-wrap: wrap; }
  .banner .pickup span { padding: 0 10px; font-size: 13px; font-weight: 800; background: var(--tomato); color: #fff; border: 2px solid var(--ink); border-radius: 8px; }
  .banner-art { position: absolute; right: 6px; bottom: -14px; display: flex; pointer-events: none; }
  .banner-art img { width: 190px; height: 240px; object-fit: contain; filter: drop-shadow(4px 4px 0 var(--ink)); }
  .banner-art img + img { margin-left: -64px; margin-top: -26px; }
  .banner > *:not(.banner-art) { position: relative; z-index: 1; }
  @media (max-width: 720px) { .banner-art { opacity: .3; } }
  .pity { display: flex; align-items: center; gap: 10px; margin-top: 16px; font-size: 13px; font-weight: 800; flex-wrap: wrap; }
  .pity-bar, .meter-bar { height: 12px; border: 2px solid var(--ink); border-radius: 999px; background: #fff; overflow: hidden; }
  .pity-bar { flex: 1; max-width: 300px; }
  .pity-bar i, .meter-bar i { display: block; height: 100%; background: repeating-linear-gradient(-45deg, var(--tomato) 0 6px, #ff7d5f 6px 12px); }
  .meter-bar.done i { background: repeating-linear-gradient(-45deg, var(--mint) 0 6px, #4fd8b2 6px 12px); }
  .pull-row { display: flex; gap: 16px; flex-wrap: wrap; align-items: center; }
  .pull-row form { margin: 0; }
  .pull-row .btn { font-size: 17px; padding: 10px 22px; }
  .pull-row .btn small { display: inline-flex; align-items: center; gap: 4px; margin-left: 4px; font-family: var(--display); font-size: 14px; }
  .pull-row form:last-of-type .btn { background: var(--tomato); color: #fff; }
  .gacha-result .grid > * { animation: cardIn .45s both; }
  .gacha-result .card.r5 { box-shadow: 4px 4px 0 var(--ink), 0 0 0 4px #ffd85e; }
  @keyframes cardIn { 0% { transform: translateY(20px) rotate(-6deg) scale(.8); opacity: 0; } 70% { transform: rotate(2deg) scale(1.04); opacity: 1; } 100% { transform: none; } }
  .warp { position: fixed; inset: 0; z-index: 100; display: grid; place-items: center; overflow: hidden; cursor: pointer;
          background: repeating-conic-gradient(from 0deg, var(--wc) 0 12deg, var(--ink) 12deg 24deg); animation: warpSpin 2.2s linear forwards; }
  .warp[hidden] { display: none; }
  .warp .tunnel { display: none; }
  .warp .orb { width: 34vmin; height: 34vmin; border-radius: 50%; background: #fff; border: 6px solid var(--ink); box-shadow: 8px 8px 0 var(--ink);
               display: grid; place-items: center; animation: orbPop 2.2s ease-in forwards; }
  .warp .orb::after { content: "?"; font-family: var(--display); font-size: 18vmin; color: var(--ink); }
  .warp .skip { position: absolute; right: 16px; top: 16px; z-index: 2; font: inherit; font-weight: 800; color: var(--ink); background: #fff; border: 2.5px solid var(--ink); border-radius: 999px; padding: 4px 16px; cursor: pointer; }
  .warp .hint { position: absolute; bottom: 24px; left: 50%; transform: translateX(-50%); padding: 2px 14px; font-weight: 800; color: var(--ink); background: #fff; border: 2.5px solid var(--ink); border-radius: 999px; }
  .warp .hint::before { content: "タップでスキップ"; } .warp .hint { font-size: 0; } .warp .hint::before { font-size: 13px; }
  @keyframes warpSpin { to { background-position: 0 0; filter: hue-rotate(0); } }
  @keyframes orbPop { 0% { transform: scale(0) rotate(-30deg); } 25% { transform: scale(1.1) rotate(8deg); } 35%, 70% { transform: scale(1) rotate(0); }
                      75% { transform: scale(1.1) rotate(-6deg); } 80% { transform: scale(1.1) rotate(6deg); } 100% { transform: scale(14); opacity: 0; } }

  /* ===== チーム編成 ===== */
  .slots { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
  .slot-wrap { display: flex; flex-direction: column; gap: 8px; }
  .slot-wrap.selected .card { box-shadow: 4px 4px 0 var(--ink), 0 0 0 4px var(--tomato); }
  .slot-label { font-family: var(--display); font-size: 13px; }
  .slot-label::before { content: "枠 "; }
  .slot-empty { display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 4px; min-height: 290px; border: 2.5px dashed var(--ink);
                border-radius: 14px; text-decoration: none; font-size: 13px; font-weight: 800; background: rgba(255,255,255,.5); }
  .slot-empty::before { content: "+"; font-family: var(--display); font-size: 40px; line-height: 1; }
  .slot-empty:hover { background: #fff3c4; }
  .slot-actions { display: flex; gap: 6px; flex-wrap: wrap; }
  .slot-actions form { margin: 0; }
  @media (max-width: 720px) { .slots { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

  /* ===== バトル ===== */
  .vs { display: grid; grid-template-columns: 1fr auto 1fr; gap: 14px; align-items: center; text-align: center; }
  .vs .side .name { font-weight: 800; font-size: 18px; }
  .vs .vs-mark { font-family: var(--display); font-size: 30px; color: #fff; background: var(--tomato); border: 2.5px solid var(--ink); border-radius: 50%; width: 64px; height: 64px; display: grid; place-items: center; transform: rotate(-8deg); box-shadow: var(--shadow-sm); }
  .result-word { font-family: var(--display); font-size: 46px; text-align: center; margin: 0; -webkit-text-stroke: 2px var(--ink); paint-order: stroke; text-shadow: 4px 4px 0 var(--ink); }
  .result-word.win { color: var(--sun); }
  .result-word.lose { color: #b8b0a2; }
  .arena { position: relative; overflow: hidden; margin: 16px 0; padding: 14px 10px 16px; border: 2.5px solid var(--ink); border-radius: 16px;
           background: linear-gradient(#ffd9cc 0 50%, #cdeedd 50% 100%); }
  .arena::before { content: ""; position: absolute; left: 0; right: 0; top: 50%; border-top: 2.5px dashed var(--ink); opacity: .35; }
  .arena.flash { animation: arenaFlash .5s; }
  .battle-controls { position: absolute; top: 10px; right: 10px; z-index: 3; display: flex; gap: 6px; }
  .battle:not(.playing) .battle-controls { display: none; }
  .side-label { position: relative; font-size: 12px; font-weight: 800; text-align: center; margin-bottom: 6px; }
  .unit-row { position: relative; display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; }
  .turn-banner { position: relative; z-index: 2; width: max-content; margin: 12px auto; padding: 0 16px; font-family: var(--display); font-size: 20px; background: #fff; border: 2.5px solid var(--ink); border-radius: 10px; box-shadow: var(--shadow-sm); }
  .turn-banner.pop { animation: bannerPop .45s; }
  .unit { position: relative; width: 112px; padding: 6px; background: #fff; border: 2.5px solid var(--ink); border-radius: 12px; text-align: center; box-shadow: var(--shadow-sm);
          transition: transform .18s ease-out, filter .4s; }
  .unit img { width: 100%; height: 70px; object-fit: contain; }
  .unit.boss { width: 156px; background: #fff3c4; }
  .unit.boss img { height: 98px; }
  .unit.down { filter: grayscale(1); opacity: .5; }
  .unit.acting { z-index: 2; background: #fff3c4; }
  .unit-side.ally .unit.acting { transform: translateY(-14px) rotate(-3deg); }
  .unit-side.enemy .unit.acting { transform: translateY(14px) rotate(3deg); }
  .unit.hit { animation: hitShake .35s; }
  .unit.ko { animation: koFall .6s forwards; }
  .unit.buff { animation: buffJump .5s; }
  .unit-name { display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .unit-name .elem { width: 16px; height: 16px; font-size: 9px; border-width: 1.5px; }
  .unit-hp { font-family: var(--display); font-size: 11px; color: var(--muted); }
  .hpbar { height: 9px; border: 2px solid var(--ink); border-radius: 999px; background: #fff; overflow: hidden; margin: 3px 0 1px; }
  .hpbar i { display: block; height: 100%; background: var(--mint); transition: width .25s; }
  .unit-side.enemy .hpbar i { background: var(--tomato); }
  .dmg-pop { position: absolute; left: 50%; top: 22px; z-index: 4; pointer-events: none; white-space: nowrap; font-family: var(--display); font-size: 24px; color: #fff;
             -webkit-text-stroke: 1.5px var(--ink); paint-order: stroke; text-shadow: 2px 2px 0 var(--ink); animation: dmgUp .9s forwards; }
  .dmg-pop.crit { font-size: 30px; color: var(--sun); }
  .dmg-pop.weak { color: #ff9f43; }
  .dmg-pop.heal { color: var(--mint); }
  .dmg-pop.guard { color: var(--sky); font-size: 18px; }
  .boss-tag, .crit-tag, .ko-tag, .weak-tag, .resist-tag, .skill-tag { display: inline-block; font-size: 10px; font-weight: 800; padding: 0 6px; border-radius: 5px; margin: 0 3px; border: 1.5px solid var(--ink); line-height: 1.5; }
  .boss-tag { background: var(--tomato); color: #fff; }
  .crit-tag { background: var(--sun); }
  .ko-tag { background: #fff; }
  .weak-tag { background: #ff9f43; }
  .resist-tag { background: #e6e0d4; }
  .skill-tag { background: var(--ink); color: var(--paper); font-size: 11px; }
  .heal-num { color: var(--ok); font-weight: 800; }
  .battle.playing .battle-after { display: none; }
  .battle-after.show { animation: cardIn .5s both; }
  .battle.playing .battle-log li.pending { display: none; }
  .battle-log { margin-top: 10px; }
  .battle-log summary { cursor: pointer; font-weight: 800; margin-bottom: 8px; }
  .battle-log ol { list-style: none; margin: 0; padding: 8px; max-height: 300px; overflow-y: auto; font-size: 13px; background: #fff; border: 2px solid var(--ink); border-radius: 12px; }
  .battle-log li { padding: 2px 8px; border-left: 4px solid transparent; }
  .battle-log li.ally { border-left-color: var(--mint); }
  .battle-log li.enemy { border-left-color: var(--tomato); }
  .battle-log li.aoe { background: #fff0e6; }
  .battle-log li.turn { font-family: var(--display); margin-top: 6px; }
  .battle-log .dmg { font-family: var(--display); }
  @keyframes bannerPop { 0% { transform: scale(.5) rotate(-6deg); } 60% { transform: scale(1.15) rotate(2deg); } 100% { transform: none; } }
  @keyframes hitShake { 0%,100% { transform: none; } 25% { transform: translateX(-7px) rotate(-3deg); } 50% { transform: translateX(6px) rotate(2deg); } 75% { transform: translateX(-3px); } }
  @keyframes koFall { to { transform: translateY(6px) rotate(8deg); } }
  @keyframes buffJump { 40% { transform: translateY(-10px); } }
  @keyframes dmgUp { 0% { transform: translate(-50%, 8px) scale(.6); opacity: 0; } 20% { transform: translate(-50%, 0) scale(1.2); opacity: 1; } 100% { transform: translate(-50%, -30px); opacity: 0; } }
  @keyframes arenaFlash { 30% { filter: brightness(1.25) saturate(1.4); } }
  @media (max-width: 600px) { .unit { width: 82px; padding: 4px; } .unit img { height: 50px; } .unit.boss { width: 116px; } .unit.boss img { height: 70px; } }

  /* ===== クエスト ===== */
  .chapter { margin-bottom: 30px; }
  .chapter-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
  .chapter-head h2 { margin: 0; font-size: 20px; }
  .chapter-head .muted { width: 100%; margin-top: -4px; }
  .chapter-head .star-total { margin-left: auto; font-family: var(--display); padding: 0 10px; background: var(--ink); color: var(--sun); border-radius: 8px; }
  .stage-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
  .stage { position: relative; display: flex; flex-direction: column; gap: 6px; padding: 14px 16px; background: var(--paper-2); border: 2.5px solid var(--ink); border-radius: 14px; box-shadow: var(--shadow-sm); }
  .stage.boss { background: #ffe1d6; }
  .stage.locked { opacity: .5; box-shadow: none; border-style: dashed; }
  .stage.next { background: #fff3c4; box-shadow: var(--shadow); }
  .stage.next::after { content: "つぎはここ！"; position: absolute; top: -12px; right: 14px; font-size: 12px; font-weight: 800; padding: 0 10px; color: #fff; background: var(--tomato); border: 2px solid var(--ink); border-radius: 999px; transform: rotate(3deg); }
  .stage-top { display: flex; align-items: center; gap: 8px; }
  .stage-no { font-family: var(--display); font-size: 15px; padding: 0 8px; background: var(--ink); color: var(--paper); border-radius: 7px; }
  .stage-name { font-weight: 800; font-size: 16px; }
  .stage-stars { margin-left: auto; letter-spacing: 1px; color: #d8cfbf; -webkit-text-stroke: 1px var(--ink); }
  .stage-stars .on { color: var(--sun); }
  .stage-enemies { display: flex; gap: 4px; }
  .stage-enemies img { width: 36px; height: 36px; object-fit: contain; background: #fff; border: 2px solid var(--ink); border-radius: 50%; padding: 2px; }
  .stage-foot { display: flex; align-items: center; gap: 8px; margin-top: auto; padding-top: 6px; flex-wrap: wrap; }
  .stage-foot form { margin: 0 0 0 auto; }
  .stage-foot .btn { padding: 4px 14px; font-size: 14px; }
  .star-goals { list-style: none; padding: 0; margin: 14px auto 0; max-width: 360px; font-size: 14px; font-weight: 700; }
  .star-goals li { display: flex; gap: 8px; padding: 2px 0; color: var(--muted); }
  .star-goals li::before { content: "★"; color: #d8cfbf; -webkit-text-stroke: 1px var(--ink); }
  .star-goals li.ok { color: var(--ink); }
  .star-goals li.ok::before { color: var(--sun); }

  /* ===== ミッション・プレゼント ===== */
  .tabs { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
  .tabs a { padding: 4px 16px; font-weight: 800; text-decoration: none; background: var(--paper-2); border: 2.5px solid var(--ink); border-radius: 12px; }
  .tabs a.active { background: var(--ink); color: var(--paper); }
  .mission-list, .present-list { display: grid; gap: 10px; margin-bottom: 20px; }
  .mission, .present { display: grid; align-items: center; gap: 14px; padding: 12px 16px; background: var(--paper-2); border: 2.5px solid var(--ink); border-radius: 14px; box-shadow: var(--shadow-sm); }
  .mission { grid-template-columns: 1fr auto auto; }
  .mission.ready { background: #fff3c4; box-shadow: var(--shadow); }
  .mission.claimed { opacity: .5; box-shadow: none; }
  .mission .t { font-weight: 800; }
  .mission .p { font-family: var(--display); font-size: 12px; color: var(--muted); margin-top: 2px; }
  .mission .meter-bar { margin-top: 6px; max-width: 320px; }
  .mission form, .present form { margin: 0; }
  .reward { display: inline-flex; align-items: center; gap: 6px; font-family: var(--display); font-size: 16px; }
  .present { grid-template-columns: auto 1fr auto; }
  .present .icon { width: 44px; height: 44px; display: grid; place-items: center; border-radius: 12px; background: #fff; border: 2.5px solid var(--ink); }
  .present .msg { font-size: 12px; color: var(--muted); }
  @media (max-width: 560px) { .mission { grid-template-columns: 1fr auto; } .mission .reward { grid-column: 1; } }

  /* ===== ホーム ===== */
  .home-hero { position: relative; display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); gap: 18px; align-items: center; padding: 20px; margin-bottom: 22px; overflow: hidden;
               background: repeating-conic-gradient(from 0deg at 25% 55%, #bfe4ff 0 10deg, #a8d8ff 10deg 20deg); border: 2.5px solid var(--ink); border-radius: 20px; box-shadow: var(--shadow); }
  .home-hero .leader { position: relative; aspect-ratio: 1; display: grid; place-items: center; }
  .home-hero .leader::before { content: ""; position: absolute; inset: 10%; border-radius: 50%; background: #fff; border: 3px solid var(--ink); box-shadow: 5px 5px 0 var(--ink); }
  .home-hero .leader img { position: relative; z-index: 1; width: 78%; height: 78%; object-fit: contain; animation: bob 2.6s ease-in-out infinite; }
  @keyframes bob { 50% { transform: translateY(-6px) rotate(-2deg); } }
  .home-hero .hello { display: inline-block; font-size: 12px; font-weight: 800; padding: 0 10px; background: #fff; border: 2px solid var(--ink); border-radius: 999px; }
  .home-hero .lname { display: flex; align-items: center; gap: 6px; font-family: var(--display); font-size: 24px; margin: 8px 0; }
  .home-hero .lname small { font-family: var(--round); }
  .home-hero .say { position: relative; padding: 12px 16px; margin: 0 0 16px 10px; font-weight: 700; background: #fff; border: 2.5px solid var(--ink); border-radius: 16px; box-shadow: var(--shadow-sm); }
  .home-hero .say::before { content: ""; position: absolute; left: -12px; top: 16px; border: 7px solid transparent; border-right: 10px solid var(--ink); }
  .home-hero .say::after { content: ""; position: absolute; left: -7px; top: 18px; border: 5px solid transparent; border-right: 7px solid #fff; }
  .home-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .home-actions form { margin: 0; }
  .home-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 14px; margin-bottom: 26px; }
  .tile { position: relative; display: flex; flex-direction: column; justify-content: flex-end; gap: 0; padding: 14px; min-height: 92px; text-decoration: none; color: var(--ink);
          background: var(--paper-2); border: 2.5px solid var(--ink); border-radius: 16px; box-shadow: var(--shadow); transition: transform .1s, box-shadow .1s; }
  .tile:hover { transform: translate(-1px, -2px); box-shadow: 5px 6px 0 var(--ink); }
  .tile:active { transform: translate(3px, 3px); box-shadow: 1px 1px 0 var(--ink); }
  .tile .t { font-family: var(--display); font-size: 19px; line-height: 1.3; }
  .tile .d { font-size: 12px; color: var(--ink-soft); }
  .tile .dot { position: absolute; top: -9px; right: -9px; min-width: 26px; height: 26px; padding: 0 6px; border-radius: 13px; background: var(--tomato); color: #fff;
               font-size: 13px; font-weight: 800; line-height: 22px; text-align: center; border: 2.5px solid var(--ink); }
  .tile.big { grid-column: span 2; background: var(--tomato); color: #fff; }
  .tile.big .t { font-size: 26px; -webkit-text-stroke: 1px var(--ink); paint-order: stroke; text-shadow: 2px 2px 0 var(--ink); }
  .tile.big .d { color: #fff; }
  .home-tiles .tile:nth-child(2) { background: var(--mint); } .home-tiles .tile:nth-child(3) { background: var(--sky); }
  .home-tiles .tile:nth-child(4) { background: var(--sun); } .home-tiles .tile:nth-child(5) { background: var(--grape); }
  .home-tiles .tile:nth-child(2) .t, .home-tiles .tile:nth-child(3) .t, .home-tiles .tile:nth-child(5) .t { color: #fff; -webkit-text-stroke: 1px var(--ink); paint-order: stroke; text-shadow: 2px 2px 0 var(--ink); }
  .home-tiles .tile:nth-child(2) .d, .home-tiles .tile:nth-child(3) .d, .home-tiles .tile:nth-child(5) .d { color: #fff; }
  @media (max-width: 640px) { .home-hero { grid-template-columns: 1fr; } .home-hero .leader { max-width: 220px; width: 100%; margin: 0 auto; } .home-hero .say { margin-left: 0; }
                              .home-hero .say::before, .home-hero .say::after { display: none; } }

  /* ===== カード詳細 ===== */
  .detail { display: grid; grid-template-columns: minmax(0, 290px) 1fr; gap: 24px; align-items: start; }
  .detail .spec dl { display: grid; grid-template-columns: auto 1fr; gap: 6px 16px; margin: 0 0 18px; padding: 14px 16px; background: var(--paper-2); border: 2.5px solid var(--ink); border-radius: 14px; }
  .detail .spec dt { font-size: 13px; font-weight: 800; color: var(--muted); }
  .detail .spec dd { margin: 0; font-weight: 800; }
  .detail .flavor { position: relative; margin: 0 0 16px; padding: 10px 14px; font-weight: 700; background: #fff; border: 2.5px solid var(--ink); border-radius: 14px; }
  .lvup { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
  .lvup form { margin: 0; }
  @media (max-width: 640px) { .detail { grid-template-columns: 1fr; } .detail > .card-wrap { max-width: 250px; } }
</style>
</head>
<body>
<header class="appbar">
  <div class="appbar-inner">
    <a class="brand" href="menu.php">STAR CARDS</a>
<?php if ($user && $status): ?>
    <div class="status">
      <span class="rank" title="次のランクまで <?= number_format($status['exp_next'] - $status['exp']) ?> EXP">
        <small class="en">Rank</small><b><?= h($status['player_rank']) ?></b>
        <i class="bar"><i style="width:<?= round($status['exp'] / $status['exp_next'] * 100) ?>%"></i></i>
      </span>
      <span class="stamina" data-stamina="<?= h($status['stamina']) ?>" data-max="<?= h($status['stamina_max']) ?>" data-next="<?= h($status['stamina_next']) ?>" data-regen="<?= STAMINA_REGEN_SEC ?>">
        <small class="en">Stamina</small><b><span data-now><?= h($status['stamina']) ?></span>/<?= h($status['stamina_max']) ?></b>
        <i class="bar"><i style="width:<?= min(100, round($status['stamina'] / $status['stamina_max'] * 100)) ?>%"></i></i>
        <em data-timer></em>
      </span>
      <span class="coin" title="ゴールド"><i class="gold-icon"></i><?= number_format($status['gold']) ?></span>
      <span class="gems" title="石"><i class="gem-icon"></i><?= number_format($status['gems']) ?></span>
    </div>
    <span class="who"><strong><?= h($user['name']) ?></strong></span>
    <a class="plain" href="select_user.php">切替</a>
    <form action="select_user.php" method="post"><button type="submit" name="logout">ログアウト</button></form>
<?php else: ?>
    <span class="who">ログインしていません</span>
    <a class="plain" href="select_user.php">ユーザーを選択</a>
<?php endif; ?>
  </div>
  <nav class="gnav">
<?php foreach ($nav as $file => [$label, $key]):
    $active = $script === $file || ($key === 'forge' && str_starts_with($script, 'forge_')); ?>
    <a href="<?= h($file) ?>" class="<?= $active ? 'active' : '' ?>"><?= h($label) ?><?php if (!empty($badges[$file])): ?><span class="dot"><?= h($badges[$file]) ?></span><?php endif; ?></a>
<?php endforeach; ?>
  </nav>
</header>
<script>
// スタミナの回復をその場で表示する（実際の回復はサーバーで計算）
(function () {
  var el = document.querySelector('.appbar .stamina');
  if (!el) return;
  var now = +el.dataset.stamina, max = +el.dataset.max, next = +el.dataset.next, regen = +el.dataset.regen;
  var num = el.querySelector('[data-now]'), timer = el.querySelector('[data-timer]'), bar = el.querySelector('.bar i');
  function tick() {
    if (now >= max) { timer.textContent = ''; return; }
    if (--next <= 0) { now++; next = regen; num.textContent = now; bar.style.width = Math.min(100, now / max * 100) + '%'; }
    timer.textContent = now >= max ? '' : Math.floor(next / 60) + ':' + String(next % 60).padStart(2, '0');
  }
  tick(); setInterval(tick, 1000);
})();
</script>
<main>
<?php if ($step > 0): ?>
<ol class="steps">
<?php foreach ($steps as $i => $label): ?>
  <li class="<?= $i + 1 === $step ? 'current' : ($i + 1 < $step ? 'done' : '') ?>"><?= h($label) ?></li>
<?php endforeach; ?>
</ol>
<?php endif; ?>
<h1><?= h($title) ?></h1>
<?php if ($lead !== ''): ?>
<p class="lead"><?= h($lead) ?></p>
<?php endif; ?>
<?php
}

function render_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}
