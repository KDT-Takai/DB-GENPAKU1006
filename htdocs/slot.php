<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
<meta name="theme-color" content="#0b0f1e">
<title>夜港の灯 ヨミナト</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Shippori+Mincho+B1:wght@800&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #070a16; --panel: #121830; --line: #2a3360; --text: #e8ecff; --muted: #8b93b8;
  --gold: #f3c969; --red: #ff4d5e; --blue: #4da3ff; --green: #49d17d;
  --cell: 64px;
}
* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
html, body { margin: 0; height: 100%; background: var(--bg); color: var(--text);
  font-family: system-ui, -apple-system, "Hiragino Sans", "Yu Gothic UI", sans-serif; overscroll-behavior: none; }
body { padding: env(safe-area-inset-top) 12px env(safe-area-inset-bottom); user-select: none; -webkit-user-select: none; touch-action: manipulation; }
button { font: inherit; color: inherit; cursor: pointer; }
.app { max-width: 560px; margin: 0 auto; min-height: 100%; display: flex; flex-direction: column; gap: 8px; padding-block: 8px; }

/* 上部バー */
.topbar { display: flex; align-items: center; gap: 6px; }
.topbar a, .topbar button { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 6px 10px; font-size: 13px; color: var(--text); text-decoration: none; }
.topbar .title { flex: 1; font-weight: 800; letter-spacing: .08em; font-size: 15px; text-align: center; }
.topbar .title small { display: block; font-size: 9px; color: var(--gold); letter-spacing: .3em; }
.topbar .on { border-color: var(--gold); color: var(--gold); }

/* 液晶 */
.lcd { --tone1: #1a2550; --tone2: #070b1c; position: relative; aspect-ratio: 16 / 9; border-radius: 14px; overflow: hidden;
  border: 3px solid #0c0f1e; box-shadow: 0 0 0 1px #3a4270, inset 0 0 40px rgba(0,0,0,.8);
  background: radial-gradient(130% 100% at 50% 110%, var(--tone1) 0%, var(--tone2) 70%); transition: --tone1 .6s; isolation: isolate; }
.lcd.tone1 { --tone1: #3a2a6a; }
.lcd.tone2 { --tone1: #5a1a5a; --tone2: #12041a; }
.lcd.tone3 { --tone1: #8a1418; --tone2: #1a0306; }
.lcd.m-cz    { --tone1: #6a0e1c; --tone2: #120306; }
.lcd.m-bonus { --tone1: #8a5a10; --tone2: #160a02; }
.lcd.m-bonus.red { --tone1: #a80c22; --tone2: #1a0206; }
.lcd.m-at    { --tone1: #c2501a; --tone2: #1a0820; }
.lcd.m-zone  { --tone1: #ffb020; --tone2: #4a0606; }
.lcd.black   { background: #000 !important; }
.lcd canvas.fx { position: absolute; inset: 0; width: 100%; height: 100%; z-index: 0; }
.lcd .horizon { position: absolute; left: 0; right: 0; bottom: 0; height: 34%; z-index: 0;
  background: linear-gradient(transparent, rgba(0,0,0,.55)), repeating-linear-gradient(180deg, rgba(255,255,255,.05) 0 1px, transparent 1px 7px); }
.lcd .vignette { position: absolute; inset: 0; z-index: 3; pointer-events: none; background: radial-gradient(120% 90% at 50% 50%, transparent 55%, rgba(0,0,0,.75)); }
.lcd .scan { position: absolute; inset: 0; z-index: 3; pointer-events: none; background: repeating-linear-gradient(0deg, rgba(0,0,0,.1) 0 1px, transparent 1px 3px); }
.lcd .bars::before, .lcd .bars::after { content: ""; position: absolute; left: 0; right: 0; height: 0; background: #000; z-index: 4; transition: height .35s ease; }
.lcd .bars::before { top: 0; } .lcd .bars::after { bottom: 0; }
.lcd.cinema .bars::before, .lcd.cinema .bars::after { height: 11%; }

.lcd .msg { position: absolute; inset: 0; z-index: 5; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 0 6%; pointer-events: none; }
.lcd .big { font-family: "Shippori Mincho B1", "Yu Mincho", "Hiragino Mincho ProN", serif; font-weight: 800;
  font-size: clamp(24px, 8.4vw, 46px); letter-spacing: .06em; line-height: 1.1; color: #fff;
  text-shadow: 0 0 2px #000, 0 0 18px rgba(0,0,0,.9), 0 3px 0 #000; }
.lcd .big.pop { animation: slam .45s cubic-bezier(.2,.9,.3,1.2); }
.lcd .big.red  { color: #ff3044; text-shadow: 0 0 2px #000, 0 0 22px rgba(255,30,50,.8), 0 3px 0 #300; }
.lcd .big.gold { background: linear-gradient(180deg, #fff6c8 0%, #f3c969 45%, #a8700e 55%, #ffe08a 100%); -webkit-background-clip: text; background-clip: text; color: transparent;
  filter: drop-shadow(0 2px 0 #000) drop-shadow(0 0 14px rgba(243,201,105,.6)); text-shadow: none; }
.lcd .big.blue { color: #8fd0ff; text-shadow: 0 0 2px #000, 0 0 18px rgba(80,160,255,.8); }
.lcd .big.green { color: #7dffa8; text-shadow: 0 0 2px #000, 0 0 18px rgba(60,220,120,.8); }
.lcd .big.rainbow { background: linear-gradient(90deg,#ff4d5e,#f3c969,#49d17d,#4da3ff,#c77dff,#ff4d5e); background-size: 200% 100%;
  -webkit-background-clip: text; background-clip: text; color: transparent; text-shadow: none;
  filter: drop-shadow(0 2px 0 #000) drop-shadow(0 0 12px rgba(255,255,255,.5)); animation: slam .45s cubic-bezier(.2,.9,.3,1.2), rainbow 1.2s linear infinite; }
.lcd .big.blink { animation: slam .45s cubic-bezier(.2,.9,.3,1.2), blink .5s steps(2) .45s infinite; }
.lcd .sub { margin-top: 6px; font-size: clamp(11px, 3.3vw, 14px); letter-spacing: .12em; color: #e6e9ff; text-shadow: 0 1px 4px #000; }
.lcd .sub:not(:empty) { padding: 2px 12px; background: linear-gradient(90deg, transparent, rgba(0,0,0,.6) 20%, rgba(0,0,0,.6) 80%, transparent); }

.lcd .hud { position: absolute; top: 7px; left: 8px; right: 8px; z-index: 6; display: flex; justify-content: space-between; align-items: flex-start; gap: 6px; pointer-events: none; }
.lcd .pill { font-size: 10px; letter-spacing: .08em; color: #dfe4ff; background: rgba(0,0,0,.55); border: 1px solid rgba(255,255,255,.18); border-radius: 4px; padding: 2px 7px; }
.lcd .pill b { color: var(--gold); font-size: 12px; }
.lcd .counter { text-align: right; font-family: "Shippori Mincho B1", serif; line-height: 1; text-shadow: 0 2px 0 #000, 0 0 10px rgba(0,0,0,.8); }
.lcd .counter b { display: block; font-size: clamp(22px, 7vw, 34px); color: #fff; }
.lcd .counter small { font-size: 10px; letter-spacing: .2em; color: var(--gold); }
.lcd .rate { position: absolute; left: 8px; bottom: 8px; z-index: 6; font-family: "Shippori Mincho B1", serif; text-shadow: 0 2px 0 #000; }
.lcd .rate small { display: block; font-size: 9px; letter-spacing: .25em; color: #ffd6a0; }
.lcd .rate b { font-size: clamp(18px, 5.6vw, 26px); color: #fff; }

.lcd .gauge { position: absolute; left: 14%; right: 14%; bottom: 13%; z-index: 6; }
.lcd .gauge span { display: flex; justify-content: space-between; font-size: 10px; letter-spacing: .2em; color: #ffb0b8; text-shadow: 0 1px 2px #000; margin-bottom: 3px; }
.lcd .gauge .track { height: 10px; border: 1px solid rgba(255,120,130,.6); background: rgba(0,0,0,.6); transform: skewX(-20deg); overflow: hidden; }
.lcd .gauge i { display: block; height: 100%; background: linear-gradient(90deg, #ff2a3a, #ff9a3c); box-shadow: 0 0 10px #ff2a3a; transition: width .5s cubic-bezier(.3,1.4,.5,1); }

.lcd .steps { position: absolute; top: 34px; left: 50%; transform: translateX(-50%); z-index: 6; display: flex; gap: 6px; }
.lcd .steps i { width: 14px; height: 18px; border-radius: 45%; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.2); opacity: 0; transition: background .15s, box-shadow .15s; }
.lcd .steps.show i { opacity: 1; }
.lcd .steps i.lit { background: var(--c, #4da3ff); box-shadow: 0 0 12px var(--c, #4da3ff); animation: pop .25s; }

.lcd .cutin { position: absolute; left: -10%; right: -10%; top: 50%; height: 34%; z-index: 7; transform: translateY(-50%) skewY(-6deg); display: none;
  align-items: center; justify-content: center; overflow: hidden; background: linear-gradient(90deg, transparent, var(--c, #a8142a) 15%, var(--c, #a8142a) 85%, transparent); }
.lcd .cutin.go { display: flex; animation: cutin 1.1s ease-in-out forwards; }
.lcd .cutin::before { content: ""; position: absolute; inset: 0; background: repeating-linear-gradient(90deg, rgba(255,255,255,.18) 0 2px, transparent 2px 30px); animation: lines .3s linear infinite; }
.lcd .cutin b { position: relative; font-family: "Shippori Mincho B1", serif; font-size: clamp(26px, 9vw, 50px); letter-spacing: .3em; color: #fff; text-shadow: 0 3px 0 #000, 0 0 20px #000; transform: skewY(6deg); }
.lcd .stamp { position: absolute; inset: 0; z-index: 8; display: none; align-items: center; justify-content: center; pointer-events: none; }
.lcd .stamp b { font-family: "Shippori Mincho B1", serif; font-size: clamp(60px, 26vw, 140px); line-height: 1; color: #fff; padding: 0 .1em;
  border: .06em solid currentColor; border-radius: .08em; text-shadow: 0 0 30px currentColor; }
.lcd .stamp.go { display: flex; animation: stampIn .6s cubic-bezier(.2,1.6,.4,1) forwards; }
.lcd .flash { position: absolute; inset: 0; z-index: 9; background: #fff; opacity: 0; pointer-events: none; }
.lcd .flash.go { animation: flash .5s ease-out; }
.lcd .debug { position: absolute; right: 6px; bottom: 4px; z-index: 9; font: 10px/1.3 ui-monospace, monospace; color: #9ff; text-shadow: 0 1px 2px #000; white-space: pre; text-align: right; }
.shake { animation: shake .35s linear; }
@keyframes slam { 0% { transform: scale(2.4); opacity: 0; filter: blur(8px); } 60% { transform: scale(.94); opacity: 1; filter: none; } 100% { transform: scale(1); } }
@keyframes pop { 0% { transform: scale(.4); opacity: 0; } 70% { transform: scale(1.15); } 100% { transform: scale(1); opacity: 1; } }
@keyframes rainbow { to { background-position: 200% 0; } }
@keyframes blink { 50% { opacity: .35; } }
@keyframes flash { 0% { opacity: .9; } 100% { opacity: 0; } }
@keyframes cutin { 0% { clip-path: inset(0 100% 0 0); } 18% { clip-path: inset(0 0 0 0); } 80% { clip-path: inset(0 0 0 0); opacity: 1; } 100% { clip-path: inset(0 0 0 100%); opacity: 0; } }
@keyframes lines { to { background-position: 30px 0; } }
@keyframes stampIn { 0% { transform: scale(3) rotate(-12deg); opacity: 0; } 100% { transform: scale(1) rotate(-6deg); opacity: 1; } }
@keyframes shake { 0%,100% { transform: none; } 20% { transform: translate(-5px, 3px); } 40% { transform: translate(5px, -3px); } 60% { transform: translate(-3px, -2px); } 80% { transform: translate(3px, 2px); } }
/* 看板 */
.marquee { position: relative; display: flex; align-items: center; justify-content: center; gap: 10px; padding: 6px 12px; border-radius: 14px 14px 4px 4px;
  background: linear-gradient(#2a1a0a, #120a04); border: 1px solid #6a4a1a; box-shadow: inset 0 0 18px rgba(255,160,40,.25), 0 0 20px rgba(255,140,30,.15); overflow: hidden; }
.marquee b { font-family: "Shippori Mincho B1", "Yu Mincho", serif; font-size: 24px; letter-spacing: .25em; line-height: 1.1;
  background: linear-gradient(180deg, #fff6c8 0%, #f3c969 45%, #a8700e 55%, #ffe08a 100%); -webkit-background-clip: text; background-clip: text; color: transparent;
  filter: drop-shadow(0 2px 0 #000); }
.marquee small { font-size: 9px; letter-spacing: .4em; color: #c08a3a; align-self: flex-end; margin-bottom: 4px; }
.marquee i { flex: 1; height: 4px; border-radius: 2px; background: repeating-linear-gradient(90deg, #ffd27a 0 6px, transparent 6px 14px); background-size: 28px 100%; animation: chase .6s linear infinite; opacity: .8; }
.marquee i:last-child { animation-direction: reverse; }
@keyframes chase { to { background-position: 28px 0; } }

/* 筐体 */
.cabinet { position: relative; padding: 10px 14px; border-radius: 6px 6px 18px 18px;
  background: linear-gradient(90deg, #0c0d14 0%, #2a2d3c 6%, #1a1c26 12%, #12131a 50%, #1a1c26 88%, #2a2d3c 94%, #0c0d14 100%);
  border: 1px solid #3a3e56; box-shadow: inset 0 1px 0 rgba(255,255,255,.15), 0 10px 30px rgba(0,0,0,.7); }
/* 左右の LED */
.cabinet::before, .cabinet::after { content: ""; position: absolute; top: 14px; bottom: 14px; width: 4px; border-radius: 2px;
  background: repeating-linear-gradient(180deg, var(--lamp, #4a5aa0) 0 5px, transparent 5px 12px); background-size: 100% 24px; animation: chaseV .5s linear infinite; opacity: .9;
  box-shadow: 0 0 8px var(--lamp, transparent); }
.cabinet::before { left: 4px; } .cabinet::after { right: 4px; animation-direction: reverse; }
@keyframes chaseV { to { background-position: 0 24px; } }
.lamp { height: 6px; border-radius: 3px; margin: -2px 6px 8px; background: var(--lamp, #2a3360); box-shadow: 0 0 12px var(--lamp, transparent); transition: background .3s, box-shadow .3s; }
.lamp.rainbow { background: linear-gradient(90deg,#ff4d5e,#f3c969,#49d17d,#4da3ff,#c77dff,#ff4d5e); background-size: 200% 100%; animation: rainbow 1s linear infinite; box-shadow: 0 0 16px #fff6; }
.lamp.blink { animation: blink .4s steps(2) infinite; }
.meters { display: flex; gap: 6px; margin-bottom: 8px; }
.meter { position: relative; flex: 1; background: linear-gradient(#020306, #0a0c14); border: 1px solid #2a2e44; border-radius: 6px; padding: 3px 8px 4px; text-align: right;
  box-shadow: inset 0 2px 6px rgba(0,0,0,.9); }
.meter span { display: block; font-size: 8px; color: #7a80a0; text-align: left; letter-spacing: .2em; }
.meter b { position: relative; font: 700 21px/1.1 "Consolas", ui-monospace, monospace; color: #ff5a3a; text-shadow: 0 0 6px rgba(255,70,40,.8), 0 0 14px rgba(255,70,40,.4); letter-spacing: .08em; }
.meter b::before { content: "8888"; position: absolute; right: 0; color: rgba(255,70,40,.08); text-shadow: none; }
.meter.replay b { color: #6ac8ff; text-shadow: 0 0 6px rgba(80,180,255,.8); }
.meter b.count { color: #ffd26a; text-shadow: 0 0 8px rgba(255,200,80,.9); }

/* リール窓：金属の枠＋ガラスの映り込み */
.reels { position: relative; display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; padding: 7px; border-radius: 10px;
  background: linear-gradient(#5a5e74, #1a1c26 18%, #0a0b10 50%, #1a1c26 82%, #5a5e74); box-shadow: 0 0 0 1px #000, 0 0 0 3px #3a3e56, inset 0 0 12px #000; }
.reels::after { content: ""; position: absolute; inset: 7px; border-radius: 6px; pointer-events: none; z-index: 2;
  background: linear-gradient(115deg, rgba(255,255,255,.16) 0%, rgba(255,255,255,.04) 22%, transparent 23%, transparent 60%, rgba(255,255,255,.06) 61%, transparent 75%); }
.reel { --rl: #fff7e6; position: relative; height: calc(var(--cell) * 3); overflow: hidden; border-radius: 5px;
  background: linear-gradient(90deg, #b8b2a4 0%, var(--rl) 18%, #fff 50%, var(--rl) 82%, #b8b2a4 100%); transition: filter .15s; }
.reel::after { content: ""; position: absolute; inset: 0; pointer-events: none;
  background: linear-gradient(rgba(0,0,0,.55), rgba(0,0,0,.05) 26%, transparent 40%, transparent 60%, rgba(0,0,0,.05) 74%, rgba(0,0,0,.55)); }
.reels.warm .reel { --rl: #ffe9b0; }
.reels.redlight .reel { --rl: #ffc8c8; }
.strip { position: absolute; left: 0; right: 0; top: 0; will-change: transform; }
.cell { height: var(--cell); display: flex; align-items: center; justify-content: center; }
.cell svg { width: 80%; height: 80%; }
.reel.spin .strip { filter: blur(1.2px); }
.reel.spin .cell svg { transform: scaleY(1.25); }
.reels.dark .reel { filter: brightness(.25); }
.reels.rflash .reel { animation: rflash .18s steps(2) 6; }
@keyframes rflash { 50% { filter: brightness(.15); } }
.reel.bounce .strip { animation: bounce .12s ease-out; }
@keyframes bounce { 50% { margin-top: 3px; } }
.payline { position: absolute; left: 4px; right: 4px; top: calc(7px + var(--cell) * 1.5); height: 2px; z-index: 3; background: rgba(255,60,60,.5); pointer-events: none; }
.reels.win .payline { background: var(--gold); box-shadow: 0 0 10px var(--gold); height: 3px; }
.reels.win .reel { animation: winGlow .5s ease-out; }
@keyframes winGlow { 0% { filter: brightness(1.5); } 100% { filter: none; } }
.controls { display: flex; align-items: center; gap: 8px; margin-top: 12px; }
.controls.lefty { flex-direction: row-reverse; }
.side { display: flex; flex-direction: column; gap: 6px; }
.bet, .auto-btn { border: 1px solid #4a4e66; border-radius: 10px; font-weight: 800; padding: 8px 10px; font-size: 11px; letter-spacing: .05em;
  background: linear-gradient(#3a3e54, #1a1c28); box-shadow: inset 0 1px 0 rgba(255,255,255,.2), 0 3px 0 #08090e; }
.bet:active, .auto-btn:active { transform: translateY(2px); box-shadow: inset 0 1px 0 rgba(255,255,255,.2), 0 1px 0 #08090e; }
.auto-btn.on { background: linear-gradient(#ffe08a, #c08a1e); color: #1a1000; border-color: #ffe08a; box-shadow: 0 0 12px rgba(243,201,105,.6), 0 3px 0 #6a4a00; }
/* レバー：金属の軸と赤い玉 */
.lever { position: relative; width: 62px; height: 92px; border: 0; background: none; padding: 0; color: transparent; font-size: 0; }
.lever::before { content: ""; position: absolute; left: 50%; bottom: 6px; width: 10px; height: 58px; margin-left: -5px; border-radius: 5px;
  background: linear-gradient(90deg, #6a6e80, #e8eaf2 45%, #8a8ea0); box-shadow: 0 2px 4px #000; transform-origin: 50% 100%; transition: transform .08s; }
.lever::after { content: ""; position: absolute; left: 50%; top: 4px; width: 40px; height: 40px; margin-left: -20px; border-radius: 50%;
  background: radial-gradient(circle at 35% 30%, #fff 0, #ff8a9a 12%, #e0102a 45%, #5a0010 100%); box-shadow: 0 4px 8px rgba(0,0,0,.7), 0 0 14px rgba(255,40,60,.35); transition: transform .08s; }
.lever:active::before { transform: scaleY(.72); }
.lever:active::after { transform: translateY(16px); }
.lever span { position: absolute; left: 0; right: 0; bottom: -2px; font-size: 9px; color: #7a80a0; letter-spacing: .2em; text-align: center; }
/* 停止ボタン：金属リング＋中が光る */
.stops { flex: 1; display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.stop { position: relative; border: 0; border-radius: 50%; aspect-ratio: 1; max-width: 84px; width: 100%; justify-self: center; font-weight: 800; font-size: 12px; color: #6a7090;
  background: radial-gradient(circle, #2a2e40 0 52%, #8a8ea4 54%, #e8eaf2 60%, #5a5e74 70%, #1a1c26 74%, #3a3e54 100%);
  box-shadow: 0 4px 0 #050608, 0 6px 10px rgba(0,0,0,.6); }
.stop.live { color: #fff; text-shadow: 0 0 6px #fff;
  background: radial-gradient(circle, #d0f0ff 0 8%, #3aa0ff 30%, #0a50c0 52%, #8a8ea4 54%, #e8eaf2 60%, #5a5e74 70%, #1a1c26 74%, #3a3e54 100%);
  box-shadow: 0 4px 0 #050608, 0 0 16px rgba(60,160,255,.55); }
.stop:active { transform: translateY(3px); box-shadow: 0 1px 0 #050608; }
.stop .navi { position: absolute; top: -6px; right: -2px; width: 26px; height: 26px; border-radius: 50%; background: var(--gold); color: #1a1000; font-size: 15px; line-height: 26px; display: none; animation: pop .3s; box-shadow: 0 0 10px var(--gold); }
.stop.navi-on .navi { display: block; }
.stop.navi-first { background: radial-gradient(circle, #fff6c8 0 8%, #f3c969 30%, #b07a12 52%, #8a8ea4 54%, #e8eaf2 60%, #5a5e74 70%, #1a1c26 74%, #3a3e54 100%) !important;
  box-shadow: 0 4px 0 #050608, 0 0 20px var(--gold) !important; color: #1a1000; text-shadow: none; }
.hint { font-size: 11px; color: var(--muted); text-align: center; }

/* シート */
.sheet-bg { position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 10; }
.sheet { position: fixed; left: 0; right: 0; bottom: 0; z-index: 11; max-height: 86vh; overflow-y: auto; background: var(--panel);
  border-top: 2px solid var(--line); border-radius: 18px 18px 0 0; padding: 14px 16px calc(16px + env(safe-area-inset-bottom)); max-width: 640px; margin: 0 auto; }
.sheet h2 { margin: 0 0 10px; font-size: 17px; display: flex; justify-content: space-between; align-items: center; }
.sheet h3 { margin: 16px 0 6px; font-size: 13px; color: var(--gold); letter-spacing: .1em; }
.sheet .close { background: none; border: 1px solid var(--line); border-radius: 8px; padding: 4px 12px; }
.opt { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 0; border-bottom: 1px solid #1f2648; font-size: 14px; }
.opt small { display: block; color: var(--muted); font-size: 11px; }
.seg { display: flex; flex-wrap: wrap; gap: 4px; justify-content: flex-end; }
.seg button { background: #0b1024; border: 1px solid var(--line); border-radius: 8px; padding: 5px 9px; font-size: 12px; }
.seg button.sel { background: var(--gold); color: #1a1000; border-color: var(--gold); font-weight: 700; }
.opt input[type=range] { width: 140px; }
.danger { background: #3a0f18; border: 1px solid #7a2030; border-radius: 10px; padding: 8px 14px; color: #ff9aa6; }
.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.stats div { background: #0b1024; border: 1px solid #1f2648; border-radius: 10px; padding: 6px 8px; }
.stats span { display: block; font-size: 10px; color: var(--muted); }
.stats b { font-size: 16px; }
.plus { color: var(--green); } .minus { color: var(--red); }
canvas.graph { width: 100%; height: 160px; background: #050713; border: 1px solid #1f2648; border-radius: 10px; }
.table-wrap { overflow-x: auto; }
table.hist { width: 100%; border-collapse: collapse; font-size: 12px; }
table.hist th, table.hist td { padding: 5px 6px; border-bottom: 1px solid #1f2648; text-align: right; white-space: nowrap; }
table.hist th:first-child, table.hist td:first-child { text-align: left; }
.toast { position: fixed; left: 50%; top: calc(12px + env(safe-area-inset-top)); transform: translateX(-50%); background: #000c; border: 1px solid var(--line); padding: 8px 14px; border-radius: 10px; font-size: 13px; z-index: 20; }

@media (orientation: landscape) and (max-height: 520px) {
  .app { max-width: none; display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: auto 1fr; align-items: start; }
  .topbar { grid-column: 1 / -1; }
  :root { --cell: 52px; }
}
</style>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <defs>
    <linearGradient id="g7" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff8a8a"/><stop offset=".45" stop-color="#e8102c"/><stop offset="1" stop-color="#6a0010"/></linearGradient>
    <linearGradient id="gGold" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff6c8"/><stop offset=".45" stop-color="#f3c969"/><stop offset=".55" stop-color="#b07a12"/><stop offset="1" stop-color="#ffe08a"/></linearGradient>
    <linearGradient id="gPlate" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4a4a58"/><stop offset=".5" stop-color="#14141c"/><stop offset="1" stop-color="#26262e"/></linearGradient>
    <radialGradient id="gBlue" cx=".38" cy=".32" r=".75"><stop offset="0" stop-color="#bfe8ff"/><stop offset=".4" stop-color="#2a86e8"/><stop offset="1" stop-color="#0a2a70"/></radialGradient>
    <linearGradient id="gBell" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff6b0"/><stop offset=".4" stop-color="#f8c81e"/><stop offset="1" stop-color="#9a6400"/></linearGradient>
    <radialGradient id="gCherry" cx=".35" cy=".3" r=".8"><stop offset="0" stop-color="#ffb0bc"/><stop offset=".35" stop-color="#e01434"/><stop offset="1" stop-color="#4a0010"/></radialGradient>
    <radialGradient id="gMelon" cx=".38" cy=".3" r=".8"><stop offset="0" stop-color="#b8ffb0"/><stop offset=".4" stop-color="#2eaa40"/><stop offset="1" stop-color="#063a10"/></radialGradient>
    <radialGradient id="gLantern" cx=".5" cy=".45" r=".6"><stop offset="0" stop-color="#fff4c0"/><stop offset=".45" stop-color="#ff9a2a"/><stop offset="1" stop-color="#a03200"/></radialGradient>
    <symbol id="s-7" viewBox="0 0 100 100">
      <text x="50" y="86" text-anchor="middle" font-size="98" font-weight="900" font-family="Arial Black, Impact, sans-serif" fill="#2a0006" stroke="#2a0006" stroke-width="14" stroke-linejoin="round">7</text>
      <text x="50" y="86" text-anchor="middle" font-size="98" font-weight="900" font-family="Arial Black, Impact, sans-serif" fill="url(#g7)" stroke="url(#gGold)" stroke-width="4" stroke-linejoin="round" paint-order="stroke">7</text>
      <path d="M30 20h40" stroke="#fff" stroke-opacity=".55" stroke-width="4" stroke-linecap="round"/>
    </symbol>
    <symbol id="s-B" viewBox="0 0 100 100">
      <rect x="4" y="27" width="92" height="46" rx="7" fill="url(#gGold)"/>
      <rect x="8" y="31" width="84" height="38" rx="5" fill="url(#gPlate)"/>
      <text x="50" y="62" text-anchor="middle" font-size="29" font-weight="900" font-family="Arial Black, sans-serif" fill="url(#gGold)" letter-spacing="1">BAR</text>
      <rect x="10" y="33" width="80" height="10" rx="4" fill="#fff" opacity=".12"/>
    </symbol>
    <symbol id="s-R" viewBox="0 0 100 100">
      <circle cx="50" cy="50" r="38" fill="#06183f"/>
      <circle cx="50" cy="50" r="35" fill="url(#gBlue)"/>
      <path d="M33 54a18 18 0 1 0 6-16" fill="none" stroke="#fff" stroke-width="8" stroke-linecap="round"/>
      <path d="M27 27l15 11-17 7z" fill="#fff"/>
      <ellipse cx="38" cy="30" rx="14" ry="7" fill="#fff" opacity=".35" transform="rotate(-25 38 30)"/>
    </symbol>
    <symbol id="s-L" viewBox="0 0 100 100">
      <circle cx="50" cy="12" r="5" fill="#9a6400"/>
      <path d="M50 14c-17 0-25 15-25 31v15l-9 13h68l-9-13V45c0-16-8-31-25-31z" fill="url(#gBell)" stroke="#5a3a00" stroke-width="3.5" stroke-linejoin="round"/>
      <path d="M36 28c-4 6-5 14-5 24" stroke="#fff" stroke-opacity=".7" stroke-width="5" stroke-linecap="round" fill="none"/>
      <rect x="18" y="70" width="64" height="5" rx="2" fill="#7a4e00" opacity=".6"/>
      <circle cx="50" cy="82" r="7" fill="url(#gBell)" stroke="#5a3a00" stroke-width="3"/>
    </symbol>
    <symbol id="s-C" viewBox="0 0 100 100">
      <path d="M50 14c-6 18-16 30-22 42M51 14c3 18 12 28 20 40" fill="none" stroke="#3a6a12" stroke-width="5" stroke-linecap="round"/>
      <path d="M51 15c10-10 26-8 32 0-10 6-22 6-32 0z" fill="#4aa02a" stroke="#2a5a0a" stroke-width="2"/>
      <circle cx="28" cy="68" r="18" fill="url(#gCherry)" stroke="#3a0008" stroke-width="2.5"/>
      <circle cx="72" cy="66" r="18" fill="url(#gCherry)" stroke="#3a0008" stroke-width="2.5"/>
      <ellipse cx="22" cy="61" rx="5" ry="3.5" fill="#fff" opacity=".7"/><ellipse cx="66" cy="59" rx="5" ry="3.5" fill="#fff" opacity=".7"/>
    </symbol>
    <symbol id="s-W" viewBox="0 0 100 100">
      <circle cx="50" cy="52" r="38" fill="#04260a"/>
      <circle cx="50" cy="52" r="35" fill="url(#gMelon)"/>
      <path d="M50 17v70M31 22c-9 18-9 42 0 60M69 22c9 18 9 42 0 60M18 40c-3 9-3 20 0 28M82 40c3 9 3 20 0 28" fill="none" stroke="#063a10" stroke-width="4.5" opacity=".85"/>
      <ellipse cx="38" cy="34" rx="12" ry="6" fill="#fff" opacity=".35" transform="rotate(-30 38 34)"/>
    </symbol>
    <symbol id="s-K" viewBox="0 0 100 100">
      <circle cx="50" cy="50" r="44" fill="#ff9a2a" opacity=".22"/>
      <rect x="37" y="7" width="26" height="9" rx="2" fill="url(#gGold)" stroke="#5a3a00" stroke-width="1.5"/>
      <rect x="37" y="84" width="26" height="9" rx="2" fill="url(#gGold)" stroke="#5a3a00" stroke-width="1.5"/>
      <ellipse cx="50" cy="50" rx="31" ry="36" fill="url(#gLantern)" stroke="#6a1e00" stroke-width="3"/>
      <path d="M22 36h56M19 50h62M22 64h56" stroke="#c4500a" stroke-width="2" opacity=".7"/>
      <text x="50" y="61" text-anchor="middle" font-size="30" font-weight="900" font-family="Shippori Mincho B1, serif" fill="#3a0e00">灯</text>
    </symbol>
  </defs>
</svg>
<div class="app">
  <div class="topbar">
    <a href="menu.php">戻る</a>
    <div class="title"><small>YOMINATO</small>夜港の灯</div>
    <button id="btnData">データ</button>
    <button id="btnSettings">設定</button>
  </div>

  <div class="marquee"><i></i><b>夜港の灯</b><small>YOMINATO</small><i></i></div>
  <div class="lcd" id="lcd">
    <canvas class="fx" id="fx"></canvas>
    <div class="bars"></div>
    <div class="hud"><span class="pill" id="hudL"></span><span id="hudR"></span></div>
    <div class="steps" id="steps"><i></i><i></i><i></i><i></i><i></i></div>
    <div class="msg"><div class="big" id="big"></div><div class="sub" id="sub"></div></div>
    <div class="rate" id="rate" hidden><small>CONTINUE</small><b id="rateVal"></b></div>
    <div class="gauge" id="gauge" hidden><span><em>影喰</em><em id="gaugeNum"></em></span><div class="track"><i id="gaugeBar"></i></div></div>
    <div class="cutin" id="cutin"><b id="cutinText"></b></div>
    <div class="stamp" id="stamp"><b id="stampText"></b></div>
    <div class="vignette"></div>
    <div class="scan"></div>
    <div class="flash" id="flash"></div>
    <div class="debug" id="debug" hidden></div>
  </div>
  <div class="cabinet" id="cabinet">
    <div class="lamp" id="lamp"></div>
    <div class="meters">
      <div class="meter"><span>CREDIT</span><b id="mCredit">0</b></div>
      <div class="meter"><span>GAME</span><b id="mGame">0</b></div>
      <div class="meter" id="mPayBox"><span>PAYOUT</span><b id="mPay">0</b></div>
    </div>
    <div class="reels" id="reels">
      <div class="reel" data-i="0"><div class="strip"></div></div>
      <div class="reel" data-i="1"><div class="strip"></div></div>
      <div class="reel" data-i="2"><div class="strip"></div></div>
      <div class="payline"></div>
    </div>
    <div class="controls" id="controls">
      <div class="side">
        <button class="bet" id="btnMedal">メダル<br>+1000</button>
        <button class="auto-btn" id="btnAuto">AUTO</button>
      </div>
      <button class="lever" id="btnLever" aria-label="レバー"><span>LEVER</span></button>
      <div class="stops">
        <button class="stop" data-i="0"><span class="navi">1</span>左</button>
        <button class="stop" data-i="1"><span class="navi">1</span>中</button>
        <button class="stop" data-i="2"><span class="navi">1</span>右</button>
      </div>
    </div>
  </div>
  <div class="hint">PC：Space＝レバー / J・K・L＝停止 / A＝オート</div>
</div>

<div id="sheetBg" class="sheet-bg" hidden></div>
<div id="sheetSettings" class="sheet" hidden></div>
<div id="sheetData" class="sheet" hidden></div>
<div id="toast" class="toast" hidden></div>

<script>
'use strict';
// ===== リール配列（上→下の並び。リールは下向きに回転） =====
const STRIPS = [
  ['7','R','L','W','C','R','L','B','R','L','K','R','L','W','R','L','C','K','R','L','B'],
  ['7','L','R','W','B','L','R','K','L','R','C','L','R','W','L','R','7','K','L','R','B'],
  ['7','R','L','B','W','R','L','K','R','L','C','R','L','W','R','L','7','B','R','L','K'],
];
const N = 21;
const mod = (a, n) => ((a % n) + n) % n;

// ===== 小役テーブル（/65536） =====
const TABLES = {
  normal: { replay: 8978, pbell: 16384, cbell: 2048, wcherry: 1024, scherry: 256, melon: 819, chance: 410, schance: 128, mcherry: 4 },
  at:     { replay: 26214, pbell: 26000, cbell: 2048, wcherry: 1024, scherry: 256, melon: 819, chance: 410, schance: 128 },
  bonus:  { wcherry: 1638, melon: 1638, chance: 820, scherry: 256, schance: 128, cbell: 61056 },
};
// 有効ライン（各リールの段：0=上段 1=中段 2=下段）
const LINES = [[1, 1, 1], [0, 0, 0], [2, 2, 2], [0, 1, 2], [2, 1, 0]];
const TRIPLE = { '7': 'red', B: 'white', R: 'replay', L: 'bell', W: 'melon', K: 'chance' };
// リーチ目：ボーナス成立中にだけ中段に出る出目
const REACH = [['7', '7', 'B'], ['B', 'B', '7'], ['7', 'B', '7'], ['K', '7', 'K']];
const pick = arr => arr[Math.floor(Math.random() * arr.length)];
// 小役 → 各リールの狙い {sym, row}（null は揃えない）と、その出目で成立する役
function makeTargets(flag) {
  const tri = (sym, li) => LINES[li].map(row => ({ sym, row }));
  const anyLine = () => Math.floor(Math.random() * LINES.length);
  switch (flag) {
    // 小役の3つ揃いは、止まったリールに合わせて揃えられるラインを選ぶ（tri）
    case 'replay': return { t: tri('R', anyLine()), win: 'replay', tri: 'R' };
    case 'pbell': case 'cbell': return { t: tri('L', anyLine()), win: 'bell', tri: 'L' };
    case 'melon': return { t: tri('W', anyLine()), win: 'melon', tri: 'W' };
    case 'chance': return { t: tri('K', anyLine()), win: 'chance', tri: 'K' };
    case 'red': return { t: tri('7', 0), win: 'red' };
    case 'white': return { t: tri('B', 0), win: 'white' };
    case 'schance': return { t: ['B', 'K', 'B'].map(sym => ({ sym, row: 1 })), win: 'schance' };
    case 'wcherry': return { t: [{ sym: 'C', row: chance(.5) ? 0 : 2 }, null, null], win: 'cherry' };
    case 'scherry': { const row = chance(.5) ? 0 : 2; return { t: [{ sym: 'C', row }, null, { sym: 'C', row }], win: 'scherry' }; }
    case 'mcherry': return { t: [{ sym: 'C', row: 1 }, null, null], win: 'mcherry' };
    case 'reach': return { t: pick(REACH).map(sym => ({ sym, row: 1 })), win: 'reach' };
    default: return { t: [null, null, null], win: null };
  }
}
// 盤面（各リールの [上段, 中段, 下段]）から成立している役をすべて返す
function judgeAll(g) {
  const wins = [];
  LINES.forEach(l => { const a = g[0][l[0]]; if (TRIPLE[a] && g[1][l[1]] === a && g[2][l[2]] === a) wins.push(TRIPLE[a]); });
  const mid = g[0][1] + g[1][1] + g[2][1];
  if (mid === 'BKB') wins.push('schance');
  if (REACH.some(p => p.join('') === mid)) wins.push('reach');
  const cr = g[0].indexOf('C');
  if (cr === 1) wins.push('mcherry');
  else if (cr >= 0) wins.push(g[2][cr] === 'C' ? 'scherry' : 'cherry');
  return wins;
}
const PAY = { replay: 0, bell: 9, melon: 6, cherry: 2, scherry: 2, mcherry: 2, chance: 1, schance: 1, reach: 0, red: 0, white: 0 };
const WIN_NAME = { replay: 'リプレイ', bell: 'ベル', melon: 'スイカ', cherry: '角チェリー', scherry: '強チェリー', mcherry: '中段チェリー',
  chance: 'チャンス目', schance: '強チャンス目', reach: 'リーチ目', red: '赤7', white: 'BAR' };
const RARE = ['wcherry', 'scherry', 'melon', 'chance', 'schance', 'mcherry'];
// ===== 設定差 =====
const SETTING = {
  1: { cz: .26, pure: 1 / 20000, tw: [58,30,10,2],  red: .25, rate: '約95%' },
  2: { cz: .28, pure: 1 / 12000, tw: [55,31,11,3],  red: .27, rate: '約98%' },
  3: { cz: .30, pure: 1 / 9000,  tw: [52,32,12,4],  red: .29, rate: '約101%' },
  4: { cz: .33, pure: 1 / 6500,  tw: [48,33,14,5],  red: .32, rate: '約105%' },
  5: { cz: .37, pure: 1 / 5000,  tw: [42,35,17,6],  red: .36, rate: '約110%' },
  6: { cz: .42, pure: 1 / 3500,  tw: [36,36,21,7],  red: .40, rate: '約115%' },
};
const TIERS = [50, 66, 80, 90, 95];
const CEILING = 800;
const AT_SET = 20;

// ===== 液晶の背景エフェクト（火の粉・火花・集中線） =====
const FX_STYLE = {
  normal: { n: 28, hue: [18, 38],   vx: [-6, 6],    vy: [-14, -30],   size: [.8, 2.2] },
  cz:     { n: 55, hue: [350, 370], vx: [-90, -30], vy: [-50, -110],  size: [.8, 2],   streak: true },
  pre:    { n: 40, hue: [270, 300], vx: [-20, 20],  vy: [-8, -20],    size: [1, 3] },
  bonus:  { n: 50, hue: [38, 52],   vx: [-12, 12],  vy: [-22, -55],   size: [.8, 2.8] },
  at:     { n: 60, hue: [14, 44],   vx: [-10, 10],  vy: [-60, -130],  size: [.8, 2.4], streak: true, lines: true },
  zone:   { n: 95, hue: [8, 50],    vx: [-25, 25],  vy: [-110, -200], size: [1.2, 3.2], streak: true, lines: true },
};
const fx = { cv: null, ctx: null, w: 0, h: 0, d: 1, mode: 'normal', tone: 0, red: false, parts: [], warm: false, t: 0, bg: null, key: '' };
// 背景の配色 [空の上, 地平線, 月, 灯り]
const PALETTE = {
  normal: ['#070d26', '#1e2e64', '#f4f1d8', '#ffcf7a'],
  tone1:  ['#0e0826', '#3e2c74', '#efe6ff', '#ffb070'],
  tone2:  ['#0c0416', '#5a1a5e', '#f0d0ff', '#ff9a60'],
  tone3:  ['#140306', '#8a1418', '#ff6a5a', '#ff7040'],
  cz:     ['#100306', '#6a0e1c', '#ff4a4a', '#ff6040'],
  bonus:  ['#140902', '#8a5a10', '#ffe7a0', '#ffd060'],
  bonusR: ['#180206', '#a80c22', '#ffd0c0', '#ffb060'],
  at:     ['#1a0822', '#d0601e', '#fff0c0', '#ffd080'],
  zone:   ['#3a0404', '#ffa020', '#fff6d0', '#fff0a0'],
};
function seeded(s) { return () => ((s = (s * 16807) % 2147483647) / 2147483647); }
// 夜の港の背景を1枚の画像に描いておく（毎フレームはこれを貼るだけ）
function fxPaintBg(pal, dawn) {
  const w = fx.w, h = fx.h, c = document.createElement('canvas');
  c.width = w * fx.d; c.height = h * fx.d;
  const g = c.getContext('2d'); g.scale(fx.d, fx.d);
  const hz = h * .66, r = seeded(11);
  let gr = g.createLinearGradient(0, 0, 0, hz);
  gr.addColorStop(0, pal[0]); gr.addColorStop(1, pal[1]);
  g.fillStyle = gr; g.fillRect(0, 0, w, hz);
  if (!dawn) for (let i = 0; i < 70; i++) { g.globalAlpha = .15 + r() * .7; g.fillStyle = '#fff'; const s = r() < .12 ? 1.6 : .9; g.fillRect(r() * w, r() * hz * .75, s, s); }
  g.globalAlpha = 1;
  // 月（夜明けRUSHでは地平線の太陽）
  const mx = dawn ? w * .5 : w * .8, my = dawn ? hz : h * .2, mr = dawn ? h * .2 : h * .085;
  gr = g.createRadialGradient(mx, my, mr * .5, mx, my, mr * (dawn ? 4.5 : 4));
  gr.addColorStop(0, pal[2] + 'aa'); gr.addColorStop(1, pal[2] + '00');
  g.fillStyle = gr; g.fillRect(0, 0, w, hz);
  g.fillStyle = pal[2]; g.beginPath(); g.arc(mx, my, mr, 0, Math.PI * 2); g.fill();
  if (!dawn) { g.fillStyle = 'rgba(0,0,0,.08)'; [[-.3, -.2, .25], [.25, .15, .18], [-.1, .35, .12]].forEach(([dx, dy, rr]) => { g.beginPath(); g.arc(mx + dx * mr, my + dy * mr, rr * mr, 0, 7); g.fill(); }); }
  // 遠くの山並み（2層）
  [['rgba(0,0,0,.28)', .16, .06], ['rgba(0,0,0,.5)', .09, .03]].forEach(([col, hi, lo]) => {
    g.fillStyle = col; g.beginPath(); g.moveTo(0, hz);
    for (let x = 0; x <= w + 20; x += w / 14) g.lineTo(x, hz - h * (lo + r() * hi));
    g.lineTo(w, hz); g.fill();
  });
  // 港町のシルエットと窓の灯り
  g.fillStyle = '#04050a';
  const lights = [];
  for (let x = w * .2; x < w * .98;) {
    const bw = 12 + r() * 22, bh = 8 + r() * 26;
    g.fillRect(x, hz - bh, bw, bh + 2);
    if (r() < .55) { g.beginPath(); g.moveTo(x - 3, hz - bh); g.lineTo(x + bw / 2, hz - bh - 8 - r() * 6); g.lineTo(x + bw + 3, hz - bh); g.fill(); }
    for (let k = 0; k < 2; k++) if (r() < .45) lights.push([x + 3 + r() * (bw - 6), hz - bh + 4 + r() * (bh - 8)]);
    x += bw + r() * 6;
  }
  g.fillStyle = pal[3];
  lights.forEach(([x, y]) => { g.globalAlpha = .5 + r() * .5; g.fillRect(x, y, 2.2, 2.6); });
  g.globalAlpha = 1;
  // 灯台
  const lx = w * .1, lh = h * .34;
  g.fillStyle = '#05060c';
  g.beginPath(); g.moveTo(lx - 9, hz); g.lineTo(lx - 5, hz - lh); g.lineTo(lx + 5, hz - lh); g.lineTo(lx + 9, hz); g.fill();
  g.fillRect(lx - 8, hz - lh - 10, 16, 10);
  g.beginPath(); g.moveTo(lx - 9, hz - lh - 10); g.lineTo(lx, hz - lh - 18); g.lineTo(lx + 9, hz - lh - 10); g.fill();
  fx.lamp = [lx, hz - lh - 5];
  // 水面
  gr = g.createLinearGradient(0, hz, 0, h);
  gr.addColorStop(0, pal[1]); gr.addColorStop(.25, pal[0]); gr.addColorStop(1, '#000');
  g.fillStyle = gr; g.fillRect(0, hz, w, h - hz);
  // 映り込み：地平線から上を上下反転して水面に重ねる
  g.save(); g.beginPath(); g.rect(0, hz, w, h - hz); g.clip();
  g.translate(0, hz * 2); g.scale(1, -1); g.globalAlpha = .45;
  g.drawImage(c, 0, 0, w * fx.d, hz * fx.d, 0, 0, w, hz);
  g.restore();
  g.globalAlpha = .5; g.fillStyle = '#000'; g.fillRect(0, hz, w, h - hz);
  g.globalAlpha = 1;
  // 桟橋と提灯
  const py = hz + (h - hz) * .45;
  g.fillStyle = '#030306'; g.fillRect(w * .52, py, w * .5, 5);
  for (let x = w * .54; x < w; x += w * .07) g.fillRect(x, py, 3, h - py);
  fx.lanterns = [];
  for (let x = w * .58; x < w; x += w * .1) { g.fillRect(x, py - 16, 2, 16); fx.lanterns.push([x + 1, py - 18]); }
  fx.hz = hz; fx.moon = [mx, my, mr];
  fx.bg = c;
}
// 霧の帯（ぼかした楕円を並べた画像を横に流す）
function fxFog() {
  const w = fx.w, h = fx.h, c = document.createElement('canvas');
  c.width = w; c.height = h;
  const g = c.getContext('2d'), r = seeded(5);
  for (let i = 0; i < 14; i++) {
    const x = r() * w, y = h * (.45 + r() * .4), rr = h * (.15 + r() * .2);
    const gr = g.createRadialGradient(x, y, 0, x, y, rr);
    gr.addColorStop(0, 'rgba(220,210,255,.35)'); gr.addColorStop(1, 'rgba(220,210,255,0)');
    g.fillStyle = gr; g.fillRect(x - rr, y - rr, rr * 2, rr * 2);
  }
  fx.fog = c;
}
// 毎フレームの背景：灯台の光・水面のきらめき・提灯・霧
function fxScene(ctx, dt) {
  const pal = PALETTE[fx.palKey] || PALETTE.normal;
  ctx.drawImage(fx.bg, 0, 0, fx.w, fx.h);
  const [lx, ly] = fx.lamp, a = fx.t * .7;
  ctx.save(); ctx.globalCompositeOperation = 'lighter';
  const beam = ctx.createLinearGradient(lx, ly, lx + Math.cos(a) * fx.w, ly);
  beam.addColorStop(0, 'rgba(255,240,200,.35)'); beam.addColorStop(1, 'rgba(255,240,200,0)');
  ctx.fillStyle = beam;
  const dir = Math.cos(a) >= 0 ? 1 : -1, spread = fx.h * .08 * Math.abs(Math.cos(a));
  ctx.beginPath(); ctx.moveTo(lx, ly); ctx.lineTo(lx + dir * fx.w, ly - spread); ctx.lineTo(lx + dir * fx.w, ly + spread); ctx.fill();
  ctx.fillStyle = 'rgba(255,240,200,.9)'; ctx.beginPath(); ctx.arc(lx, ly, 2.5, 0, 7); ctx.fill();
  // 提灯（ゆらぎ）
  fx.lanterns.forEach(([x, y], i) => {
    const f = .7 + Math.sin(fx.t * 6 + i * 1.7) * .15;
    const gr = ctx.createRadialGradient(x, y, 0, x, y, 12 * f);
    gr.addColorStop(0, pal[3]); gr.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = gr; ctx.globalAlpha = f; ctx.fillRect(x - 14, y - 14, 28, 28);
    ctx.fillRect(x - 2, y + 22 + Math.sin(fx.t * 3 + i) * 2, 4, 1.5);
  });
  ctx.globalAlpha = 1;
  // 水面のきらめき（月の映り込み）
  const [mx] = fx.moon;
  ctx.fillStyle = pal[2];
  for (let i = 0; i < 22; i++) {
    const k = i / 22, y = fx.hz + 3 + k * k * (fx.h - fx.hz);
    const len = 6 + k * 26, x = mx + Math.sin(fx.t * 1.3 + i * 2.1) * (6 + k * 18) - len / 2;
    ctx.globalAlpha = (.5 - k * .35) * (.6 + .4 * Math.sin(fx.t * 4 + i));
    ctx.fillRect(x, y, len, 1.2);
  }
  ctx.restore();
  // 霧：期待度が高いほど濃い
  const fogA = [.12, .25, .45, .55][Math.min(3, fx.tone)] + (fx.mode === 'pre' ? .2 : 0);
  if (fx.fog && C.effect !== 'off') {
    const ox = (fx.t * 12) % fx.w;
    ctx.globalAlpha = fogA; ctx.drawImage(fx.fog, -ox, 0); ctx.drawImage(fx.fog, fx.w - ox, 0);
    ctx.globalAlpha = 1;
  }
}
function fxResize() {
  fx.cv = document.getElementById('fx');
  const r = fx.cv.getBoundingClientRect(), d = Math.min(1.5, window.devicePixelRatio || 1);
  fx.cv.width = r.width * d; fx.cv.height = r.height * d; fx.w = r.width; fx.h = r.height;
  fx.ctx = fx.cv.getContext('2d'); fx.ctx.setTransform(d, 0, 0, d, 0, 0);
  fx.d = d; fx.key = ''; fx.parts = []; fx.warm = false;
  if (fx.w) fxFog();
}
const rnd = ([a, b]) => a + Math.random() * (b - a);
function fxSpawn(st, anywhere) {
  return { x: Math.random() * fx.w, y: anywhere ? Math.random() * fx.h : fx.h + 8, vx: rnd(st.vx), vy: rnd(st.vy), s: rnd(st.size), hue: rnd(st.hue), a: .35 + Math.random() * .65, life: 1 };
}
function fxBurst(hue, n = 70) {
  if (C.effect === 'off' || !fx.w) return;
  for (let i = 0; i < n; i++) {
    const a = Math.random() * Math.PI * 2, v = 90 + Math.random() * 280;
    fx.parts.push({ burst: true, x: fx.w / 2, y: fx.h / 2, vx: Math.cos(a) * v, vy: Math.sin(a) * v - 70, s: 1 + Math.random() * 2.4, hue: hue + Math.random() * 24 - 12, a: 1, life: 1 });
  }
}
function fxTick(dt) {
  const ctx = fx.ctx; if (!ctx || !fx.w) return;
  const st = FX_STYLE[fx.mode] || FX_STYLE.normal;
  const want = C.effect === 'off' ? 0 : Math.round(st.n * (C.effect === 'few' ? .5 : 1));
  fx.t += dt;
  // 背景の描き直しは配色が変わったときだけ
  const pk = fx.mode === 'normal' || fx.mode === 'pre' ? (fx.tone ? 'tone' + fx.tone : 'normal') : fx.mode === 'bonus' && fx.red ? 'bonusR' : fx.mode;
  if (pk !== fx.key) { fx.key = pk; fx.palKey = pk; fxPaintBg(PALETTE[pk] || PALETTE.normal, pk === 'at' || pk === 'zone'); }
  fxScene(ctx, dt);
  if (st.lines && want) { // 集中線
    ctx.save(); ctx.translate(fx.w / 2, fx.h * .52); ctx.strokeStyle = 'rgba(255,225,170,.10)'; ctx.lineWidth = 1.5;
    for (let k = 0; k < 16; k++) {
      const a = Math.random() * Math.PI * 2, r0 = fx.h * (.28 + Math.random() * .2), r1 = r0 + fx.h * (.3 + Math.random() * .6);
      ctx.beginPath(); ctx.moveTo(Math.cos(a) * r0, Math.sin(a) * r0); ctx.lineTo(Math.cos(a) * r1, Math.sin(a) * r1); ctx.stroke();
    }
    ctx.restore();
  }
  let ambient = 0;
  fx.parts = fx.parts.filter(p => {
    const alive = p.life > 0 && p.y > -20 && p.y < fx.h + 40 && p.x > -60 && p.x < fx.w + 60;
    if (alive && !p.burst) ambient++;
    return alive && (p.burst || ambient <= want);
  });
  for (; ambient < want; ambient++) fx.parts.push(fxSpawn(st, !fx.warm));
  fx.warm = true;
  ctx.globalCompositeOperation = 'lighter';
  for (const p of fx.parts) {
    p.x += p.vx * dt; p.y += p.vy * dt;
    if (p.burst) { p.vy += 170 * dt; p.life -= dt * 1.3; } else p.x += Math.sin(p.y * .03 + p.hue) * .25;
    ctx.strokeStyle = ctx.fillStyle = `hsla(${p.hue},100%,${p.burst ? 72 : 60}%,${p.a * Math.min(1, p.life)})`;
    if (st.streak || p.burst) { ctx.lineWidth = p.s; ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(p.x - p.vx * .05, p.y - p.vy * .05); ctx.stroke(); }
    else { ctx.beginPath(); ctx.arc(p.x, p.y, p.s, 0, Math.PI * 2); ctx.fill(); }
  }
  ctx.globalCompositeOperation = 'source-over';
}
// ===== 状態 =====
const DEFAULT_CONFIG = {
  setting: 'R', autoCount: 0, stopOnBonus: false, stopOnAT: false, stopBelow: 0, autoAim: true, manualAim: false,
  autoSpeed: 'normal', reelSpeed: 'normal', wait: false, effect: 'normal', volume: 50, vibe: true,
  hand: 'right', stopMode: 'button', debug: false,
};
function freshState() {
  return {
    credit: 1000, invest: 1000, inTotal: 0, outTotal: 0, games: 0, sinceHit: 0,
    mode: 'normal', high: 0, standby: null, replay: false,
    cz: null, bonus: null, at: null, chain: null,
    realSetting: 1 + Math.floor(Math.random() * 6), revealed: false,
    counts: { cz: 0, czWin: 0, white: 0, red: 0, at: 0, freeze: 0, maxSets: 0, maxGain: 0 },
    hist: [], graph: [0],
    pre: null, battle: null, tone: 0, peeks: [], lastPeek: -999, endPeek: false, zoroDay: '',
  };
}
let S = freshState();
let C = { ...DEFAULT_CONFIG };
const SAVE_KEY = 'yominato_v1';
function load() {
  try {
    // slot.php?reset で保存データを消して開き直せる
    if (new URLSearchParams(location.search).has('reset')) { localStorage.removeItem(SAVE_KEY); history.replaceState(null, '', location.pathname); }
    const raw = localStorage.getItem(SAVE_KEY);
    if (raw) {
      const d = JSON.parse(raw);
      S = { ...freshState(), ...d.S }; C = { ...DEFAULT_CONFIG, ...d.C };
      // 壊れた途中状態は通常時に戻す
      const broken = (S.mode === 'at' && !(S.at && S.chain)) || (S.mode === 'bonus' && !(S.bonus && S.at && S.chain)) || (S.mode === 'cz' && !S.cz);
      if (broken) { S.mode = 'normal'; S.at = S.bonus = S.cz = S.chain = null; }
      if (!Array.isArray(S.graph) || S.graph.some(v => !Number.isFinite(v))) S.graph = [0];
    }
  } catch (e) { /* 保存領域が使えない環境ではそのまま遊ぶ */ }
}
function save() { try { localStorage.setItem(SAVE_KEY, JSON.stringify({ S, C })); } catch (e) {} }
const setting = () => SETTING[C.setting === 'R' ? S.realSetting : +C.setting];

// ===== 抽選ユーティリティ =====
const chance = p => Math.random() < p;
function roll(table) {
  let r = Math.floor(Math.random() * 65536);
  for (const [k, w] of Object.entries(table)) { r -= w; if (r < 0) return k; }
  return 'none';
}
function weighted(ws) {
  let r = Math.random() * ws.reduce((a, b) => a + b, 0);
  for (let i = 0; i < ws.length; i++) { r -= ws[i]; if (r < 0) return i; }
  return ws.length - 1;
}
// ===== 音・振動 =====
let actx = null;
function beep(freq, dur = .08, type = 'square', vol = 1, when = 0) {
  if (!C.volume) return;
  try {
    actx = actx || new (window.AudioContext || window.webkitAudioContext)();
    const t = actx.currentTime + when, o = actx.createOscillator(), g = actx.createGain();
    o.type = type; o.frequency.setValueAtTime(freq, t);
    g.gain.setValueAtTime(.12 * vol * C.volume / 100, t); g.gain.exponentialRampToValueAtTime(.0001, t + dur);
    o.connect(g).connect(actx.destination); o.start(t); o.stop(t + dur + .02);
  } catch (e) {}
}
const melody = (notes, step = .09, type = 'square') => notes.forEach((f, i) => f && beep(f, step * 1.4, type, 1, i * step));
const SND = {
  lever: () => beep(220, .05, 'triangle'),
  stop: () => beep(660, .03, 'square', .6),
  win: () => melody([880, 1175], .06),
  replay: () => beep(1046, .06, 'triangle'),
  rare: () => melody([523, 659, 784, 1046], .06, 'triangle'),
  fanfare: () => melody([523, 659, 784, 1046, 0, 784, 1046, 1318], .1),
  big: () => melody([392, 523, 659, 784, 1046, 1318, 1568], .08, 'sawtooth'),
  lose: () => melody([392, 330, 262], .14, 'triangle'),
};
const vibe = p => { if (C.vibe && navigator.vibrate) try { navigator.vibrate(p); } catch (e) {} };

// ===== DOM =====
const $ = id => document.getElementById(id);
const reelEls = [...document.querySelectorAll('.reel')];
const stripEls = reelEls.map(r => r.querySelector('.strip'));
const stopBtns = [...document.querySelectorAll('.stop')];
const symSvg = s => `<svg><use href="#s-${s}"/></svg>`;
STRIPS.forEach((strip, i) => {
  const cells = strip.map(s => `<div class="cell">${symSvg(s)}</div>`).join('');
  stripEls[i].innerHTML = cells + cells + cells;
});
let cellPx = 64;
function measure() {
  const w = reelEls[0].clientWidth || 100;
  const landscape = matchMedia('(orientation: landscape) and (max-height: 520px)').matches;
  cellPx = Math.round(Math.min(landscape ? 52 : 86, w * .62));
  document.documentElement.style.setProperty('--cell', cellPx + 'px');
  reels.forEach((r, i) => draw(i));
  fxResize();
}

// ===== リール =====
// offset = 上段に来ている図柄の番号（小数）。回転中は減っていく＝図柄が下へ流れる
const reels = [0, 1, 2].map(i => ({ offset: [0, 7, 14][i], spinning: false, stopTo: null, done: true }));
function draw(i) {
  const base = mod(reels[i].offset, N) + N;
  stripEls[i].style.transform = `translateY(${-base * cellPx}px)`;
}
const rowsAt = (i, top) => [0, 1, 2].map(r => STRIPS[i][mod(top + r, N)]);
// 停止済み（停止アニメ中を含む）リールの上段の番号。未停止なら null
const finalTop = j => (!reels[j].done ? null : reels[j].stopTo !== null ? reels[j].stopTo : Math.round(reels[j].offset));
const grid = () => [0, 1, 2].map(i => rowsAt(i, Math.round(reels[i].offset)));const spinSpeed = () => (C.reelSpeed === 'fast' ? 38 : 26); // コマ/秒
let lastT = 0;
function frame(t) {
  const dt = Math.min(.05, (t - lastT) / 1000 || 0); lastT = t;
  if (!document.hidden) fxTick(dt);
  reels.forEach((r, i) => {
    reelEls[i].classList.toggle('spin', r.spinning && r.stopTo === null);
    if (!r.spinning) return;
    r.offset -= spinSpeed() * dt * (game && game.freeze && !game.freezeDone ? .15 : 1);
    if (r.stopTo !== null && r.offset <= r.stopTo) {
      r.offset = r.stopTo; r.spinning = false; r.stopTo = null;
      reelEls[i].classList.remove('bounce'); void reelEls[i].offsetWidth; reelEls[i].classList.add('bounce');
      onReelStopped(i);
    }
    draw(i);
  });
  requestAnimationFrame(frame);
}

// ===== 1ゲーム =====
let game = null;   // 現在のゲーム
let busy = false;  // 液晶演出などで操作待ち
let lastStart = 0;
let autoLeft = 0, autoOn = false, autoTimer = null;

function lever() {
  if (busy || (game && !game.settled)) return;
  if (C.wait && Date.now() - lastStart < 4100) {
    setTimeout(lever, 4100 - (Date.now() - lastStart));
    return;
  }
  if (!S.replay) {
    if (S.credit < 3) { toast('メダルが足りません'); stopAuto(); return; }
    S.credit -= 3; S.inTotal += 3;
  }
  S.replay = false;
  lastStart = Date.now();

  const g = { flag: 'none', firstStop: null, order: [], targets: null, expected: null, pay: 0, settled: false, freeze: false, freezeDone: true };
  const mode = S.mode;
  S.endPeek = false; peekSeq = [];
  if (S.standby) {
    // 無告知中はリーチ目が出ることがある
    g.flag = S.standby.hidden > 0 ? (chance(.7) ? 'reach' : 'none') : S.standby.type;
  } else {
    g.flag = roll(mode === 'at' ? TABLES.at : mode === 'bonus' ? TABLES.bonus : TABLES.normal);
    // フリーズ（プレミア）
    if ((mode === 'normal' || mode === 'cz') && chance(1 / 16384)) {
      g.freeze = true; S.standby = { type: 'red', premium: true }; g.flag = 'red';
    } else if (mode === 'at' && chance(1 / 8192)) {
      g.freeze = true; g.atFreeze = true;
    }
  }
  const mt = makeTargets(g.flag);
  g.targets = mt.t; g.expected = mt.win; g.tri = mt.tri || null;
  if (g.flag === 'pbell') g.correct = Math.floor(Math.random() * 3);
  g.navi = g.flag === 'pbell' && (mode === 'at' || mode === 'bonus');
  game = g;

  SND.lever();
  $('reels').classList.remove('win');
  $('mPay').textContent = '0';
  updateMeters();
  preview(g);

  const begin = () => {
    reels.forEach(r => { r.spinning = true; r.stopTo = null; r.done = false; });
    stopBtns.forEach(b => b.classList.add('live'));
    if (g.navi) showNavi(g.correct);
    if (autoOn) scheduleAutoStops();
  };
  if (g.freeze) {
    g.freezeDone = false; busy = true;
    $('lcd').classList.add('black'); $('reels').classList.add('dark'); setLcd('', '', ''); vibe([60, 80, 60, 80, 300]);
    SND.big();
    reels.forEach(r => { r.spinning = true; r.stopTo = null; r.done = false; });
    setTimeout(() => {
      $('lcd').classList.remove('black'); $('reels').classList.remove('dark'); flash(); g.freezeDone = true; busy = false;
      stamp('極', '#fff'); fxBurst(45, 140);
      if (g.atFreeze) setLcd('極・夜明け', '+300G & 継続率95%', 'rainbow');
      else setLcd('赤7を狙え', '極・灯火ボーナス', 'rainbow');
      stopBtns.forEach(b => b.classList.add('live'));
      if (g.navi) showNavi(g.correct);
      if (autoOn) scheduleAutoStops();
    }, fastMode() ? 500 : 2200);
  } else begin();
}

// 予告演出：ステップアップ（段階と色で期待度を示す）・リール消灯
function preview(g) {
  const mode = S.mode;
  if (S.standby && !S.standby.hidden && !g.freeze) {
    const red = S.standby.type === 'red';
    setLcd(red ? '赤7を狙え' : 'BARを狙え', 'ボーナス確定', (red ? 'red' : 'gold') + ' blink');
    return;
  }
  if (g.navi) setLcd(['左', '中', '右'][g.correct] + 'から押せ', mode === 'bonus' ? '灯火ボーナス' : '夜明けRUSH', 'gold', false);
  const freq = { many: 1, normal: .65, few: .3, off: 0 }[C.effect];
  if (!freq || fastMode()) return;
  const strong = ['scherry', 'schance'].includes(g.flag);
  let lv = 0, color = '#4da3ff';
  if (strong) { if (chance(freq)) { lv = 3 + weighted([30, 40, 30]); color = chance(.6) ? '#ff3044' : '#49d17d'; } }
  else if (RARE.includes(g.flag)) { if (chance(freq)) { lv = 2 + weighted([60, 40]); color = chance(.75) ? '#4da3ff' : '#49d17d'; } }
  else if (chance((S.pre || S.battle ? .3 : .05) * freq)) lv = 1 + weighted([60, 30, 10]); // ガセ
  // 暗転予告：強レア役・リーチ目・本前兆で出やすい
  if ((strong || g.flag === 'reach' || (S.pre && S.pre.real)) && chance(.3 * freq)) blackout();
  if (lv) stepUp(lv, color);
  if (strong && chance(.5 * freq)) { $('reels').classList.add('dark'); vibe(40); }
  if (mode === 'cz' && RARE.includes(g.flag) && chance(freq)) cutin('好機', '#a8142a');
}
function blackout() {
  const l = $('lcd');
  l.classList.add('black'); beep(90, .3, 'sawtooth');
  setTimeout(() => l.classList.remove('black'), 650);
}

function showNavi(first) {
  stopBtns.forEach((b, i) => {
    b.classList.toggle('navi-on', i === first);
    b.classList.toggle('navi-first', i === first);
  });
}
function clearNavi() { stopBtns.forEach(b => b.classList.remove('navi-on', 'navi-first')); }

function stopReel(i, aim) {
  const g = game, r = reels[i];
  if (!g || g.settled || !r.spinning || r.stopTo !== null || busy) return;
  if (g.freeze && !g.freezeDone) return;
  if (g.firstStop === null) {
    g.firstStop = i;
    $('reels').classList.remove('dark');
    if (g.flag === 'pbell' && i !== g.correct) { g.targets = [null, null, null]; g.expected = null; g.tri = null; }
  }
  g.order.push(i);
  stopBtns[i].classList.remove('live');
  SND.stop();

  const pressTop = Math.floor(r.offset);
  const tg = g.targets[i];
  const hit = k => STRIPS[i][mod(pressTop - k + tg.row, N)] === tg.sym;
  let k = null;
  // 目押し補助ありなら1周まで、なしなら4コマまで滑らせて狙い図柄を引き込む
  const range = aim ? N : 5;
  if (g.tri) {
    // 既に止まったリールと矛盾しないラインを候補に、どれかで揃える
    const lines = LINES.filter(l => [0, 1, 2].every(j => j === i || finalTop(j) === null || rowsAt(j, finalTop(j))[l[j]] === g.tri));
    for (let j = 0; j < range && k === null; j++)
      if (lines.some(l => STRIPS[i][mod(pressTop - j + l[i], N)] === g.tri) && fits(i, pressTop - j, g.expected)) k = j;
  } else if (tg) for (let j = 0; j < range; j++) if (hit(j) && fits(i, pressTop - j, g.expected)) { k = j; break; }
  if (k === null && (tg || g.tri)) {
    g.tri = null;
    // 取りこぼし：以降は揃えない
    g.targets = g.targets.map((t, j) => (reels[j].done ? t : null));
    const left = finalTop(0);
    g.expected = g.flag === 'scherry' && left !== null && rowsAt(0, left).includes('C') ? 'cherry' : null;
    g.missed = true;
  }
  if (k === null) for (let j = 0; j < 5; j++) if (fits(i, pressTop - j, g.expected)) { k = j; break; }
  if (k === null) k = 0;
  r.done = true;
  if (fastMode() && autoOn) { r.offset = pressTop - k; r.spinning = false; draw(i); onReelStopped(i); }
  else r.stopTo = pressTop - k;
}
// リール i を上段 top で止めたとき、成立役どおりの出目になるか
function fits(i, top, expected) {
  const tops = [0, 1, 2].map(j => (j === i ? top : finalTop(j)));
  if (tops.includes(null)) {
    // 左リールのチェリーはチェリー成立時だけ見せる
    return !(i === 0 && rowsAt(0, top).includes('C') && !['cherry', 'scherry', 'mcherry'].includes(expected));
  }
  const wins = judgeAll(tops.map((t, j) => rowsAt(j, t)));
  return expected ? wins.length === 1 && wins[0] === expected : wins.length === 0;
}
function onReelStopped(i) {
  if (reels.every(r => r.done && !r.spinning)) settle();
}

function settle() {
  const g = game; if (!g || g.settled) return;
  g.settled = true; clearNavi();
  stopBtns.forEach(b => b.classList.remove('live'));
  const wins = judgeAll(grid());
  g.wins = wins;
  g.pay = wins.reduce((s, w) => s + PAY[w], 0);
  const line = wins.includes('red') ? 'red' : wins.includes('white') ? 'white' : null;
  if (wins.includes('replay')) { S.replay = true; SND.replay(); }
  if (g.pay) { S.credit += g.pay; S.outTotal += g.pay; SND.win(); }
  if (g.pay || line) $('reels').classList.add('win');  $('reels').classList.remove('dark');
  countPay(g.pay);
  if (RARE.includes(g.flag) && C.effect !== 'off') SND.rare();

  const wasMode = S.mode;
  S.games++;
  if (S.mode === 'normal' || S.mode === 'cz') S.sinceHit++;
  if (line && S.standby && !S.standby.hidden) startBonus(line);
  else if (S.mode === 'normal') normalStep(g);
  else if (S.mode === 'cz') czStep(g);
  else if (S.mode === 'bonus') bonusStep(g);
  else if (S.mode === 'at') atStep(g);

  pushGraph();
  updateAll();
  save();
  afterGame(wasMode);
}

// ===== 設定示唆（RUSH終了画面・裏ボタン共通） =====
const SUGGEST = [
  { key: 'white',   text: '白い灯が揺れた',         color: '#e8ecff', note: 'デフォルト',     w: [70, 62, 66, 56, 58, 44] },
  { key: 'blue',    text: '青い灯が二つ灯った',     color: '#4da3ff', note: '奇数設定を示唆', w: [20, 8, 20, 8, 18, 8] },
  { key: 'red',     text: '赤い灯が並んだ',         color: '#ff4d5e', note: '偶数設定を示唆', w: [10, 20, 8, 20, 8, 20] },
  { key: 'purple',  text: '紫の灯が静かに燃える',   color: '#c77dff', note: '設定2以上確定',  w: [0, 10, 6, 6, 6, 6] },
  { key: 'gold',    text: '金色の灯が夜を照らす',   color: '#f3c969', note: '設定4以上確定',  w: [0, 0, 0, 10, 10, 14] },
  { key: 'rainbow', text: '七色の灯 ― 夜明けは約束された', color: null, note: '設定6確定', w: [0, 0, 0, 0, 0, 6] },
];
const stNum = () => (C.setting === 'R' ? S.realSetting : +C.setting);
function suggest(where) {
  const s = SUGGEST[weighted(SUGGEST.map(x => x.w[stNum() - 1]))];
  S.peeks.unshift({ where, key: s.key, g: S.games });
  S.peeks = S.peeks.slice(0, 30);
  setLcd(s.text, `― ${where} ―`, s.color ? '' : 'rainbow');
  if (s.color) $('big').style.color = s.color;
  const l = $('lamp');
  l.classList.toggle('rainbow', !s.color);
  l.style.setProperty('--lamp', s.color || '#fff');
  melody([784, 0, 1046], .08, 'triangle'); vibe(30);
  save();
}

// ===== 通常時 =====
const randInt = (a, b) => a + Math.floor(Math.random() * (b - a + 1));
function normalStep(g) {
  const sb = S.standby;
  if (sb) {
    if (sb.hidden > 0) { // 無告知：数G後に告知。リーチ目に気づけば先に分かる
      sb.hidden--;
      if (sb.hidden <= 0) announce();
      else if (g.flag === 'reach') setLcd('', '……');
      else idleLcd();
      return;
    }
    const red = sb.type === 'red';
    setLcd(red ? '赤7を狙え' : 'BARを狙え', '次ゲームも揃えられます', (red ? 'red' : 'gold') + ' blink');
    return;
  }
  if (S.high > 0 && --S.high === 0 && S.tone === 1) S.tone = 0;
  if (g.flag === 'mcherry') { // 中段チェリー：赤ボーナス＋継続率アップ確定
    S.pre = null; S.battle = null;
    stamp('中段', '#ff3044');
    setStandby('red', { boost: true });
    setLcd('中段チェリー', '赤7を狙え', 'rainbow');
    return;
  }
  if (S.battle) return battleStep(g);

  const st = setting();
  const DIRECT = { wcherry: .005, melon: .005, scherry: .10, chance: .02, schance: .25 };
  const CZ = { wcherry: .04, melon: .03, scherry: .40, chance: .08, schance: .75 };
  let outcome = null;
  if (chance(DIRECT[g.flag] || 0) || chance(st.pure)) outcome = 'bonus';
  else if (chance((CZ[g.flag] || 0) * (S.high > 0 ? 2 : 1))) outcome = 'cz';
  if (!outcome && S.sinceHit >= CEILING) { S.pre = null; return hit('ceiling'); }

  if (S.pre) { // 前兆中の当選は前兆の終わりに告知
    if (outcome) S.pre.real = S.pre.real === 'bonus' ? 'bonus' : outcome;
    return preStep(g);
  }
  if (outcome) {
    if (chance(.4)) return startPre(outcome, randInt(2, 5));
    return hit(outcome);
  }
  // 100Gごとのゾーンとゾロ目ゲームでは前兆に入りやすい
  const zoro = [333, 555, 777].includes(S.sinceHit);
  if (zoro || S.sinceHit % 100 === 0) {
    if (chance((zoro ? .3 : .045) + .012 * (stNum() - 1))) return startPre('cz', randInt(6, 12), zoro);
    if (chance(.35)) return startPre(null, randInt(6, 12), zoro);
  }
  if (['wcherry', 'melon', 'chance', 'scherry'].includes(g.flag) && chance(.25)) { S.high = 32; if (chance(.6)) S.tone = 1; }
  rareLcd(g);
}
function rareLcd(g) {
  if (g.flag === 'scherry') setLcd('強チェリー', '……', 'red');
  else if (g.flag === 'schance') setLcd('強チャンス目', '……', 'red');
  else if (RARE.includes(g.flag) && C.effect !== 'off') setLcd(WIN_NAME[g.expected || 'cherry'] || '', S.tone ? '― 夜霧が濃くなってきた ―' : '', 'blue');
  else idleLcd();
}
// 当選の告知（前兆・連続演出の結末もここ）
function hit(outcome) {
  S.pre = null; S.battle = null; S.tone = 0;
  const st = setting();
  if (outcome === 'ceiling') { setStandby('red'); setLcd('天井到達', '赤7を狙え', 'red blink'); return; }
  if (outcome === 'cz') return startCz();
  const type = chance(st.red) ? 'red' : 'white';
  if (chance(.25)) setStandby(type, { hidden: randInt(1, 3) }); // 無告知
  else setStandby(type);
}
function startPre(real, len, zoro) {
  S.pre = { left: len, real };
  S.tone = 2;
  if (zoro) { flash(); setLcd(`${S.sinceHit}G`, '― ゾロ目ゲーム ―', 'gold'); }
  else setLcd('', '― 霧が立ち込める ―');
}
function preStep(g) {
  const pre = S.pre;
  pre.left--;
  if (pre.real && S.tone < 3 && chance(.25)) S.tone = 3;
  if (pre.left > 0) { rareLcd(g); if (!RARE.includes(g.flag)) setLcd('', S.tone === 3 ? '― 霧が赤く染まる ―' : '― 霧が立ち込める ―'); return; }
  S.pre = null;
  if (chance(pre.real ? .7 : .35)) return startBattle(pre.real);
  if (pre.real) return hit(pre.real);
  S.tone = 0; setLcd('', '― 霧が晴れた ―');
}
// 連続演出「決闘」：3Gかけて勝てば当たり
function startBattle(real) {
  S.battle = { step: 0, real };
  S.tone = 3;
  cutin('決闘', '#5a0a1a'); shake();
  setLcd('影喰、出現', '― 決闘 ―', 'red');
}
function battleStep(g) {
  const b = S.battle;
  b.step++;
  if (!b.real && RARE.includes(g.flag) && chance(.3)) b.real = 'cz'; // レア役で逆転
  if (b.step === 1) { setLcd('対峙', '― 決闘 ―', 'red'); return; }
  if (b.step === 2) { shake(); fxBurst(355, 60); setLcd('斬り結ぶ', '― 決闘 ―', 'gold'); return; }
  const real = b.real;
  if (real) { hit(real); stamp('勝', '#f3c969'); setLcd('勝利', real === 'cz' ? '迎撃チャンスへ' : 'ボーナス確定', 'gold'); }
  else { S.battle = null; S.tone = 0; SND.lose(); setLcd('敗北', '影喰は闇へ退いた', 'blue'); }
}
function setStandby(type, opt = {}) {
  S.standby = { type, premium: false, hidden: opt.hidden || 0, boost: !!opt.boost };
  if (S.standby.hidden) return; // 無告知はここでは何も起きない
  flash(); vibe([80, 40, 80]); SND.fanfare();
  stamp('確', type === 'red' ? '#ff3044' : '#f3c969'); fxBurst(type === 'red' ? 355 : 45);
  setLcd(type === 'red' ? '赤7を狙え' : 'BARを狙え', 'ボーナス確定', (type === 'red' ? 'red' : 'gold') + ' blink');
}
function announce() {
  const type = S.standby.type;
  setStandby(type, { boost: S.standby.boost });
  setLcd(type === 'red' ? '赤7を狙え' : 'BARを狙え', '後告知', (type === 'red' ? 'red' : 'gold') + ' blink');
}
// ===== CZ「迎撃チャンス」 =====
function startCz() {
  S.mode = 'cz';
  S.cz = { left: 10, success: chance(setting().cz), hp: 100 };
  S.counts.cz++;
  flash(); vibe(60); SND.fanfare(); cutin('迎撃', '#a8142a');
  setLcd('迎撃チャンス', '10G以内に影喰を討て', 'red');
}
function czStep(g) {
  const cz = S.cz;
  cz.left--;
  const BOOST = { wcherry: .2, melon: .2, chance: .3, scherry: 1, schance: 1 };
  let instant = false;
  if (!cz.success && chance(BOOST[g.flag] || 0)) { cz.success = true; instant = true; }
  if (RARE.includes(g.flag) && cz.success) instant = true;
  // 体力ゲージは見た目。成功なら最後に0、失敗なら少し残る
  const goal = cz.success ? 0 : 12 + Math.random() * 25;
  cz.hp = Math.max(goal, cz.hp - (100 - goal) / 10 * (0.6 + Math.random() * 0.8));
  if (instant || (cz.left <= 0 && cz.success)) {
    cz.hp = 0; S.counts.czWin++;
    S.mode = 'normal'; S.cz = null;
    const type = chance(setting().red) ? 'red' : 'white';
    setStandby(type);
    stamp('破', '#f3c969');
    setLcd('撃破', type === 'red' ? '赤7を狙え' : 'BARを狙え', 'gold');
    return;
  }
  if (cz.left <= 0) {
    S.mode = 'normal'; S.cz = null; SND.lose();
    setLcd('撃退失敗', '影喰は霧の中へ消えた', 'blue');
    return;
  }
  if (RARE.includes(g.flag) || chance(.5)) { shake(); fxBurst(355, RARE.includes(g.flag) ? 90 : 30); }
  setLcd(RARE.includes(g.flag) ? '斬撃' : '迎撃中', `残り ${cz.left}G`, RARE.includes(g.flag) ? 'gold' : 'red', RARE.includes(g.flag));
}

// ===== ボーナス =====
function startBonus(type) {
  const prem = S.standby && S.standby.premium;
  const boost = S.standby && S.standby.boost;
  S.standby = null; S.cz = null; S.pre = null; S.battle = null; S.tone = 0;
  const fromAt = S.mode === 'at';
  if (!fromAt) {
    let tier = weighted(setting().tw);
    if (type === 'red') tier = Math.min(3, tier + 1);
    if (boost) tier = Math.min(3, tier + 1);
    if (prem) tier = 4;
    S.chain = { start: S.outTotal - S.inTotal, g: S.sinceHit, type, sets: 0, bonuses: 0, prem };
    S.at = { left: 0, tier, zone: 0 };
    S.sinceHit = 0; S.high = 0;
  } else if (type === 'red' && chance(.5)) S.at.tier = Math.min(4, S.at.tier + 1);
  if (prem) { S.at.tier = 4; S.counts.freeze++; }
  S.chain.bonuses++;
  S.counts[type]++;
  S.mode = 'bonus';
  S.bonus = { type, left: type === 'red' ? 30 : 20, fromAt };
  flash(); vibe([100, 50, 200]); SND.big();
  restart($('reels'), 'rflash'); stamp('灯', type === 'red' ? '#ff3044' : '#f3c969'); fxBurst(type === 'red' ? 355 : 45, 120);
  setLcd(type === 'red' ? '赤・灯火ボーナス' : '灯火ボーナス', `${S.bonus.left}G　ナビに従ってベルを揃えよ`, type === 'red' ? 'red' : 'gold');
  stopIfNeeded('bonus');
}
function bonusStep(g) {
  const b = S.bonus;
  b.left--;
  const UP = { wcherry: .05, melon: .05, chance: .15, scherry: .5, schance: 1 };
  if (S.at.tier < 3 && chance(UP[g.flag] || 0)) {
    S.at.tier++; flash(); SND.rare(); cutin('継続率UP', '#b07a10');
    setLcd('継続率UP！', `継続率 ${TIERS[S.at.tier]}%`, 'rainbow');
  } else setLcd(b.type === 'red' ? '赤・灯火ボーナス' : '灯火ボーナス', `残り ${b.left}G`, b.type === 'red' ? 'red' : 'gold', false);
  if (b.left > 0) return;
  S.mode = 'at'; S.bonus = null;
  if (!b.fromAt) {
    S.at.left = AT_SET; S.chain.sets = 1; S.counts.at++; newSet();
    flash(); SND.fanfare(); vibe([60, 40, 60]); cutin('夜明けRUSH', '#c2501a'); fxBurst(30, 140);
    setLcd('夜明けRUSH 突入', `継続率 ${TIERS[S.at.tier]}%`, 'rainbow');
    stopIfNeeded('at');
  } else {
    if (S.at.left < 1) S.at.left = 1;
    setLcd('RUSH 復帰', `残り ${S.at.left}G`, 'gold');
  }
}

// ===== AT「夜明けRUSH」 =====
function atStep(g) {
  const at = S.at;
  if (g.atFreeze) { at.left += 300; at.tier = 4; S.counts.freeze++; }
  if (at.zone > 0) { // 特化ゾーン「焔ノ刻」
    at.zone--;
    const add = RARE.includes(g.flag) ? [20, 30, 50][weighted([60, 30, 10])] : [5, 10][weighted([75, 25])];
    at.left += add; flash(); SND.win(); shake(); fxBurst(40, add >= 20 ? 80 : 30);
    setLcd(`+${add}G`, at.zone > 0 ? `焔ノ刻 残り${at.zone}G` : '焔ノ刻 終了', 'gold');
    return;
  }
  at.left--;
  let add = 0, msg = null;
  if (g.flag === 'wcherry' && chance(.25)) add = 5;
  else if (g.flag === 'melon' && chance(.3)) add = chance(.3) ? 10 : 5;
  else if (g.flag === 'chance') { add = chance(.5) ? 10 : 0; if (at.tier < 3 && chance(.05)) { at.tier++; msg = `継続率 ${TIERS[at.tier]}%へUP！`; } }
  else if (g.flag === 'scherry') {
    if (chance(.2)) { setStandby(chance(.4) ? 'red' : 'white'); return; }
    at.zone = 3; flash(); SND.big(); vibe([50, 30, 50]); stamp('焔', '#ff8a1e'); fxBurst(25, 140);
    setLcd('焔ノ刻', '3Gの間、上乗せし放題！', 'rainbow'); return;
  } else if (g.flag === 'schance') {
    if (chance(.3)) { setStandby(chance(.5) ? 'red' : 'white'); return; }
    add = 30;
  }
  if (add) { at.left += add; flash(); SND.win(); if (add >= 20) cutin(`+${add}G`, '#b07a10'); fxBurst(40, add * 3); setLcd(`+${add}G`, msg || `残り ${at.left}G`, 'gold'); }
  else if (msg) setLcd('継続率UP！', msg, 'rainbow');
  else if (!g.navi) setLcd('夜明けRUSH', `残り ${at.left}G　継続率 ${TIERS[at.tier]}%`, 'gold', false);
  if (at.left <= 0) judgeContinue();
}
function newSet() {
  const at = S.at;
  at.contPre = chance(TIERS[at.tier] / 100); at.preTier = at.tier; at.peeked = false;
}
// セット終了時の継続判定「夜番の刻」
function judgeContinue() {
  const at = S.at;
  // セット開始時の抽選＋その後に上がった継続率ぶんの再抽選（合計で現在の継続率になる）
  const p0 = TIERS[at.preTier], p1 = TIERS[at.tier];
  const cont = at.contPre || (p1 > p0 && chance((p1 - p0) / (100 - p0)));
  busy = true; judging = true; updateLcdFrame();
  setLcd('夜番の刻', `継続率 ${TIERS[at.tier]}%`, 'red');
  if (!fastMode()) stepUp(5, cont ? '#f3c969' : '#4da3ff');
  setTimeout(() => {
    busy = false; judging = false;
    if (cont) {
      at.left = AT_SET; S.chain.sets++; newSet();
      flash(); SND.fanfare(); vibe([80, 40, 80]); stamp('継続', '#f3c969'); fxBurst(45, 140);
      setLcd('継続', `${S.chain.sets}セット目　継続率 ${TIERS[at.tier]}%`, 'rainbow');
    } else endChain();
    updateAll(); save(); afterGame('at');
  }, fastMode() ? 250 : 1600);
}
function endChain() {
  const ch = S.chain;
  const gain = S.outTotal - S.inTotal - ch.start;
  S.hist.unshift({ g: ch.g, type: ch.type, sets: ch.sets, bonuses: ch.bonuses, gain, prem: ch.prem, at: S.games });
  S.hist = S.hist.slice(0, 50);
  S.counts.maxSets = Math.max(S.counts.maxSets, ch.sets);
  S.counts.maxGain = Math.max(S.counts.maxGain, gain);
  S.mode = 'normal'; S.at = null; S.chain = null;
  SND.lose(); stamp('終', '#8b93b8');
  setLcd('RUSH 終了', `${ch.sets}セット　獲得 ${gain > 0 ? '+' : ''}${gain}枚`, 'gold');
  // 終了画面：少し待って設定示唆。次のレバーまでは裏ボタンも受け付ける
  S.endPeek = true;
  setTimeout(() => { if (S.endPeek && !fastMode()) suggest('RUSH終了画面'); }, 1500);
}

// ===== 液晶 =====
function setLcd(big, sub, cls = '', pop = true) {
  const b = $('big');
  b.textContent = big; b.className = 'big ' + cls; b.style.color = '';
  if (pop && big) { void b.offsetWidth; b.classList.add('pop'); }
  $('sub').textContent = sub;
}
function idleLcd() {
  setLcd('', S.high > 0 ? '― 夜霧が濃い ―' : '');
}
const restart = (el, cls) => { el.classList.remove(cls); void el.offsetWidth; el.classList.add(cls); };
const showy = () => C.effect !== 'off' && !fastMode();
function flash() { if (C.effect !== 'off') restart($('flash'), 'go'); }
function shake() { if (!showy()) return; restart($('lcd'), 'shake'); restart($('cabinet'), 'shake'); }
function cutin(text, color = '#a8142a') {
  if (!showy()) return;
  const el = $('cutin'); el.style.setProperty('--c', color); $('cutinText').textContent = text; restart(el, 'go');
}
let stampTimer;
function stamp(text, color = '#fff') {
  if (!showy()) return;
  const el = $('stamp'), b = $('stampText');
  b.textContent = text; b.style.color = color; b.style.fontSize = text.length > 1 ? 'clamp(44px, 17vw, 96px)' : '';
  restart(el, 'go'); shake();
  clearTimeout(stampTimer); stampTimer = setTimeout(() => el.classList.remove('go'), 1300);
}
let stepTimers = [];
function stepUp(n, color) {
  const el = $('steps'), lamps = [...el.children];
  stepTimers.forEach(clearTimeout); stepTimers = [];
  lamps.forEach(l => { l.classList.remove('lit'); l.style.removeProperty('--c'); });
  el.classList.add('show');
  for (let i = 0; i < n; i++) stepTimers.push(setTimeout(() => {
    const c = i === n - 1 ? color : '#4da3ff';
    lamps[i].style.setProperty('--c', c); lamps[i].classList.add('lit');
    beep(500 + i * 140, .07, 'triangle');
    if (i === n - 1 && n >= 4) { flash(); vibe(30); }
  }, 110 * i));
  stepTimers.push(setTimeout(() => el.classList.remove('show'), 110 * n + 1300));
}
function setLamp() {
  const l = $('lamp');
  const color = S.standby ? null : { normal: S.high > 0 ? '#5a3a8a' : '#2a3360', cz: '#ff2a3a', bonus: S.bonus && S.bonus.type === 'red' ? '#ff2a3a' : '#f3c969', at: '#ff8a1e' }[S.mode];
  l.classList.toggle('rainbow', !!S.standby || (S.mode === 'at' && S.at.zone > 0));
  l.classList.toggle('blink', S.mode === 'cz');
  l.style.setProperty('--lamp', color || '#fff');
}
let judging = false;
function updateLcdFrame() {
  const lcd = $('lcd');
  const m = S.mode === 'at' && S.at.zone > 0 ? 'zone' : S.mode;
  ['m-normal', 'm-cz', 'm-bonus', 'm-at', 'm-zone'].forEach(c => lcd.classList.toggle(c, c === 'm-' + m));
  lcd.classList.toggle('red', !!(S.bonus && S.bonus.type === 'red'));
  lcd.classList.toggle('cinema', S.mode === 'bonus' || judging || !!S.battle);
  const tone = S.mode === 'normal' && !S.standby ? S.tone : 0;
  [1, 2, 3].forEach(n => lcd.classList.toggle('tone' + n, tone === n));
  fx.mode = S.mode === 'normal' && S.battle ? 'cz' : S.mode === 'normal' && S.pre ? 'pre' : m;
  fx.tone = tone; fx.red = !!(S.bonus && S.bonus.type === 'red');
  $('reels').classList.toggle('warm', S.mode === 'bonus' || S.mode === 'at');
  $('reels').classList.toggle('redlight', S.mode === 'cz' || !!S.battle || fx.red);
  const hl = $('hudL'), hr = $('hudR');
  const counter = (label, n) => `<div class="counter"><small>${label}</small><b>${n}</b></div>`;
  if (S.mode === 'normal') { hl.innerHTML = `<b>${S.sinceHit}</b> G　${S.battle ? '決闘' : S.pre ? '霧の港' : S.tone ? '宵闇の港' : '夜の港'}`; hr.innerHTML = `<span class="pill">天井まで <b>${Math.max(0, CEILING - S.sinceHit)}</b></span>`; }
  if (S.mode === 'cz') { hl.innerHTML = '迎撃チャンス'; hr.innerHTML = counter('REMAIN', S.cz.left); }
  if (S.mode === 'bonus') { hl.innerHTML = S.bonus.type === 'red' ? '赤・灯火ボーナス' : '灯火ボーナス'; hr.innerHTML = counter('REMAIN', S.bonus.left); }
  if (S.mode === 'at') { hl.innerHTML = `夜明けRUSH <b>${S.chain.sets}</b>set ／ <b>+${S.outTotal - S.inTotal - S.chain.start}</b>枚`; hr.innerHTML = counter(S.at.zone > 0 ? '焔ノ刻' : 'REMAIN', S.at.left); }
  const showRate = S.mode === 'at' || S.mode === 'bonus';
  $('rate').hidden = !showRate;
  if (showRate) $('rateVal').textContent = TIERS[S.at.tier] + '%';
  $('gauge').hidden = S.mode !== 'cz';
  if (S.cz) { $('gaugeBar').style.width = S.cz.hp + '%'; $('gaugeNum').textContent = 'HP ' + Math.ceil(S.cz.hp); }
  setLamp();
  const dbg = $('debug');
  dbg.hidden = !C.debug;
  if (C.debug) dbg.textContent = `設定${C.setting === 'R' ? S.realSetting : C.setting} mode=${S.mode} 高確=${S.high}\nflag=${game ? game.flag : '-'} standby=${S.standby ? S.standby.type : '-'}${S.cz ? ' cz=' + (S.cz.success ? '成功' : '失敗') : ''}`;
}
// 払い出し枚数をカウントアップ表示
let payTimer;
function countPay(n) {
  const el = $('mPay');
  clearInterval(payTimer);
  if (!n || fastMode()) { el.textContent = n; return; }
  let k = 0; el.classList.add('count');
  payTimer = setInterval(() => {
    el.textContent = ++k; beep(1200 + k * 20, .02, 'square', .4);
    if (k >= n) { clearInterval(payTimer); el.classList.remove('count'); }
  }, 45);
}
function updateMeters() {
  $('mCredit').textContent = S.credit;
  $('mGame').textContent = S.mode === 'normal' || S.mode === 'cz' ? S.sinceHit : (S.bonus ? S.bonus.left : S.at.left);
  $('mPayBox').classList.toggle('replay', S.replay);
  if (S.replay) $('mPay').textContent = 'RE';
}
function updateAll() { updateMeters(); updateLcdFrame(); }

function pushGraph() {
  S.graph.push(S.outTotal - S.inTotal);
  if (S.graph.length > 4000) S.graph = S.graph.filter((_, i) => i % 2 === 0);
}

// ===== オート =====
const fastMode = () => C.autoSpeed === 'max' && autoOn;
function toggleAuto() {
  if (autoOn) { stopAuto(); return; }
  autoOn = true; autoLeft = C.autoCount || Infinity;
  $('btnAuto').classList.add('on'); updateAutoLabel();
  if (!game || game.settled) lever(); else scheduleAutoStops();
}
function stopAuto() {
  autoOn = false; clearTimeout(autoTimer);
  $('btnAuto').classList.remove('on'); $('btnAuto').textContent = 'AUTO';
}
function updateAutoLabel() { if (autoOn) $('btnAuto').textContent = autoLeft === Infinity ? 'AUTO ∞' : `AUTO ${autoLeft}`; }
function scheduleAutoStops() {
  const g = game;
  let order = [0, 1, 2];
  if (g.navi) order = [g.correct, ...[0, 1, 2].filter(i => i !== g.correct)];
  const gap = { normal: 260, fast: 90, max: 0 }[C.autoSpeed];
  const first = { normal: 420, fast: 150, max: 0 }[C.autoSpeed];
  order.forEach((i, n) => setTimeout(() => { if (autoOn && game === g) stopReel(i, C.autoAim); }, first + gap * n));
}
let stopReason = null;
function stopIfNeeded(kind) {
  if (!autoOn) return;
  if ((kind === 'bonus' && C.stopOnBonus) || (kind === 'at' && C.stopOnAT)) stopReason = kind === 'bonus' ? 'ボーナスでオート停止' : 'RUSH突入でオート停止';
}
function afterGame() {
  if (!autoOn || busy) return;
  if (stopReason) { toast(stopReason); stopReason = null; stopAuto(); return; }
  if (autoLeft !== Infinity && --autoLeft <= 0) { toast('オート終了'); stopAuto(); return; }
  if (C.stopBelow && S.credit < C.stopBelow) { toast(`クレジットが${C.stopBelow}枚を下回ったので停止`); stopAuto(); return; }
  if (!S.replay && S.credit < 3) { toast('メダルが足りません'); stopAuto(); return; }
  updateAutoLabel();
  const delay = { normal: 450, fast: 140, max: 10 }[C.autoSpeed] + (game && (game.pay >= 9 || S.standby) && C.autoSpeed === 'normal' ? 250 : 0);
  autoTimer = setTimeout(lever, delay);
}

// ===== トースト =====
let toastTimer;
function toast(t) { const el = $('toast'); el.textContent = t; el.hidden = false; clearTimeout(toastTimer); toastTimer = setTimeout(() => (el.hidden = true), 2200); }

// ===== 設定シート =====
const OPTIONS = [
  ['オート', [
    ['autoCount', 'オート回数', 'seg', [[0, '∞'], [50, '50'], [100, '100'], [300, '300'], [1000, '1000']]],
    ['autoSpeed', 'オート速度', 'seg', [['normal', '通常'], ['fast', '高速'], ['max', '最速']], '最速は演出をほぼ省略します'],
    ['autoAim', 'オート目押し', 'toggle', null, '7・BAR・スイカなどを自動で狙います'],
    ['stopOnBonus', 'ボーナス当選で停止', 'toggle'],
    ['stopOnAT', 'RUSH突入で停止', 'toggle'],
    ['stopBelow', 'クレジット下限で停止', 'seg', [[0, 'なし'], [100, '100'], [300, '300'], [500, '500']]],
  ]],
  ['操作', [
    ['stopMode', '停止方法', 'seg', [['button', 'ボタン'], ['tap', 'リールタップ'], ['both', '両方']]],
    ['hand', 'ボタン配置', 'seg', [['right', '右手'], ['left', '左手']]],
    ['manualAim', '手動の目押し補助', 'toggle', null, 'どこで押しても狙い図柄を引き込みます'],
    ['reelSpeed', 'リール速度', 'seg', [['normal', '標準'], ['fast', '速い']]],
    ['wait', 'ウェイト（4.1秒）', 'toggle', null, '実機と同じく1ゲームに最低4.1秒かかります'],
  ]],
  ['演出・サウンド', [
    ['effect', '演出頻度', 'seg', [['many', '多い'], ['normal', '普通'], ['few', '少ない'], ['off', 'なし']]],
    ['volume', '音量', 'range'],
    ['vibe', 'バイブ（振動）', 'toggle', null, '対応端末のみ'],
  ]],
  ['台設定', [
    ['setting', '設定', 'seg', [['R', 'ランダム'], ['1', '1'], ['2', '2'], ['3', '3'], ['4', '4'], ['5', '5'], ['6', '6']], 'ランダムはデータ画面で答え合わせできます'],
    ['debug', '内部状態を表示', 'toggle', null, '成立役・モード・高確などを液晶に表示'],
  ]],
];
function renderSettings() {
  let html = '<h2>設定 <button class="close" data-close>閉じる</button></h2>';
  for (const [title, opts] of OPTIONS) {
    html += `<h3>${title}</h3>`;
    for (const [key, label, type, choices, note] of opts) {
      html += `<div class="opt"><div>${label}${note ? `<small>${note}</small>` : ''}</div>`;
      if (type === 'seg') html += '<div class="seg">' + choices.map(([v, t]) => `<button data-k="${key}" data-v="${v}" class="${String(C[key]) === String(v) ? 'sel' : ''}">${t}</button>`).join('') + '</div>';
      if (type === 'toggle') html += `<div class="seg"><button data-k="${key}" data-v="1" class="${C[key] ? 'sel' : ''}">ON</button><button data-k="${key}" data-v="0" class="${C[key] ? '' : 'sel'}">OFF</button></div>`;
      if (type === 'range') html += `<input type="range" min="0" max="100" step="5" value="${C[key]}" data-range="${key}">`;
      html += '</div>';
    }
  }
  html += helpHtml();
  html += `<h3>データ</h3><div class="opt"><div>すべてのデータをリセット<small>メダル・履歴・グラフが初期化されます</small></div><button class="danger" id="btnReset">リセット</button></div>`;
  $('sheetSettings').innerHTML = html;
}
$('sheetSettings').addEventListener('click', e => {
  const b = e.target.closest('button'); if (!b) return;
  if (b.hasAttribute('data-close')) return closeSheets();
  if (b.id === 'btnReset') {
    if (b.dataset.armed) { stopAuto(); S = freshState(); game = null; save(); updateAll(); setLcd('リセットしました', ''); closeSheets(); return; }
    b.dataset.armed = '1'; b.textContent = 'もう一度押すと実行'; return;
  }
  const k = b.dataset.k; if (!k) return;
  const def = DEFAULT_CONFIG[k];
  C[k] = typeof def === 'boolean' ? b.dataset.v === '1' : typeof def === 'number' ? +b.dataset.v : b.dataset.v;
  applyConfig(); save(); renderSettings();
});
$('sheetSettings').addEventListener('input', e => {
  const k = e.target.dataset.range; if (!k) return;
  C[k] = +e.target.value; save();
});

function helpHtml() {
  const rows = (list) => list.map(([a, b]) => `<tr><td>${a}</td><td style="text-align:left;white-space:normal">${b}</td></tr>`).join('');
  return `<h3>遊び方・ネタバレ</h3>
  <details><summary class="hint" style="text-align:left;cursor:pointer">出目</summary><div class="table-wrap"><table class="hist">${rows([
    ['角チェリー', '左リールの上段か下段にチェリー（弱）'],
    ['強チェリー', '角チェリー＋右リールの同じ段にもチェリー'],
    ['中段チェリー', '左リール中段にチェリー。赤ボーナス＋継続率アップ確定'],
    ['斜めスイカ・ベル', '5ライン（上中下段・斜め2本）のどこでも揃う'],
    ['チャンス目', '灯籠が5ラインのどこかに揃う'],
    ['強チャンス目', '中段に BAR・灯籠・BAR'],
    ['リーチ目', '中段に 7・7・BAR／BAR・BAR・7／7・BAR・7／灯籠・7・灯籠。出たらボーナス確定（無告知中だけ出る）'],
  ])}</table></div></details>
  <details><summary class="hint" style="text-align:left;cursor:pointer">演出</summary><div class="table-wrap"><table class="hist">${rows([
    ['ステップアップ', '灯りの段階が多く、最後が赤や緑ほど期待度アップ'],
    ['暗転', '液晶が一瞬真っ暗に。強レア役・リーチ目・本前兆で出やすい'],
    ['リール消灯', '第一停止までリールが暗い。強レア役の合図'],
    ['液晶の色', '夜の港 → 宵闇（紫）→ 霧（濃い紫）→ 赤い霧 の順に期待度アップ'],
    ['前兆', '100Gごと・ゾロ目ゲーム（333/555/777G）で霧の港へ。本前兆とガセがある'],
    ['決闘', '3Gの連続演出。勝てば迎撃チャンスかボーナス'],
    ['無告知', 'ボーナス成立を数G隠すことがある。リーチ目で見抜ける'],
  ])}</table></div></details>
  <details><summary class="hint" style="text-align:left;cursor:pointer">裏ボタン・設定示唆</summary><div class="table-wrap"><table class="hist">${rows([
    ['液晶を3回タップ', '通常時・リール停止中。設定示唆（50Gに1回）'],
    ['PAYOUTを長押し', 'RUSH中・リール停止中。金＝継続濃厚／青＝ピンチ（1セット1回）'],
    ['左→中→右', 'RUSH終了画面で停止ボタンを順に押す。もう一度設定示唆'],
    ['ゾロ目の日', '1/1・2/2…12/12 に開くと高確スタート'],
    ...SUGGEST.map(s => [`<span style="color:${s.color || 'var(--gold)'}">${s.text}</span>`, s.note]),
  ])}</table></div></details>`;
}

// ===== データシート =====
function renderData() {
  const diff = S.outTotal - S.inTotal;
  const c = S.counts;
  const bonusAll = c.white + c.red;
  const st = C.setting === 'R' ? S.realSetting : +C.setting;
  const rate = S.inTotal ? (S.outTotal / S.inTotal * 100).toFixed(1) + '%' : '-';
  const fr = (n) => (n ? '1/' + Math.round(S.games / n) : '-');
  let html = '<h2>データ <button class="close" data-close>閉じる</button></h2>';
  html += `<div class="stats">
    <div><span>総ゲーム数</span><b>${S.games}</b></div>
    <div><span>現在</span><b>${S.sinceHit}G</b></div>
    <div><span>差枚</span><b class="${diff >= 0 ? 'plus' : 'minus'}">${diff >= 0 ? '+' : ''}${diff}</b></div>
    <div><span>白ボーナス</span><b>${c.white}</b></div>
    <div><span>赤ボーナス</span><b>${c.red}</b></div>
    <div><span>ボーナス合算</span><b>${fr(bonusAll)}</b></div>
    <div><span>RUSH突入</span><b>${c.at}</b></div>
    <div><span>迎撃チャンス</span><b>${c.czWin}/${c.cz}</b></div>
    <div><span>フリーズ</span><b>${c.freeze}</b></div>
    <div><span>最大セット</span><b>${c.maxSets}</b></div>
    <div><span>最大獲得</span><b>${c.maxGain}</b></div>
    <div><span>実戦機械割</span><b>${rate}</b></div>
    <div><span>投資</span><b>${S.invest}</b></div>
    <div><span>クレジット</span><b>${S.credit}</b></div>
    <div><span>設定</span><b>${C.setting !== 'R' || S.revealed ? st : '？'}</b></div>
  </div>`;
  if (C.setting === 'R' && !S.revealed) html += `<p><button class="seg-like danger" id="btnReveal" style="color:var(--gold);border-color:var(--line);background:#0b1024">設定を確認する</button></p>`;
  html += `<p class="hint" style="text-align:left">設定${st}の機械割の目安：${SETTING[st].rate}</p>`;
  html += '<h3>差枚グラフ</h3><canvas class="graph" id="graph"></canvas>';
  if (S.peeks.length) html += '<h3>設定示唆の履歴</h3><div class="table-wrap"><table class="hist"><tr><th>ゲーム</th><th>場所</th><th>示唆</th></tr>' +
    S.peeks.map(k => { const s = SUGGEST.find(x => x.key === k.key); return `<tr><td>${k.g}</td><td>${k.where}</td><td style="color:${s.color || 'var(--gold)'}">${s.text}</td></tr>`; }).join('') + '</table></div>';
  html += '<h3>当たり履歴</h3>';
  if (!S.hist.length) html += '<p class="hint" style="text-align:left">まだ当たりがありません。</p>';
  else html += '<div class="table-wrap"><table class="hist"><tr><th>#</th><th>G数</th><th>契機</th><th>セット</th><th>ボーナス</th><th>獲得</th></tr>' +
    S.hist.map((h, i) => `<tr><td>${S.hist.length - i}</td><td>${h.g}</td><td>${h.prem ? '極' : h.type === 'red' ? '赤7' : 'BAR'}</td><td>${h.sets}</td><td>${h.bonuses}</td><td class="${h.gain >= 0 ? 'plus' : 'minus'}">${h.gain >= 0 ? '+' : ''}${h.gain}</td></tr>`).join('') + '</table></div>';
  $('sheetData').innerHTML = html;
  drawGraph();
}
$('sheetData').addEventListener('click', e => {
  const b = e.target.closest('button'); if (!b) return;
  if (b.hasAttribute('data-close')) return closeSheets();
  if (b.id === 'btnReveal') { S.revealed = true; save(); renderData(); }
});
function drawGraph() {
  const cv = $('graph'); if (!cv) return;
  const dpr = window.devicePixelRatio || 1;
  const w = cv.clientWidth, h = cv.clientHeight;
  cv.width = w * dpr; cv.height = h * dpr;
  const ctx = cv.getContext('2d'); ctx.scale(dpr, dpr);
  const data = S.graph;
  const max = Math.max(500, ...data), min = Math.min(-500, ...data);
  const y = v => 8 + (max - v) / (max - min) * (h - 16);
  ctx.strokeStyle = '#2a3360'; ctx.lineWidth = 1;
  ctx.fillStyle = '#8b93b8'; ctx.font = '10px sans-serif';
  const step = Math.pow(10, Math.floor(Math.log10((max - min) / 3)));
  for (let v = Math.ceil(min / step) * step; v <= max; v += step) {
    ctx.beginPath(); ctx.moveTo(0, y(v)); ctx.lineTo(w, y(v)); ctx.stroke(); ctx.fillText(v, 4, y(v) - 2);
  }
  ctx.strokeStyle = '#8b93b8'; ctx.beginPath(); ctx.moveTo(0, y(0)); ctx.lineTo(w, y(0)); ctx.stroke();
  ctx.strokeStyle = '#f3c969'; ctx.lineWidth = 1.6; ctx.beginPath();
  data.forEach((v, i) => { const x = data.length > 1 ? i / (data.length - 1) * w : 0; i ? ctx.lineTo(x, y(v)) : ctx.moveTo(x, y(v)); });
  ctx.stroke();
}

function openSheet(id) { $('sheetBg').hidden = false; $(id).hidden = false; }
function closeSheets() { $('sheetBg').hidden = true; $('sheetSettings').hidden = true; $('sheetData').hidden = true; }
$('sheetBg').addEventListener('click', closeSheets);
$('btnSettings').addEventListener('click', () => { renderSettings(); openSheet('sheetSettings'); });
$('btnData').addEventListener('click', () => { renderData(); openSheet('sheetData'); });

// ===== 裏ボタン =====
// ① 通常時、リール停止中に液晶を素早く3回タップ → 設定示唆（50Gに1回まで）
// ② RUSH中、リール停止中に PAYOUT を長押し → 今セットの継続を示唆（1セット1回）
// ③ RUSH終了画面で、停止ボタンを 左→中→右 の順に押す → もう一度設定示唆
let peekSeq = [], lcdTaps = [];
const idle = () => (!game || game.settled) && !busy && !autoOn;
$('lcd').addEventListener('pointerdown', () => {
  if (!idle() || S.mode !== 'normal' || S.standby) return;
  const now = Date.now();
  lcdTaps = lcdTaps.filter(x => now - x < 700).concat(now);
  if (lcdTaps.length < 3) return;
  lcdTaps = [];
  if (S.games - S.lastPeek < 50) { beep(200, .05, 'triangle'); return; }
  S.lastPeek = S.games;
  suggest('液晶タップ');
});
let pressTimer;
$('mPayBox').addEventListener('pointerdown', () => {
  clearTimeout(pressTimer);
  pressTimer = setTimeout(() => {
    if (!idle() || S.mode !== 'at' || S.at.peeked) return;
    S.at.peeked = true;
    const strong = S.at.contPre ? chance(.7) : false;
    const weak = !S.at.contPre && chance(.15);
    setLcd(strong ? '灯が強く燃えている' : weak ? '灯が細くなっている' : '灯が揺れている', '― 裏ボタン ―', strong ? 'gold' : weak ? 'blue' : '');
    melody([659, 0, 880], .08, 'triangle'); vibe(30); save();
  }, 700);
});
['pointerup', 'pointerleave', 'pointercancel'].forEach(ev => $('mPayBox').addEventListener(ev, () => clearTimeout(pressTimer)));
function peekStop(i) {
  if (!S.endPeek || !idle()) return;
  peekSeq.push(i);
  if (peekSeq.join('') === '012') { S.endPeek = false; peekSeq = []; suggest('終了画面の裏ボタン'); }
  else if (!'012'.startsWith(peekSeq.join(''))) peekSeq = [];
}

// ===== 入力 =====
function applyConfig() {
  $('controls').classList.toggle('lefty', C.hand === 'left');
  if (autoOn && C.autoCount && autoLeft === Infinity) autoLeft = C.autoCount;
  updateLcdFrame();
}
const press = (el, fn) => el.addEventListener('pointerdown', e => { e.preventDefault(); fn(); });
press($('btnLever'), () => { if (!autoOn) lever(); });
press($('btnAuto'), toggleAuto);
press($('btnMedal'), () => { S.credit += 1000; S.invest += 1000; save(); updateMeters(); toast('メダルを1000枚追加しました'); });
stopBtns.forEach((b, i) => press(b, () => { peekStop(i); if (C.stopMode !== 'tap' && !autoOn) stopReel(i, C.manualAim); }));
reelEls.forEach((r, i) => press(r, () => {
  if (autoOn) return;
  if (C.stopMode === 'button') return;
  if (!game || game.settled) lever(); else stopReel(i, C.manualAim);
}));
document.addEventListener('keydown', e => {
  if (e.repeat || !$('sheetSettings').hidden || !$('sheetData').hidden) return;
  const k = e.key.toLowerCase();
  if (k === ' ' || k === 'enter') { e.preventDefault(); if (!autoOn) lever(); }
  const i = { j: 0, k: 1, l: 2, '1': 0, '2': 1, '3': 2 }[k];
  if (i !== undefined && !autoOn) stopReel(i, C.manualAim);
  if (k === 'a') toggleAuto();
});
document.addEventListener('visibilitychange', () => { if (document.hidden) { stopAuto(); save(); } });
window.addEventListener('resize', measure);

// ===== 起動 =====
load();
if (S.mode === 'at' && S.at && S.at.left <= 0) S.at.left = 1; // 継続判定の途中で閉じた場合
if (S.at && S.at.preTier === undefined) { S.at.contPre = chance(TIERS[S.at.tier] / 100); S.at.preTier = S.at.tier; }
const today = new Date();
const zoroDay = today.getMonth() + 1 === today.getDate() && S.zoroDay !== today.toDateString();
if (zoroDay) { S.zoroDay = today.toDateString(); if (S.mode === 'normal') { S.high = Math.max(S.high, 64); S.tone = 1; } save(); }
applyConfig();
measure();
updateAll();
if (S.standby) setLcd(S.standby.type === 'red' ? '赤7を狙え！' : 'BARを狙え！', 'ボーナス確定', S.standby.type === 'red' ? 'red' : 'gold');
else if (zoroDay) { setLcd('ゾロ目の日', '今日は夜霧が濃い（高確スタート）', 'rainbow'); }
else if (S.mode === 'normal') setLcd('夜港の灯', 'レバーを叩いてスタート', 'gold');
requestAnimationFrame(frame);
</script>
</body>
</html>
