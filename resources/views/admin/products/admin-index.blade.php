@extends('layouts.app')

@section('title', 'Produtos · Preços')

@section('content')
    @php
        $canManage = auth()->user()->hasModule('products.manage');
    @endphp

    <div class="page-content">
        <div class="container-fluid">
            @include('admin.products.partials.style-admin')

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
                        </div>
                    </div>
                </div>

                <div class="pa-chips">
                    <span class="pa-flab">Situação</span>
                    @foreach (['1' => 'Ativos', '0' => 'Inativos', '' => 'Todos'] as $v => $label)
                        <a href="{{ route('products-admin.index', ['active' => $v, 'brand' => $brand, 'atacado' => $atacado]) }}"
                            class="pa-chip {{ (string) $active === (string) $v ? 'on' : '' }}"
                            style="text-decoration:none">{{ $label }}</a>
                    @endforeach

                    <span class="pa-flab" style="margin-left:10px">Atacado</span>
                    @foreach (['1' => 'Com preço', '0' => 'Sem preço', '' => 'Mostrar todos'] as $v => $label)
                        <a href="{{ route('products-admin.index', ['active' => $active, 'brand' => $brand, 'atacado' => $v]) }}"
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
                                <th class="pa-idx" scope="col" data-k="idx" title="Ordenar pela posição original">#</th>
                                <th scope="col" data-k="name">Produto <span class="ar"></span></th>
                                @foreach ($cols as $key => $col)
                                    <th class="r {{ $col['tom'] === 'pri' ? 'pri' : '' }}" scope="col"
                                        data-k="{{ $key }}" style="width:104px">{{ $col['label'] }} <span class="ar"></span></th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $i => $product)
                                <tr class="pa-row {{ $product->active ? '' : 'off' }}"
                                    data-id="{{ $product->id }}"
                                    data-idx="{{ $i + 1 }}"
                                    data-busca="{{ mb_strtolower($product->name . ' ' . $product->short_name . ' ' . ($product->brand->name ?? '')) }}"
                                    data-v-name="{{ $product->name }}">
                                    <td class="pa-idx">{{ $i + 1 }}</td>
                                    <td>
                                        <span class="pa-name">{{ $product->name }}</span>
                                        @if ($product->brand)
                                            <span class="pa-brand">{{ $product->brand->name }}</span>
                                        @endif
                                        @unless ($product->active)
                                            <span class="pa-pill bad" title="Produto inativo">inativo</span>
                                        @endunless
                                    </td>
                                    @foreach ($cols as $key => $col)
                                        @php
                                            $val = (float) $product->{$key};
                                            $fmt = number_format($val, 2, '.', ',');
                                            // Tom e "zerado" são classes independentes: ao digitar
                                            // numa célula zerada o JS só tira `zero`, e o tom da
                                            // coluna volta sozinho.
                                            $tom = trim($col['tom'] . ($val <= 0 ? ' zero' : ''));
                                        @endphp
                                        <td class="pa-cell" data-col="{{ $key }}">
                                            <span class="pa-cur">$</span>
                                            <input type="text" class="pa-inp {{ $tom }}" value="{{ $fmt }}"
                                                data-orig="{{ $fmt }}" data-col="{{ $key }}"
                                                inputmode="decimal" autocomplete="off" spellcheck="false" tabindex="-1"
                                                @unless ($canManage) readonly @endunless>
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($cols) + 2 }}">
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
            const colKeys = @json(array_keys($cols));
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

            // Marca visualmente o que saiu do valor original e atualiza o contador
            // de pendentes (o que ainda não foi gravado).
            function marcarSujo(inp) {
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
                        inp.classList.toggle('zero', centsDe(inp) === 0);

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
                caretFim(inp);
            });

            // Sair da célula é o commit: pega Tab, setas, clique fora e perder
            // o foco da página toda.
            tbody.addEventListener('focusout', e => {
                const inp = e.target.closest && e.target.closest('input.pa-inp');
                if (inp) salvar(inp);
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
                        porCents(inp, centsDe(inp) * 10 + Number(e.key));
                        return;
                    }
                    if (e.key === 'Backspace') { e.preventDefault(); porCents(inp, Math.floor(centsDe(inp) / 10)); return; }
                    if (e.key === 'Delete')    { e.preventDefault(); porCents(inp, 0); return; }
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
                    const bate = alvos.every(t => texto.includes(t));
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
                    return inp ? centsDe(inp) : 0;
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

            table.querySelectorAll('thead th[data-k]').forEach(th => {
                th.addEventListener('click', () => ordenar(th.dataset.k));
            });

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
