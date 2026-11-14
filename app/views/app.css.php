<?php defined('SU_VERSION') || exit; ?>
<style>
/* Schrift kommt vom eigenen Server, es wird nichts von außen geladen */
@font-face { font-family: 'Inter'; font-style: normal; font-weight: 100 900; font-display: swap; src: url('?action=font') format('woff2'); }
<?= suCssTokens() ?>

*, *::before, *::after { box-sizing: border-box; }
* { -webkit-tap-highlight-color: transparent; }
html { color-scheme: var(--scheme); overflow-x: clip; }
body { margin: 0; min-height: 100vh; min-height: 100dvh; overflow-x: clip;
       background: linear-gradient(165deg, var(--bg1) 0%, var(--bg2) 45%, var(--bg3) 100%) fixed;
       color: var(--text); font: 15px/1.5 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
[hidden] { display: none !important; }
a { color: var(--acc-text); }
svg.i { width: 1.15rem; height: 1.15rem; flex-shrink: 0; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-thumb { background: var(--line); border-radius: 3px; }

.wrap { width: 100%; max-width: 76rem; margin: 0 auto; padding: 0 1rem 4rem; }

/* ---------- Kopf ---------- */
.top { position: sticky; top: 0; z-index: 20; margin: 0 -1rem 1.25rem; padding: .75rem 1rem; display: flex; align-items: center; gap: .6rem;
       background: color-mix(in srgb, var(--bg1) 82%, transparent); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); border-bottom: 1px solid var(--line2); }
.top .logo { display: flex; align-items: center; gap: .65rem; margin-right: auto; min-width: 0; text-decoration: none; color: var(--text); }
.top .logo svg { border-radius: 11px; box-shadow: 0 6px 18px -6px var(--acc-glow); flex-shrink: 0; }
.top .logo b { font-size: 1.1rem; letter-spacing: -.02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.top .who { color: var(--muted); font-size: .82rem; white-space: nowrap; }
@media (max-width: 640px) { .top .who { display: none; } }

/* ---------- Knöpfe ---------- */
.btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; font: inherit; font-weight: 600; font-size: .85rem; line-height: 1;
       border-radius: .7rem; border: 1px solid transparent; padding: .65rem 1rem; cursor: pointer; white-space: nowrap; user-select: none; text-decoration: none;
       transition: background .15s, filter .15s, transform .1s, border-color .15s, color .15s; }
.btn:active { transform: scale(.97); }
.btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }
.btn:focus-visible, .ibtn:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible { outline: 2px solid var(--acc3); outline-offset: 2px; }
.btn-primary { background: linear-gradient(135deg, var(--acc), var(--acc2)); color: #fff; box-shadow: 0 6px 16px -6px var(--acc-glow); }
.btn-primary:hover:not(:disabled) { filter: brightness(1.12); }
.btn-second { background: var(--solid); color: var(--text); border-color: var(--line); }
.btn-second:hover:not(:disabled) { background: var(--hover); }
.btn-danger { background: rgba(239,68,68,.14); color: var(--bad); border-color: rgba(239,68,68,.4); }
.btn-danger:hover:not(:disabled) { background: rgba(239,68,68,.24); }
.btn-soft { background: var(--acc-soft); color: var(--acc-text); border-color: color-mix(in srgb, var(--acc) 40%, transparent); }
.btn-soft:hover:not(:disabled) { background: color-mix(in srgb, var(--acc) 28%, transparent); }
.ibtn { display: inline-flex; align-items: center; justify-content: center; width: 2.35rem; height: 2.35rem; padding: 0; border-radius: .7rem; cursor: pointer; flex-shrink: 0;
        background: var(--solid); border: 1px solid var(--line); color: var(--muted); transition: background .15s, color .15s, border-color .15s, transform .1s; }
.ibtn:hover { background: var(--hover); color: var(--text); }
.ibtn:active { transform: scale(.94); }
.ibtn.on { background: var(--acc-soft); color: var(--acc-text); border-color: color-mix(in srgb, var(--acc) 45%, transparent); }
.ibtn.danger:hover { color: var(--bad); border-color: rgba(239,68,68,.45); }

/* ---------- Flächen ---------- */
.glass { background: var(--card); border: 1px solid var(--line); border-radius: 1.15rem; box-shadow: var(--shadow); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }

/* ---------- Eingaben ---------- */
label.f { display: block; font-size: .78rem; font-weight: 600; color: var(--muted); min-width: 0; }
label.f > span.hint { font-weight: 400; }
.in, select.in, textarea.in { display: block; width: 100%; margin-top: .3rem; padding: .62rem .8rem; border-radius: .7rem; border: 1px solid var(--line); background: var(--field);
      color: var(--text); font: inherit; font-size: .95rem; outline: none; transition: border-color .15s, box-shadow .15s; min-width: 0; }
.in:focus { border-color: var(--acc); box-shadow: 0 0 0 3px var(--acc-soft); }
.in::placeholder { color: color-mix(in srgb, var(--muted) 70%, transparent); }
select.in { appearance: none; -webkit-appearance: none; padding-right: 2rem; cursor: pointer;
      background-image: linear-gradient(45deg, transparent 50%, var(--muted) 50%), linear-gradient(135deg, var(--muted) 50%, transparent 50%);
      background-position: calc(100% - 1.05rem) 55%, calc(100% - .75rem) 55%; background-size: .3rem .3rem; background-repeat: no-repeat; }
.pre { display: flex; align-items: stretch; margin-top: .3rem; border-radius: .7rem; border: 1px solid var(--line); background: var(--field); overflow: hidden; transition: border-color .15s, box-shadow .15s; }
.pre:focus-within { border-color: var(--acc); box-shadow: 0 0 0 3px var(--acc-soft); }
.pre > span { display: flex; align-items: center; padding: 0 0 0 .8rem; color: var(--muted); font-size: .9rem; font-weight: 400; white-space: nowrap; flex-shrink: 0; }
.pre > .in { margin: 0; border: 0; background: transparent; box-shadow: none !important; padding-left: .15rem; }
.check { display: flex; align-items: center; gap: .55rem; font-size: .88rem; color: var(--text); cursor: pointer; }
.check input { width: 1.05rem; height: 1.05rem; accent-color: var(--acc); flex-shrink: 0; }
.grid2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .8rem; }
.grid3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .8rem; }
@media (max-width: 720px) { .grid3 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 480px) { .grid2, .grid3 { grid-template-columns: minmax(0, 1fr); } }
.stack > * + * { margin-top: .8rem; }

/* ---------- Kürzen ---------- */
.hero { padding: 1.35rem; margin-bottom: 1.25rem; }
.hero h1 { margin: 0 0 .2rem; font-size: 1.5rem; letter-spacing: -.03em; line-height: 1.2; }
.hero p.sub { margin: 0 0 1rem; color: var(--muted); font-size: .92rem; }
.shorten { display: flex; gap: .6rem; }
.shorten .in { margin: 0; font-size: 1.05rem; padding: .85rem 1rem; border-radius: .85rem; }
.shorten .btn { padding: .85rem 1.4rem; font-size: .95rem; border-radius: .85rem; }
@media (max-width: 560px) { .shorten { flex-direction: column; } }
.optbar { display: flex; align-items: center; gap: .5rem; margin-top: .75rem; flex-wrap: wrap; }
.optbar .link { background: none; border: 0; padding: .3rem .2rem; font: inherit; font-size: .86rem; font-weight: 600; color: var(--acc-text); cursor: pointer; display: inline-flex; align-items: center; gap: .3rem; }
.optbar .link svg { transition: transform .2s; }
.optbar .link[aria-expanded=true] svg { transform: rotate(180deg); }
.opts { margin-top: .9rem; padding-top: 1rem; border-top: 1px solid var(--line2); }

.result { margin-top: 1rem; padding: 1rem; border-radius: .95rem; background: var(--acc-soft); border: 1px solid color-mix(in srgb, var(--acc) 45%, transparent);
          display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; animation: pop .25s ease; }
.result .short { font-size: 1.25rem; font-weight: 700; letter-spacing: -.02em; word-break: break-all; color: var(--text); text-decoration: none; margin-right: auto; }
.result .short span { color: var(--muted); font-weight: 500; }
@keyframes pop { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }

/* ---------- Leiste über der Liste ---------- */
.bar { display: flex; gap: .6rem; align-items: center; margin-bottom: .9rem; flex-wrap: wrap; }
.bar .search { position: relative; flex: 1 1 14rem; min-width: 0; }
.bar .search svg { position: absolute; left: .8rem; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
.bar .search .in { margin: 0; padding-left: 2.4rem; }
.bar select.in { margin: 0; width: auto; flex: 0 1 auto; }
@media (max-width: 640px) { .bar select.in { flex: 1 1 8rem; } }
.sum { color: var(--muted); font-size: .84rem; margin: 0 0 .9rem; }

/* ---------- Karten ---------- */
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(19.5rem, 1fr)); gap: .9rem; }
@media (max-width: 420px) { .cards { grid-template-columns: minmax(0, 1fr); } }
.card { padding: 1rem; display: flex; flex-direction: column; min-width: 0; transition: border-color .15s, transform .15s; }
.card:hover { border-color: color-mix(in srgb, var(--acc) 45%, var(--line)); }
.card.ended { opacity: .62; }
.card .head { display: flex; align-items: flex-start; gap: .6rem; }
.card .code { font-size: 1.12rem; font-weight: 700; letter-spacing: -.02em; color: var(--text); text-decoration: none; word-break: break-all; line-height: 1.3; }
.card .code span { color: var(--muted); font-weight: 500; font-size: .86rem; }
.card .code:hover b { color: var(--acc-text); }
.card .code b { font-weight: 700; transition: color .15s; }
.card .clicks { margin-left: auto; text-align: right; flex-shrink: 0; line-height: 1.1; }
.card .clicks b { display: block; font-size: 1.2rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.card .clicks small { color: var(--muted); font-size: .7rem; }
.card .to { margin-top: .35rem; color: var(--muted); font-size: .86rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.card .to b { color: var(--text); font-weight: 600; }
.card .note { margin-top: .3rem; font-size: .86rem; color: var(--text); overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.tags { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .6rem; }
.tag { display: inline-flex; align-items: center; gap: .3rem; font-size: .74rem; font-weight: 500; padding: .2rem .5rem; border-radius: .45rem; border: 1px solid var(--line); color: var(--muted); background: var(--field); }
.tag svg.i { width: .8rem; height: .8rem; }
.tag.acc { color: var(--acc-text); border-color: color-mix(in srgb, var(--acc) 40%, transparent); background: var(--acc-soft); }
.tag.bad { color: var(--bad); border-color: rgba(239,68,68,.4); background: rgba(239,68,68,.1); }
.tag.warn { color: var(--warn); border-color: rgba(245,158,11,.4); background: rgba(245,158,11,.1); }
.card .acts { display: flex; gap: .35rem; align-items: center; margin-top: auto; padding-top: .8rem; }
.card .acts::before { content: ''; }
.card .sep { height: 1px; background: var(--line2); margin-top: .8rem; }
.card .acts .btn { flex: 1 1 auto; min-width: 0; }
.empty { text-align: center; padding: 3rem 1rem; color: var(--muted); }
.empty svg { width: 2.6rem; height: 2.6rem; opacity: .5; margin-bottom: .5rem; }
.more { display: flex; justify-content: center; margin-top: 1.2rem; }
.foot { margin-top: 2.5rem; text-align: center; color: var(--muted); font-size: .78rem; }
.foot a { color: var(--muted); }

/* ---------- Fenster ---------- */
dialog { border: 1px solid var(--line); border-radius: 1.15rem; padding: 0; background: var(--solid); color: var(--text); width: min(34rem, calc(100vw - 1.5rem));
         max-height: calc(100dvh - 1.5rem); box-shadow: 0 30px 80px -20px rgba(0,0,0,.7); overflow: hidden; }
dialog.wide { width: min(46rem, calc(100vw - 1.5rem)); }
dialog[open] { display: flex; flex-direction: column; animation: pop .18s ease; }
dialog::backdrop { background: rgba(2,6,23,.62); backdrop-filter: blur(3px); }
.dhead { display: flex; align-items: center; gap: .6rem; padding: 1rem 1.15rem; border-bottom: 1px solid var(--line2); }
.dhead h2 { margin: 0; font-size: 1.08rem; letter-spacing: -.01em; margin-right: auto; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dbody { padding: 1.15rem; overflow-y: auto; }
.dfoot { display: flex; gap: .6rem; justify-content: flex-end; padding: .9rem 1.15rem; border-top: 1px solid var(--line2); flex-wrap: wrap; }
.dbody h3 { margin: 1.4rem 0 .5rem; font-size: .78rem; text-transform: uppercase; letter-spacing: .07em; color: var(--muted); }
.dbody h3:first-child { margin-top: 0; }
.dbody p { margin: 0 0 .8rem; color: var(--muted); font-size: .88rem; }
.msg { border-radius: .7rem; padding: .65rem .8rem; font-size: .88rem; border: 1px solid; margin-bottom: .8rem; }
.msg.bad { background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.4); color: var(--bad); }
.msg.good { background: rgba(16,185,129,.12); border-color: rgba(16,185,129,.4); color: var(--ok); }
.msg.warn { background: rgba(245,158,11,.12); border-color: rgba(245,158,11,.4); color: var(--warn); }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .92rem; letter-spacing: .03em; word-break: break-all; }
.codebox { padding: .8rem; border-radius: .7rem; background: var(--field); border: 1px dashed var(--line); text-align: center; font-size: 1.05rem; font-weight: 600; user-select: all; }
.seg { display: inline-flex; border: 1px solid var(--line); border-radius: .7rem; overflow: hidden; background: var(--field); }
.seg button { border: 0; background: transparent; color: var(--muted); font: inherit; font-size: .82rem; font-weight: 600; padding: .5rem .8rem; cursor: pointer; display: inline-flex; align-items: center; gap: .35rem; }
.seg button.on { background: var(--acc-soft); color: var(--acc-text); }
.seg button .dot { width: .7rem; height: .7rem; border-radius: 50%; display: inline-block; }
.row { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
.row .grow { flex: 1 1 auto; min-width: 0; }

/* ---------- QR-Code ---------- */
.qr { display: flex; flex-direction: column; align-items: center; gap: .9rem; }
.qr .pic { background: #fff; padding: .9rem; border-radius: .9rem; line-height: 0; }
.qr .pic svg { width: min(15rem, 62vw); height: auto; display: block; }
.qr .url { font-weight: 600; word-break: break-all; text-align: center; }

/* ---------- Statistik ---------- */
.kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .6rem; margin-bottom: 1rem; }
.kpi { padding: .7rem .8rem; border-radius: .8rem; background: var(--field); border: 1px solid var(--line2); min-width: 0; }
.kpi b { display: block; font-size: 1.45rem; font-weight: 700; letter-spacing: -.02em; font-variant-numeric: tabular-nums; line-height: 1.15; }
.kpi span { color: var(--muted); font-size: .76rem; }
.chart { position: relative; }
.chart svg { width: 100%; height: auto; display: block; overflow: visible; }
.chart .grid { stroke: var(--line2); stroke-width: 1; }
.chart .axis { fill: var(--muted); font-size: 11px; font-family: inherit; }
.chart .bar { fill: var(--acc); transition: fill .1s; }
.chart .hit { fill: transparent; }
.chart .hit:hover + .bar, .chart .bar.hot { fill: var(--acc3); }
.tip { position: absolute; pointer-events: none; z-index: 5; padding: .35rem .55rem; border-radius: .5rem; background: var(--solid); border: 1px solid var(--line); box-shadow: var(--shadow);
       font-size: .78rem; white-space: nowrap; transform: translate(-50%, -100%); }
.tip b { font-variant-numeric: tabular-nums; }
.tip span { color: var(--muted); }
table.t { width: 100%; border-collapse: collapse; font-size: .86rem; }
table.t th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); font-weight: 600; padding: .4rem .5rem; border-bottom: 1px solid var(--line2); }
table.t td { padding: .5rem; border-bottom: 1px solid var(--line2); vertical-align: middle; }
table.t td.num, table.t th.num { text-align: right; font-variant-numeric: tabular-nums; }
table.t tr:last-child td { border-bottom: 0; }
.scrollx { overflow-x: auto; }
details.tbl { margin-top: .9rem; }
details.tbl summary { cursor: pointer; color: var(--acc-text); font-size: .84rem; font-weight: 600; }
details.tbl .box { max-height: 14rem; overflow-y: auto; margin-top: .5rem; }

/* ---------- Meldungen ---------- */
.toasts { position: fixed; left: 50%; bottom: 1.25rem; transform: translateX(-50%); z-index: 100; display: flex; flex-direction: column; gap: .5rem; align-items: center; pointer-events: none; width: max-content; max-width: calc(100vw - 2rem); }
.toast { padding: .65rem 1rem; border-radius: .8rem; background: var(--solid); border: 1px solid var(--line); box-shadow: var(--shadow); font-size: .88rem; font-weight: 500; animation: pop .2s ease; display: flex; align-items: center; gap: .5rem; }
.toast.bad { border-color: rgba(239,68,68,.5); color: var(--bad); }
.toast.good svg { color: var(--ok); }
@media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
</style>
