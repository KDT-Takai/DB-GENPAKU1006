<?php
// 限界突破機能 共通処理（DB接続・表示ヘルパー）

// ログイン機能はないので操作ユーザーは固定
const FORGE_USER_ID = 1;

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

function render_header(string $title, int $step): void
{
    $steps = ['強化対象選択', '素材選択', '強化結果'];
    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> | 限界突破</title>
<style>
  body { font-family: sans-serif; background: #f4f5f7; color: #222; margin: 0; padding: 24px 16px; }
  main { max-width: 860px; margin: 0 auto; }
  h1 { font-size: 22px; margin: 0 0 16px; }
  .steps { display: flex; gap: 8px; margin-bottom: 20px; padding: 0; list-style: none; flex-wrap: wrap; }
  .steps li { padding: 6px 12px; border-radius: 16px; background: #dde1e7; font-size: 13px; }
  .steps li.current { background: #3b6fd8; color: #fff; }
  .box { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom: 16px; overflow-x: auto; }
  table { border-collapse: collapse; width: 100%; }
  th, td { padding: 8px 10px; border-bottom: 1px solid #e3e5e8; text-align: left; white-space: nowrap; }
  th { background: #f0f2f5; font-size: 13px; }
  .num { text-align: right; }
  .rarity { color: #e0a800; }
  .tag { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; background: #fde2e1; color: #b3261e; }
  .muted { color: #888; font-size: 13px; }
  .error { background: #fde2e1; color: #b3261e; padding: 12px; border-radius: 6px; }
  .arrow { font-size: 28px; text-align: center; margin: 8px 0; }
  button.link { background: none; border: none; padding: 0; color: #3b6fd8; text-decoration: underline; cursor: pointer; font: inherit; }
  a { color: #3b6fd8; }
</style>
</head>
<body>
<main>
<h1><?= h($title) ?></h1>
<ol class="steps">
<?php foreach ($steps as $i => $label): ?>
  <li class="<?= $i + 1 === $step ? 'current' : '' ?>"><?= $i + 1 ?>. <?= h($label) ?></li>
<?php endforeach; ?>
</ol>
<?php
}

function render_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}
