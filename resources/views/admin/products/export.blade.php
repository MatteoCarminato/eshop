@extends('layouts.app')

@section('title', 'Exportar lista de produtos')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            @include('admin.products.partials.style-admin')

            <div class="pa">
                <div class="pa-head">
                    <div class="pa-crumb">
                        <a href="{{ route('dashboard') }}">Dashboard</a><span>›</span>
                        <a href="{{ route('products.index') }}">Produtos</a><span>›</span>
                        <a href="{{ route('products-admin.index') }}">Preços</a><span>›</span>
                        <b>Exportar</b>
                    </div>
                    <div class="pa-title">
                        <h1>Exportar lista</h1>
                        <span class="sub">
                            {{ $totalProdutos }} {{ $totalProdutos === 1 ? 'produto' : 'produtos' }} com estoque
                            em {{ $grupos->count() }} {{ $grupos->count() === 1 ? 'subgrupo' : 'subgrupos' }}
                        </span>
                        <div class="pa-toolbar">
                            <a href="{{ route('products-admin.index') }}" class="pa-act ghost">← Voltar pra grade</a>
                        </div>
                    </div>
                </div>

                <div class="pa-chips">
                    <span class="pa-flab">Lista</span>
                    @foreach ([
                        'preco' => ['Produto e preço', 'NOMPRO + preço de atacado'],
                        'produto' => ['Somente produto', 'Só o NOMPRO, sem número nenhum'],
                        'estoque' => ['Somente estoque', 'NOMPRO + quantidade, sem preço'],
                        'ambos' => ['Estoque e preço', 'NOMPRO + preço + quantidade'],
                    ] as $v => [$label, $dica])
                        <button type="button" class="pa-chip pa-modo {{ $v === 'preco' ? 'on' : '' }}"
                            data-modo="{{ $v }}" aria-pressed="{{ $v === 'preco' ? 'true' : 'false' }}"
                            title="{{ $dica }}">{{ $label }}</button>
                    @endforeach

                    <span class="pa-flab" style="margin-left:10px">Ajuste de valor</span>
                    <span class="pa-aj" id="paAj">
                        <i id="paAjSinal">%</i>
                        <input type="text" id="paAjVal" value="0" inputmode="decimal"
                            autocomplete="off" spellcheck="false"
                            title="Negativo desconta, positivo acresce. Vale só nesta lista — não grava no produto.">
                    </span>
                    <button type="button" class="pa-chip on pa-ajm" data-ajuste="pct" aria-pressed="true"
                        title="Valor percentual">%</button>
                    <button type="button" class="pa-chip pa-ajm" data-ajuste="fixo" aria-pressed="false"
                        title="Valor fixo em dólar">U$</button>

                    <span class="pa-flab" style="margin-left:10px">Subgrupos</span>
                    <button type="button" class="pa-chip" id="paTodos">Todos</button>
                    <button type="button" class="pa-chip" id="paNenhum">Nenhum</button>

                    <span class="pa-cnt" id="paResumo"></span>
                </div>

                @if ($grupos->isEmpty())
                    <div class="pa-empty">
                        Nenhum produto com estoque no momento — não há o que exportar.
                    </div>
                @else
                    <div class="pa-exp">
                        {{-- Esquerda: escolhe o que entra --}}
                        <div class="pa-exp-lista" id="paLista">
                            @foreach ($grupos as $i => $grupo)
                                <label class="pa-sg {{ $grupo['sem_subgrupo'] ? 'orfao' : '' }}">
                                    <input type="checkbox" class="pa-sg-chk" data-i="{{ $i }}" checked>
                                    <span class="pa-sg-nome">{{ $grupo['nome'] }}</span>
                                    {{-- vira "3/12" quando o subgrupo está parcialmente marcado --}}
                                    <b class="pa-sg-cnt">{{ count($grupo['produtos']) }}</b>
                                </label>
                                <div class="pa-sg-itens">
                                    @foreach ($grupo['produtos'] as $j => $produto)
                                        <label class="pa-item">
                                            <input type="checkbox" class="pa-item-chk"
                                                data-g="{{ $i }}" data-i="{{ $j }}" checked>
                                            <span>{{ $produto['nome'] }}</span>
                                            @if ($produto['preco'] <= 0)
                                                <span class="pa-pill al" title="Sem PREATAC no ERP — sairia como U$ 0,00">sem atacado</span>
                                            @endif
                                            <em title="Estoque">{{ $produto['estoque'] == (int) $produto['estoque']
                                                ? number_format($produto['estoque'], 0, ',', '.')
                                                : number_format($produto['estoque'], 2, ',', '.') }}</em>
                                            <i data-preco="{{ $produto['preco'] }}">{{ number_format($produto['preco'], 2, ',', '.') }}</i>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>

                        {{-- Direita: exatamente o texto que vai pra área de transferência --}}
                        <div class="pa-exp-prev">
                            <div class="pa-exp-head">
                                <span class="pa-flab">Pré-visualização · atacado<span id="paAjRot"></span></span>
                                <span class="pa-exp-info" id="paLinhas"></span>
                                <button type="button" class="pa-act" id="paCopiar">Copiar</button>
                            </div>
                            <pre class="pa-saida" id="paSaida"></pre>
                        </div>
                    </div>

                    <div class="pa-foot">
                        <span class="l">Formato</span>
                        <span>nome do subgrupo, depois <b>NOMPRO</b> + preço em <b>U$</b></span>
                        <span>preço de <b>atacado</b> (PREATAC)</span>
                        <span>estoque sai entre parênteses, ex.: <b>(14)</b></span>
                        <span>o ajuste vale <b>só nesta lista</b> — não grava no produto</span>
                        <span style="margin-left:auto">Só produtos com <b>estoque &gt; 0</b></span>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const saida = document.getElementById('paSaida');
            if (!saida) return;

            const grupos   = @json($grupos);
            const lista    = document.getElementById('paLista');
            const btCopiar = document.getElementById('paCopiar');
            const resumo   = document.getElementById('paResumo');
            const linhas   = document.getElementById('paLinhas');
            const inpAj    = document.getElementById('paAjVal');
            const sinalAj  = document.getElementById('paAjSinal');
            const rotuloAj = document.getElementById('paAjRot');

            // Checkboxes indexados na carga: a lista inteira é estática, então
            // não vale varrer o DOM a cada tecla do ajuste.
            const chkGrupo = Array.from(lista.querySelectorAll('.pa-sg-chk'));
            const chkItens = chkGrupo.map((_, gi) =>
                Array.from(lista.querySelectorAll('.pa-item-chk[data-g="' + gi + '"]')));
            const contador = Array.from(lista.querySelectorAll('.pa-sg-cnt'));
            const precos   = Array.from(lista.querySelectorAll('.pa-item i[data-preco]'));

            const totalProdutos = chkItens.reduce((t, g) => t + g.length, 0);

            // O que cada linha carrega. As quatro combinações têm nome próprio
            // na tela em vez de dois botões soltos de preço e estoque.
            const MODOS = {
                preco:   { preco: true,  estoque: false },
                produto: { preco: false, estoque: false },
                estoque: { preco: false, estoque: true },
                ambos:   { preco: true,  estoque: true },
            };

            let modo = 'preco';
            let tipoAjuste = 'pct';   // 'pct' = percentual, 'fixo' = em dólar

            // U$ 1.190,00 — formato pt-BR (ponto no milhar, vírgula nos centavos),
            // que é como a lista é lida do outro lado.
            const fmtPreco = v => 'U$ ' + v.toLocaleString('pt-BR', {
                minimumFractionDigits: 2, maximumFractionDigits: 2,
            });

            const fmtNum = v => v.toLocaleString('pt-BR', {
                minimumFractionDigits: 2, maximumFractionDigits: 2,
            });

            // Estoque é inteiro na prática (decimal no banco por causa do ERP):
            // só mostra casas se existirem.
            const fmtEstoque = v => v.toLocaleString('pt-BR', { maximumFractionDigits: 2 });

            // ---- ajuste de valor ------------------------------------------------
            // Aceita vírgula ou ponto e sinal: "-20" tira 20%, "-20,5" idem com
            // meio por cento. Lixo digitado vale 0 (nenhum ajuste) em vez de NaN.
            function valorAjuste() {
                const n = parseFloat(String(inpAj.value).replace(/\s/g, '').replace(',', '.'));
                return Number.isFinite(n) ? n : 0;
            }

            // Nunca devolve negativo: 100% de desconto zera, não inverte o preço.
            function ajustar(preco) {
                const a = valorAjuste();
                if (a === 0) return preco;
                const novo = tipoAjuste === 'pct' ? preco * (1 + a / 100) : preco + a;
                return Math.max(0, Math.round(novo * 100) / 100);
            }

            function rotuloAjuste() {
                const a = valorAjuste();
                if (a === 0) return '';
                const sinal = a > 0 ? '+' : '−';
                const abs = Math.abs(a).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
                return tipoAjuste === 'pct' ? ' · ' + sinal + abs + '%' : ' · ' + sinal + 'U$ ' + abs;
            }

            // ---- texto da lista -------------------------------------------------
            // "BLUE U$ 1.215,00 (138)" — preço e estoque entram conforme o modo.
            function linhaDe(p) {
                const partes = [p.nome];
                if (MODOS[modo].preco) partes.push(fmtPreco(ajustar(p.preco)));
                if (MODOS[modo].estoque) partes.push('(' + fmtEstoque(p.estoque) + ')');
                return partes.join(' ');
            }

            // Só o que está marcado, produto a produto. Subgrupo sem nenhum item
            // marcado sai da lista inteiro, sem deixar o nome órfão.
            const selecao = () => grupos
                .map((g, gi) => ({
                    nome: g.nome,
                    produtos: g.produtos.filter((p, pi) => chkItens[gi][pi].checked),
                }))
                .filter(g => g.produtos.length > 0);

            function montarTexto() {
                // Cada bloco é "subgrupo + itens"; os blocos são separados por
                // duas linhas em branco.
                return selecao().map(g => {
                    const itens = g.produtos.map(linhaDe);
                    return [g.nome, ...itens].join('\n');
                }).join('\n\n\n');
            }

            // ---- pintura --------------------------------------------------------
            function render() {
                const texto = montarTexto();
                saida.textContent = texto;

                // A lista da esquerda mostra o preço já ajustado — é ela que
                // confere o que vai sair. Nos modos sem preço o ajuste não se
                // aplica, então volta a mostrar o atacado cru.
                precos.forEach(el => {
                    const cru = parseFloat(el.dataset.preco) || 0;
                    el.textContent = fmtNum(MODOS[modo].preco ? ajustar(cru) : cru);
                });

                const sel = selecao();
                const nProdutos = sel.reduce((t, g) => t + g.produtos.length, 0);
                resumo.textContent = nProdutos === 0
                    ? 'nada selecionado'
                    : sel.length + ' de ' + grupos.length + ' subgrupos · '
                        + nProdutos + ' de ' + totalProdutos + ' produtos';
                linhas.textContent = texto === '' ? '' : texto.split('\n').length + ' linhas';
                rotuloAj.textContent = MODOS[modo].preco ? rotuloAjuste() : '';

                btCopiar.disabled = texto === '';
                btCopiar.classList.toggle('dim', texto === '');
            }

            // Marca do subgrupo = resumo dos filhos: cheio, vazio ou traço (parcial).
            function sincronizarGrupo(gi) {
                const itens = chkItens[gi];
                const n = itens.filter(c => c.checked).length;

                chkGrupo[gi].checked = n > 0;
                chkGrupo[gi].indeterminate = n > 0 && n < itens.length;
                contador[gi].textContent = n === itens.length ? itens.length : n + '/' + itens.length;
            }

            // ---- eventos --------------------------------------------------------
            lista.addEventListener('change', e => {
                const alvo = e.target;

                if (alvo.classList.contains('pa-sg-chk')) {
                    const gi = Number(alvo.dataset.i);
                    chkItens[gi].forEach(c => c.checked = alvo.checked);
                    sincronizarGrupo(gi);
                    render();
                    return;
                }

                if (alvo.classList.contains('pa-item-chk')) {
                    sincronizarGrupo(Number(alvo.dataset.g));
                    render();
                }
            });

            function marcarTudo(on) {
                chkItens.forEach((itens, gi) => {
                    itens.forEach(c => c.checked = on);
                    sincronizarGrupo(gi);
                });
                render();
            }

            document.getElementById('paTodos').addEventListener('click', () => marcarTudo(true));
            document.getElementById('paNenhum').addEventListener('click', () => marcarTudo(false));

            document.querySelectorAll('.pa-modo').forEach(bt => {
                bt.addEventListener('click', () => {
                    modo = bt.dataset.modo;
                    document.querySelectorAll('.pa-modo').forEach(o => {
                        const on = o === bt;
                        o.classList.toggle('on', on);
                        o.setAttribute('aria-pressed', on ? 'true' : 'false');
                    });
                    // Sem preço na linha, o ajuste não tem o que fazer.
                    document.getElementById('paAj').classList.toggle('dim', !MODOS[modo].preco);
                    render();
                });
            });

            document.querySelectorAll('.pa-ajm').forEach(bt => {
                bt.addEventListener('click', () => {
                    tipoAjuste = bt.dataset.ajuste;
                    document.querySelectorAll('.pa-ajm').forEach(o => {
                        const on = o === bt;
                        o.classList.toggle('on', on);
                        o.setAttribute('aria-pressed', on ? 'true' : 'false');
                    });
                    sinalAj.textContent = tipoAjuste === 'pct' ? '%' : 'U$';
                    render();
                });
            });

            // Entrar no campo tira o 0: digitar "20" com o 0 já lá daria "020", e
            // o zero não é um valor que alguém quis escrever, é o estado "sem
            // ajuste". Com valor de verdade, seleciona tudo — digitar substitui.
            inpAj.addEventListener('focus', () => {
                if (valorAjuste() === 0) { inpAj.value = ''; return; }
                inpAj.select();
            });

            // Sair com o campo em branco volta pro 0 (= sem ajuste), pra não
            // ficar um campo vazio sem significado na tela.
            inpAj.addEventListener('blur', () => {
                if (String(inpAj.value).trim() === '') { inpAj.value = '0'; render(); }
            });

            inpAj.addEventListener('input', render);
            // Enter num input solto submeteria algum form vizinho — aqui não há o
            // que submeter, a lista é toda client-side.
            inpAj.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });

            function avisar(texto, erro) {
                btCopiar.textContent = texto;
                btCopiar.classList.toggle('ok', !erro);
                btCopiar.classList.toggle('ruim', !!erro);
                setTimeout(() => {
                    btCopiar.textContent = 'Copiar';
                    btCopiar.classList.remove('ok', 'ruim');
                }, 1600);
            }

            btCopiar.addEventListener('click', () => {
                const texto = montarTexto();
                if (texto === '') return;

                // clipboard API exige contexto seguro (https ou localhost);
                // o execCommand cobre o resto.
                const fallback = () => {
                    const ta = document.createElement('textarea');
                    ta.value = texto;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    let ok = false;
                    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
                    document.body.removeChild(ta);
                    avisar(ok ? 'Copiado!' : 'Falhou — copie à mão', !ok);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(texto).then(() => avisar('Copiado!', false), fallback);
                } else {
                    fallback();
                }
            });

            render();
        })();
    </script>
@endpush
