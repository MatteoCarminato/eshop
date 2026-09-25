<style>
/* Carteira v2 — grade densa estilo planilha, inspirada no template de Contas a Pagar.
   Tudo escopado sob .wv2 pra não colidir com o Bootstrap/tema do resto do sistema. */
.wv2{
  --wv2-canvas:#eef1f5; --wv2-panel:#fff; --wv2-panel2:#f7f9fb; --wv2-panel3:#f1f4f8;
  --wv2-ink:#0f141a; --wv2-ink2:#39434e; --wv2-muted:#737e8a;
  --wv2-line:#e0e5ec; --wv2-line2:#eaeef3; --wv2-line3:#f2f5f8;
  --wv2-accent:#1257a8; --wv2-accent2:#2b7de0; --wv2-accent-w:#eaf2fc; --wv2-accent-b:#c9ddf6;
  --wv2-amber:#8a5209; --wv2-amber-w:#fdf3e3; --wv2-amber-b:#f0dcb8;
  --wv2-red:#a11f26; --wv2-red-w:#fdedee; --wv2-red-b:#f3cdd0;
  --wv2-green:#186b3c; --wv2-green-w:#e9f5ee; --wv2-green-b:#c2e2ce;
  --wv2-code:ui-monospace,"SF Mono","Cascadia Mono","Roboto Mono",Menlo,Consolas,monospace;
  --wv2-r:6px; --wv2-r2:4px; --wv2-sh:0 1px 2px rgba(14,22,34,.05),0 0 0 1px rgba(14,22,34,.03);
  --wv2-ring:0 0 0 3px rgba(18,87,168,.14);
  background:var(--wv2-canvas);color:var(--wv2-ink);
  font-variant-numeric:tabular-nums;border-radius:var(--wv2-r);
  border:1px solid var(--wv2-line);box-shadow:var(--wv2-sh);overflow:hidden;
}
.wv2 *{box-sizing:border-box}
.wv2 .mono{font-family:var(--wv2-code);letter-spacing:-.01em}
.wv2 .num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}

/* cabeçalho da tela */
.wv2-head{background:var(--wv2-panel);border-bottom:1px solid var(--wv2-line);padding:10px 14px}
.wv2-crumb{font-size:10.5px;color:var(--wv2-muted);display:flex;align-items:center;gap:6px;margin-bottom:6px;flex-wrap:wrap}
.wv2-crumb a{color:var(--wv2-muted);text-decoration:none} .wv2-crumb a:hover{color:var(--wv2-accent);text-decoration:underline}
.wv2-crumb b{color:var(--wv2-ink);font-weight:700}
.wv2-title{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.wv2-title h1{margin:0;font-size:16px;letter-spacing:-.02em;font-weight:700;color:var(--wv2-ink)}
.wv2-title .sub{font-size:11px;color:var(--wv2-muted)}
.wv2-toolbar{display:flex;align-items:center;gap:8px;margin-left:auto;flex-wrap:wrap}

/* busca */
.wv2-busca{display:flex;align-items:center;gap:7px;background:#fff;border:1.5px solid var(--wv2-accent-b);border-radius:99px;
  padding:0 6px 0 11px;height:28px;flex:1 1 340px;max-width:480px;min-width:220px;
  box-shadow:0 1px 2px rgba(18,87,168,.10);transition:border-color .1s,box-shadow .1s}
.wv2-busca:focus-within{border-color:var(--wv2-accent);box-shadow:var(--wv2-ring)}
.wv2-busca input{flex:1;border:0;outline:0;background:none;font-size:12.5px;min-width:0;font-weight:600;letter-spacing:-.01em;height:100%;color:var(--wv2-ink)}
.wv2-busca input::placeholder{font-weight:400;color:#96a1ad}
.wv2-busca .x{border:0;background:none;color:var(--wv2-muted);font-size:13px;padding:0 3px;border-radius:99px;cursor:pointer;visibility:hidden;line-height:1}
.wv2-busca.tem .x{visibility:visible}
.wv2-busca .x:hover{background:var(--wv2-red-w);color:var(--wv2-red)}

/* chips de visão / filtro rápido */
.wv2-chips{display:flex;gap:6px;flex-wrap:wrap;padding:8px 14px;background:var(--wv2-panel2);border-bottom:1px solid var(--wv2-line)}
.wv2-chip{font-size:11px;border:1px solid var(--wv2-line);background:var(--wv2-panel);border-radius:99px;padding:3px 10px;cursor:pointer;
  color:var(--wv2-ink2);white-space:nowrap;display:inline-flex;align-items:center;gap:5px;transition:background .1s,border-color .1s}
.wv2-chip:hover{border-color:#a9b4bf}
.wv2-chip b{font-variant-numeric:tabular-nums;font-weight:700;color:var(--wv2-muted)}
.wv2-chip.on{background:var(--wv2-accent);border-color:var(--wv2-accent);color:#fff}
.wv2-chip.on b{color:#cfe0f5}
.wv2-cnt{margin-left:auto;font-size:10.5px;color:var(--wv2-muted);white-space:nowrap;align-self:center}

/* stat tiles */
.wv2-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));border-bottom:1px solid var(--wv2-line);background:var(--wv2-panel)}
.wv2-stats>div{padding:8px 14px;border-right:1px solid var(--wv2-line2);min-width:0}
.wv2-stats .l{font-size:8px;letter-spacing:.12em;text-transform:uppercase;color:var(--wv2-muted);font-weight:700}
.wv2-stats .v{font-family:var(--wv2-code);font-size:16px;font-weight:700;margin-top:3px;letter-spacing:-.02em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.wv2-stats .v.pos{color:var(--wv2-green)} .wv2-stats .v.neg{color:var(--wv2-red)}
.wv2-stats .hint{font-size:9.5px;color:var(--wv2-muted);margin-top:2px}

/* grade */
.wv2-gridwrap{overflow:auto;background:var(--wv2-panel);max-height:70vh}
.wv2 table.wv2-rows{border-collapse:separate;border-spacing:0;width:100%;font-size:12px}
.wv2 table.wv2-rows th{position:sticky;top:0;z-index:2;background:#e2e8ef;border-bottom:1px solid #c6d0da;
  font-size:9px;letter-spacing:.1em;text-transform:uppercase;color:#44515f;font-weight:700;text-align:left;padding:8px 9px;
  white-space:nowrap;cursor:pointer;user-select:none;box-shadow:inset 0 1px 0 #fff,0 1px 0 rgba(14,22,34,.07)}
.wv2 table.wv2-rows th:hover{background:#d5dee8;color:var(--wv2-accent)}
.wv2 table.wv2-rows th.r{text-align:right}
.wv2 table.wv2-rows th .ar{color:var(--wv2-accent);margin-left:3px}
.wv2 table.wv2-rows td{border-bottom:1px solid var(--wv2-line3);padding:6px 9px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wv2 table.wv2-rows tbody tr{transition:background .08s}
.wv2 table.wv2-rows tbody tr:hover td{background:#f8fafd}
.wv2 table.wv2-rows tbody tr.wv2-hide{display:none}
.wv2 table.wv2-rows tfoot td{background:var(--wv2-panel2);font-weight:700;border-top:2px solid var(--wv2-line);border-bottom:0}
.wv2-name{font-weight:700;color:var(--wv2-ink)}
.wv2-mu{color:var(--wv2-muted)}
mark{background:#ffeaa0;color:inherit;border-radius:2px;padding:0 1px;box-shadow:0 0 0 1px #f0d886}

.wv2-pill{font-size:8.5px;border:1px solid var(--wv2-line);background:var(--wv2-panel2);padding:1px 7px;border-radius:99px;color:var(--wv2-ink2);
  text-transform:uppercase;letter-spacing:.06em;font-weight:700;white-space:nowrap;display:inline-block}
.wv2-pill.bad{border-color:var(--wv2-red-b);background:var(--wv2-red-w);color:var(--wv2-red)}
.wv2-pill.ok{border-color:var(--wv2-green-b);background:var(--wv2-green-w);color:var(--wv2-green)}
.wv2-pill.al{border-color:var(--wv2-amber-b);background:var(--wv2-amber-w);color:var(--wv2-amber)}
.wv2-pill.ac{border-color:var(--wv2-accent-b);background:var(--wv2-accent-w);color:var(--wv2-accent)}

.wv2-act{border:1px solid var(--wv2-accent);background:var(--wv2-accent);border-radius:var(--wv2-r2);padding:3px 10px;font-size:11px;color:#fff;
  display:inline-flex;align-items:center;gap:4px;text-decoration:none;font-weight:600}
.wv2-act:hover{background:#1463bd;color:#fff}
.wv2-act.ghost{background:var(--wv2-panel);color:var(--wv2-ink2);border-color:var(--wv2-line)}
.wv2-act.ghost:hover{background:var(--wv2-panel2);border-color:#c4cdd8;color:var(--wv2-ink2)}

.wv2-foot{display:flex;align-items:center;gap:14px;padding:8px 14px;background:var(--wv2-panel2);border-top:1px solid var(--wv2-line);
  font-size:10.5px;flex-wrap:wrap;color:var(--wv2-muted)}
.wv2-foot b{color:var(--wv2-ink2)}
.wv2-empty{padding:30px 14px;text-align:center;color:var(--wv2-muted);font-size:12px}
</style>
