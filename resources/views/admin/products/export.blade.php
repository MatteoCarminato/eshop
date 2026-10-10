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
                    <span class="pa-flab">Preço</span>
                    <button type="button" class="pa-chip on" id="paComPreco" data-modo="com">Com preço</button>
                    <button type="button" class="pa-chip" id="paSemPreco" data-modo="sem">Sem preço</button>

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
                                    <b>{{ count($grupo['produtos']) }}</b>
                                </label>
                                <div class="pa-sg-itens">
                                    @foreach ($grupo['produtos'] as $produto)
                                        <div class="pa-item">
                                            <span>{{ $produto['nome'] }}</span>
                                            @if ($produto['preco'] <= 0)
                                                <span class="pa-pill al" title="Sem PREATAC no ERP — sairia como U$ 0,00">sem atacado</span>
                                            @endif
                                            <i>{{ number_format($produto['preco'], 2, ',', '.') }}</i>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>

                        {{-- Direita: exatamente o texto que vai pra área de transferência --}}
                        <div class="pa-exp-prev">
                            <div class="pa-exp-head">
                                <span class="pa-flab">Pré-visualização · atacado</span>
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

            const grupos = @json($grupos);
            const lista = document.getElementById('paLista');
            const btCopiar = document.getElementById('paCopiar');
            const resumo = document.getElementById('paResumo');
            const linhas = document.getElementById('paLinhas');
            const btCom = document.getElementById('paComPreco');
            const btSem = document.getElementById('paSemPreco');

            let comPreco = true;

            // U$ 1.190,00 — formato pt-BR (ponto no milhar, vírgula nos centavos),
            // que é como a lista é lida do outro lado.
            const fmtPreco = v => 'U$ ' + v.toLocaleString('pt-BR', {
                minimumFractionDigits: 2, maximumFractionDigits: 2,
            });

            const marcados = () => Array.from(lista.querySelectorAll('.pa-sg-chk'))
                .filter(c => c.checked)
                .map(c => grupos[Number(c.dataset.i)]);

            function montarTexto() {
                // Cada bloco é "subgrupo + itens"; os blocos são separados por
                // duas linhas em branco.
                return marcados().map(g => {
                    const itens = g.produtos.map(p => comPreco ? `${p.nome} ${fmtPreco(p.preco)}` : p.nome);
                    return [g.nome, ...itens].join('\n');
                }).join('\n\n\n');
            }

            function render() {
                const texto = montarTexto();
                saida.textContent = texto;

                const sel = marcados();
                const nProdutos = sel.reduce((t, g) => t + g.produtos.length, 0);
                resumo.textContent = sel.length === 0
                    ? 'nenhum subgrupo selecionado'
                    : `${sel.length} de ${grupos.length} subgrupos · ${nProdutos} produtos`;
                linhas.textContent = texto === '' ? '' : texto.split('\n').length + ' linhas';

                btCopiar.disabled = texto === '';
                btCopiar.classList.toggle('dim', texto === '');
            }

            function setModo(novo) {
                comPreco = novo;
                btCom.classList.toggle('on', comPreco);
                btSem.classList.toggle('on', !comPreco);
                render();
            }

            btCom.addEventListener('click', () => setModo(true));
            btSem.addEventListener('click', () => setModo(false));

            lista.addEventListener('change', e => {
                if (e.target.classList.contains('pa-sg-chk')) render();
            });

            document.getElementById('paTodos').addEventListener('click', () => {
                lista.querySelectorAll('.pa-sg-chk').forEach(c => c.checked = true);
                render();
            });

            document.getElementById('paNenhum').addEventListener('click', () => {
                lista.querySelectorAll('.pa-sg-chk').forEach(c => c.checked = false);
                render();
            });

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
