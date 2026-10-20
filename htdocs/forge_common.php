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
        render_notice('カードを強化するには、まずユーザーを選択してください。', 'error');
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

/**
 * カード1枚をタイル表示する
 * $c    : name, rarity, limit_break, attack, hp を含む配列（user_card_id があれば所持IDも表示）
 * $opts : href（リンク先）/ post（POST送信する値の配列。action は post_action）/
 *         badges（表示するバッジ [文字, 種類]）/ note（下部の補足文）/ disabled（選択不可で薄く表示）/
 *         diff（強化前カード。ステータスの増加量を表示）/ large（大きく表示）
 */
function render_card(array $c, array $opts = []): void
{
    $classes = ['card', 'r' . (int)$c['rarity']];
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
  <span class="emblem"><?= h(mb_substr($c['name'], 0, 1)) ?></span>
  <div class="card-body">
    <div class="card-name"><?= h($c['name']) ?></div>
    <div class="lb">限界突破 <?= str_repeat('<i class="on"></i>', (int)$c['limit_break']) ?><?= str_repeat('<i></i>', max(0, 2 - (int)$c['limit_break'])) ?></div>
    <dl class="stats">
      <div><dt>ATK</dt><dd><?= h($c['attack']) ?><?= $diff('attack') ?></dd></div>
      <div><dt>HP</dt><dd><?= h($c['hp']) ?><?= $diff('hp') ?></dd></div>
    </dl>
<?php if (!empty($opts['badges'])): ?>
    <div class="badges"><?php foreach ($opts['badges'] as [$text, $type]): ?><span class="badge badge-<?= h($type) ?>"><?= h($text) ?></span><?php endforeach; ?></div>
<?php endif; ?>
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
    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> | カード強化</title>
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
  .card { --rc: var(--r1); --rd: var(--r1-d); position: relative; display: flex; flex-direction: column; width: 100%; min-height: 250px;
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
  .badge { font-size: 11px; font-weight: 700; padding: 1px 9px; border-radius: 999px; background: rgba(0,0,0,.45); border: 1px solid rgba(255,255,255,.25); }
  .badge-team { color: #ffd88a; border-color: rgba(255,216,138,.6); }
  .badge-max { color: #cfe0ff; border-color: rgba(180,205,255,.6); }
  .badge-warn { color: #ffb3b3; border-color: rgba(255,140,140,.6); }
  .card-note { margin-top: 6px; font-size: 11px; color: rgba(255,255,255,.7); }
  .card.large { min-height: 290px; }
  .card.large .card-name { font-size: 21px; }

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
</style>
</head>
<body>
<header class="appbar">
  <div class="appbar-inner">
    <a class="brand" href="menu.php">CARD FORGE</a>
<?php if ($user): ?>
    <span class="who">ログイン中: <strong><?= h($user['name']) ?></strong>（ID: <?= h($user['id']) ?>）</span>
    <a class="plain" href="menu.php">メニュー</a>
    <form action="select_user.php" method="post"><button type="submit" name="logout">ログアウト</button></form>
<?php else: ?>
    <span class="who">ログインしていません</span>
    <a class="plain" href="select_user.php">ユーザーを選択</a>
<?php endif; ?>
  </div>
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
