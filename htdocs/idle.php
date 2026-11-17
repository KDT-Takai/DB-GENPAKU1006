<?php
// 放置（チームが自動で周回し、時間とともに報酬が貯まる。ボスに勝つと次のステージへ進み、報酬が増える）
require_once __DIR__ . '/common.php';

$user = require_login();

// 今日の時短の回数
function quick_used(int $userId): int
{
    $stmt = db()->prepare("SELECT cnt FROM user_daily_counts WHERE user_id = :u AND day = CURDATE() AND mkey = 'idle_quick'");
    $stmt->execute([':u' => $userId]);
    return (int)$stmt->fetchColumn();
}

function reward_text(array $r): string
{
    return 'ゴールド ' . number_format($r['gold']) . '・EXP ' . number_format($r['exp']) . '・石 ' . number_format($r['gems']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $pdo = db();

    if ($action === 'claim') {
        $pdo->beginTransaction();
        [$got, $rankUp, $drops] = idle_claim($user['id']);
        if (array_sum($got) > 0) count_up($user['id'], 'idle');
        $pdo->commit();
        if (array_sum($got) > 0) {
            flash('放置報酬を受け取りました：' . reward_text($got) . ($drops ? '／装備 ' . equip_names($drops) : '') . ($rankUp ? '（ランクアップ！）' : ''));
        } else {
            flash('まだ報酬が貯まっていません。少し待ってから受け取ろう。', 'error');
        }
    } elseif ($action === 'quick') {
        $pdo->beginTransaction();
        $used = quick_used($user['id']);
        $cost = $used === 0 ? 0 : IDLE_QUICK_COST;
        if ($used >= IDLE_QUICK_DAILY) {
            flash('今日の時短はもう使えません（1日 ' . IDLE_QUICK_DAILY . ' 回まで）。', 'error');
        } elseif ($cost > 0 && user_gems($user['id']) < $cost) {
            flash('石が足りません（必要 ' . $cost . '）。', 'error');
        } else {
            if ($cost > 0) {
                $pdo->prepare('UPDATE users SET gems = gems - :c WHERE id = :id')->execute([':c' => $cost, ':id' => $user['id']]);
            }
            $state  = idle_state($user['id'], true);
            $got    = idle_amount($state['stage'], IDLE_QUICK_SEC);
            $rankUp = idle_give($user['id'], $got);
            $drops  = equip_drop($user['id'], $state['stage'], 1);
            count_up($user['id'], 'idle_quick');
            flash('時短で2時間分の報酬を受け取りました' . ($cost ? '（石 ' . $cost . ' 消費）' : '（無料）') . '：'
                  . reward_text($got) . '／装備 ' . equip_names($drops) . ($rankUp ? '（ランクアップ！）' : ''));
        }
        $pdo->commit();
    } elseif ($action === 'boss') {
        $team = team_cards($user['id']);
        if (!$team) {
            flash('チームが編成されていません。先に編成してください。', 'error');
        } else {
            $pdo->beginTransaction();
            $state  = idle_state($user['id'], true);
            $stage  = $state['stage'];
            $battle = simulate_battle(card_units($team), idle_enemies($stage));
            $win    = $battle['result'] === 'win';
            $reward = null;
            $rankUp = 0;
            if ($win) {
                // 先に今のステージの放置報酬を精算してから、次のステージへ進む
                [$idleGot, $r1, $idleDrops] = idle_claim($user['id']);
                $reward = idle_boss_reward($stage);
                $rankUp = $r1 + idle_give($user['id'], $reward);
                // ボスは R 以上の装備を必ず落とす（強敵ボスは SR 以上）
                $bossDrop = equip_drop($user['id'], $stage, 1, $stage % IDLE_BOSS_EVERY === 0 ? 3 : 2);
                $pdo->prepare('UPDATE user_idle SET stage = stage + 1, wins = wins + 1 WHERE user_id = :u')->execute([':u' => $user['id']]);
                count_up($user['id'], 'quest');
                $reward = ['boss' => $reward, 'idle' => $idleGot, 'drops' => array_merge($bossDrop, $idleDrops)];
            }
            $pdo->commit();
            $_SESSION['idle_result'] = ['stage' => $stage, 'battle' => $battle, 'reward' => $reward, 'rank_up' => $rankUp];
        }
        redirect('idle.php#result');
    }
    redirect('idle.php');
}

$state   = idle_state($user['id']);
$stage   = $state['stage'];
$team    = team_cards($user['id']);
$foes    = idle_enemies($stage);
$power   = team_power($team);
$foePow  = team_power($foes);
$next    = idle_rate($stage + 1);
$used    = quick_used($user['id']);
$bossRw  = idle_boss_reward($stage);
$isBig   = $stage % IDLE_BOSS_EVERY === 0;
$last    = $_SESSION['idle_result'] ?? null;
unset($_SESSION['idle_result']);

// 戦力差の目安
$ratio = $foePow > 0 ? $power / $foePow : 0;
[$hintText, $hintClass] = $ratio >= 1.3 ? ['楽勝', 'ok'] : ($ratio >= 0.9 ? ['互角', 'even'] : ['格上', 'hard']);

render_header('放置', 0, '放っておくだけで報酬が貯まる。ボスを倒して先へ進もう。');
render_flash();
?>
<style>
  .idle-field { position: relative; overflow: hidden; border: 3px solid var(--ink); border-radius: 18px; box-shadow: var(--shadow);
                background: linear-gradient(#bfe6ff 0 55%, #9fdc8f 55% 100%); padding: 18px 12px 14px; min-height: 290px; }
  .idle-field::before { content: ""; position: absolute; inset: auto -40% 45% -40%; height: 2px; background: rgba(29,26,34,.25); }
  .idle-field .cloud { position: absolute; top: 18px; width: 70px; height: 22px; background: #fff; border-radius: 20px; opacity: .85;
                       animation: idle-cloud 22s linear infinite; }
  .idle-field .cloud.c2 { top: 52px; width: 46px; animation-duration: 31s; animation-delay: -12s; }
  @keyframes idle-cloud { from { left: 110%; } to { left: -20%; } }
  .idle-stage-tag { position: relative; z-index: 2; display: inline-block; background: var(--ink); color: var(--paper); font-weight: 800;
                    padding: 2px 12px; border-radius: 999px; font-size: 13px; }
  .idle-row { position: relative; z-index: 1; display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
  .idle-row.enemy { margin: 14px 0 34px; }
  .idle-unit { position: relative; width: 74px; text-align: center; }
  .idle-unit img { width: 74px; height: 74px; object-fit: contain; background: var(--paper-2); border: 3px solid var(--ink); border-radius: 14px;
                   box-shadow: var(--shadow-sm); }
  .idle-row.enemy .idle-unit img { background: #ffe1d6; }
  .idle-row.ally .idle-unit { animation: idle-hop 1.4s ease-in-out infinite; }
  .idle-row.enemy .idle-unit { animation: idle-sway 2.2s ease-in-out infinite; }
  .idle-unit:nth-child(2) { animation-delay: -.35s !important; } .idle-unit:nth-child(3) { animation-delay: -.7s !important; }
  .idle-unit:nth-child(4) { animation-delay: -1.05s !important; }
  @keyframes idle-hop  { 0%,100% { transform: translateY(0); } 45% { transform: translateY(-10px); } 55% { transform: translateY(-10px) rotate(-4deg); } }
  @keyframes idle-sway { 0%,100% { transform: translateX(0); } 50% { transform: translateX(5px); } }
  .idle-unit.hit img { animation: idle-hit .3s; }
  @keyframes idle-hit { 0% { filter: brightness(2.2); transform: translateX(-4px); } 100% { filter: none; transform: none; } }
  .idle-unit .nm { display: block; font-size: 11px; font-weight: 800; line-height: 1.3; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .idle-pop { position: absolute; left: 50%; top: 10px; transform: translateX(-50%); font-family: var(--display); font-size: 20px; color: #fff;
              -webkit-text-stroke: 1.5px var(--ink); paint-order: stroke; pointer-events: none; animation: idle-pop .9s ease-out forwards; z-index: 3; }
  .idle-pop.crit { color: var(--sun); font-size: 26px; }
  @keyframes idle-pop { from { opacity: 1; transform: translate(-50%, 0); } to { opacity: 0; transform: translate(-50%, -34px); } }
  .idle-loot { position: absolute; z-index: 3; font-size: 18px; animation: idle-loot 1.2s ease-in forwards; pointer-events: none; }
  @keyframes idle-loot { from { opacity: 1; transform: translateY(0) scale(1); } to { opacity: 0; transform: translateY(-60px) scale(.6); } }

  .idle-pending { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin: 14px 0 10px; }
  .idle-pending div { background: var(--paper-2); border: 3px solid var(--ink); border-radius: 14px; padding: 8px 10px; text-align: center; box-shadow: var(--shadow-sm); }
  .idle-pending b { display: block; font-family: var(--display); font-weight: 400; font-size: 24px; line-height: 1.3; }
  .idle-pending span { font-size: 12px; font-weight: 800; color: var(--ink-soft); }
  .idle-timer { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 800; }
  .idle-timer .meter-bar { flex: 1; }
  .idle-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
  .idle-actions form { margin: 0; }
  .boss-card { display: grid; grid-template-columns: auto 1fr; gap: 14px; align-items: center; }
  .boss-card .foes { display: flex; gap: 6px; }
  .boss-card .foes img { width: 54px; height: 54px; object-fit: contain; background: #ffe1d6; border: 3px solid var(--ink); border-radius: 12px; }
  .boss-card .foes img.boss { width: 66px; height: 66px; background: var(--sun); }
  .odds { display: inline-block; padding: 1px 10px; border: 2px solid var(--ink); border-radius: 999px; font-weight: 800; font-size: 13px; }
  .odds.ok { background: var(--mint); } .odds.even { background: var(--sun); } .odds.hard { background: var(--tomato); color: #fff; }
  .rate-table td, .rate-table th { padding: 4px 10px; }
  @media (max-width: 560px) {
    .idle-unit, .idle-unit img { width: 58px; } .idle-unit img { height: 58px; }
    .idle-pending b { font-size: 18px; }
    .boss-card { grid-template-columns: 1fr; }
  }
  @media (prefers-reduced-motion: reduce) {
    .idle-field *, .idle-field .cloud { animation: none !important; }
  }

  /* ---- 作り込み：遠くの丘・流れる地面・敵のHPバー・撃破と復活・戦利品が報酬欄へ飛ぶ ---- */
  .idle-field { isolation: isolate; background: linear-gradient(#8fd3ff 0, #c9ecff 52%, #a7e08f 52%, #86cf6e 100%); }
  .idle-field::before { display: none; }
  .idle-field .hills { position: absolute; left: -10%; right: -10%; top: 30%; height: 24%; z-index: -1; opacity: .9;
    background: radial-gradient(ellipse 18% 100% at 12% 100%, #6fbf86 98%, transparent 100%), radial-gradient(ellipse 22% 120% at 40% 100%, #5fb27a 98%, transparent 100%),
                radial-gradient(ellipse 16% 90% at 66% 100%, #6fbf86 98%, transparent 100%), radial-gradient(ellipse 24% 110% at 90% 100%, #5fb27a 98%, transparent 100%);
    background-size: 50% 100%; background-repeat: repeat-x; animation: hillsMove 40s linear infinite; }
  @keyframes hillsMove { to { background-position: -100% 0; } }
  .idle-field .ground { position: absolute; left: 0; right: 0; bottom: 0; height: 48%; z-index: -1; border-top: 3px solid var(--ink);
    background: repeating-linear-gradient(100deg, rgba(255,255,255,.18) 0 22px, transparent 22px 60px); background-size: 120px 100%; animation: groundRun 1.2s linear infinite; }
  @keyframes groundRun { to { background-position: -120px 0; } }
  .idle-hud { position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
  .idle-stage-tag { box-shadow: 2px 2px 0 rgba(0,0,0,.25); }
  .idle-stage-tag::before { content: ""; display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 50%; background: var(--mint); animation: dotPulse 1s infinite; }
  .idle-kills { font-size: 12px; font-weight: 800; background: #fff; border: 2px solid var(--ink); border-radius: 999px; padding: 0 10px; }
  .idle-kills b { font-family: var(--display); font-weight: 400; font-size: 15px; color: var(--tomato); }
  .idle-unit .ihp { display: block; height: 7px; margin: 3px 4px 0; background: #fff; border: 2px solid var(--ink); border-radius: 999px; overflow: hidden; }
  .idle-unit .ihp i { display: block; height: 100%; width: 100%; background: var(--tomato); transition: width .25s; }
  .idle-unit.boss img { width: 92px; height: 92px; background: var(--sun) !important; }
  .idle-unit.boss { width: 92px; }
  .idle-unit.dead { animation: none !important; }
  .idle-unit.dead img, .idle-unit.dead .nm, .idle-unit.dead .ihp { animation: poof .45s forwards; }
  .idle-unit.respawn img { animation: respawn .4s cubic-bezier(.2,1.6,.4,1); }
  @keyframes poof { to { opacity: 0; transform: scale(.4) rotate(20deg); filter: brightness(3); } }
  @keyframes respawn { from { opacity: 0; transform: translateY(-20px) scale(.5); } }
  .idle-row.ally .idle-unit.lunge img { animation: lunge .35s; }
  @keyframes lunge { 40% { transform: translateY(-22px) scale(1.08); } }
  .idle-slash { position: absolute; z-index: 3; width: 60px; height: 6px; margin: -3px 0 0 -30px; border-radius: 3px; pointer-events: none;
                background: linear-gradient(90deg, transparent, #fff, transparent); box-shadow: 0 0 10px #fff; animation: slash .3s forwards; }
  @keyframes slash { from { transform: rotate(-35deg) scaleX(0); opacity: 1; } to { transform: rotate(-35deg) scaleX(1.4); opacity: 0; } }
  .idle-fly { position: fixed; z-index: 50; font-size: 20px; pointer-events: none; transition: transform .8s cubic-bezier(.5,-0.4,.7,1), opacity .8s; }
  .idle-pending div { position: relative; overflow: hidden; }
  .idle-pending div::before { position: absolute; left: 8px; top: 6px; font-size: 18px; opacity: .9; }
  .idle-pending div:nth-child(1)::before { content: "🪙"; } .idle-pending div:nth-child(2)::before { content: "📘"; } .idle-pending div:nth-child(3)::before { content: "💎"; }
  .idle-pending div:nth-child(1) { background: #fff4cf; } .idle-pending div:nth-child(3) { background: #e3f1ff; }
  .idle-pending b.bump { animation: bump .35s; }
  @keyframes bump { 40% { transform: scale(1.25); color: var(--tomato); } }
  .idle-actions .btn[value="claim"] { font-size: 17px; padding: 10px 28px; background: var(--tomato); color: #fff; }
  .idle-actions .btn[value="claim"].ready { animation: claimPulse 1.4s ease-in-out infinite; }
  @keyframes claimPulse { 50% { box-shadow: 4px 4px 0 var(--ink), 0 0 0 6px rgba(255,91,58,.3); } }
  .boss-card .foes img.boss { animation: bossFloat 2s ease-in-out infinite; }
  @keyframes bossFloat { 50% { transform: translateY(-5px) rotate(-3deg); } }
</style>

<?php if ($last): $lb = $last['battle']; ?>
<section class="panel battle" id="result" data-battle>
  <p class="muted" style="text-align:center; margin:0">ステージ <?= h($last['stage']) ?> ボス戦</p>
<?php render_battle($lb, 'あなたのチーム', 'ステージボス'); ?>
  <div class="battle-after">
  <p class="result-word <?= h($lb['result']) ?>"><?= $lb['result'] === 'win' ? '突破！' : ($lb['timeout'] ? '時間切れ' : 'まけ…') ?></p>
  <p style="text-align:center; margin:12px 0 0">
<?php if ($last['reward']): $br = $last['reward']['boss']; $ir = $last['reward']['idle']; ?>
    ボス報酬：<span class="coin"><i class="gold-icon"></i><?= number_format($br['gold']) ?></span>
    <span class="gems"><i class="gem-icon"></i><?= h($br['gems']) ?></span>
    <span class="badge" style="margin-left:6px">EXP +<?= h($br['exp']) ?></span><br>
    装備：<b><?= h(equip_names($last['reward']['drops'])) ?></b> <a class="mini" href="equip.php">装備する</a><br>
    <span class="muted">ステージ <?= h($last['stage'] + 1) ?> へ進みました。放置報酬が増えます！
    <?php if (array_sum($ir) > 0): ?>（それまでの放置報酬 <?= h(reward_text($ir)) ?> も受け取り済み）<?php endif; ?></span>
<?php else: ?>
    <span class="muted">カードを強化・限界突破するか、属性を考えて編成し直そう。放置で貯まったゴールドで強化できます。</span>
<?php endif; ?>
<?php if (!empty($last['rank_up'])): ?>
    <br><span class="badge badge-new" style="margin-top:8px">ランクアップ！ スタミナ全回復・プレゼントに石が届きました</span>
<?php endif; ?>
  </p>
  </div>
</section>
<?php endif; ?>

<section class="panel">
  <div class="idle-field" id="idle-field" aria-label="オート周回中のバトルの様子">
    <i class="cloud"></i><i class="cloud c2"></i>
    <i class="hills" aria-hidden="true"></i><i class="ground" aria-hidden="true"></i>
    <div class="idle-hud">
      <span class="idle-stage-tag">ステージ <?= h($stage) ?> 周回中</span>
      <span class="idle-kills">撃破 <b id="idle-kills">0</b></span>
    </div>
    <div class="idle-row enemy">
<?php foreach ($foes as $e): ?>
      <div class="idle-unit<?= $e['boss'] ? ' boss' : '' ?>"><img src="resource/cards/<?= h($e['image']) ?>" alt=""><span class="nm"><?= h($e['name']) ?></span><i class="ihp"><i></i></i></div>
<?php endforeach; ?>
    </div>
    <div class="idle-row ally">
<?php foreach ($team as $c): ?>
      <div class="idle-unit"><img src="resource/cards/<?= h($c['image']) ?>" alt=""><span class="nm"><?= h($c['name']) ?></span></div>
<?php endforeach; ?>
<?php if (!$team): ?>
      <p class="notice notice-error" style="margin:0">チームが未編成です。<a href="team.php">編成する</a></p>
<?php endif; ?>
    </div>
  </div>

  <div class="idle-pending" id="idle-pending"
       data-elapsed="<?= h($state['elapsed']) ?>" data-max="<?= IDLE_MAX_SEC ?>"
       data-gold="<?= h($state['rate']['gold']) ?>" data-exp="<?= h($state['rate']['exp']) ?>" data-gems="<?= h($state['rate']['gems']) ?>">
    <div><span>ゴールド</span><b data-k="gold"><?= number_format($state['pending']['gold']) ?></b></div>
    <div><span>EXP</span><b data-k="exp"><?= number_format($state['pending']['exp']) ?></b></div>
    <div><span>石</span><b data-k="gems"><?= number_format($state['pending']['gems']) ?></b></div>
  </div>
  <div class="idle-timer">
    <span id="idle-time"><?= sprintf('%d:%02d', intdiv($state['elapsed'], 3600), intdiv($state['elapsed'] % 3600, 60)) ?></span>
    <div class="meter-bar"><i id="idle-bar" style="width:<?= round($state['elapsed'] / IDLE_MAX_SEC * 100, 1) ?>%"></i></div>
    <span>上限 12:00</span>
  </div>
  <p class="muted" id="idle-full" style="margin:6px 0 0; <?= $state['elapsed'] >= IDLE_MAX_SEC ? '' : 'display:none' ?>">上限まで貯まっています。受け取らないとこれ以上増えません。</p>

  <div class="idle-actions">
    <form method="post" action="idle.php"><button type="submit" name="action" value="claim" class="btn">受け取る</button></form>
    <form method="post" action="idle.php">
      <button type="submit" name="action" value="quick" class="btn btn-ghost" <?= $used >= IDLE_QUICK_DAILY ? 'disabled' : '' ?>>
        時短（2時間分）<small style="opacity:.75"><?= $used === 0 ? '今日は無料' : '石' . IDLE_QUICK_COST ?> ・ 残り<?= max(0, IDLE_QUICK_DAILY - $used) ?>回</small>
      </button>
    </form>
  </div>
</section>

<section class="panel">
  <h2><?= $isBig ? '強敵ボス' : 'ステージボス' ?>に挑戦</h2>
  <div class="boss-card">
    <div class="foes">
<?php foreach ($foes as $e): ?>
      <img src="resource/cards/<?= h($e['image']) ?>" class="<?= $e['boss'] ? 'boss' : '' ?>" alt="<?= h($e['name']) ?>"
           title="<?= h($e['name']) ?>（ATK <?= h($e['attack']) ?> / HP <?= h($e['hp']) ?>）">
<?php endforeach; ?>
    </div>
    <div>
      <div class="power"><span class="label">あなたの戦力</span><b><?= number_format($power) ?></b>
        <span class="label">敵の戦力</span><b style="color:var(--ink)"><?= number_format($foePow) ?></b>
        <span class="odds <?= $hintClass ?>"><?= $hintText ?></span></div>
      <div class="muted">属性 <?php foreach (array_unique(array_column($foes, 'element')) as $el) echo element_chip($el); ?>
        ・ スタミナ不要・何度でも挑戦できます</div>
      <div style="margin-top:4px">勝利報酬：<span class="coin"><i class="gold-icon"></i><?= number_format($bossRw['gold']) ?></span>
        <span class="gems"><i class="gem-icon"></i><?= h($bossRw['gems']) ?></span> <span class="badge">EXP +<?= h($bossRw['exp']) ?></span>
        <span class="badge"><?= $isBig ? 'SR以上' : 'R以上' ?>の装備 確定</span></div>
    </div>
  </div>
  <div class="idle-actions">
    <form method="post" action="idle.php"><button type="submit" name="action" value="boss" class="btn" <?= $team ? '' : 'disabled' ?>>ボスに挑戦</button></form>
    <a class="btn btn-ghost" href="team.php">編成</a>
    <a class="btn btn-ghost" href="cards.php">カード強化</a>
  </div>
</section>

<section class="panel">
  <h2>放置報酬（1時間あたり）</h2>
  <div class="table-wrap">
  <table class="list rate-table">
    <tr><th></th><th style="text-align:right">ゴールド</th><th style="text-align:right">EXP</th><th style="text-align:right">石</th></tr>
    <tr><td>いま（ステージ <?= h($stage) ?>）</td><td class="num"><?= number_format($state['rate']['gold']) ?></td><td class="num"><?= number_format($state['rate']['exp']) ?></td><td class="num"><?= h($state['rate']['gems']) ?></td></tr>
    <tr><td>突破後（ステージ <?= h($stage + 1) ?>）</td><td class="num"><?= number_format($next['gold']) ?></td><td class="num"><?= number_format($next['exp']) ?></td><td class="num"><?= h($next['gems']) ?></td></tr>
  </table>
  </div>
  <p class="muted" style="margin-bottom:0">報酬は最大12時間分まで貯まります。放置2時間ごとに装備が1つ見つかり、ステージが進むほど SR・SSR が出やすくなります。<?= IDLE_BOSS_EVERY ?>ステージごとに強敵ボスが出て、倒すと石の放置報酬が増えます。</p>
</section>

<script>
(function () {
  // 貯まっていく報酬を画面上でも増やす（実際の受け取り量はサーバーで計算する）
  var box = document.getElementById('idle-pending');
  if (!box) return;
  var start = Date.now(), base = +box.dataset.elapsed, max = +box.dataset.max;
  var fmt = function (n) { return n.toLocaleString('ja-JP'); };
  function tick() {
    var sec = Math.min(max, base + Math.floor((Date.now() - start) / 1000));
    box.querySelectorAll('b[data-k]').forEach(function (b) {
      b.textContent = fmt(Math.floor(+box.dataset[b.dataset.k] * sec / 3600));
    });
    document.getElementById('idle-time').textContent = Math.floor(sec / 3600) + ':' + String(Math.floor(sec % 3600 / 60)).padStart(2, '0');
    document.getElementById('idle-bar').style.width = (sec / max * 100).toFixed(1) + '%';
    document.getElementById('idle-full').style.display = sec >= max ? '' : 'none';
  }
  setInterval(tick, 1000);

  // オート周回の演出（ダメージの数字と戦利品がランダムに出る）
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var field = document.getElementById('idle-field');
  var foes = field.querySelectorAll('.idle-row.enemy .idle-unit');
  var allies = field.querySelectorAll('.idle-row.ally .idle-unit');
  if (!foes.length || !allies.length) return;
  var claimBtn = document.querySelector('.idle-actions .btn[value="claim"]');
  if (claimBtn && base >= 600) claimBtn.classList.add('ready');
  var kills = 0, killEl = document.getElementById('idle-kills');
  var hp = [].map.call(foes, function () { return 100; });
  var scale = <?= max(1, round(1.13 ** ($stage - 1), 2)) ?>;

  function again(el, cls) { el.classList.remove(cls); void el.offsetWidth; el.classList.add(cls); }

  // 戦利品を敵の位置から報酬欄へ飛ばす
  function flyLoot(from) {
    var kind = Math.random() < 0.75 ? ['🪙', 0] : (Math.random() < 0.6 ? ['📘', 1] : ['💎', 2]);
    var target = box.children[kind[1]];
    var a = from.getBoundingClientRect(), b = target.getBoundingClientRect();
    var s = document.createElement('span');
    s.className = 'idle-fly';
    s.textContent = kind[0];
    s.style.left = (a.left + a.width / 2 - 10) + 'px';
    s.style.top = (a.top + a.height / 2 - 10) + 'px';
    document.body.appendChild(s);
    requestAnimationFrame(function () {
      s.style.transform = 'translate(' + (b.left + b.width / 2 - a.left - a.width / 2) + 'px,' + (b.top + b.height / 2 - a.top - a.height / 2) + 'px) scale(.6)';
      s.style.opacity = '.2';
    });
    setTimeout(function () { s.remove(); again(target.querySelector('b'), 'bump'); }, 820);
  }

  setInterval(function () {
    var alive = [].filter.call(foes, function (f) { return !f.classList.contains('dead'); });
    if (!alive.length) return;
    var t = alive[Math.floor(Math.random() * alive.length)], i = [].indexOf.call(foes, t);
    var atk = allies[Math.floor(Math.random() * allies.length)];
    again(atk, 'lunge');

    var crit = Math.random() < 0.15;
    var pop = document.createElement('span');
    pop.className = 'idle-pop' + (crit ? ' crit' : '');
    pop.textContent = Math.floor((crit ? 1.5 : 1) * (80 + Math.random() * 120) * scale);
    t.appendChild(pop);
    var slash = document.createElement('i');
    slash.className = 'idle-slash';
    slash.style.left = '50%'; slash.style.top = '40%';
    t.appendChild(slash);
    again(t, 'hit');
    setTimeout(function () { pop.remove(); slash.remove(); }, 900);

    // HPを減らし、0になったら倒れて、少しして次の敵が出てくる
    hp[i] -= (t.classList.contains('boss') ? 6 : 18) * (crit ? 1.6 : 1) * (0.7 + Math.random() * 0.6);
    t.querySelector('.ihp i').style.width = Math.max(0, hp[i]) + '%';
    if (hp[i] <= 0) {
      t.classList.add('dead');
      killEl.textContent = ++kills;
      flyLoot(t);
      setTimeout(function () {
        hp[i] = 100;
        t.querySelector('.ihp i').style.width = '100%';
        t.classList.remove('dead');
        again(t, 'respawn');
      }, 1200);
    } else if (Math.random() < 0.12) {
      flyLoot(t);
    }
  }, 650);
})();
</script>
<?php render_footer(); ?>
