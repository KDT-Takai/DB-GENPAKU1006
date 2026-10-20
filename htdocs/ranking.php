<?php
// 対戦ランキング（勝利数 → 勝率の順）
require_once __DIR__ . '/common.php';

$me = current_user();

$rows = db()->query(
    "SELECT u.id, u.name,
            COUNT(b.id)                    AS battles,
            COALESCE(SUM(b.result = 'win'), 0) AS wins,
            COALESCE(SUM(b.result = 'win') / NULLIF(COUNT(b.id), 0) * 100, 0) AS win_rate
       FROM users u
       LEFT JOIN battle_histories b ON b.user_id = u.id
      GROUP BY u.id, u.name
      ORDER BY wins DESC, win_rate DESC, u.id"
)->fetchAll();

render_header('ランキング', 0, '対戦の勝利数ランキングです。勝利数が同じ場合は勝率の高い順に並びます。');
?>
<section class="panel">
  <div class="table-wrap">
  <table class="list">
    <tr><th>順位</th><th>ユーザー</th><th style="text-align:right">勝利数</th><th style="text-align:right">対戦数</th><th style="text-align:right">勝率</th><th style="text-align:right">チーム戦闘力</th></tr>
<?php
$rank = 0;
$prev = null;
foreach ($rows as $i => $r):
    // 勝利数と勝率が同じなら同順位
    $key = $r['wins'] . '-' . round($r['win_rate'], 2);
    if ($key !== $prev) {
        $rank = $i + 1;
        $prev = $key;
    }
?>
    <tr class="<?= $me && $me['id'] === (int)$r['id'] ? 'me' : '' ?>">
      <td class="num" style="text-align:left; <?= $rank <= 3 ? 'color:var(--gold)' : '' ?>"><?= $rank ?></td>
      <td><?= h($r['name']) ?></td>
      <td class="num"><?= h($r['wins']) ?></td>
      <td class="num"><?= h($r['battles']) ?></td>
      <td class="num"><?= number_format((float)$r['win_rate'], 1) ?>%</td>
      <td class="num"><?= number_format(team_power(team_cards((int)$r['id']))) ?></td>
    </tr>
<?php endforeach; ?>
  </table>
  </div>
</section>
<?php render_footer(); ?>
