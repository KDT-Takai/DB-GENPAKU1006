<?php
// ガチャ（単発・10連、天井、10連★4以上確定、提供割合、履歴）
require_once __DIR__ . '/common.php';

$user    = require_login();
$gachaId = 1;

$stmt = db()->prepare('SELECT * FROM gachas WHERE id = :id');
$stmt->execute([':id' => $gachaId]);
$gacha = $stmt->fetch();

// 排出対象カードと重み
$stmt = db()->prepare(
    'SELECT gi.card_id, gi.weight, c.name, c.image, c.rarity, c.limit_break, c.attack, c.hp
       FROM gacha_items gi
       JOIN cards c ON c.id = gi.card_id
      WHERE gi.gacha_id = :id
      ORDER BY c.rarity DESC, c.id'
);
$stmt->execute([':id' => $gachaId]);
$pool        = $stmt->fetchAll();
$totalWeight = array_sum(array_column($pool, 'weight'));

// 重み付き抽選
function pick_weighted(array $items): array
{
    $total = array_sum(array_column($items, 'weight'));
    $r = random_int(1, $total);
    foreach ($items as $item) {
        $r -= (int)$item['weight'];
        if ($r <= 0) {
            return $item;
        }
    }
    return end($items);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = (int)($_POST['count'] ?? 0) === 10 ? 10 : 1;
    $cost  = $gacha['cost'] * $count;
    $pdo   = db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT gems, pity_count FROM users WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $user['id']]);
        $me = $stmt->fetch();
        if ($me['gems'] < $cost) {
            throw new RuntimeException('石が足りません。（必要: ' . number_format($cost) . '）');
        }

        $stmt = $pdo->prepare('SELECT DISTINCT card_id FROM user_cards WHERE user_id = :id');
        $stmt->execute([':id' => $user['id']]);
        $owned = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

        $pool5   = array_values(array_filter($pool, fn($p) => $p['rarity'] == 5));
        $pool4up = array_values(array_filter($pool, fn($p) => $p['rarity'] >= 4));
        $pity    = (int)$me['pity_count'];
        $hasHigh = false;
        $results = [];

        $insCard = $pdo->prepare('INSERT INTO user_cards (user_id, card_id) VALUES (:user_id, :card_id)');
        $insHist = $pdo->prepare('INSERT INTO user_gacha_histories (user_id, gacha_id, card_id) VALUES (:user_id, :gacha_id, :card_id)');

        for ($i = 0; $i < $count; $i++) {
            $pity++;
            if ($pity >= $gacha['pity']) {
                $item = pick_weighted($pool5);                       // 天井：★5確定
            } elseif ($count === 10 && $i === 9 && !$hasHigh) {
                $item = pick_weighted($pool4up);                     // 10連の最後：★4以上確定
            } else {
                $item = pick_weighted($pool);
            }
            if ($item['rarity'] == 5) {
                $pity = 0;
            }
            if ($item['rarity'] >= 4) {
                $hasHigh = true;
            }

            $insCard->execute([':user_id' => $user['id'], ':card_id' => $item['card_id']]);
            $userCardId = (int)$pdo->lastInsertId();
            $insHist->execute([':user_id' => $user['id'], ':gacha_id' => $gachaId, ':card_id' => $item['card_id']]);

            $results[] = ['user_card_id' => $userCardId, 'new' => !isset($owned[$item['card_id']])];
            $owned[$item['card_id']] = true;
        }

        $pdo->prepare('UPDATE users SET gems = gems - :cost, pity_count = :pity WHERE id = :id')
            ->execute([':cost' => $cost, ':pity' => $pity, ':id' => $user['id']]);

        count_up($user['id'], 'gacha', $count);
        $pdo->commit();
        $_SESSION['gacha_result'] = $results;
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        flash($e->getMessage(), 'error');
    }
    redirect('gacha.php');
}

// 直前の結果（リロードで再抽選されないようにセッションから1回だけ表示）
$results = [];
if (!empty($_SESSION['gacha_result'])) {
    $ids = array_column($_SESSION['gacha_result'], 'user_card_id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        'SELECT ' . CARD_COLS . "
           FROM user_cards uc JOIN cards c ON c.id = uc.card_id
          WHERE uc.id IN ($in)"
    );
    $stmt->execute($ids);
    $cards = array_column($stmt->fetchAll(), null, 'user_card_id');
    foreach ($_SESSION['gacha_result'] as $r) {
        if (isset($cards[$r['user_card_id']])) {
            $results[] = $cards[$r['user_card_id']] + ['new' => $r['new']];
        }
    }
    unset($_SESSION['gacha_result']);
}

$stmt = db()->prepare('SELECT gems, pity_count FROM users WHERE id = :id');
$stmt->execute([':id' => $user['id']]);
$me = $stmt->fetch();

$stmt = db()->prepare(
    'SELECT h.created_at, c.name, c.rarity
       FROM user_gacha_histories h JOIN cards c ON c.id = h.card_id
      WHERE h.user_id = :id AND h.gacha_id = :gacha_id
      ORDER BY h.id DESC LIMIT 20'
);
$stmt->execute([':id' => $user['id'], ':gacha_id' => $gachaId]);
$histories = $stmt->fetchAll();

$pickups = array_filter($pool, fn($p) => $p['rarity'] == 5);
$untilPity = $gacha['pity'] - $me['pity_count'];

render_header('ガチャ');
render_flash();
?>
<?php if ($results): $best = max(array_column($results, 'rarity')); ?>
<div class="warp" id="warp" style="--wc: <?= $best >= 5 ? '#ffc96a' : ($best >= 4 ? '#b07aff' : '#5aa8ff') ?>">
  <div class="tunnel"></div>
  <div class="orb"></div>
  <button type="button" class="skip">スキップ</button>
  <div class="hint">TAP TO SKIP</div>
</div>
<?php if ($best >= 5): ?>
<!-- ★5 確定演出：暗転 → 金の亀裂 → 「星辰降臨」の4文字が1文字ずつ下からせり上がる → 閃光 → ★5確定 -->
<div class="kakutei" id="kakutei" hidden>
  <div class="k-crack"></div>
  <div class="k-burst"></div>
  <div class="k-words"><span>六</span><span>根</span><span>清</span><span>浄</span></div>
  <div class="k-sub">★5 確定</div>
  <div class="k-flash"></div>
</div>
<style>
  .kakutei { position: fixed; inset: 0; z-index: 110; display: grid; place-items: center; overflow: hidden; cursor: pointer; background: #000;
             --g1: #fff3c4; --g2: #ffc22e; --g3: #b36b00; }
  .kakutei[hidden] { display: none; }
  .kakutei.go { animation: k-shake .5s 1.1s, k-shake .4s 1.45s, k-shake .4s 1.8s, k-shake .7s 2.15s; }
  /* 暗転の後、横一文字に走る金の亀裂 */
  .k-crack { position: absolute; left: 50%; top: 50%; width: 0; height: 3px; transform: translate(-50%, -50%);
             background: linear-gradient(90deg, transparent, var(--g1), var(--g2), var(--g1), transparent); box-shadow: 0 0 18px var(--g2), 0 0 50px var(--g2); }
  .go .k-crack { animation: k-crack .45s .55s ease-out forwards, k-fade .3s 1.05s forwards; }
  @keyframes k-crack { to { width: 120vw; } }
  /* 文字の後ろの放射状の光 */
  .k-burst { position: absolute; width: 160vmax; height: 160vmax; opacity: 0;
             background: repeating-conic-gradient(from 0deg, rgba(255,194,46,.28) 0 6deg, transparent 6deg 18deg); border-radius: 50%;
             -webkit-mask: radial-gradient(circle, #000 10%, transparent 62%); mask: radial-gradient(circle, #000 10%, transparent 62%); }
  .go .k-burst { animation: k-in .4s 2.15s forwards, k-spin 9s 2.15s linear infinite; }
  @keyframes k-spin { to { transform: rotate(360deg); } }
  .k-words { position: relative; display: flex; gap: 1.5vmin; padding: 2vmin 1vmin; overflow: hidden; }
  .k-words span { display: block; font-family: var(--display); font-size: min(21vw, 26vmin); line-height: 1.05; opacity: 0;
                  background: linear-gradient(180deg, var(--g1) 10%, var(--g2) 50%, var(--g3) 92%); -webkit-background-clip: text; background-clip: text; color: transparent;
                  -webkit-text-stroke: 2px #4a2a00; filter: drop-shadow(0 0 14px rgba(255,194,46,.75)); }
  /* 1文字ずつ下からせり上がって、少し行き過ぎてから止まる */
  .go .k-words span { animation: k-rise .34s cubic-bezier(.2,1.4,.4,1) forwards; }
  .go .k-words span:nth-child(1) { animation-delay: 1.1s; }  .go .k-words span:nth-child(2) { animation-delay: 1.45s; }
  .go .k-words span:nth-child(3) { animation-delay: 1.8s; }  .go .k-words span:nth-child(4) { animation-delay: 2.15s; }
  .go .k-words { animation: k-pulse 1.1s 2.6s ease-in-out infinite; }
  @keyframes k-rise { 0% { opacity: 1; transform: translateY(115%); filter: blur(3px) brightness(2.4); }
                      70% { opacity: 1; transform: translateY(-6%); filter: blur(0) brightness(1.6) drop-shadow(0 0 14px rgba(255,194,46,.75)); }
                      100% { opacity: 1; transform: translateY(0); } }
  @keyframes k-pulse { 50% { transform: scale(1.04); filter: brightness(1.25); } }
  .k-sub { position: absolute; bottom: 18vh; font-family: var(--display); font-size: 30px; letter-spacing: .3em; color: var(--g1); opacity: 0;
           text-shadow: 0 0 12px var(--g2); }
  .go .k-sub { animation: k-in .5s 2.7s forwards; }
  .k-flash { position: absolute; inset: 0; background: #fff; opacity: 0; pointer-events: none; }
  .go .k-flash { animation: k-flash .5s 2.15s; }
  .kakutei.out .k-flash { animation: k-out .45s forwards; }
  @keyframes k-flash { 0% { opacity: .9; } 100% { opacity: 0; } }
  @keyframes k-out { to { opacity: 1; } }
  @keyframes k-in { to { opacity: 1; } }
  @keyframes k-fade { to { opacity: 0; } }
  @keyframes k-shake { 0%,100% { transform: none; } 20% { transform: translate(-8px, 5px); } 40% { transform: translate(7px, -6px); }
                       60% { transform: translate(-5px, -3px); } 80% { transform: translate(4px, 4px); } }
</style>
<?php endif; ?>
<script>
// ワープ演出：光の色で最高レア度が分かる（金＝★5、紫＝★4、青＝★3）。終わったら結果を1枚ずつめくる
// ★5のときはワープの途中で画面が暗転し、確定演出に切り替わる
document.addEventListener('DOMContentLoaded', function () {
  var warp = document.getElementById('warp');
  var kakutei = document.getElementById('kakutei');
  var grid = document.querySelector('.gacha-result .grid');
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var timers = [];
  // 結果のカードは裏向きで並べ、演出が終わったら1枚ずつめくる（★5は少し溜めてから金色に光ってめくれる）
  var cards = grid ? [].slice.call(grid.children) : [];
  cards.forEach(function (c) {
    var back = document.createElement('div');
    back.className = 'card-back';
    back.innerHTML = '<span>★</span>';
    c.appendChild(back);
    c.classList.add('face-down');
    if (c.classList.contains('r5')) c.classList.add('hint5');
    else if (c.classList.contains('r4')) c.classList.add('hint4');
  });
  function flip(c) {
    if (!c.classList.contains('face-down')) return;
    c.classList.remove('face-down');
    c.classList.add('flipping');
    setTimeout(function () { var b = c.querySelector('.card-back'); if (b) b.remove(); }, reduce ? 0 : 230);
    setTimeout(function () { c.classList.remove('flipping'); if (c.classList.contains('r5')) c.classList.add('burst'); }, reduce ? 0 : 480);
  }
  function revealAll(stagger) {
    var t = 0;
    cards.forEach(function (c) {
      if (!c.classList.contains('face-down')) return;
      t += c.classList.contains('r5') && stagger ? 450 : 0;   // ★5の前だけ一拍おく
      setTimeout(function () { flip(c); }, stagger ? t : 0);
      t += stagger ? 160 : 0;
    });
  }
  if (grid) {
    grid.addEventListener('click', function (e) {
      var c = e.target.closest('.face-down');
      if (c) flip(c);
    });
    var allBtn = document.getElementById('flip-all');
    if (allBtn) allBtn.addEventListener('click', function () { revealAll(false); allBtn.hidden = true; });
  }
  function end() {
    if (warp.hidden && (!kakutei || kakutei.hidden)) return;
    timers.forEach(clearTimeout);
    warp.hidden = true;
    if (kakutei) kakutei.hidden = true;
    setTimeout(function () { revealAll(!reduce); }, reduce ? 0 : 350);
  }
  function startKakutei() {
    warp.hidden = true;
    kakutei.hidden = false;
    kakutei.classList.add('go');
    timers.push(setTimeout(function () { kakutei.classList.add('out'); }, 4300));
    timers.push(setTimeout(end, 4750));
  }
  warp.addEventListener('click', end);
  if (kakutei && !reduce) {
    kakutei.addEventListener('click', end);
    timers.push(setTimeout(startKakutei, 1300));   // ワープの最中にいきなり暗転
  } else {
    timers.push(setTimeout(end, 2400));
  }
});
</script>
<section class="panel gacha-result">
  <p class="celebrate"><span class="ja"><?= count($results) ?>回 ワープの結果</span></p>
  <p class="flip-hint"><span>カードをタップでめくれます</span><button type="button" class="mini" id="flip-all">すべてめくる</button></p>
  <div class="grid">
<?php foreach ($results as $c): ?>
    <?php render_card($c, ['badges' => $c['new'] ? [['NEW', 'new']] : []]); ?>
<?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<style>
  /* ---- ガチャの作り込み：回るバナー・きらめき・ピックアップのリボン・カードめくり ---- */
  .banner { isolation: isolate; background: #ffb52e; }
  .banner::before { content: ""; position: absolute; inset: -60%; z-index: -1;
                    background: repeating-conic-gradient(from 0deg at 50% 50%, #ffcf4a 0 10deg, #ffb52e 10deg 20deg); animation: bannerSpin 40s linear infinite; transform-origin: 70% 50%; }
  .banner::after { content: "✦ ✧ ★ ✦ ✧"; position: absolute; right: 30%; top: 14px; z-index: 0; font-size: 22px; letter-spacing: 18px; color: #fff;
                   -webkit-text-stroke: 1px var(--ink); animation: twinkle 2.6s ease-in-out infinite; pointer-events: none; }
  @keyframes bannerSpin { to { transform: rotate(360deg); } }
  .banner .ribbon { position: absolute; z-index: 2; left: -42px; top: 18px; width: 170px; text-align: center; transform: rotate(-35deg); font-family: var(--display); font-size: 13px;
                    letter-spacing: .1em; color: #fff; background: var(--tomato); border-block: 2.5px solid var(--ink); box-shadow: 0 3px 0 rgba(0,0,0,.2); }
  .banner h2 { margin-left: 34px; animation: headIn .6s cubic-bezier(.2,1.4,.4,1) both; }
  .banner-art img { animation: artFloat 3.2s ease-in-out infinite; }
  .banner-art img + img { animation-delay: -1.6s; }
  @keyframes artFloat { 50% { transform: translateY(-10px) rotate(2deg); } }
  .banner .pickup span { box-shadow: var(--shadow-sm); }
  .pull-row .btn { min-width: 210px; flex-direction: column; gap: 0; line-height: 1.3; padding: 12px 24px; font-family: var(--display); font-weight: 400; font-size: 20px; }
  .pull-row .btn small { margin: 4px 0 0; padding: 0 10px; background: rgba(255,255,255,.85); color: var(--ink); border: 2px solid var(--ink); border-radius: 999px; }
  .pull-row form { position: relative; }
  .pull-row form:last-of-type::after { content: "★4以上確定"; position: absolute; top: -12px; right: -8px; z-index: 2; font-size: 11px; font-weight: 800; padding: 0 8px;
                                        background: var(--grape); color: #fff; border: 2px solid var(--ink); border-radius: 999px; transform: rotate(6deg); animation: dotPulse 1.6s infinite; }
  .pull-row form:last-of-type .btn:not([disabled]) { animation: pullGlow 2s ease-in-out infinite; }
  @keyframes pullGlow { 50% { box-shadow: 4px 4px 0 var(--ink), 0 0 0 6px rgba(255,91,58,.28); } }

  .flip-hint { display: flex; justify-content: center; align-items: center; gap: 10px; margin: -6px 0 16px; font-size: 13px; font-weight: 800; color: var(--muted); }
  .gacha-result .grid > * { animation: none; }
  .card-back { position: absolute; inset: 0; z-index: 8; display: grid; place-items: center; border-radius: 11px;
               background: repeating-linear-gradient(45deg, rgba(255,255,255,.12) 0 10px, transparent 10px 20px), radial-gradient(circle, #3a3444, var(--ink)); }
  .card-back span { display: grid; place-items: center; width: 64px; height: 64px; font-size: 34px; color: var(--sun); background: var(--paper-2); border: 3px solid var(--ink);
                    border-radius: 50%; box-shadow: 0 0 0 6px rgba(255,255,255,.15); }
  .face-down { cursor: pointer; }
  .face-down:hover { transform: translateY(-4px); }
  .hint4 .card-back { box-shadow: inset 0 0 0 4px var(--grape), inset 0 0 26px rgba(139,103,255,.8); }
  .hint5 .card-back { box-shadow: inset 0 0 0 4px var(--sun), inset 0 0 34px rgba(255,194,46,.95); }
  .gacha-result .grid > .hint5.face-down { animation: hint5Shake 1.2s ease-in-out infinite; }
  .hint5 .card-back span { animation: twinkle 1s infinite; }
  @keyframes hint5Shake { 0%, 70%, 100% { transform: none; } 75% { transform: rotate(-2deg); } 85% { transform: rotate(2deg); } }
  .gacha-result .grid > .flipping { animation: cardFlip .48s ease-in-out; }
  @keyframes cardFlip { 0% { transform: perspective(800px) rotateY(0); } 48% { transform: perspective(800px) rotateY(90deg) scale(1.05); }
                        52% { transform: perspective(800px) rotateY(90deg) scale(1.05); } 100% { transform: perspective(800px) rotateY(0); } }
  .gacha-result .card { overflow: hidden; }
  .gacha-result .card.burst { animation: burst5 .7s ease-out; box-shadow: 4px 4px 0 var(--ink), 0 0 0 4px #ffd85e, 0 0 30px rgba(255,194,46,.9); }
  @keyframes burst5 { 0% { filter: brightness(2.2); transform: scale(1.12); } 100% { filter: none; transform: none; } }
</style>
<section class="banner">
  <span class="ribbon">PICK UP</span>
  <div class="banner-art">
<?php foreach ($pickups as $p): ?>
    <img src="resource/cards/<?= h($p['image']) ?>" alt="">
<?php endforeach; ?>
  </div>
  <h2><?= h($gacha['name']) ?></h2>
  <p><?= h($gacha['description']) ?></p>
  <div class="pickup">
<?php foreach ($pickups as $p): ?>
    <span>★5 <?= h($p['name']) ?></span>
<?php endforeach; ?>
  </div>
  <div class="pity">
    <span>★5確定まであと <b style="color:var(--gold)"><?= h($untilPity) ?></b> 回</span>
    <div class="pity-bar"><i style="width: <?= round($me['pity_count'] / $gacha['pity'] * 100) ?>%"></i></div>
  </div>
</section>

<div class="pull-row" style="margin-bottom: 32px">
<?php foreach ([1, 10] as $n): $cost = $gacha['cost'] * $n; ?>
  <form method="post" action="gacha.php">
    <input type="hidden" name="count" value="<?= $n ?>">
    <button type="submit" class="btn" <?= $me['gems'] < $cost ? 'disabled' : '' ?>>
      <?= $n ?>回ワープ<small><i class="gem-icon"></i><?= number_format($cost) ?></small>
    </button>
  </form>
<?php endforeach; ?>
  <span class="muted">10回ワープは★4以上が1枚以上確定</span>
</div>

<section class="panel">
  <h2>提供割合</h2>
  <div class="table-wrap">
  <table class="list">
    <tr><th>レア度</th><th>カード</th><th style="text-align:right">確率</th></tr>
<?php foreach ($pool as $p): ?>
    <tr>
      <td class="r-text-<?= (int)$p['rarity'] ?>"><?= stars((int)$p['rarity']) ?></td>
      <td><?= h($p['name']) ?></td>
      <td class="num"><?= number_format($p['weight'] / $totalWeight * 100, 2) ?>%</td>
    </tr>
<?php endforeach; ?>
  </table>
  </div>
</section>

<section class="panel">
  <h2>ガチャ履歴（直近20件）</h2>
<?php if (!$histories): ?>
  <p class="muted">まだガチャを引いていません。</p>
<?php else: ?>
  <div class="table-wrap">
  <table class="list">
    <tr><th>日時</th><th>レア度</th><th>カード</th></tr>
<?php foreach ($histories as $hst): ?>
    <tr>
      <td class="muted"><?= h($hst['created_at']) ?></td>
      <td class="r-text-<?= (int)$hst['rarity'] ?>"><?= stars((int)$hst['rarity']) ?></td>
      <td><?= h($hst['name']) ?></td>
    </tr>
<?php endforeach; ?>
  </table>
  </div>
<?php endif; ?>
</section>
<?php render_footer(); ?>
