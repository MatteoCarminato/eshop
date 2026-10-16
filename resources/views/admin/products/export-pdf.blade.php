{{--
    Catálogo em PDF gerado por ProductAdminController@exportPdf (mPDF, A4
    retrato). Porte do template `catalogo_eshop.html`: papel branco, hero
    escuro com faixa azul, grade de 4 cards e selo azul de página no rodapé.

    HTML escrito pro mPDF, não pro navegador: o flex/grid do template vira
    tabela (o mPDF não tem nenhum dos dois), as sombras saem e as medidas são
    em mm. Hero e rodapé são `htmlpageheader`/`htmlpagefooter`, então se
    repetem em toda página — é no hero que fica o WhatsApp do vendedor.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            header: html_topo;
            footer: html_rodape;
        }

        body {
            background-color: #ffffff;
            color: #111827;
            font-family: dejavusans, sans-serif;
            font-size: 8pt;
        }

        /* ---- hero (cabeçalho de todas as páginas) ---- */
        .hero { width: 100%; background-color: #141b27; }
        .hero td { vertical-align: middle; padding: 5mm 10mm 4.5mm 10mm; }
        .sobrancelha {
            font-size: 6pt;
            font-weight: bold;
            color: #93b8ff;
            letter-spacing: 1.8pt;
        }
        .hero-tit {
            font-size: 19pt;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 1.2pt;
            padding-top: 1mm;
        }
        .hero-sub {
            font-size: 6.5pt;
            color: #c4cfdf;
            letter-spacing: 0.5pt;
            padding-top: 1.2mm;
        }
        .contato {
            font-size: 10pt;
            font-weight: bold;
            color: #ffffff;
            padding-top: 2.2mm;
        }
        .hero-nota {
            font-size: 6.5pt;
            color: #b8c6da;
            text-align: right;
            letter-spacing: 0.8pt;
            line-height: 1.8;
        }
        .hero-nota b { color: #ffffff; }
        /* Faixa azul no pé do hero (o ::after do template). */
        .hero-risco { width: 100%; background-color: #246bfe; }
        .hero-risco td { padding: 0; font-size: 1pt; line-height: 1.3mm; }

        /* ---- rodapé de todas as páginas ---- */
        .rodape { width: 100%; }
        .rodape td {
            border-top: 0.2mm solid #e5eaf2;
            padding: 3mm 10mm 0 10mm;
            font-size: 6pt;
            color: #8190a5;
            letter-spacing: 0.5pt;
            vertical-align: middle;
        }
        .rodape b { color: #25344b; }
        .selo-pag { background-color: #246bfe; border-radius: 1.6mm; }
        .selo-pag td {
            border: none;
            padding: 1.8mm 3mm;
            color: #ffffff;
            font-weight: bold;
            letter-spacing: 1.5pt;
            text-align: center;
        }
        .miudo {
            font-size: 6pt;
            color: #9aa7b8;
            text-align: center;
            padding-top: 2.5mm;
        }

        /* ---- abertura (uma vez, no topo do catálogo) ---- */
        .intro { width: 100%; }
        .intro td { vertical-align: bottom; padding: 0 0 4mm 0; }
        .intro-tit { font-size: 12pt; font-weight: bold; color: #111827; }
        .intro-sub { font-size: 7.5pt; color: #64748b; padding-top: 1.5mm; }
        .contador {
            background-color: #f1f5fb;
            border: 0.2mm solid #e4eaf3;
            border-radius: 6mm;
        }
        .contador td {
            padding: 2mm 3.5mm;
            font-size: 6.5pt;
            font-weight: bold;
            color: #42536c;
            letter-spacing: 0.8pt;
            text-align: center;
        }

        /* ---- card ---- */
        .card {
            width: 100%;
            background-color: #ffffff;
            border: 0.2mm solid #e5eaf2;
            border-radius: 2.2mm;
        }
        .card td { padding: 0 3mm; }
        .cel-cheia { padding: 0 !important; }

        .card-topo { width: 100%; background-color: #f8faff; }
        /* Altura fixa: "MACBOOK AIR 13 M5 16GB/512" quebra em duas linhas e
           "CABO" não — sem isso os cards da mesma linha ficariam de alturas
           diferentes e os preços desalinhados. */
        .card-topo td {
            padding: 2mm 3mm !important;
            border-bottom: 0.2mm solid #e5eaf2;
            vertical-align: middle;
        }
        .modelo {
            font-size: 6.5pt;
            font-weight: bold;
            color: #205de0;
            letter-spacing: 0.8pt;
        }
        .etiqueta {
            font-size: 5.5pt;
            font-weight: bold;
            color: #64748b;
            letter-spacing: 0.7pt;
            text-align: right;
        }

        .foto { width: 100%; background-color: #fbfcfe; }
        .foto td {
            padding: 2mm;
            text-align: center;
            vertical-align: middle;
            border: none;
            /* Sem entrelinha: a foto sozinha na célula ganhava uns pontos de
               leading e deixava o card mais alto que os vizinhos da linha. */
            line-height: 1;
        }
        .placeholder {
            font-size: 13pt;
            font-weight: bold;
            color: #d3dce9;
            letter-spacing: 1.5pt;
        }

        .cor {
            font-size: 8pt;
            font-weight: bold;
            color: #27364b;
            letter-spacing: 0.5pt;
            padding-top: 3mm !important;
            padding-bottom: 2mm !important;
            vertical-align: top;
        }
        /* Preço como tabela: no mPDF só a tabela ocupa a largura toda da
           célula — um div pinta o fundo apenas atrás do texto. */
        .preco { width: 100%; background-color: #246bfe; border-radius: 1.6mm; }
        .preco td { padding: 2.4mm 0; text-align: center; }
        .preco .moeda { font-size: 6.5pt; color: #d7e4ff; }
        .preco .valor { font-size: 11.5pt; font-weight: bold; color: #ffffff; }
        .sem-preco { width: 100%; background-color: #f1f5fb; border: 0.2mm dashed #cbd6e6; border-radius: 1.6mm; }
        .sem-preco td {
            padding: 3.2mm 0;
            text-align: center;
            font-size: 7pt;
            font-weight: bold;
            color: #8190a5;
            letter-spacing: 0.8pt;
        }
        .cel-meta { padding-bottom: 3mm !important; }
        .meta { width: 100%; }
        .meta td {
            padding: 2.4mm 0 0 0;
            border-top: 0.2mm solid #e5eaf2;
            font-size: 6pt;
            color: #7a899e;
            letter-spacing: 0.8pt;
        }
        .meta .sku { text-align: right; font-weight: bold; color: #53647c; font-size: 7pt; }
    </style>
</head>
<body>

<htmlpageheader name="topo">
    <table class="hero">
        <tr>
            <td width="24%">
                @if (file_exists($logo))
                    <img src="{{ $logo }}" height="38">
                @endif
            </td>
            <td width="44%">
                <div class="sobrancelha">SELEÇÃO DE PRODUTOS</div>
                <div class="hero-tit">CATÁLOGO</div>
                <div class="hero-sub">PREÇOS DE ATACADO · PRONTA ENTREGA</div>
                {{-- O WhatsApp é o canal do pedido, então vai no alto de toda página. --}}
                <div class="contato">
                    @if (file_exists($wppIcone))
                        <img src="{{ $wppIcone }}" height="14">
                    @endif
                    {{ $whatsapp }}
                </div>
            </td>
            <td width="32%" class="hero-nota" nowrap>
                PRODUTOS ORIGINAIS<br>
                <b>ATUALIZADO EM {{ $gerado->format('d/m/Y') }}</b>
            </td>
        </tr>
    </table>
    <table class="hero-risco">
        <tr><td></td></tr>
    </table>
</htmlpageheader>

<htmlpagefooter name="rodape">
    <table class="rodape">
        <tr>
            <td width="46%" nowrap><b>eShop</b> · PRODUTOS ORIGINAIS E DE ALTA QUALIDADE</td>
            <td width="30%" nowrap>CATÁLOGO ATACADISTA</td>
            <td width="24%">
                <table class="selo-pag" align="right">
                    <tr><td>PÁGINA {PAGENO}</td></tr>
                </table>
            </td>
        </tr>
    </table>
    <div class="miudo">Valores e disponibilidade sujeitos a confirmação no momento do pedido.</div>
</htmlpagefooter>

<table class="intro">
    <tr>
        <td width="68%">
            <div class="intro-tit">Produtos em destaque</div>
            <div class="intro-sub">Seleção de modelos e acessórios disponíveis para consulta.</div>
        </td>
        <td width="32%">
            <table class="contador" align="right">
                <tr><td>{{ $total }} {{ $total === 1 ? 'ITEM' : 'ITENS' }} NESTE CATÁLOGO</td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- Grade de 4 cards por linha, como no template. O subgrupo é o "modelo" no
     cabeçalho do card (é ele que diz modelo/capacidade) e a marca é a etiqueta
     à direita; o nome do produto costuma ser só a cor. --}}
<table class="grade">
    @foreach ($itens->chunk(4) as $linha)
        <tr>
            @foreach ($linha as $produto)
                <td width="25%" style="padding: 0 1.5mm 5mm 1.5mm; vertical-align: top;">
                    <table class="card" width="100%">
                        <tr>
                            <td class="cel-cheia">
                                <table class="card-topo" width="100%">
                                    <tr>
                                        <td width="62%" class="modelo" height="38">{{ mb_strtoupper($produto['rotulo']) }}</td>
                                        <td width="38%" class="etiqueta">{{ mb_strtoupper($produto['etiqueta']) }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td class="cel-cheia">
                                {{-- Altura fixa: com ou sem foto, todos os cards da
                                     linha terminam no mesmo lugar. --}}
                                <table class="foto" width="100%">
                                    <tr>
                                        <td height="110">
                                            @if ($produto['foto'])
                                                <img src="{{ $produto['foto'] }}" height="90">
                                            @else
                                                <span class="placeholder">eShop</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td class="cor" height="24">{{ mb_strtoupper($produto['nome']) }}</td>
                        </tr>
                        @if ($mostraPreco)
                            <tr>
                                <td>
                                    @if ($produto['preco'] > 0)
                                        <table class="preco" width="100%">
                                            <tr>
                                                <td>
                                                    <span class="moeda">US$&nbsp;</span><span class="valor">{{ number_format($produto['preco'], 2, ',', '.') }}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    @else
                                        <table class="sem-preco" width="100%">
                                            <tr><td>CONSULTE</td></tr>
                                        </table>
                                    @endif
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td class="cel-meta">
                                <table class="meta" width="100%">
                                    <tr>
                                        <td>{{ $mostraEstoque ? 'EST. ' . number_format($produto['estoque'], 0, ',', '.') : 'CÓDIGO' }}</td>
                                        <td class="sku">{{ $produto['codigo'] ?? '—' }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            @endforeach

            {{-- Completa a linha pra última fileira não esticar os cards. --}}
            @for ($i = count($linha); $i < 4; $i++)
                <td width="25%" style="padding: 0 1.5mm 5mm 1.5mm;"></td>
            @endfor
        </tr>
    @endforeach
</table>

</body>
</html>
