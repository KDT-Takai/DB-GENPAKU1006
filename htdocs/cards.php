<?php
// 所持カード一覧（レア度で絞り込み・並び替え）
require_once __DIR__ . '/common.php';

$user = require_login();

$rarity = filter_input(INPUT_GET, 'rarity', FILTER_VALIDATE_INT) ?: 0;
$sort   = $_GET['sort'] ?? 'rarity';
$orders = [
    'rarity' => ['レア度順', 'c.rarity DESC, c.limit_break DESC, c.id, uc.id'],
    'attack' => ['ATK順',    'c.attack DESC, uc.id'],
    'hp'     => ['HP順',     'c.hp DESC, uc.id'],
    'new'    => ['入手順',   'uc.id DESC'],
];
if (!isset($orders[$sort])) {
    $sort = 'rarity';
}

// ORDER BY はプレースホルダにできないので、許可したものだけを埋め込む
$sql = 'SELECT uc.id AS user_card_id, c.name, c.image, c.rarity, c.limit_break, c.attack, c.hp,
               (t.user_card_id IS NOT NULL) AS in_team
          FROM user_cards uc
          JOIN cards c ON c.id = uc.card_id
          LEFT JOIN user_team_cards t ON t.user_card_id = uc.id
         WHERE uc.user_id = :user_id' . ($rarity ? ' AND c.rarity = :rarity' : '') . '
         ORDER BY ' . $orders[$sort][1];
$params = [':user_id' => $user['id']];
if ($rarity) {
    $params[':rarity'] = $rarity;
}
$stmt = db()->prepare($sql);
$stmt->execute($params);
$cards = $stmt->fetchAll();

// 図鑑（何種類集めたか）
$stmt = db()->prepare(
    'SELECT COUNT(DISTINCT uc.card_id) AS owned, (SELECT COUNT(*) FROM cards) AS total
       FROM user_cards uc WHERE uc.user_id = :user_id'
);
$stmt->execute([':user_id' => $user['id']]);
$book = $stmt->fetch();

$url = fn(array $q) => 'cards.php?' . http_build_query(array_merge(['rarity' => $rarity, 'sort' => $sort], $q));

render_header('所持カード', 0, '持っているカードの一覧です。同じカードが2枚以上あれば限界突破できます。');
?>
<div class="stat-row">
  <div class="stat"><span class="en">Cards</span><b><?= count($cards) ?></b> 枚<?= $rarity ? '（★' . $rarity . '）' : '' ?></div>
  <div class="stat"><span class="en">Collection</span><b><?= h($book['owned']) ?></b> / <?= h($book['total']) ?> 種類</div>
</div>

<div class="filters">
  <a href="<?= h($url(['rarity' => 0])) ?>" class="<?= $rarity === 0 ? 'active' : '' ?>">すべて</a>
<?php foreach ([5, 4, 3] as $r): ?>
  <a href="<?= h($url(['rarity' => $r])) ?>" class="<?= $rarity === $r ? 'active' : '' ?>">★<?= $r ?></a>
<?php endforeach; ?>
  <span class="sep"></span>
<?php foreach ($orders as $key => [$label]): ?>
  <a href="<?= h($url(['sort' => $key])) ?>" class="<?= $sort === $key ? 'active' : '' ?>"><?= h($label) ?></a>
<?php endforeach; ?>
</div>

<?php if (!$cards): ?>
<?php render_notice('カードがありません。ガチャで手に入れましょう。'); ?>
<p><a class="btn" href="gacha.php">ガチャへ</a></p>
<?php else: ?>
<div class="grid">
<?php foreach ($cards as $c):
    render_card($c, ['badges' => $c['in_team'] ? [['編成中', 'team']] : []]);
endforeach; ?>
</div>
<?php endif; ?>
<?php render_footer(); ?>
