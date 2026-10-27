<?php
// 共通処理（セッション・DB接続・ログイン確認・表示ヘルパー）

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// チーム編成（slot => カード情報）
function team_cards(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT t.slot, uc.id AS user_card_id, uc.card_id, c.name, c.image, c.rarity, c.limit_break, c.attack, c.hp
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
                    'hp' => (int)$c['hp'], 'max_hp' => (int)$c['hp'], 'boss' => false];
    }
    return $units;
}

/**
 * 味方 → 敵 の順に1体ずつ攻撃するターン制バトル
 * - 攻撃する相手は、生き残っている相手からランダム
 * - ダメージは ATK の 85〜115%。15% の確率で会心（1.5倍）
 * - ボスは3ターンごとに全体攻撃（全員に ATK の 60%）
 * - BATTLE_MAX_TURNS ターンで敵を全滅できなければ時間切れで敗北
 * 戻り値: result（win/lose）, turns, ko（倒された味方の数）, allies, enemies（戦闘後のHP）, log
 */
function simulate_battle(array $allies, array $enemies): array
{
    $units = ['ally' => array_values($allies), 'enemy' => array_values($enemies)];
    $log   = [];
    $alive = function (string $side) use (&$units): array {
        return array_keys(array_filter($units[$side], fn($u) => $u['hp'] > 0));
    };

    $hit = function (string $side, int $i, int $t, float $rate, array $tags) use (&$units, &$log, &$turn): void {
        $foe    = $side === 'ally' ? 'enemy' : 'ally';
        $crit   = random_int(1, 100) <= 15;
        $damage = (int)round($units[$side][$i]['attack'] * $rate * random_int(85, 115) / 100 * ($crit ? 1.5 : 1));
        $units[$foe][$t]['hp'] = max(0, $units[$foe][$t]['hp'] - $damage);
        $ko = $units[$foe][$t]['hp'] === 0;
        if ($crit) $tags[] = 'crit';
        if ($ko)   $tags[] = 'ko';
        $log[] = ['turn' => $turn, 'side' => $side, 'tags' => $tags,
                  'actor' => $units[$side][$i]['name'], 'target' => $units[$foe][$t]['name'], 'damage' => $damage];
    };

    $result = 'lose';
    for ($turn = 1; $turn <= BATTLE_MAX_TURNS; $turn++) {
        foreach (['ally' => 'enemy', 'enemy' => 'ally'] as $side => $foe) {
            foreach (array_keys($units[$side]) as $i) {
                if ($units[$side][$i]['hp'] <= 0) continue;
                $targets = $alive($foe);
                if (!$targets) break 2;
                if (!empty($units[$side][$i]['boss']) && $turn % 3 === 0) {
                    foreach ($targets as $t) $hit($side, $i, $t, 0.6, ['aoe']);
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

// バトル後のユニットのHPと、ターンごとの戦闘ログを表示する
function render_battle(array $battle, string $allyLabel, string $enemyLabel): void
{
    $side = function (array $units, string $label, string $cls): void { ?>
  <div class="unit-side <?= $cls ?>">
    <div class="muted"><?= h($label) ?></div>
    <div class="unit-row">
<?php foreach ($units as $u): $rate = $u['max_hp'] > 0 ? $u['hp'] / $u['max_hp'] * 100 : 0; ?>
      <div class="unit<?= $u['hp'] <= 0 ? ' down' : '' ?><?= !empty($u['boss']) ? ' boss' : '' ?>">
        <?php if ($u['image'] !== ''): ?><img src="resource/cards/<?= h($u['image']) ?>" alt=""><?php endif; ?>
        <div class="unit-name"><?= !empty($u['boss']) ? '<span class="boss-tag">BOSS</span>' : '' ?><?= h($u['name']) ?></div>
        <div class="hpbar"><i style="width:<?= round($rate, 1) ?>%"></i></div>
        <div class="unit-hp"><?= number_format($u['hp']) ?> / <?= number_format($u['max_hp']) ?></div>
      </div>
<?php endforeach; ?>
    </div>
  </div>
<?php };
    echo '<div class="unit-sides">';
    $side($battle['allies'], $allyLabel, 'ally');
    $side($battle['enemies'], $enemyLabel, 'enemy');
    echo '</div>';
?>
  <details class="battle-log" open>
    <summary>戦闘ログ（<?= h($battle['turns']) ?> ターン）</summary>
    <ol>
<?php $prevTurn = 0; foreach ($battle['log'] as $n => $l): ?>
<?php if ($l['turn'] !== $prevTurn): $prevTurn = $l['turn']; ?>
      <li class="turn" style="--i:<?= min($n, 60) ?>">TURN <?= h($l['turn']) ?></li>
<?php endif; ?>
      <li class="<?= h($l['side']) ?> <?= h(implode(' ', $l['tags'])) ?>" style="--i:<?= min($n, 60) ?>">
        <b><?= h($l['actor']) ?></b><?= in_array('aoe', $l['tags'], true) ? ' の全体攻撃！' : ' の攻撃' ?>
        → <?= h($l['target']) ?> に <span class="dmg"><?= number_format($l['damage']) ?></span><?= in_array('crit', $l['tags'], true) ? ' <span class="crit-tag">会心！</span>' : '' ?>
        <?= in_array('ko', $l['tags'], true) ? '<span class="ko-tag">' . h($l['target']) . ' は倒れた</span>' : '' ?>
      </li>
<?php endforeach; ?>
<?php if ($battle['timeout']): ?>
      <li class="turn">時間切れ（<?= BATTLE_MAX_TURNS ?> ターン以内に倒せなかった）</li>
<?php endif; ?>
    </ol>
  </details>
<?php
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
  <div class="card-band"><span class="stars"><?= stars((int)$c['rarity']) ?></span><?php if (isset($c['user_card_id'])): ?><span class="uid">#<?= h($c['user_card_id']) ?></span><?php endif; ?></div>
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
    $user  = current_user();
    $gems  = $user ? user_gems($user['id']) : 0;

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
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700;900&family=Rajdhani:wght@600;700&display=swap" rel="stylesheet">
<style>
  /* 崩壊：スターレイル風テーマ（宇宙・金アクセント・レア度グラデーション） */
  :root {
    --bg: #0a0d1a; --surface: rgba(22, 27, 52, .72); --surface-2: rgba(255,255,255,.04);
    --text: #ecebf3; --muted: #9da3bd; --line: rgba(255,255,255,.10);
    --gold: #e8c98f; --gold-deep: #b8925a; --primary: var(--gold); --primary-soft: rgba(232,201,143,.12);
    --danger: #ff8a8a; --danger-soft: rgba(255,90,90,.12); --ok: #9fe0b5; --ok-soft: rgba(110,210,150,.12);
    --r1: #7d6a5c; --r2: #4f9a7a; --r3: #4d7fd1; --r4: #a273e0; --r5: #e2b467;
    --r1-d: #2e2620; --r2-d: #173a2e; --r3-d: #172a52; --r4-d: #2e1b52; --r5-d: #5a3b16;
  }
  * { box-sizing: border-box; }
  body { margin: 0; color: var(--text); font-size: 15px; line-height: 1.65; letter-spacing: .02em; min-height: 100vh;
         font-family: "Noto Sans JP", "Hiragino Sans", "Yu Gothic UI", Meiryo, sans-serif;
         background:
           radial-gradient(1px 1px at 12% 18%, rgba(255,255,255,.8), transparent 60%),
           radial-gradient(1px 1px at 72% 12%, rgba(255,255,255,.7), transparent 60%),
           radial-gradient(1.5px 1.5px at 38% 64%, rgba(255,255,255,.6), transparent 60%),
           radial-gradient(1px 1px at 88% 48%, rgba(255,255,255,.7), transparent 60%),
           radial-gradient(1px 1px at 22% 86%, rgba(255,255,255,.5), transparent 60%),
           radial-gradient(1.5px 1.5px at 58% 32%, rgba(255,240,210,.7), transparent 60%),
           radial-gradient(1px 1px at 94% 82%, rgba(255,255,255,.6), transparent 60%),
           radial-gradient(1px 1px at 6% 52%, rgba(255,255,255,.5), transparent 60%),
           radial-gradient(ellipse 70% 50% at 85% 0%, rgba(120, 90, 200, .28), transparent 70%),
           radial-gradient(ellipse 60% 50% at 0% 100%, rgba(60, 120, 200, .22), transparent 70%),
           linear-gradient(180deg, #0d1124 0%, #0a0d1a 60%, #080a14 100%);
         background-attachment: fixed; }
  a { color: var(--gold); }
  .en { font-family: "Rajdhani", "Noto Sans JP", sans-serif; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; }

  /* ヘッダー */
  .appbar { padding: 14px 16px; border-bottom: 1px solid var(--line);
            background: linear-gradient(180deg, rgba(10,13,26,.92), rgba(10,13,26,.6)); backdrop-filter: blur(8px);
            position: sticky; top: 0; z-index: 10; }
  .appbar::after { content: ""; position: absolute; left: 0; right: 0; bottom: -1px; height: 1px;
                   background: linear-gradient(90deg, transparent, var(--gold), transparent); opacity: .6; }
  .appbar-inner { max-width: 1000px; margin: 0 auto; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
  .appbar .brand { display: flex; align-items: center; gap: 10px; margin-right: auto; color: var(--text); text-decoration: none;
                   font-family: "Rajdhani", sans-serif; font-weight: 700; font-size: 20px; letter-spacing: .2em; }
  .appbar .brand::before { content: ""; width: 12px; height: 12px; transform: rotate(45deg); border: 2px solid var(--gold);
                           box-shadow: 0 0 10px rgba(232,201,143,.6); }
  .appbar .who { font-size: 13px; color: var(--muted); }
  .appbar .who strong { color: var(--text); }
  .appbar form { margin: 0; }
  .appbar a.plain, .appbar button { color: var(--text); background: transparent; border: 1px solid var(--line); border-radius: 999px;
       padding: 5px 14px; font: inherit; font-size: 13px; cursor: pointer; text-decoration: none; transition: border-color .15s, background .15s; }
  .appbar a.plain:hover, .appbar button:hover { border-color: var(--gold); background: var(--primary-soft); }

  main { max-width: 1000px; margin: 0 auto; padding: 28px 16px 56px; }
  h1 { font-size: 26px; font-weight: 700; margin: 0 0 6px; display: flex; align-items: center; gap: 12px; }
  h1::before { content: ""; width: 4px; height: 26px; background: linear-gradient(180deg, var(--gold), transparent); }
  .lead { color: var(--muted); margin: 0 0 24px; padding-left: 16px; }
  h2 { font-size: 16px; margin: 0 0 12px; letter-spacing: .08em; }

  /* ステップ表示（ひし形のノード） */
  .steps { display: flex; list-style: none; padding: 0; margin: 0 0 28px; counter-reset: step; }
  .steps li { flex: 1; position: relative; text-align: center; font-size: 12px; color: var(--muted); padding-top: 34px; }
  .steps li::before { counter-increment: step; content: counter(step); position: absolute; top: 4px; left: 50%; z-index: 1;
        width: 20px; height: 20px; line-height: 20px; transform: translateX(-50%) rotate(45deg); background: #141936;
        border: 1px solid rgba(255,255,255,.25); font-size: 0; }
  .steps li:not(:first-child)::after { content: ""; position: absolute; top: 14px; right: 50%; width: 100%; height: 1px; background: rgba(255,255,255,.18); }
  .steps li.done::before { background: var(--gold-deep); border-color: var(--gold); }
  .steps li.current::before { background: var(--gold); border-color: #fff3d8; box-shadow: 0 0 14px rgba(232,201,143,.8); }
  .steps li.done::after, .steps li.current::after { background: linear-gradient(90deg, var(--gold-deep), var(--gold)); height: 2px; }
  .steps li.current { color: var(--gold); font-weight: 700; }

  /* 汎用パーツ */
  .panel { position: relative; background: var(--surface); border: 1px solid var(--line); padding: 24px; margin-bottom: 24px;
           backdrop-filter: blur(6px);
           clip-path: polygon(0 0, calc(100% - 18px) 0, 100% 18px, 100% 100%, 18px 100%, 0 calc(100% - 18px)); }
  .notice { padding: 12px 16px; margin: 0 0 20px; background: var(--primary-soft); border-left: 3px solid var(--gold); color: var(--text); }
  .notice-error { background: var(--danger-soft); border-left-color: var(--danger); color: #ffd2d2; }
  .notice-ok { background: var(--ok-soft); border-left-color: var(--ok); color: #d6f5e1; }
  .btn { display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(180deg, #f7f3ea, #dcd5c6); color: #1a1b26;
         border: 0; border-radius: 999px; padding: 9px 22px 9px 10px; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer;
         box-shadow: 0 2px 10px rgba(0,0,0,.35); transition: transform .12s, box-shadow .12s; }
  .btn::before { content: ""; width: 18px; height: 18px; border-radius: 50%; background: #1a1b26;
                 box-shadow: inset 0 0 0 5px #dcd5c6, inset 0 0 0 7px #1a1b26; flex: none; }
  .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 18px rgba(232,201,143,.35); }
  .btn-ghost { background: rgba(255,255,255,.04); color: var(--text); border: 1px solid rgba(255,255,255,.28); box-shadow: none; }
  .btn-ghost::before { background: transparent; box-shadow: inset 0 0 0 1.5px var(--muted); }
  .btn-ghost:hover { border-color: var(--gold); box-shadow: none; }
  .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; }
  .muted { color: var(--muted); font-size: 13px; }
  input[type=text] { font: inherit; color: var(--text); padding: 9px 16px; background: rgba(0,0,0,.3); border: 1px solid var(--line);
                     border-radius: 999px; min-width: 0; flex: 1; max-width: 340px; outline: none; }
  input[type=text]:focus { border-color: var(--gold); }
  input[type=text]::placeholder { color: #6f7590; }
  .row { display: flex; gap: 10px; flex-wrap: wrap; }

  /* カードのグループ */
  .group { margin-bottom: 34px; }
  .group-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; padding-bottom: 8px;
                border-bottom: 1px solid var(--line); }
  .group-head h2 { margin: 0; font-size: 18px; }
  .group-head .count { color: var(--gold); font-family: "Rajdhani", sans-serif; font-weight: 700; font-size: 18px; }
  .group-head .to { color: var(--muted); font-size: 13px; margin-left: auto; }

  /* カードタイル（光円錐風） */
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 18px; }
  .card-form { margin: 0; display: contents; }
  .card { --rc: var(--r1); --rd: var(--r1-d); position: relative; display: flex; flex-direction: column; width: 100%; min-height: 300px;
          text-align: left; font: inherit; color: var(--text); text-decoration: none; padding: 0; border: 0; overflow: hidden;
          background:
            radial-gradient(circle at 70% 35%, rgba(255,255,255,.18), transparent 45%),
            linear-gradient(160deg, var(--rc) 0%, var(--rd) 75%, #0b0d18 100%);
          clip-path: polygon(0 0, calc(100% - 22px) 0, 100% 22px, 100% 100%, 0 100%);
          box-shadow: inset 0 0 0 1px rgba(255,255,255,.14);
          transition: transform .15s, filter .15s; }
  .card::after { content: ""; position: absolute; inset: 0; pointer-events: none;
                 box-shadow: inset 0 -60px 50px -20px rgba(5,6,14,.85); }
  .card.r1 { --rc: var(--r1); --rd: var(--r1-d); } .card.r2 { --rc: var(--r2); --rd: var(--r2-d); }
  .card.r3 { --rc: var(--r3); --rd: var(--r3-d); } .card.r4 { --rc: var(--r4); --rd: var(--r4-d); }
  .card.r5 { --rc: var(--r5); --rd: var(--r5-d); }
  .card.clickable { cursor: pointer; }
  .card.clickable:hover, .card.clickable:focus-visible { transform: translateY(-4px); outline: none;
          filter: brightness(1.12) drop-shadow(0 0 12px rgba(232,201,143,.45)); }
  .card.disabled { filter: grayscale(.85) brightness(.55); }
  .card-band { position: relative; z-index: 1; display: flex; justify-content: space-between; align-items: center; padding: 10px 12px 0; font-size: 12px; }
  .card-band .stars { color: #ffd88a; letter-spacing: 2px; text-shadow: 0 0 6px rgba(255,200,100,.7); font-size: 14px; }
  .card-band .uid { font-family: "Rajdhani", sans-serif; font-weight: 600; color: rgba(255,255,255,.7); margin-right: 14px; }
  .emblem { position: absolute; right: -6px; top: 26px; font-size: 120px; font-weight: 900; line-height: 1; color: rgba(255,255,255,.10);
            pointer-events: none; }
  .card-body { position: relative; z-index: 1; margin-top: auto; padding: 12px; }
  .card-name { font-size: 17px; font-weight: 700; margin-bottom: 2px; text-shadow: 0 1px 4px rgba(0,0,0,.6); }
  .lb { font-size: 11px; color: rgba(255,255,255,.7); display: flex; align-items: center; gap: 2px; }
  .lb i { display: inline-block; width: 9px; height: 9px; transform: rotate(45deg); border: 1px solid #ffd88a; margin-left: 4px; }
  .lb i.on { background: #ffd88a; box-shadow: 0 0 6px rgba(255,216,138,.8); }
  .stats { display: flex; gap: 14px; margin: 8px 0 0; padding-top: 8px; border-top: 1px solid rgba(255,255,255,.15); }
  .stats div { display: flex; align-items: baseline; gap: 5px; flex-wrap: wrap; }
  .stats dt { font-family: "Rajdhani", sans-serif; font-size: 12px; color: rgba(255,255,255,.65); font-weight: 600; letter-spacing: .1em; }
  .stats dd { margin: 0; font-family: "Rajdhani", sans-serif; font-weight: 700; font-size: 17px; font-variant-numeric: tabular-nums; }
  .stats .up { color: #8ff0b2; font-size: 13px; }
  .badges { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
  .card .badges { position: absolute; top: 34px; left: 10px; right: 10px; margin: 0; z-index: 2; flex-direction: column; align-items: flex-start; }
  .badge { font-size: 11px; font-weight: 700; padding: 1px 9px; border-radius: 999px; background: rgba(0,0,0,.45); border: 1px solid rgba(255,255,255,.25); }
  .badge-team { color: #ffd88a; border-color: rgba(255,216,138,.6); }
  .badge-max { color: #cfe0ff; border-color: rgba(180,205,255,.6); }
  .badge-warn { color: #ffb3b3; border-color: rgba(255,140,140,.6); }
  .card-note { margin-top: 6px; font-size: 11px; color: rgba(255,255,255,.7); }
  .card.large { min-height: 340px; }
  .card.large .card-name { font-size: 21px; }

  /* カードイラスト（限界突破するほど光る） */
  .card-art { position: absolute; left: 0; right: 0; top: 22px; width: 100%; height: 60%; object-fit: contain; pointer-events: none;
              transition: transform .25s; }
  .card.clickable:hover .card-art { transform: scale(1.05); }
  .card.lb1 .card-art { filter: drop-shadow(0 0 8px rgba(255,230,170,.55)); }
  .card.lb2 .card-art { filter: drop-shadow(0 0 14px rgba(255,216,138,.9)) saturate(1.15); }
  .card.lb2::before { content: ""; position: absolute; inset: 0; pointer-events: none; z-index: 0;
                      background: linear-gradient(115deg, transparent 30%, rgba(255,255,255,.18) 45%, transparent 60%); }
  .banner-art { position: absolute; right: 10px; bottom: -10px; display: flex; pointer-events: none; }
  .banner-art img { width: 190px; height: 250px; object-fit: contain; filter: drop-shadow(0 0 18px rgba(255,210,140,.5)); }
  .banner-art img + img { margin-left: -60px; margin-top: -30px; }
  .banner > *:not(.banner-art) { position: relative; z-index: 1; }
  @media (max-width: 720px) { .banner-art { opacity: .35; } }
  /* 強化前→強化後 */
  .compare { display: grid; grid-template-columns: minmax(0,1fr) auto minmax(0,1fr); gap: 20px; align-items: center; max-width: 520px; margin: 0 auto; }
  .compare .arrow { font-size: 0; width: 34px; height: 34px; position: relative; }
  .compare .arrow::before { content: ""; position: absolute; inset: 8px; border-top: 2px solid var(--gold); border-right: 2px solid var(--gold);
                            transform: rotate(45deg); filter: drop-shadow(0 0 6px rgba(232,201,143,.8)); }
  .compare .label { font-size: 12px; font-weight: 700; color: var(--muted); margin-bottom: 8px; text-align: center; letter-spacing: .2em; }
  .idchange { text-align: center; font-size: 15px; color: var(--muted); margin: 22px 0 4px; }
  .idchange strong { font-family: "Rajdhani", sans-serif; font-size: 30px; color: var(--gold); margin: 0 6px; text-shadow: 0 0 12px rgba(232,201,143,.6); }
  .celebrate { text-align: center; margin: 0 0 20px; }
  .celebrate .en { display: block; color: var(--gold); font-size: 13px; letter-spacing: .5em; }
  .celebrate .ja { display: block; font-size: 24px; font-weight: 700;
                   background: linear-gradient(90deg, #fff6e0, var(--gold), #fff6e0); -webkit-background-clip: text; background-clip: text; color: transparent; }
  @media (max-width: 600px) {
    .compare { grid-template-columns: 1fr; justify-items: stretch; }
    .compare .arrow { transform: rotate(90deg); justify-self: center; }
    .steps li { font-size: 11px; }
    .group-head .to { margin-left: 0; width: 100%; }
  }

  /* メニュー */
  .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
  .menu-item { position: relative; display: block; padding: 22px 24px; text-decoration: none; color: var(--text); overflow: hidden;
               background: linear-gradient(120deg, rgba(232,201,143,.10), rgba(22,27,52,.7) 55%); border: 1px solid var(--line);
               clip-path: polygon(0 0, calc(100% - 16px) 0, 100% 16px, 100% 100%, 0 100%); transition: border-color .15s, filter .15s; }
  .menu-item::after { content: ""; position: absolute; right: 20px; top: 50%; width: 10px; height: 10px; margin-top: -5px;
                      border-top: 2px solid var(--gold); border-right: 2px solid var(--gold); transform: rotate(45deg); }
  .menu-item:hover { border-color: rgba(232,201,143,.6); filter: brightness(1.15); }
  .menu-item .en { display: block; font-size: 11px; color: var(--gold); }
  .menu-item .t { font-weight: 700; font-size: 18px; }
  .menu-item .d { font-size: 13px; color: var(--muted); padding-right: 24px; }

  /* ユーザー一覧 */
  .user-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; }
  .user-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; background: var(--surface); border: 1px solid var(--line); }
  .user-item.me { border-color: rgba(232,201,143,.7); box-shadow: 0 0 16px rgba(232,201,143,.18) inset; }
  .avatar { width: 44px; height: 44px; border-radius: 50%; flex: none; display: flex; align-items: center; justify-content: center; font-weight: 700;
            color: #1a1b26; background: radial-gradient(circle at 35% 30%, #fff6e0, var(--gold) 60%, var(--gold-deep));
            box-shadow: 0 0 0 2px rgba(10,13,26,1), 0 0 0 3px rgba(232,201,143,.6); }
  .user-item .info { flex: 1; min-width: 0; }
  .user-item .name { font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .user-item form { margin: 0; }
  .user-item .btn { padding: 6px 16px 6px 8px; font-size: 13px; }
  .user-item .btn::before { width: 14px; height: 14px; box-shadow: inset 0 0 0 4px #dcd5c6, inset 0 0 0 6px #1a1b26; }

  /* ナビゲーション */
  .gems { display: inline-flex; align-items: center; gap: 6px; padding: 3px 12px 3px 6px; border-radius: 999px;
          background: rgba(0,0,0,.35); border: 1px solid rgba(232,201,143,.35); font-family: "Rajdhani", sans-serif; font-weight: 700; font-size: 15px; }
  .gem-icon { display: inline-block; width: 14px; height: 14px; transform: rotate(45deg); flex: none;
              background: linear-gradient(135deg, #fff6e0, #9fd4ff 45%, #7b6cff); box-shadow: 0 0 8px rgba(160,200,255,.8); }
  .gnav { max-width: 1000px; margin: 10px auto 0; display: flex; gap: 4px; flex-wrap: wrap; }
  .gnav a { color: var(--muted); text-decoration: none; padding: 4px 12px; font-size: 13px; border-bottom: 2px solid transparent; }
  .gnav a:hover { color: var(--text); }
  .gnav a.active { color: var(--gold); border-bottom-color: var(--gold); }

  /* テーブル */
  .table-wrap { overflow-x: auto; }
  table.list { width: 100%; border-collapse: collapse; font-size: 14px; }
  table.list th { text-align: left; font-size: 12px; color: var(--muted); font-weight: 700; padding: 8px 10px; border-bottom: 1px solid var(--line); white-space: nowrap; }
  table.list td { padding: 9px 10px; border-bottom: 1px solid rgba(255,255,255,.05); white-space: nowrap; }
  table.list tr.me td { background: var(--primary-soft); }
  .num { text-align: right; font-family: "Rajdhani", sans-serif; font-weight: 700; font-size: 16px; font-variant-numeric: tabular-nums; }
  .r-text-3 { color: #8db4ff; } .r-text-4 { color: #c9a2ff; } .r-text-5 { color: #ffd88a; }
  .win { color: #8ff0b2; font-weight: 700; } .lose { color: #ff9a9a; font-weight: 700; }

  /* ガチャ */
  .banner { position: relative; overflow: hidden; padding: 32px 28px; margin-bottom: 24px; border: 1px solid rgba(232,201,143,.35);
            background:
              radial-gradient(circle at 80% 40%, rgba(255,220,150,.35), transparent 35%),
              radial-gradient(circle at 75% 45%, rgba(150,120,255,.35), transparent 55%),
              linear-gradient(120deg, #1b1f45 0%, #2a1f55 50%, #4a3418 100%);
            clip-path: polygon(0 0, calc(100% - 26px) 0, 100% 26px, 100% 100%, 26px 100%, 0 calc(100% - 26px)); }
  .banner .en { color: var(--gold); font-size: 12px; letter-spacing: .4em; }
  .banner h2 { font-size: 30px; margin: 4px 0 8px; letter-spacing: .1em; }
  .banner p { color: #d8d6e8; margin: 0 0 16px; max-width: 520px; }
  .banner .pickup { display: flex; gap: 8px; flex-wrap: wrap; }
  .banner .pickup span { padding: 3px 12px; border: 1px solid rgba(255,216,138,.6); color: #ffd88a; font-size: 13px; background: rgba(0,0,0,.3); }
  .pity { display: flex; align-items: center; gap: 12px; margin-top: 18px; font-size: 13px; color: var(--muted); }
  .pity-bar { flex: 1; max-width: 320px; height: 6px; background: rgba(255,255,255,.12); border-radius: 3px; overflow: hidden; }
  .pity-bar i { display: block; height: 100%; background: linear-gradient(90deg, var(--gold-deep), #ffd88a); }
  .pull-row { display: flex; gap: 16px; flex-wrap: wrap; align-items: center; }
  .pull-row form { margin: 0; }
  .pull-row .btn small { font-family: "Rajdhani", sans-serif; font-weight: 700; margin-left: 6px; display: inline-flex; align-items: center; gap: 4px; }
  .btn[disabled] { opacity: .45; cursor: not-allowed; transform: none; }
  .gacha-result .card { animation: reveal .5s both; }
  .gacha-result .card:nth-child(1) { animation-delay: 0s; } .gacha-result .card:nth-child(2) { animation-delay: 0.08s; } .gacha-result .card:nth-child(3) { animation-delay: 0.16s; } .gacha-result .card:nth-child(4) { animation-delay: 0.24s; } .gacha-result .card:nth-child(5) { animation-delay: 0.32s; } .gacha-result .card:nth-child(6) { animation-delay: 0.4s; } .gacha-result .card:nth-child(7) { animation-delay: 0.48s; } .gacha-result .card:nth-child(8) { animation-delay: 0.56s; } .gacha-result .card:nth-child(9) { animation-delay: 0.64s; } .gacha-result .card:nth-child(10) { animation-delay: 0.72s; }
  .gacha-result .card.r5 { box-shadow: inset 0 0 0 1px rgba(255,216,138,.8), 0 0 24px rgba(255,200,100,.6); }
  .gacha-result .card.r4 { box-shadow: inset 0 0 0 1px rgba(200,160,255,.7), 0 0 18px rgba(170,120,255,.45); }
  @keyframes reveal { from { opacity: 0; transform: translateY(14px) scale(.96); } to { opacity: 1; transform: none; } }
  .badge-new { color: #1a1b26; background: #ffd88a; border-color: #ffd88a; }

  /* チーム編成 */
  .slots { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
  .slot-empty { display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 6px; min-height: 300px;
                border: 1px dashed rgba(255,255,255,.25); color: var(--muted); text-decoration: none; font-size: 13px;
                clip-path: polygon(0 0, calc(100% - 22px) 0, 100% 22px, 100% 100%, 0 100%); transition: border-color .15s, color .15s; }
  .slot-empty::before { content: "+"; font-size: 34px; line-height: 1; font-weight: 300; }
  .slot-empty:hover { border-color: var(--gold); color: var(--gold); }
  .slot-wrap { display: flex; flex-direction: column; gap: 8px; }
  .slot-wrap.selected .card { filter: drop-shadow(0 0 12px rgba(232,201,143,.7)); }
  .slot-label { font-family: "Rajdhani", sans-serif; font-size: 12px; color: var(--muted); letter-spacing: .2em; }
  .slot-actions { display: flex; gap: 6px; flex-wrap: wrap; }
  .slot-actions form { margin: 0; }
  .mini { font: inherit; font-size: 12px; color: var(--text); background: transparent; border: 1px solid var(--line); border-radius: 999px;
          padding: 2px 10px; cursor: pointer; text-decoration: none; }
  .mini:hover { border-color: var(--gold); }
  .power { display: flex; align-items: baseline; gap: 10px; }
  .power .en { color: var(--muted); font-size: 12px; }
  .power b { font-family: "Rajdhani", sans-serif; font-size: 32px; color: var(--gold); text-shadow: 0 0 10px rgba(232,201,143,.5); }
  @media (max-width: 720px) { .slots { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

  /* バトル・ホーム */
  .vs { display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px; align-items: center; text-align: center; }
  .vs .side .name { font-weight: 700; font-size: 18px; }
  .vs .vs-mark { font-family: "Rajdhani", sans-serif; font-size: 34px; font-weight: 700; color: var(--gold); letter-spacing: .1em; }
  .result-word { font-family: "Rajdhani", sans-serif; font-size: 48px; font-weight: 700; letter-spacing: .3em; text-align: center; margin: 0; }
  .result-word.win { color: #ffd88a; text-shadow: 0 0 20px rgba(255,216,138,.7); }
  .result-word.lose { color: #9aa0b8; }
  .stat-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px; }
  .stat { padding: 16px 18px; background: var(--surface); border: 1px solid var(--line); }
  .stat .en { display: block; font-size: 11px; color: var(--muted); }
  .stat b { font-family: "Rajdhani", sans-serif; font-size: 26px; }
  .filters { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; align-items: center; }
  .filters a { color: var(--muted); text-decoration: none; padding: 4px 14px; border: 1px solid var(--line); border-radius: 999px; font-size: 13px; }
  .filters a.active { color: #1a1b26; background: var(--gold); border-color: var(--gold); font-weight: 700; }
  .filters .sep { width: 1px; height: 20px; background: var(--line); margin: 0 6px; }

  /* バトル結果（ユニットのHP・戦闘ログ） */
  .unit-sides { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 18px 0; }
  .unit-side.enemy { text-align: right; }
  .unit-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 8px; margin-top: 6px; }
  .unit { position: relative; padding: 8px; background: var(--surface-2); border: 1px solid var(--line); text-align: center; }
  .unit img { width: 100%; height: 64px; object-fit: contain; }
  .unit.boss { border-color: rgba(255,140,140,.6); box-shadow: 0 0 14px rgba(255,90,90,.25) inset; }
  .unit.down { filter: grayscale(1) brightness(.45); }
  .unit-name { font-size: 12px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .unit-hp { font-family: "Rajdhani", sans-serif; font-size: 12px; color: var(--muted); }
  .hpbar { height: 5px; background: rgba(255,255,255,.12); border-radius: 3px; overflow: hidden; margin: 4px 0 2px; }
  .hpbar i { display: block; height: 100%; background: linear-gradient(90deg, #5fd38a, #b8f5c8); }
  .unit-side.enemy .hpbar i { background: linear-gradient(90deg, #ff7a7a, #ffc2a8); }
  .boss-tag, .crit-tag, .ko-tag { font-size: 10px; font-weight: 700; padding: 0 6px; border-radius: 999px; margin: 0 4px; }
  .boss-tag { background: #ff7a7a; color: #1a1b26; }
  .crit-tag { background: #ffd88a; color: #1a1b26; }
  .ko-tag { border: 1px solid rgba(255,140,140,.6); color: #ffb3b3; }
  .battle-log summary { cursor: pointer; font-weight: 700; margin-bottom: 8px; }
  .battle-log ol { list-style: none; margin: 0; padding: 0; max-height: 320px; overflow-y: auto; font-size: 13px; }
  .battle-log li { padding: 3px 10px; border-left: 2px solid transparent; animation: reveal .3s both; animation-delay: calc(var(--i, 0) * 45ms); }
  .battle-log li.ally { border-left-color: #5fd38a; }
  .battle-log li.enemy { border-left-color: #ff7a7a; color: #e2dbe6; }
  .battle-log li.aoe { background: rgba(255,90,90,.10); }
  .battle-log li.turn { font-family: "Rajdhani", sans-serif; color: var(--gold); letter-spacing: .2em; margin-top: 8px; }
  .battle-log .dmg { font-family: "Rajdhani", sans-serif; font-weight: 700; font-size: 15px; }
  @media (max-width: 720px) { .unit-sides { grid-template-columns: 1fr; } .unit-side.enemy { text-align: left; } }

  /* クエスト */
  .chapter { margin-bottom: 30px; }
  .chapter-head { display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--line); }
  .chapter-head h2 { margin: 0; font-size: 18px; }
  .chapter-head .en { color: var(--gold); font-size: 12px; }
  .chapter-head .muted { width: 100%; }
  .chapter-head .star-total { margin-left: auto; color: #ffd88a; font-family: "Rajdhani", sans-serif; font-weight: 700; }
  .stage-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
  .stage { position: relative; display: flex; flex-direction: column; gap: 6px; padding: 16px 18px; background: var(--surface); border: 1px solid var(--line);
           clip-path: polygon(0 0, calc(100% - 16px) 0, 100% 16px, 100% 100%, 0 100%); }
  .stage.boss { border-color: rgba(255,140,140,.45); background: linear-gradient(120deg, rgba(255,90,90,.12), var(--surface) 60%); }
  .stage.locked { opacity: .45; }
  .stage.next { border-color: rgba(232,201,143,.7); box-shadow: 0 0 18px rgba(232,201,143,.2) inset; }
  .stage-top { display: flex; align-items: center; gap: 10px; }
  .stage-no { font-family: "Rajdhani", sans-serif; font-weight: 700; font-size: 20px; color: var(--gold); }
  .stage-name { font-weight: 700; font-size: 16px; }
  .stage-stars { margin-left: auto; letter-spacing: 2px; color: rgba(255,255,255,.2); }
  .stage-stars .on { color: #ffd88a; text-shadow: 0 0 6px rgba(255,200,100,.7); }
  .stage-enemies { display: flex; gap: 4px; }
  .stage-enemies img { width: 34px; height: 34px; object-fit: contain; background: rgba(0,0,0,.25); border-radius: 50%; padding: 2px; }
  .stage-foot { display: flex; align-items: center; gap: 10px; margin-top: auto; padding-top: 6px; flex-wrap: wrap; }
  .stage-foot form { margin: 0 0 0 auto; }
  .stage-foot .btn { padding: 6px 16px 6px 8px; font-size: 13px; }
  .stage-foot .btn::before { width: 14px; height: 14px; box-shadow: inset 0 0 0 4px #dcd5c6, inset 0 0 0 6px #1a1b26; }
  .star-goals { list-style: none; padding: 0; margin: 14px auto 0; max-width: 360px; font-size: 14px; }
  .star-goals li { display: flex; gap: 10px; padding: 3px 0; color: var(--muted); }
  .star-goals li::before { content: "★"; color: rgba(255,255,255,.2); }
  .star-goals li.ok { color: var(--text); }
  .star-goals li.ok::before { color: #ffd88a; text-shadow: 0 0 6px rgba(255,200,100,.7); }
</style>
</head>
<body>
<header class="appbar">
  <div class="appbar-inner">
    <a class="brand" href="menu.php">STAR CARDS</a>
<?php if ($user): ?>
    <span class="gems" title="石"><i class="gem-icon"></i><?= number_format($gems) ?></span>
    <span class="who"><strong><?= h($user['name']) ?></strong>（ID: <?= h($user['id']) ?>）</span>
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
    <a href="<?= h($file) ?>" class="<?= $active ? 'active' : '' ?>"><?= h($label) ?></a>
<?php endforeach; ?>
  </nav>
</header>
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
