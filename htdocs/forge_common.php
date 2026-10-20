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
<style>
  :root {
    --bg: #eef1f6; --surface: #fff; --text: #1d2433; --muted: #6b7385; --line: #dfe3ea;
    --primary: #4f5bd5; --primary-soft: #e8eafb; --danger: #c62f3b; --danger-soft: #fdecee;
    --ok: #1f8a4c; --ok-soft: #e4f5ea;
    --r1: #a0714f; --r2: #3aa66b; --r3: #3d7fe0; --r4: #9b59d0; --r5: #e0a419;
  }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--text);
         font-family: "Hiragino Sans", "Noto Sans JP", "Yu Gothic UI", Meiryo, sans-serif; font-size: 15px; line-height: 1.6; }
  a { color: var(--primary); }

  /* ヘッダー */
  .appbar { background: #1f2540; color: #fff; padding: 12px 16px; }
  .appbar-inner { max-width: 960px; margin: 0 auto; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
  .appbar .brand { font-weight: 700; font-size: 17px; color: #fff; text-decoration: none; margin-right: auto; }
  .appbar .who { font-size: 14px; opacity: .9; }
  .appbar .who strong { font-weight: 700; }
  .appbar form { margin: 0; }
  .appbar a.plain, .appbar button { color: #fff; background: rgba(255,255,255,.12); border: 0; border-radius: 6px;
       padding: 6px 12px; font: inherit; font-size: 13px; cursor: pointer; text-decoration: none; }
  .appbar a.plain:hover, .appbar button:hover { background: rgba(255,255,255,.22); }

  main { max-width: 960px; margin: 0 auto; padding: 24px 16px 48px; }
  h1 { font-size: 24px; margin: 0 0 4px; }
  .lead { color: var(--muted); margin: 0 0 20px; }
  h2 { font-size: 17px; margin: 0 0 12px; }

  /* ステップ表示 */
  .steps { display: flex; list-style: none; padding: 0; margin: 0 0 24px; counter-reset: step; }
  .steps li { flex: 1; position: relative; text-align: center; font-size: 13px; color: var(--muted); }
  .steps li::before { counter-increment: step; content: counter(step); display: block; width: 30px; height: 30px; line-height: 30px;
        margin: 0 auto 6px; border-radius: 50%; background: var(--line); color: var(--muted); font-weight: 700; position: relative; z-index: 1; }
  .steps li:not(:first-child)::after { content: ""; position: absolute; top: 15px; right: 50%; width: 100%; height: 3px; background: var(--line); }
  .steps li.done::before, .steps li.current::before { background: var(--primary); color: #fff; }
  .steps li.done::after, .steps li.current::after { background: var(--primary); }
  .steps li.current { color: var(--text); font-weight: 700; }

  /* 汎用パーツ */
  .panel { background: var(--surface); border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(20,30,60,.08); margin-bottom: 20px; }
  .notice { padding: 12px 16px; border-radius: 8px; margin: 0 0 20px; background: var(--primary-soft); }
  .notice-error { background: var(--danger-soft); color: var(--danger); }
  .notice-ok { background: var(--ok-soft); color: var(--ok); }
  .btn { display: inline-block; background: var(--primary); color: #fff; border: 0; border-radius: 8px; padding: 10px 18px;
         font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
  .btn:hover { filter: brightness(1.08); }
  .btn-ghost { background: transparent; color: var(--primary); border: 1px solid var(--primary); }
  .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; }
  .muted { color: var(--muted); font-size: 13px; }
  input[type=text] { font: inherit; padding: 9px 12px; border: 1px solid var(--line); border-radius: 8px; min-width: 0; flex: 1; max-width: 320px; }
  .row { display: flex; gap: 8px; flex-wrap: wrap; }

  /* カードのグループ（同じカードごと） */
  .group { margin-bottom: 28px; }
  .group-head { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
  .group-head h2 { margin: 0; }
  .group-head .count { background: var(--text); color: #fff; border-radius: 10px; padding: 0 8px; font-size: 12px; font-weight: 700; }
  .group-head .to { color: var(--muted); font-size: 14px; }

  /* カードタイル */
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; }
  .card-form { margin: 0; display: contents; }
  .card { --rc: var(--r1); display: block; width: 100%; text-align: left; font: inherit; color: inherit; text-decoration: none;
          background: var(--surface); border: 2px solid transparent; border-radius: 12px; overflow: hidden; padding: 0;
          box-shadow: 0 1px 3px rgba(20,30,60,.10); transition: transform .12s, box-shadow .12s, border-color .12s; }
  .card.r1 { --rc: var(--r1); } .card.r2 { --rc: var(--r2); } .card.r3 { --rc: var(--r3); }
  .card.r4 { --rc: var(--r4); } .card.r5 { --rc: var(--r5); }
  .card.clickable { cursor: pointer; }
  .card.clickable:hover, .card.clickable:focus-visible { transform: translateY(-3px); border-color: var(--rc);
          box-shadow: 0 8px 20px rgba(20,30,60,.15); outline: none; }
  .card.disabled { opacity: .55; filter: grayscale(.6); }
  .card-band { background: var(--rc); color: #fff; padding: 6px 12px; display: flex; justify-content: space-between; font-size: 13px; }
  .card-band .stars { letter-spacing: 1px; }
  .card-band .uid { opacity: .85; }
  .card-body { padding: 10px 12px 12px; }
  .card-name { font-size: 17px; font-weight: 700; margin-bottom: 2px; }
  .lb { font-size: 12px; color: var(--muted); display: flex; align-items: center; gap: 3px; }
  .lb i { display: inline-block; width: 10px; height: 10px; transform: rotate(45deg); border: 1.5px solid var(--rc); margin-left: 3px; }
  .lb i.on { background: var(--rc); }
  .stats { display: flex; gap: 16px; margin: 8px 0 0; }
  .stats div { display: flex; align-items: baseline; gap: 5px; }
  .stats dt { font-size: 11px; color: var(--muted); font-weight: 700; }
  .stats dd { margin: 0; font-weight: 700; font-variant-numeric: tabular-nums; }
  .stats .up { color: var(--ok); font-size: 12px; }
  .badges { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
  .badge { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; }
  .badge-team { background: #fff3d6; color: #8a5a00; }
  .badge-max { background: var(--primary-soft); color: var(--primary); }
  .badge-warn { background: var(--danger-soft); color: var(--danger); }
  .card-note { margin-top: 8px; font-size: 12px; color: var(--muted); }
  .card.large .card-name { font-size: 20px; }

  /* 強化前→強化後 */
  .compare { display: grid; grid-template-columns: minmax(0,1fr) auto minmax(0,1fr); gap: 16px; align-items: center; max-width: 560px; margin: 0 auto; }
  .compare .arrow { font-size: 30px; color: var(--primary); font-weight: 700; }
  .compare .label { font-size: 12px; font-weight: 700; color: var(--muted); margin-bottom: 6px; text-align: center; }
  .idchange { text-align: center; font-size: 18px; margin: 20px 0 4px; }
  .idchange strong { font-size: 24px; color: var(--primary); }
  .celebrate { text-align: center; font-size: 22px; font-weight: 700; color: var(--ok); margin: 0 0 16px; }
  @media (max-width: 600px) {
    .compare { grid-template-columns: 1fr; }
    .compare .arrow { transform: rotate(90deg); text-align: center; }
    .steps li { font-size: 11px; }
  }

  /* メニュー */
  .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
  .menu-item { display: block; background: var(--surface); border-radius: 12px; padding: 18px 20px; text-decoration: none; color: inherit;
               box-shadow: 0 1px 3px rgba(20,30,60,.08); border: 2px solid transparent; transition: border-color .12s, transform .12s; }
  .menu-item:hover { border-color: var(--primary); transform: translateY(-2px); }
  .menu-item .t { font-weight: 700; font-size: 17px; color: var(--primary); }
  .menu-item .d { font-size: 13px; color: var(--muted); }

  /* ユーザー一覧 */
  .user-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px; }
  .user-item { display: flex; align-items: center; gap: 12px; background: var(--surface); border-radius: 12px; padding: 14px;
               box-shadow: 0 1px 3px rgba(20,30,60,.08); border: 2px solid transparent; }
  .user-item.me { border-color: var(--primary); }
  .avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--primary-soft); color: var(--primary);
            display: flex; align-items: center; justify-content: center; font-weight: 700; flex: none; }
  .user-item .info { flex: 1; min-width: 0; }
  .user-item .name { font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .user-item form { margin: 0; }
  .user-item .btn { padding: 7px 12px; font-size: 13px; }
</style>
</head>
<body>
<header class="appbar">
  <div class="appbar-inner">
    <a class="brand" href="menu.php">カード強化</a>
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
