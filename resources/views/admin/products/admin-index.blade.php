@extends('layouts.app')

@section('title', 'Produtos · Preços')

@section('content')
    @php
        $canManage = auth()->user()->hasModule('products.manage');
        // Só as colunas digitáveis entram no caminho do Tab/setas — o Médio é
        // exibido mas não é célula de edição.
        $navKeys = array_keys(array_filter($cols, fn ($c) => $c['edit']));
    @endphp

    <div class="page-content">
        <div class="container-fluid">
            @include('admin.products.partials.style-admin')

            {{-- Retorno das ações da toolbar (hoje só "Atualizar XML"): esta
                 tela tem layout próprio e não herda o bloco de flash das
                 outras, então precisa do seu. --}}
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Sucesso!</strong> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Erro!</strong> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif

            <div class="pa">
                <div class="pa-head">
                    <div class="pa-crumb">
                        <a href="{{ route('dashboard') }}">Dashboard</a><span>›</span>
                        <a href="{{ route('products.index') }}">Produtos</a><span>›</span>
                        <b>Preços</b>
                    </div>
                    <div class="pa-title">
                        <h1>Produtos × Preços</h1>
                        <span class="sub">
                            {{ $products->count() }} produtos carregados — sem paginação
                            @if ($atacado === '1' && $semAtacado > 0)
                                · <b>{{ $semAtacado }}</b> sem atacado ocultos
                            @endif
                            @if ($estoque === '1' && $semEstoque > 0)
                                · <b>{{ $semEstoque }}</b> zerados ocultos
                            @endif
                        </span>
                        @unless ($canManage)
                            <span class="pa-pill al" title="Falta o módulo products.manage">somente leitura</span>
                        @endunless

                        <div class="pa-busca" id="paBusca">
                            <span class="l">🔎</span>
                            <span class="pa-tags" id="paTags"></span>
                            <input id="paSearch" type="text" placeholder="termo e Enter — os termos somam" autocomplete="off" spellcheck="false">
                            <kbd>/</kbd>
                            <button type="button" class="x" id="paSearchX" title="Limpar tudo">✕</button>
                        </div>

                        <div class="pa-toolbar">
                            <a href="{{ route('products-admin.export') }}" class="pa-act">Exportar</a>
                            <a href="{{ route('products.index') }}" class="pa-act ghost">Catálogo completo</a>
                            @if ($canManage)
                                {{-- O feed do ComprasPY se regenera sozinho de hora em hora; este
                                     botão é pra publicar na hora quem acabou de mexer em preço/estoque. --}}
                                <form method="POST" action="{{ route('compraspy.feed.refresh') }}" style="display:contents">
                                    @csrf
                                    <button type="submit" class="pa-act ghost"
                                        title="Republica o feed XML do ComprasPY agora (ele também é atualizado automaticamente a cada hora)">Atualizar XML</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="pa-chips">
                    <span class="pa-flab">Situação</span>
                    @foreach (['1' => 'Ativos', '0' => 'Inativos', '' => 'Todos'] as $v => $label)
                        <a href="{{ route('products-admin.index', ['active' => $v, 'brand' => $brand, 'atacado' => $atacado, 'estoque' => $estoque]) }}"
                            class="pa-chip {{ (string) $active === (string) $v ? 'on' : '' }}"
                            style="text-decoration:none">{{ $label }}</a>
                    @endforeach

                    <span class="pa-flab" style="margin-left:10px">Estoque</span>
                    @foreach (['1' => 'Com estoque', '0' => 'Zerados', '' => 'Mostrar todos'] as $v => $label)
                        <a href="{{ route('products-admin.index', ['active' => $active, 'brand' => $brand, 'atacado' => $atacado, 'estoque' => $v]) }}"
                            class="pa-chip {{ (string) $estoque === (string) $v ? 'on' : '' }}"
                            style="text-decoration:none"
                            @if ($v === '0') title="Produtos com saldo zerado no ERP — fora da lista por padrão" @endif>
                            {{ $label }}
                            @if ($v === '0' && $semEstoque > 0)
                                <b>{{ $semEstoque }}</b>
                            @endif
                        </a>
                    @endforeach

                    <span class="pa-flab" style="margin-left:10px">Atacado</span>
                    @foreach (['1' => 'Com preço', '0' => 'Sem preço', '' => 'Mostrar todos'] as $v => $label)
                        <a href="{{ route('products-admin.index', ['active' => $active, 'brand' => $brand, 'atacado' => $v, 'estoque' => $estoque]) }}"
                            class="pa-chip {{ (string) $atacado === (string) $v ? 'on' : '' }}"
                            style="text-decoration:none"
                            @if ($v === '0') title="Produtos sem PREATAC no ERP — os que precisam de preço" @endif>
                            {{ $label }}
                            @if ($v === '0' && $semAtacado > 0)
                                <b>{{ $semAtacado }}</b>
                            @endif
                        </a>
                    @endforeach

                    <span class="pa-flab" style="margin-left:10px">Marca</span>
                    <form method="GET" action="{{ route('products-admin.index') }}" style="display:contents">
                        <input type="hidden" name="active" value="{{ $active }}">
                        <input type="hidden" name="atacado" value="{{ $atacado }}">
                        <input type="hidden" name="estoque" value="{{ $estoque }}">
                        <select name="brand" class="pa-sel {{ $brand ? 'on' : '' }}" onchange="this.form.submit()">
                            <option value="">Todas as marcas</option>
                            @foreach ($brands as $b)
                                <option value="{{ $b->id }}" {{ (string) $brand === (string) $b->id ? 'selected' : '' }}>
                                    {{ $b->name }}
                                </option>
                            @endforeach
                        </select>
                    </form>

                    <span class="pa-cnt" id="paCnt"></span>
                </div>

                <div class="pa-gridwrap" id="paGrid">
                    <table class="pa-rows" id="paTable">
                        <thead>
                            <tr>
                                <th class="pa-idx" scope="col" data-k="idx" title="ID do produto — ordenar por ID">ID</th>
                                <th scope="col" data-k="name">Produto <span class="ar"></span></th>
                                <th class="r pa-est" scope="col" data-k="stock" style="width:74px"
                                    title="Estoque no ERP (ESTOQUE)">Estoque <span class="ar"></span></th>
                                @foreach ($cols as $key => $col)
                                    <th class="r {{ $col['tom'] === 'pri' ? 'pri' : '' }}" scope="col"
                                        data-k="{{ $key }}" style="width:104px">{{ $col['label'] }} <span class="ar"></span></th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr class="pa-row {{ $product->active ? '' : 'off' }}"
                                    data-id="{{ $product->id }}"
                                    data-idx="{{ $product->id }}"
                                    data-busca="{{ mb_strtolower($product->name . ' ' . $product->short_name . ' ' . ($product->brand->name ?? '')) }}"
                                    data-v-name="{{ $product->name }}">
                                    <td class="pa-idx">{{ $product->id }}</td>
                                    <td>
                                        <span class="pa-name">{{ $product->name }}</span>
                                        @if ($product->brand)
                                            <span class="pa-brand">{{ $product->brand->name }}</span>
                                        @endif
                                        @unless ($product->active)
                                            <span class="pa-pill bad" title="Produto inativo">inativo</span>
                                        @endunless
                                    </td>
                                    @php
                                        $est = (float) $product->stock;
                                        // Estoque é inteiro na prática (decimal no banco por
                                        // causa do ERP), então só mostra centavos se existirem.
                                        $estFmt = number_format($est, $est == (int) $est ? 0 : 2, '.', ',');
                                    @endphp
                                    <td class="r pa-est {{ $est <= 0 ? 'zero' : '' }}" data-col="stock" data-v="{{ $est }}">
                                        {{ $estFmt }}
                                    </td>
                                    @foreach ($cols as $key => $col)
                                        @php
                                            $calc = ! empty($col['calc']);
                                            // Diferença = Atacado − Médio. Só vale com os dois lados
                                            // preenchidos: sem um deles ela viraria o negativo do
                                            // outro número, e a célula mostra "—" em vez de mentir.
                                            $temDiff = $calc
                                                && (float) $product->wholesale_price > 0
                                                && (float) $product->average_price > 0;
                                            $val = $calc
                                                ? (float) $product->wholesale_price - (float) $product->average_price
                                                : (float) $product->{$key};
                                            $fmt = number_format($val, 2, '.', ',');
                                            // Tom e "zerado" são classes independentes: ao digitar
                                            // numa célula zerada o JS só tira `zero`, e o tom da
                                            // coluna volta sozinho.
                                            $tom = trim($col['tom'] . ($val <= 0 ? ' zero' : ''));
                                        @endphp
                                        @if ($calc)
                                            {{-- Coluna calculada (Diferença): nunca é input e não tem
                                                 coluna no banco. O `data-v` é o número cru (vazio
                                                 quando não dá pra comparar), usado pela ordenação; o
                                                 JS reescreve a célula a cada atacado digitado. --}}
                                            <td class="pa-cell ro" data-col="{{ $key }}"
                                                data-v="{{ $temDiff ? $val : '' }}" title="Atacado − Médio">
                                                <span class="pa-cur">$</span>
                                                <span class="pa-ro diff {{ $temDiff ? ($val > 0 ? 'pos' : ($val < 0 ? 'neg' : '')) : 'nulo' }}">{{ $temDiff ? $fmt : '—' }}</span>
                                            </td>
                                        @elseif ($col['edit'])
                                            <td class="pa-cell" data-col="{{ $key }}">
                                                <span class="pa-cur">$</span>
                                                <input type="text" class="pa-inp {{ $tom }}" value="{{ $fmt }}"
                                                    data-orig="{{ $fmt }}" data-col="{{ $key }}" placeholder="{{ $fmt }}"
                                                    inputmode="decimal" autocomplete="off" spellcheck="false" tabindex="-1"
                                                    @unless ($canManage) readonly @endunless>
                                            </td>
                                        @else
                                            {{-- Coluna de leitura (Médio): texto, não input — fora do
                                                 Tab e sem endpoint de gravação. O `data-v` é o número
                                                 cru, usado pela ordenação. --}}
                                            <td class="pa-cell ro" data-col="{{ $key }}" data-v="{{ $val }}">
                                                <span class="pa-cur">$</span>
                                                <span class="pa-ro {{ $tom }}">{{ $fmt }}</span>
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($cols) + 3 }}">
                                        <div class="pa-empty">Nenhum produto encontrado com esses filtros.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <form id="paCsrf" style="display:none">@csrf</form>

                <div class="pa-foot">
                    <span class="l">Busca</span>
                    <span><kbd>/</kbd> focar</span>
                    <span><kbd>Enter</kbd> fixa o termo (somam)</span>
                    <span><kbd>Backspace</kbd> apaga o último</span>
                    <span><kbd>alt</kbd>+<kbd>Enter</kbd> vai pra grade</span>
                    <span class="l" style="margin-left:6px">Grade</span>
                    <span><kbd>Tab</kbd> desce na mesma coluna</span>
                    <span><kbd>←</kbd><kbd>→</kbd> muda de coluna</span>
                    <span><kbd>Enter</kbd> confirma e desce</span>
                    <span><kbd>Esc</kbd> desfaz a célula</span>
                    <span class="l" style="margin-left:6px">Colunas</span>
                    <span>arraste o <b>cabeçalho</b> pra mover a coluna</span>
                    <button type="button" class="pa-colreset" id="paColsReset"
                        title="Volta as colunas pra ordem original">↺ ordem padrão</button>
                    <span><b>Atacado</b> grava <b>Promo</b> e <b>Mínimo</b> junto (fora da grade)</span>
                    <span><b>Atacado</b> vai direto pro <b>ERP</b> (PREATAC/PREVEN/PREMIN) — <b>Web</b> fica só no site</span>
                    <span><b>Diferença</b> = Atacado − Médio</span>
                    <span><b>Médio</b> e <b>Estoque</b> são só leitura (vêm do ERP)</span>
                    <span class="pa-sujos" id="paSujos"></span>
                    <span style="margin-left:auto">Tudo em <b>US$</b> · clique no cabeçalho pra ordenar</span>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const table = document.getElementById('paTable');
            if (!table) return;

            const tbody   = table.querySelector('tbody');
            const busca   = document.getElementById('paBusca');
            const tagsBox = document.getElementById('paTags');
            const input   = document.getElementById('paSearch');
            const clearBt = document.getElementById('paSearchX');
            const counter = document.getElementById('paCnt');
            const sujos   = document.getElementById('paSujos');
            const rows    = Array.from(tbody.querySelectorAll('tr.pa-row'));
            // Navegação só pelas colunas digitáveis: Médio, Diferença e Estoque
            // são texto. A ordem é lida da tela, não da constante do PHP: as
            // colunas podem ser arrastadas, e ← → têm que seguir o que se vê.
            const NAV = @json($navKeys);
            const ordemNav = () => Array.from(table.querySelectorAll('thead th[data-k]'))
                .map(th => th.dataset.k).filter(k => NAV.includes(k));
            let colKeys = ordemNav();
            const podeGravar = @json($canManage);
            const urlSalvar = id => @json(url('products-admin')) + '/' + id + '/prices';
            const token = document.querySelector('#paCsrf [name=_token]').value;

            let cur = -1;
            let sortKey = null;
            let sortDir = 1;
            let termos = [];
            let celAtual = null;   // célula com tabindex=0 (roving tabindex)

            const visible = () => rows.filter(r => !r.classList.contains('pa-hide'));

            function marcar(novo) {
                const vis = visible();
                if (!vis.length) { cur = -1; return; }
                cur = Math.max(0, Math.min(novo, vis.length - 1));
                rows.forEach(r => r.classList.remove('cur'));
                const alvo = vis[cur];
                alvo.classList.add('cur');
                alvo.scrollIntoView({ block: 'nearest' });
            }

            // ---- máscara de dólar ----------------------------------------------
            // Máscara da direita pra esquerda (centavos): digitar 1-2-3-4 dá
            // 12.34. Assim as 2 casas decimais existem sempre e não há estado
            // inválido pra validar.
            const MAX_CENTS = 99999999999;          // 999,999,999.99

            const centsTxt = txt => parseInt(String(txt).replace(/\D/g, ''), 10) || 0;
            const centsDe  = inp => centsTxt(inp.value);
            const fmtCents = c => (c / 100).toLocaleString('en-US', {
                minimumFractionDigits: 2, maximumFractionDigits: 2,
            });

            // Entrar na célula esvazia o campo: a máscara é da direita pra
            // esquerda, então digitar por cima do valor antigo emendaria os
            // dígitos (1.190,00 + "885" viraria 119.088,50). O número gravado
            // continua à vista no placeholder.
            //
            // Só esvazia célula que está igual ao banco: pendente ou com erro ela
            // guarda o que o usuário digitou e não foi gravado — limpar apagaria
            // o trabalho dele.
            function esvaziar(inp) {
                if (!podeGravar || inp.value !== inp.dataset.orig) return;
                inp.dataset.vazio = '1';
                inp.value = '';
            }

            // Saiu sem digitar nada: a célula foi só visitada (Tab, seta, clique),
            // então volta o valor gravado e nada é enviado.
            function restaurar(inp) {
                delete inp.dataset.vazio;
                inp.value = inp.dataset.orig;
                inp.classList.toggle('zero', centsDe(inp) === 0);
                marcarSujo(inp);
            }

            function caretFim(inp) {
                const n = inp.value.length;
                try { inp.setSelectionRange(n, n); } catch (e) {}
            }

            function porCents(inp, c) {
                c = Math.max(0, Math.min(c, MAX_CENTS));
                inp.value = fmtCents(c);
                inp.classList.toggle('zero', c === 0);
                caretFim(inp);
                marcarSujo(inp);
            }

            // ---- Diferença (Atacado − Médio) -----------------------------------
            // Coluna calculada: não existe no banco e não volta do servidor, então
            // é a tela que a mantém de pé. Usa o valor VIVO do atacado (o que está
            // digitado, mesmo antes de gravar) contra o Médio do ERP — é assim que
            // dá pra ver a margem enquanto se escolhe o preço.
            function atualizaDiff(tr) {
                const td = tr.querySelector('td[data-col="diff"]');
                if (!td) return;

                const span = td.querySelector('.pa-ro');
                const inpAt = tr.querySelector('input.pa-inp[data-col="wholesale_price"]');
                const tdMed = tr.querySelector('td[data-col="average_price"]');
                if (!span || !inpAt || !tdMed) return;

                const atacado = centsDe(inpAt) / 100;
                const medio = parseFloat(tdMed.dataset.v) || 0;

                // Faltando um dos lados não há o que comparar — mesma regra da
                // renderização no servidor.
                if (atacado <= 0 || medio <= 0) {
                    td.dataset.v = '';
                    span.className = 'pa-ro diff nulo';
                    span.textContent = '—';
                    return;
                }

                const d = Math.round((atacado - medio) * 100) / 100;
                td.dataset.v = d;
                span.className = 'pa-ro diff ' + (d > 0 ? 'pos' : (d < 0 ? 'neg' : ''));
                span.textContent = (d < 0 ? '-' : '') + fmtCents(Math.abs(Math.round(d * 100)));
            }

            // Marca visualmente o que saiu do valor original e atualiza o contador
            // de pendentes (o que ainda não foi gravado).
            function marcarSujo(inp) {
                // Toda mudança no atacado passa por aqui (digitação, Esc, resposta
                // do servidor), então é o único lugar que precisa repintar a
                // Diferença da linha.
                if (inp.dataset.col === 'wholesale_price') atualizaDiff(inp.closest('tr'));
                const td = inp.parentElement;
                td.classList.toggle('sujo', inp.value !== inp.dataset.orig);
                if (inp.value !== inp.dataset.orig) td.classList.remove('salvo', 'erro');

                const n = tbody.querySelectorAll('td.pa-cell.sujo').length;
                sujos.textContent = n === 0 ? '' : n + (n === 1 ? ' preço pendente' : ' preços pendentes');
                sujos.classList.toggle('on', n > 0);
            }

            // ---- gravação ------------------------------------------------------
            // Uma célula por request, no commit (sair da célula ou Enter) — nunca
            // a cada tecla. `data-orig` é a verdade do que está no banco: só muda
            // quando o servidor confirma.
            function salvar(inp) {
                if (!podeGravar) return Promise.resolve();
                if (inp.value === inp.dataset.orig) return Promise.resolve();

                const td = inp.parentElement;

                // Já tem request em voo nesta célula: não empilha. Ao terminar,
                // o próprio salvar() refaz a checagem de pendência.
                if (td.dataset.gravando === '1') return Promise.resolve();
                td.dataset.gravando = '1';

                const valor = (centsDe(inp) / 100).toFixed(2);
                td.classList.remove('salvo', 'erro');
                td.classList.add('gravando');

                return fetch(urlSalvar(inp.closest('tr').dataset.id), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({ column: inp.dataset.col, value: valor }),
                })
                    .then(r => r.json().then(d => ({ ok: r.ok, d })))
                    .then(({ ok, d }) => {
                        if (!ok || !d.success) throw new Error(montaErro(d));

                        // O servidor devolve o valor normalizado pelo banco.
                        inp.value = fmtCents(Math.round(parseFloat(d.value) * 100));
                        inp.dataset.orig = inp.value;
                        inp.placeholder = inp.value;
                        inp.classList.toggle('zero', centsDe(inp) === 0);
                        aplicarEspelhos(inp, d.espelhos);

                        td.classList.remove('gravando', 'sujo', 'erro');
                        td.classList.add('salvo');
                        td.removeAttribute('title');
                        setTimeout(() => td.classList.remove('salvo'), 1200);
                    })
                    .catch(e => {
                        // Mantém o valor digitado: o usuário não perde o que fez.
                        td.classList.remove('gravando', 'salvo');
                        td.classList.add('erro');
                        td.title = e.message || 'Falha ao gravar';
                    })
                    .finally(() => {
                        td.dataset.gravando = '0';
                        marcarSujo(inp);
                        // Mudou de novo enquanto gravava: manda a última versão.
                        if (inp.value !== inp.dataset.orig && !td.classList.contains('erro')) salvar(inp);
                    });
            }

            // O atacado grava Promo e Mínimo junto (ver updatePrices): repinta
            // essas células com o que o servidor confirmou, senão elas mostrariam
            // o valor velho até recarregar a tela. Hoje as duas estão fora da
            // grade (`grade => false`) e o loop não acha input nenhum — fica de
            // pé porque voltar a exibi-las é trocar um booleano.
            //
            // Célula que o usuário já mexeu fica de fora — pendente (sujo) ou com
            // request em voo. A resposta chega async: sem essa guarda, o espelho
            // apagaria o que ele está digitando na célula ao lado, e é a edição
            // dele que tem que ganhar.
            function aplicarEspelhos(inp, espelhos) {
                if (!espelhos) return;

                const tr = inp.closest('tr');

                Object.keys(espelhos).forEach(col => {
                    const alvo = tr.querySelector('input.pa-inp[data-col="' + col + '"]');
                    if (!alvo || alvo === inp) return;

                    const td = alvo.parentElement;
                    if (td.dataset.gravando === '1' || td.classList.contains('sujo')) return;

                    const novo = fmtCents(Math.round(parseFloat(espelhos[col]) * 100));
                    alvo.dataset.orig = novo;
                    alvo.placeholder = novo;
                    // Célula aberta e ainda vazia continua vazia: o usuário está
                    // prestes a digitar nela. O focusout replõe com o valor novo.
                    if (alvo.dataset.vazio !== '1') {
                        alvo.value = novo;
                        alvo.classList.toggle('zero', centsTxt(novo) === 0);
                    }

                    td.classList.remove('gravando', 'sujo', 'erro');
                    td.classList.add('salvo');
                    td.removeAttribute('title');
                    setTimeout(() => td.classList.remove('salvo'), 1200);
                    marcarSujo(alvo);
                });
            }

            function montaErro(d) {
                if (d && d.errors) return Object.values(d.errors).flat().join(' ');
                if (d && d.message) return d.message;
                return 'Falha ao gravar';
            }

            // Paste e qualquer mutação fora do keydown caem aqui e são reformatados.
            tbody.addEventListener('input', e => {
                const inp = e.target.closest && e.target.closest('input.pa-inp');
                if (inp) porCents(inp, centsDe(inp));
            });

            // ---- navegação célula a célula (modelo planilha) -------------------
            // Tab anda PRA BAIXO na mesma coluna; as setas ← → andam de lado.
            function focarCelula(tr, col) {
                if (!tr) return;
                const inp = tr.querySelector('input.pa-inp[data-col="' + col + '"]');
                if (!inp) return;
                inp.focus({ preventScroll: true });
                inp.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            }

            // Entra na grade: primeira linha visível, primeira coluna de preço.
            function entrarNaGrade() {
                const vis = visible();
                if (!vis.length) return;
                const tr = cur >= 0 && vis[cur] ? vis[cur] : vis[0];
                focarCelula(tr, celAtual ? celAtual.dataset.col : colKeys[0]);
            }

            // Foco num input (clique, Tab nativo ou programático): roving tabindex,
            // destaque da linha e caret no fim.
            tbody.addEventListener('focusin', e => {
                const inp = e.target.closest && e.target.closest('input.pa-inp');
                if (!inp) return;

                if (celAtual && celAtual !== inp) celAtual.tabIndex = -1;
                inp.tabIndex = 0;
                celAtual = inp;

                const tr = inp.closest('tr');
                rows.forEach(r => r.classList.remove('cur'));
                tr.classList.add('cur');
                cur = visible().indexOf(tr);
                esvaziar(inp);
                caretFim(inp);
            });

            // Sair da célula é o commit: pega Tab, setas, clique fora e perder
            // o foco da página toda.
            tbody.addEventListener('focusout', e => {
                const inp = e.target.closest && e.target.closest('input.pa-inp');
                if (!inp) return;
                if (inp.dataset.vazio === '1') { restaurar(inp); return; }
                salvar(inp);
            });

            tbody.addEventListener('keydown', e => {
                const inp = e.target.closest && e.target.closest('input.pa-inp');
                if (!inp) return;

                const tr  = inp.closest('tr');
                const vis = visible();
                const i   = vis.indexOf(tr);
                const c   = colKeys.indexOf(inp.dataset.col);
                if (i < 0 || c < 0) return;

                // dígitos e apagar: alimentam a máscara
                if (podeGravar) {
                    if (/^[0-9]$/.test(e.key) && !e.ctrlKey && !e.metaKey && !e.altKey) {
                        e.preventDefault();
                        delete inp.dataset.vazio;
                        porCents(inp, centsDe(inp) * 10 + Number(e.key));
                        return;
                    }
                    if (e.key === 'Backspace') {
                        e.preventDefault();
                        // Célula recém-aberta já está vazia — apagar de novo não pode
                        // virar "gravar 0" sem o usuário ter digitado nada.
                        if (inp.dataset.vazio === '1') return;
                        porCents(inp, Math.floor(centsDe(inp) / 10));
                        return;
                    }
                    if (e.key === 'Delete') {
                        e.preventDefault();
                        delete inp.dataset.vazio;
                        porCents(inp, 0);
                        return;
                    }
                }

                // Tab / Shift+Tab: desce (ou sobe) na mesma coluna. No fim da
                // coluna pula pro topo da coluna seguinte.
                if (e.key === 'Tab') {
                    e.preventDefault();
                    const dir = e.shiftKey ? -1 : 1;
                    let ni = i + dir, nc = c;
                    if (ni >= vis.length)   { ni = 0;              nc = (c + 1) % colKeys.length; }
                    else if (ni < 0)        { ni = vis.length - 1; nc = (c - 1 + colKeys.length) % colKeys.length; }
                    focarCelula(vis[ni], colKeys[nc]);
                    return;
                }

                // O caret fica preso no fim (máscara da direita pra esquerda),
                // então ← → ficam livres pra andar entre colunas.
                if (e.key === 'ArrowRight') { e.preventDefault(); focarCelula(tr, colKeys[Math.min(c + 1, colKeys.length - 1)]); return; }
                if (e.key === 'ArrowLeft')  { e.preventDefault(); focarCelula(tr, colKeys[Math.max(c - 1, 0)]); return; }
                if (e.key === 'ArrowDown')  { e.preventDefault(); focarCelula(vis[Math.min(i + 1, vis.length - 1)], inp.dataset.col); return; }
                if (e.key === 'ArrowUp')    { e.preventDefault(); focarCelula(vis[Math.max(i - 1, 0)], inp.dataset.col); return; }
                if (e.key === 'Home')       { e.preventDefault(); focarCelula(vis[0], inp.dataset.col); return; }
                if (e.key === 'End')        { e.preventDefault(); focarCelula(vis[vis.length - 1], inp.dataset.col); return; }

                // Enter confirma e desce na mesma coluna (convenção de planilha).
                // Nunca sai da tela — o focusout acima é quem dispara a gravação.
                if (e.key === 'Enter') {
                    e.preventDefault();
                    focarCelula(vis[Math.min(i + 1, vis.length - 1)], inp.dataset.col);
                    return;
                }

                if (e.key === '/') { e.preventDefault(); input.focus(); input.select(); return; }

                // Esc desfaz a edição da célula (volta ao valor gravado e NÃO
                // dispara request); se ela já está limpa, volta pra busca.
                if (e.key === 'Escape') {
                    e.preventDefault();
                    // Célula só visitada (ainda vazia): nada mudou, então Esc faz o
                    // de sempre — volta pra busca. Quem replóe o valor é o
                    // focusout, ao sair.
                    if (inp.dataset.vazio === '1') { input.focus(); input.select(); return; }
                    if (inp.value !== inp.dataset.orig) {
                        porCents(inp, centsTxt(inp.dataset.orig));
                        inp.parentElement.classList.remove('erro');
                        inp.parentElement.removeAttribute('title');
                        return;
                    }
                    input.focus(); input.select();
                    return;
                }
            });

            // Chips dos termos já fixados + o que está sendo digitado (tracejado).
            function renderTags() {
                tagsBox.textContent = '';

                termos.forEach((termo, i) => {
                    const chip = document.createElement('span');
                    chip.className = 'pa-term';
                    chip.title = 'Clique para remover este termo';
                    chip.appendChild(document.createTextNode(termo));
                    const x = document.createElement('i');
                    x.textContent = '✕';
                    chip.appendChild(x);
                    chip.addEventListener('click', () => {
                        termos.splice(i, 1);
                        renderTags();
                        filtrar();
                        input.focus();
                    });
                    tagsBox.appendChild(chip);
                });

                const vivo = input.value.trim();
                if (vivo !== '') {
                    const chip = document.createElement('span');
                    chip.className = 'pa-term vivo';
                    chip.title = 'Digitando — Enter fixa como termo';
                    chip.appendChild(document.createTextNode(vivo));
                    const k = document.createElement('i');
                    k.textContent = '↵';
                    chip.appendChild(k);
                    tagsBox.appendChild(chip);
                }

                busca.classList.toggle('tem', termos.length > 0 || vivo !== '');
            }

            // Todos os termos precisam bater (AND), igual ao filtro do catálogo.
            function filtrar() {
                const alvos = termos.map(t => t.toLowerCase());
                const vivo = input.value.trim().toLowerCase();
                if (vivo !== '') alvos.push(vivo);

                let n = 0;
                rows.forEach(r => {
                    const texto = r.dataset.busca;
                    // Termo igual ao ID do produto também casa (ex.: "259").
                    const bate = alvos.every(t => texto.includes(t) || t === r.dataset.id);
                    r.classList.toggle('pa-hide', !bate);
                    if (bate) n++;
                });

                counter.textContent = n === rows.length
                    ? rows.length + ' produtos'
                    : n + ' de ' + rows.length + ' produtos';
                rows.forEach(r => r.classList.remove('cur'));
                cur = -1;
            }

            function ordenar(key) {
                if (sortKey === key) { sortDir = -sortDir; } else { sortKey = key; sortDir = 1; }

                const valor = r => {
                    if (key === 'name') return (r.getAttribute('data-v-name') || '').toLowerCase();
                    if (key === 'idx')  return parseFloat(r.getAttribute('data-idx')) || 0;
                    // valor vivo do input: ordenar bate com o que está na tela,
                    // inclusive depois de editar.
                    const inp = r.querySelector('input.pa-inp[data-col="' + key + '"]');
                    if (inp) return centsDe(inp);
                    // Médio e Estoque não têm input — o número cru está no td.
                    const td = r.querySelector('td[data-col="' + key + '"]');
                    return td ? parseFloat(td.dataset.v) || 0 : 0;
                };

                const ordenados = rows.slice().sort((a, b) => {
                    const va = valor(a), vb = valor(b);
                    if (va < vb) return -1 * sortDir;
                    if (va > vb) return 1 * sortDir;
                    return 0;
                });
                ordenados.forEach(r => tbody.appendChild(r));

                table.querySelectorAll('thead th .ar').forEach(s => s.textContent = '');
                const th = table.querySelector('thead th[data-k="' + key + '"] .ar');
                if (th) th.textContent = sortDir === 1 ? '▲' : '▼';

                rows.forEach(r => r.classList.remove('cur'));
                cur = -1;
            }

            // ---- ordem das colunas (arrastar o cabeçalho) ----------------------
            // Só as colunas de número se movem: # e Produto ficam ancoradas à
            // esquerda. Como as móveis são sempre as ÚLTIMAS da linha, reordenar
            // é reanexar th e td na ordem desejada — não há posição a calcular.
            //
            // A ordem é de quem usa a tela (cada um varre o catálogo de um jeito),
            // então fica no localStorage do navegador, não no banco.
            const FIXAS = ['idx', 'name'];
            const CHAVE_COLS = 'pa:produtos:cols';
            const headRow = table.querySelector('thead tr');
            const ORDEM_PADRAO = Array.from(headRow.querySelectorAll('th[data-k]'))
                .map(th => th.dataset.k).filter(k => !FIXAS.includes(k));
            const btReset = document.getElementById('paColsReset');

            let ordemCols = ORDEM_PADRAO.slice();
            let arrastando = null;
            // Soltar a coluna dispara um clique no cabeçalho: sem isso, mover a
            // coluna reordenaria a grade de brinde.
            let arrastou = false;

            // Ordem guardada é dado velho: pode ter coluna que saiu da grade ou
            // não ter uma que entrou depois. Fica só o que existe hoje, e o que
            // faltar entra no fim.
            function sanear(ordem) {
                const vista = ordem.filter(k => ORDEM_PADRAO.includes(k));
                ORDEM_PADRAO.forEach(k => { if (!vista.includes(k)) vista.push(k); });
                return vista;
            }

            const padrao = () => ordemCols.join(',') === ORDEM_PADRAO.join(',');

            function aplicarOrdem() {
                ordemCols.forEach(k => {
                    const th = headRow.querySelector('th[data-k="' + k + '"]');
                    if (th) headRow.appendChild(th);
                    rows.forEach(r => {
                        const td = r.querySelector('td[data-col="' + k + '"]');
                        if (td) r.appendChild(td);
                    });
                });
                // ← → andam pela ordem da tela.
                colKeys = ordemNav();
                if (btReset) btReset.classList.toggle('on', !padrao());
            }

            function gravarOrdem() {
                try {
                    if (padrao()) localStorage.removeItem(CHAVE_COLS);
                    else localStorage.setItem(CHAVE_COLS, JSON.stringify(ordemCols));
                } catch (e) {}
            }

            headRow.querySelectorAll('th[data-k]').forEach(th => {
                th.addEventListener('click', () => {
                    if (arrastou) return;
                    ordenar(th.dataset.k);
                });

                if (FIXAS.includes(th.dataset.k)) return;
                th.draggable = true;

                th.addEventListener('dragstart', e => {
                    arrastando = th.dataset.k;
                    arrastou = true;
                    th.classList.add('pa-drag');
                    try {
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', arrastando);
                    } catch (err) {}
                });

                // A metade do cabeçalho em que o ponteiro está decide se a coluna
                // cai antes ou depois desta — a barra azul mostra onde vai parar.
                const lado = e => e.clientX > th.getBoundingClientRect().left + th.offsetWidth / 2;

                th.addEventListener('dragover', e => {
                    if (arrastando === null || arrastando === th.dataset.k) return;
                    e.preventDefault();
                    try { e.dataTransfer.dropEffect = 'move'; } catch (err) {}
                    const depois = lado(e);
                    th.classList.toggle('pa-dropR', depois);
                    th.classList.toggle('pa-dropL', !depois);
                });

                th.addEventListener('dragleave', () => th.classList.remove('pa-dropL', 'pa-dropR'));

                th.addEventListener('drop', e => {
                    e.preventDefault();
                    th.classList.remove('pa-dropL', 'pa-dropR');
                    const alvo = th.dataset.k;
                    if (arrastando === null || arrastando === alvo) return;

                    const nova = ordemCols.filter(k => k !== arrastando);
                    const i = nova.indexOf(alvo);
                    nova.splice(lado(e) ? i + 1 : i, 0, arrastando);
                    ordemCols = nova;
                    aplicarOrdem();
                    gravarOrdem();
                });

                th.addEventListener('dragend', () => {
                    th.classList.remove('pa-drag');
                    headRow.querySelectorAll('th').forEach(o => o.classList.remove('pa-dropL', 'pa-dropR'));
                    arrastando = null;
                    // Depois do clique que o próprio soltar dispara.
                    setTimeout(() => { arrastou = false; }, 0);
                });
            });

            if (btReset) {
                btReset.addEventListener('click', () => {
                    ordemCols = ORDEM_PADRAO.slice();
                    aplicarOrdem();
                    gravarOrdem();
                });
            }

            try {
                const salva = localStorage.getItem(CHAVE_COLS);
                if (salva) ordemCols = sanear(JSON.parse(salva));
            } catch (e) {}
            aplicarOrdem();

            input.addEventListener('input', () => { renderTags(); filtrar(); });

            input.addEventListener('keydown', e => {
                // Alt+Enter: sai da busca e cai na grade de preços.
                if (e.key === 'Enter' && e.altKey) {
                    e.preventDefault();
                    entrarNaGrade();
                    return;
                }

                if (e.key === 'Enter') {
                    e.preventDefault();
                    const valor = input.value.trim();

                    // Campo vazio: Enter só joga o foco na grade (igual Alt+Enter).
                    if (valor === '') {
                        entrarNaGrade();
                        return;
                    }

                    if (!termos.some(t => t.toLowerCase() === valor.toLowerCase())) termos.push(valor);
                    input.value = '';
                    renderTags();
                    filtrar();
                    return;
                }
                // Backspace com o campo vazio apaga o último termo fixado.
                if (e.key === 'Backspace' && input.value === '' && termos.length > 0) {
                    e.preventDefault();
                    termos.pop();
                    renderTags();
                    filtrar();
                }
            });

            clearBt.addEventListener('click', () => {
                termos = [];
                input.value = '';
                renderTags();
                filtrar();
                input.focus();
            });

            rows.forEach(r => {
                r.addEventListener('click', () => {
                    const vis = visible();
                    marcar(vis.indexOf(r));
                });
            });

            document.addEventListener('keydown', e => {
                const foco = document.activeElement;

                // Foco dentro da grade: quem manda é o handler do tbody.
                if (foco && foco.classList && foco.classList.contains('pa-inp')) {
                    if (e.key === '/') { e.preventDefault(); input.focus(); input.select(); }
                    return;
                }

                const digitando = /^(INPUT|TEXTAREA|SELECT)$/.test(foco.tagName);

                if (e.key === '/' && !digitando) { e.preventDefault(); input.focus(); input.select(); return; }

                // Alt+Enter leva pra grade de qualquer lugar da página.
                if (e.key === 'Enter' && e.altKey) { e.preventDefault(); entrarNaGrade(); return; }

                if (e.key === 'Escape' && document.activeElement === input) {
                    if (input.value) { input.value = ''; renderTags(); filtrar(); }
                    else if (termos.length) { termos = []; renderTags(); filtrar(); }
                    else { input.blur(); }
                    return;
                }

                if (digitando && document.activeElement !== input) return;

                if (e.key === 'ArrowDown' || (e.key === 'j' && !digitando)) { e.preventDefault(); marcar(cur + 1); return; }
                if (e.key === 'ArrowUp'   || (e.key === 'k' && !digitando)) { e.preventDefault(); marcar(cur - 1); return; }

                // Enter com a busca focada é tratado no handler do próprio input.
                if (e.key === 'Enter' && foco !== input) {
                    e.preventDefault();
                    entrarNaGrade();
                }
            });

            renderTags();
            filtrar();

            // Deixa a primeira célula de preço no fluxo do Tab, pra grade ser
            // alcançável sem atalho.
            const primeira = visible()[0];
            if (primeira) {
                const inp = primeira.querySelector('input.pa-inp[data-col="' + colKeys[0] + '"]');
                if (inp) { inp.tabIndex = 0; celAtual = inp; }
            }
        })();
    </script>
@endpush
