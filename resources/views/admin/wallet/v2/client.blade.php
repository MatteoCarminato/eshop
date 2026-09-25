@extends('layouts.app')
@section('title', 'Carteira · v2')
@section('content')
    @php
        $canViewPnl = auth()->user()->hasModule('wallet.pnl.view');
        $custoMedioCli = $treasuryClientSummary['custo_medio_cliente'] ?? null;
        $caixaCliente = (float) ($treasuryClientSummary['usd_em_caixa_cliente'] ?? 0);
        $hideSummaryCards = true;
    @endphp
    <div class="page-content wallet-compact">
        <div class="container-fluid">
            @include('admin.wallet.v2.partials.style')

            {{-- Grade densa (estilo planilha) só na tabela de Entrada — as outras duas
                 tabelas (Saída U$ / Entrada U$) ficam com o visual clássico. --}}
            <style>
                #tabela_entrada_brl_scroll{
                    max-height:70vh;overflow:auto;border:1px solid #e0e5ec;border-radius:6px;
                    box-shadow:0 1px 2px rgba(14,22,34,.05),0 0 0 1px rgba(14,22,34,.03);
                }
                #tabela_entrada_brl{border-collapse:separate;border-spacing:0;font-size:12px;font-variant-numeric:tabular-nums;margin-bottom:0}
                #tabela_entrada_brl thead th{
                    position:sticky;top:0;z-index:2;background:#e2e8ef;border-bottom:1px solid #c6d0da;
                    font-size:9px;letter-spacing:.1em;text-transform:uppercase;color:#44515f;font-weight:700;
                    padding:8px 9px;white-space:nowrap;vertical-align:middle;
                    box-shadow:inset 0 1px 0 #fff,0 1px 0 rgba(14,22,34,.07);
                }
                #tabela_entrada_brl tbody td{padding:6px 9px;border-bottom:1px solid #f2f5f8;vertical-align:middle}
                #tabela_entrada_brl tbody tr:not(.table-warning):not(.table-light):not(.table-info-pronto):not(.table-pre-purchased):not(.table-pre-sold):hover td{
                    background:#f8fafd;
                }
                #tabela_entrada_brl .badge{border-radius:99px;font-size:8.5px;letter-spacing:.05em;text-transform:uppercase;padding:2px 8px;font-weight:700}
                #tabela_entrada_brl tfoot td{background:#f7f9fb;font-weight:700;border-top:2px solid #e0e5ec}

                /* Painel "Seleção da Entrada" no mesmo tema dos cartões do topo (colunas
                   separadas por linha vertical, rótulo pequeno em maiúsculas, valor grande). */
                #entrada_selection_row .card{
                    border:1px solid #e0e5ec;border-radius:6px;
                    box-shadow:0 1px 2px rgba(14,22,34,.05),0 0 0 1px rgba(14,22,34,.03);
                }
                #entrada_selection_row .card-header{
                    background:#fff;border-bottom:1px solid #e0e5ec;padding:8px 14px;
                }
                #entrada_selection_row .card-header h6{color:#0f141a;font-size:11px;letter-spacing:.04em}
                #entrada_selection_row .card-header i{color:#737e8a}
                #entrada_selection_row .card-body{padding:0}
                #entrada_selection_row .row.g-3{margin:0;--bs-gutter-x:0}
                #entrada_selection_row .row.g-3 > [class*="col-"]{
                    border-right:1px solid #eaeef3;padding:10px 14px !important;text-align:left !important;
                }
                #entrada_selection_row .row.g-3 > [class*="col-"]:last-child{border-right:0}
                #entrada_selection_row .text-uppercase.small,
                #entrada_selection_row .text-uppercase.text-muted{
                    font-size:8px;letter-spacing:.12em;color:#737e8a !important;font-weight:700;margin-bottom:4px !important;
                }
                #entrada_selection_row .fs-5.fw-bold{
                    font-family:ui-monospace,"SF Mono","Cascadia Mono","Roboto Mono",Menlo,Consolas,monospace;
                    font-size:16px;letter-spacing:-.02em;color:#0f141a;
                }
                #entrada_selection_row #entrada_red_summary{
                    background:#fdedee;border:0;border-top:1px solid #f3cdd0;border-radius:0;padding:0;margin:0 !important;
                }
                #entrada_selection_row #entrada_red_summary .row{margin:0;--bs-gutter-x:0}
                #entrada_selection_row #entrada_red_summary [class*="col-"]{
                    border-right:1px solid #f3cdd0;padding:8px 14px !important;text-align:left !important;
                }
                #entrada_selection_row #entrada_red_summary [class*="col-"]:last-child{border-right:0}
                #entrada_selection_row #entrada_red_summary .fw-bold{
                    font-family:ui-monospace,"SF Mono","Cascadia Mono","Roboto Mono",Menlo,Consolas,monospace;
                    color:#a11f26;
                }
            </style>

            <div class="wv2 mb-3">
                <div class="wv2-head">
                    <div class="wv2-crumb">
                        <a href="{{ route('dashboard') }}">Dashboard</a><span>›</span>
                        <a href="{{ route('admin.wallet.index.v2') }}">Carteira</a><span>›</span>
                        <b>{{ $client->name }}</b><span>·</span><b>v2</b>
                    </div>
                    <div class="wv2-title">
                        <h1>{{ $client->name }}</h1>
                        <div class="wv2-toolbar">
                            <a href="{{ route('admin.wallet.client', $client) }}" class="wv2-act ghost">Tela clássica</a>
                            <a href="{{ route('admin.wallet.index.v2') }}" class="wv2-act ghost">← Todos os clientes</a>
                        </div>
                    </div>
                </div>

                <div class="wv2-stats">
                    <div>
                        <div class="l">Saldo BRL</div>
                        <div class="v {{ $balances['BRL'] < 0 ? 'neg' : 'pos' }}">
                            {{ $balances['BRL'] < 0 ? '-' : '' }}R$ {{ number_format(abs($balances['BRL']), 2, ',', '.') }}
                        </div>
                    </div>
                    <div>
                        <div class="l">Saldo USD</div>
                        <div class="v {{ ($balances['USD'] ?? 0) < 0 ? 'neg' : '' }}" style="color:{{ ($balances['USD'] ?? 0) < 0 ? '' : 'var(--wv2-accent)' }}">
                            {{ ($balances['USD'] ?? 0) < 0 ? '-' : '' }}US$ {{ number_format(abs($balances['USD'] ?? 0), 2, ',', '.') }}
                        </div>
                    </div>
                    <div>
                        <div class="l">Devo ao cliente</div>
                        <div class="v {{ $devoAoCliente > 0 ? 'neg' : '' }}">R$ {{ number_format($devoAoCliente, 2, ',', '.') }}</div>
                        @if($devoAoClienteRaw < 0)
                            <div class="hint">Bruto negativo: -R$ {{ number_format(abs($devoAoClienteRaw), 2, ',', '.') }}</div>
                        @endif
                    </div>
                    <div>
                        <div class="l">USD pré-comprado</div>
                        <div class="v">US$ {{ number_format($prePurchaseSummary['usd_pre_comprado'] ?? 0, 2, ',', '.') }}</div>
                        @if(($prePurchaseSummary['taxa_media'] ?? null))
                            <div class="hint">Taxa média: {{ number_format($prePurchaseSummary['taxa_media'], 4, ',', '.') }}</div>
                        @endif
                    </div>
                    <div>
                        <div class="l">USD pré-vendido</div>
                        <div class="v">US$ {{ number_format($preSellSummary['usd_pre_vendido'] ?? 0, 2, ',', '.') }}</div>
                        @if(($preSellSummary['taxa_media'] ?? null))
                            <div class="hint">Taxa média: {{ number_format($preSellSummary['taxa_media'], 4, ',', '.') }}</div>
                        @endif
                    </div>
                    @if($canViewPnl)
                        <div>
                            <div class="l">Caixa USD (cliente)</div>
                            <div class="v">US$ {{ number_format($caixaCliente, 2, ',', '.') }}</div>
                            @if($custoMedioCli)
                                <div class="hint">Custo médio: {{ number_format($custoMedioCli, 4, ',', '.') }}</div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            @include('admin.wallet.partials.client-body')
        </div>
    </div>
@endsection
