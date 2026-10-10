<style>
/* Produtos × Preços — grade densa estilo planilha, mesmo tema da Carteira v2
   (template de Contas a Pagar). Tudo escopado sob .pa pra não colidir com o
   Bootstrap/tema do resto do sistema.

   TIPOGRAFIA: a tela tem UMA escala só, declarada aqui nos tokens --pa-fs*.
   Nada abaixo deve usar font-size literal — mexe no token e a tela inteira
   acompanha. 10px é o corpo (grade, busca, chips, rodapé). */
.pa{
  /* escala tipográfica — a única fonte de verdade da tela */
  --pa-fs:10px;      /* corpo: células, busca, chips, filtros, rodapé */
  --pa-fs-h:9px;     /* cabeçalho de coluna (caixa alta + tracking) */
  --pa-fs-mi:8.5px;  /* miúdos: pills, kbd, rótulos, marca, índice */
  --pa-fs-t:15px;    /* título da tela */

  --pa-canvas:#eef1f5; --pa-panel:#fff; --pa-panel2:#f7f9fb; --pa-panel3:#f1f4f8;
  --pa-ink:#0f141a; --pa-ink2:#39434e; --pa-muted:#737e8a;
  --pa-line:#e0e5ec; --pa-line2:#eaeef3; --pa-line3:#f2f5f8;
  --pa-accent:#1257a8; --pa-accent2:#2b7de0; --pa-accent-w:#eaf2fc; --pa-accent-b:#c9ddf6;
  --pa-amber:#8a5209; --pa-amber-w:#fdf3e3; --pa-amber-b:#f0dcb8;
  --pa-red:#a11f26; --pa-red-w:#fdedee; --pa-red-b:#f3cdd0;
  --pa-green:#186b3c; --pa-green-w:#e9f5ee; --pa-green-b:#c2e2ce;
  --pa-code:ui-monospace,"SF Mono","Cascadia Mono","Roboto Mono",Menlo,Consolas,monospace;
  --pa-r:6px; --pa-r2:4px; --pa-sh:0 1px 2px rgba(14,22,34,.05),0 0 0 1px rgba(14,22,34,.03);
  --pa-ring:0 0 0 3px rgba(18,87,168,.14);

  background:var(--pa-canvas);color:var(--pa-ink);
  font-size:var(--pa-fs);line-height:1.35;
  font-variant-numeric:tabular-nums;border-radius:var(--pa-r);
  border:1px solid var(--pa-line);box-shadow:var(--pa-sh);overflow:hidden;
}
/* Sem reset de font-size no `*`: `.pa *` pesa mais que as classes de uma
   palavra (.pa-idx, .pa-pill, .pa-title h1...) e anularia as exceções. Quem
   não declara tamanho simplesmente herda os 10px de .pa. */
.pa *{box-sizing:border-box}
.pa .mono{font-family:var(--pa-code);letter-spacing:-.01em}

/* cabeçalho da tela */
.pa-head{background:var(--pa-panel);border-bottom:1px solid var(--pa-line);padding:9px 12px}
.pa-crumb{color:var(--pa-muted);display:flex;align-items:center;gap:6px;margin-bottom:5px;flex-wrap:wrap}
.pa-crumb a{color:var(--pa-muted);text-decoration:none}
.pa-crumb a:hover{color:var(--pa-accent);text-decoration:underline}
.pa-crumb b{color:var(--pa-ink);font-weight:700}
.pa-title{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.pa-title h1{margin:0;font-size:var(--pa-fs-t);letter-spacing:-.02em;font-weight:700;color:var(--pa-ink);line-height:1.2}
.pa-title .sub{color:var(--pa-muted)}
.pa-toolbar{display:flex;align-items:center;gap:7px;margin-left:auto;flex-wrap:wrap}

/* busca por termos — cada Enter fixa um termo e eles somam (AND) */
.pa-busca{display:flex;align-items:center;gap:5px;background:#fff;border:1.5px solid var(--pa-accent-b);border-radius:13px;
  padding:3px 6px 3px 9px;min-height:26px;flex:1 1 340px;max-width:560px;min-width:210px;flex-wrap:wrap;
  box-shadow:0 1px 2px rgba(18,87,168,.10);transition:border-color .1s,box-shadow .1s}
.pa-busca:focus-within{border-color:var(--pa-accent);box-shadow:var(--pa-ring)}
.pa-busca .l{color:var(--pa-accent);line-height:1;opacity:.85}
.pa-busca input{flex:1 1 110px;border:0;outline:0;background:none;min-width:90px;font-weight:700;
  letter-spacing:-.01em;color:var(--pa-ink);padding:1px 0;line-height:1.4}
.pa-busca input::placeholder{font-weight:400;color:#96a1ad}
.pa-busca .x{border:0;background:none;color:var(--pa-muted);padding:0 3px;border-radius:99px;cursor:pointer;visibility:hidden;line-height:1}
.pa-busca.tem .x{visibility:visible}
.pa-busca .x:hover{background:var(--pa-red-w);color:var(--pa-red)}
.pa-tags{display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.pa-term{border:1px solid var(--pa-accent-b);background:var(--pa-panel);color:var(--pa-accent);
  border-radius:99px;padding:1px 7px;font-weight:700;cursor:pointer;white-space:nowrap;display:inline-flex;
  align-items:center;gap:4px;line-height:1.5;transition:background .1s,border-color .1s,color .1s}
.pa-term:hover{background:var(--pa-red-w);border-color:var(--pa-red-b);color:var(--pa-red)}
.pa-term i{font-style:normal;font-size:var(--pa-fs-mi);opacity:.6}
.pa-term:hover i{opacity:1}
.pa-term.vivo{border-style:dashed;background:var(--pa-panel2);color:var(--pa-ink2);cursor:default}
.pa-term.vivo:hover{background:var(--pa-panel2);border-color:var(--pa-line);color:var(--pa-ink2)}

/* filtros */
.pa-chips{display:flex;gap:5px;flex-wrap:wrap;align-items:center;padding:6px 12px;background:var(--pa-panel2);
  border-top:1px solid var(--pa-line);border-bottom:1px solid var(--pa-line)}
.pa-flab{font-size:var(--pa-fs-mi);letter-spacing:.12em;text-transform:uppercase;color:var(--pa-muted);
  white-space:nowrap;font-weight:700}
.pa-chip{border:1px solid var(--pa-line);background:var(--pa-panel);border-radius:99px;padding:2px 9px;cursor:pointer;
  color:var(--pa-ink2);white-space:nowrap;display:inline-flex;align-items:center;gap:5px;line-height:1.5;
  transition:background .1s,border-color .1s}
.pa-chip:hover{border-color:#a9b4bf}
.pa-chip b{font-variant-numeric:tabular-nums;font-weight:700;color:var(--pa-muted)}
.pa-chip.on{background:var(--pa-accent);border-color:var(--pa-accent);color:#fff}
.pa-chip.on b{color:#cfe0f5}
.pa-sel{background:#fff;border:1px solid var(--pa-line);border-radius:var(--pa-r2);padding:2px 5px;height:22px;max-width:190px}
.pa-sel.on{border-color:var(--pa-accent);background:#f5f9fe;font-weight:700}
.pa-cnt{margin-left:auto;color:var(--pa-muted);white-space:nowrap}

/* grade */
.pa-gridwrap{overflow:auto;background:var(--pa-panel);max-height:calc(100vh - 270px);min-height:320px}
.pa table.pa-rows{border-collapse:separate;border-spacing:0;width:100%;margin:0}
.pa table.pa-rows th{position:sticky;top:0;z-index:2;background:#e2e8ef;border-bottom:1px solid #c6d0da;
  font-size:var(--pa-fs-h);letter-spacing:.1em;text-transform:uppercase;color:#44515f;font-weight:700;text-align:left;
  padding:6px 8px;white-space:nowrap;cursor:pointer;user-select:none;
  box-shadow:inset 0 1px 0 #fff,0 1px 0 rgba(14,22,34,.07)}
.pa table.pa-rows th:hover{background:#d5dee8;color:var(--pa-accent)}
.pa table.pa-rows th.r{text-align:right}
/* cabeçalho da coluna principal (atacado) */
.pa table.pa-rows th.pri{background:#d8e2ee;color:var(--pa-accent);
  box-shadow:inset 0 1px 0 #fff,0 1px 0 rgba(14,22,34,.07),inset 0 -2px 0 var(--pa-accent)}
.pa table.pa-rows th.pri:hover{background:#cbd9e9}
.pa table.pa-rows th .ar{color:var(--pa-accent);margin-left:3px}
.pa table.pa-rows td{border-bottom:1px solid var(--pa-line3);padding:3px 8px;height:22px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pa table.pa-rows tbody tr{transition:background .08s}
.pa table.pa-rows tbody tr:hover td{background:#f8fafd}
.pa table.pa-rows tbody tr.cur td{background:var(--pa-accent-w) !important}
.pa table.pa-rows tbody tr.cur td:first-child{box-shadow:inset 2px 0 0 var(--pa-accent)}
.pa table.pa-rows tbody tr.pa-hide{display:none}

.pa-idx{font-family:var(--pa-code);font-size:var(--pa-fs-mi);color:#98a2ae;text-align:right;width:40px}
.pa-name{font-weight:700;color:var(--pa-ink);white-space:nowrap}
.pa-brand{font-size:var(--pa-fs-mi);color:var(--pa-muted);margin-left:6px;font-weight:400}
.pa-num{text-align:right;white-space:nowrap;font-family:var(--pa-code);letter-spacing:-.02em}
.pa table.pa-rows tbody tr.off .pa-name{color:var(--pa-muted);font-weight:400}

/* ---- célula de preço: input liberado com máscara de dólar -----------------
   O "$" é cravado na extrema esquerda da célula (absoluto, fora do fluxo) e o
   valor fica na extrema direita — o input ocupa a célula toda e alinha à
   direita, então os dois nunca se encostam nem dançam com o tamanho do número. */
.pa table.pa-rows td.pa-cell{padding:0;position:relative;overflow:visible}
.pa-cur{position:absolute;left:5px;top:50%;transform:translateY(-50%);
  font-family:var(--pa-code);font-size:var(--pa-fs-mi);color:#b4bcc5;pointer-events:none;user-select:none}
.pa-inp{display:block;width:100%;height:22px;border:0;background:none;margin:0;
  padding:0 7px 0 15px;text-align:right;font-family:var(--pa-code);font-size:var(--pa-fs);
  letter-spacing:-.02em;color:var(--pa-ink);font-variant-numeric:tabular-nums;
  border-radius:0;-moz-appearance:textfield}
.pa-inp:focus{outline:0;background:#fff;box-shadow:inset 0 0 0 2px var(--pa-accent);
  position:relative;z-index:1;color:var(--pa-ink);font-weight:700}
.pa-inp.pri{font-weight:700;color:var(--pa-ink)}
.pa-inp.min{color:var(--pa-amber)}
.pa-inp.sale{color:var(--pa-green);font-weight:700}
/* `zero` vem por último de propósito: mesma especificidade das regras de tom
   acima, então ganha enquanto o valor é 0 e sai do caminho quando o JS a
   remove. */
.pa-inp.zero{color:#c3cad2;font-weight:400}
/* ---- estados de gravação da célula ---------------------------------------
   sujo (âmbar) = alterado, ainda não foi pro servidor
   gravando (azul) = request em voo
   salvo (verde) = confirmado pelo banco, apaga sozinho
   erro (vermelho) = falhou e o valor digitado foi mantido; title tem o motivo */
.pa table.pa-rows td.pa-cell.sujo{background:var(--pa-amber-w) !important;
  box-shadow:inset 0 0 0 1px var(--pa-amber-b)}
.pa table.pa-rows td.pa-cell.sujo .pa-inp{color:var(--pa-amber);font-weight:700}
.pa table.pa-rows td.pa-cell.sujo .pa-cur{color:var(--pa-amber);opacity:.7}

.pa table.pa-rows td.pa-cell.gravando{background:var(--pa-accent-w) !important;
  box-shadow:inset 0 0 0 1px var(--pa-accent-b)}
.pa table.pa-rows td.pa-cell.gravando .pa-inp{color:var(--pa-accent);font-weight:700}
.pa table.pa-rows td.pa-cell.gravando .pa-cur{color:var(--pa-accent)}

.pa table.pa-rows td.pa-cell.salvo{background:var(--pa-green-w) !important;
  box-shadow:inset 0 0 0 1px var(--pa-green-b);transition:background .35s,box-shadow .35s}
.pa table.pa-rows td.pa-cell.salvo .pa-inp{color:var(--pa-green);font-weight:700}
.pa table.pa-rows td.pa-cell.salvo .pa-cur{color:var(--pa-green)}

.pa table.pa-rows td.pa-cell.erro{background:var(--pa-red-w) !important;
  box-shadow:inset 0 0 0 2px var(--pa-red);cursor:help}
.pa table.pa-rows td.pa-cell.erro .pa-inp{color:var(--pa-red);font-weight:700}
.pa table.pa-rows td.pa-cell.erro .pa-cur{color:var(--pa-red)}

.pa-inp[readonly]{cursor:default}
.pa-inp[readonly]:focus{box-shadow:inset 0 0 0 2px #c4cdd8}

.pa-sujos{display:none}
.pa-sujos.on{display:inline;color:var(--pa-amber);font-weight:700}

.pa-pill{font-size:var(--pa-fs-mi);border:1px solid var(--pa-line);background:var(--pa-panel2);padding:0 6px;
  border-radius:99px;color:var(--pa-ink2);text-transform:uppercase;letter-spacing:.06em;font-weight:700;
  white-space:nowrap;display:inline-block;line-height:1.6}
.pa-pill.bad{border-color:var(--pa-red-b);background:var(--pa-red-w);color:var(--pa-red)}
.pa-pill.al{border-color:var(--pa-amber-b);background:var(--pa-amber-w);color:var(--pa-amber)}
.pa mark{background:#ffeaa0;color:inherit;border-radius:2px;padding:0 1px;box-shadow:0 0 0 1px #f0d886}

/* ações / rodapé */
.pa-act{border:1px solid var(--pa-accent);background:var(--pa-accent);border-radius:var(--pa-r2);padding:3px 9px;color:#fff;
  display:inline-flex;align-items:center;gap:4px;text-decoration:none;font-weight:700;line-height:1.5}
.pa-act:hover{background:#1463bd;color:#fff}
.pa-act.ghost{background:var(--pa-panel);color:var(--pa-ink2);border-color:var(--pa-line);font-weight:600}
.pa-act.ghost:hover{background:var(--pa-panel2);border-color:#c4cdd8;color:var(--pa-ink2)}
.pa-foot{display:flex;align-items:center;gap:11px;padding:6px 12px;background:var(--pa-panel2);
  border-top:1px solid var(--pa-line);flex-wrap:wrap;color:var(--pa-muted)}
.pa-foot b{color:var(--pa-ink2)}
.pa-foot .l{font-size:var(--pa-fs-mi);letter-spacing:.13em;text-transform:uppercase;font-weight:700;color:#9aa4ae}
.pa kbd{font-family:var(--pa-code);font-size:var(--pa-fs-mi);border:1px solid var(--pa-line);background:#fff;
  border-radius:3px;padding:0 3px;color:var(--pa-ink2);line-height:1.5}
.pa-busca kbd{background:var(--pa-panel2);color:var(--pa-muted)}
.pa-empty{padding:30px 12px;text-align:center;color:var(--pa-muted)}

/* ---- tela de exportação ---------------------------------------------------
   Duas colunas: à esquerda o que entra, à direita o texto exato que vai pra
   área de transferência. */
.pa-exp{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);background:var(--pa-panel)}
.pa-exp-lista{border-right:1px solid var(--pa-line);overflow:auto;max-height:calc(100vh - 290px);min-height:320px}
.pa-sg{display:flex;align-items:center;gap:7px;padding:5px 12px;cursor:pointer;background:var(--pa-panel2);
  border-bottom:1px solid var(--pa-line2);border-top:1px solid var(--pa-line2);position:sticky;top:0}
.pa-sg:hover{background:var(--pa-accent-w)}
.pa-sg input{margin:0;accent-color:var(--pa-accent)}
.pa-sg-nome{flex:1;font-weight:700;color:var(--pa-ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pa-sg b{font-family:var(--pa-code);font-size:var(--pa-fs-mi);color:var(--pa-muted);font-weight:700}
.pa-sg.orfao .pa-sg-nome{color:var(--pa-amber)}
.pa-sg-itens{padding:2px 0}
.pa-item{display:flex;align-items:center;gap:8px;padding:2px 12px 2px 30px;border-bottom:1px solid var(--pa-line3)}
.pa-item span{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pa-item i{font-style:normal;font-family:var(--pa-code);color:var(--pa-muted);letter-spacing:-.02em}

.pa-exp-prev{display:flex;flex-direction:column;min-width:0}
.pa-exp-head{display:flex;align-items:center;gap:9px;padding:6px 12px;background:var(--pa-panel2);
  border-bottom:1px solid var(--pa-line)}
.pa-exp-info{color:var(--pa-muted);margin-left:auto;font-family:var(--pa-code);font-size:var(--pa-fs-mi)}
.pa-saida{margin:0;padding:10px 12px;overflow:auto;max-height:calc(100vh - 330px);min-height:280px;
  font-family:var(--pa-code);font-size:11px;line-height:1.5;color:var(--pa-ink);
  background:var(--pa-panel);white-space:pre-wrap;word-break:break-word;tab-size:2}
.pa-act.ok{background:var(--pa-green);border-color:var(--pa-green)}
.pa-act.ruim{background:var(--pa-red);border-color:var(--pa-red)}
.pa-act.dim{opacity:.45;pointer-events:none}

@media (max-width:900px){
  .pa-exp{grid-template-columns:minmax(0,1fr)}
  .pa-exp-lista{border-right:0;border-bottom:1px solid var(--pa-line);max-height:40vh;min-height:0}
  .pa-saida{max-height:50vh}
}
@media (prefers-reduced-motion:reduce){.pa *{transition:none!important;animation:none!important}}
</style>
