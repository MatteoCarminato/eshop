{{--
    Catálogo em PDF gerado por ProductAdminController@exportPdf (mPDF, A4
    retrato). Porte fiel do template `catalogo_eshop.html`: hero escuro com
    brilho azul, grade de 4 cards arredondados e rodapé com selo de página.

    Escrito pro mPDF, não pro navegador, e aí vale a regra que mandou no
    layout: dentro de célula de tabela o mPDF NÃO pinta fundo nem borda de
    div (e tabela não aceita border-radius). Por isso os cards são `div`
    flutuantes na raiz do documento — é a única forma de ter canto
    arredondado — e tabela só aparece onde é de fato duas colunas de texto.
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
            /* Condensed: é a fonte do mPDF que mais se aproxima da Inter do
               template e ainda dá conta dos nomes longos dentro do card. */
            font-family: dejavusanscondensed, sans-serif;
            font-size: 8pt;
        }

        /* ---- hero (cabeçalho de todas as páginas) ---- */
        .hero {
            background-image: radial-gradient(ellipse at 88% 0%, #2b5ba8 0%, #141c29 58%);
            padding: 4.5mm 7mm 4mm 7mm;
        }
        .hero-logo { vertical-align: middle; }
        .sobrancelha {
            font-size: 6pt;
            font-weight: bold;
            color: #93b8ff;
            letter-spacing: 1.9pt;
        }
        .hero-tit {
            font-size: 20pt;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 1.4pt;
            padding-top: 0.8mm;
        }
        .hero-sub {
            font-size: 7pt;
            color: #c4cfdf;
            letter-spacing: 1.3pt;
            padding-top: 1.2mm;
        }
        .contato {
            font-size: 10.5pt;
            font-weight: bold;
            color: #ffffff;
            padding-top: 2mm;
        }
        .hero-nota {
            font-size: 6.5pt;
            color: #b8c6da;
            text-align: right;
            letter-spacing: 1.1pt;
            line-height: 1.7;
            vertical-align: middle;
        }
        .hero-nota b { color: #ffffff; letter-spacing: 1.4pt; }
        /* Faixa azul no pé do hero (o ::after do template). */
        .hero-faixa {
            background-color: #246bfe;
            height: 1.1mm;
            font-size: 1pt;
            line-height: 1.1mm;
        }

        /* ---- rodapé de todas as páginas ---- */
        .rodape {
            border-top: 0.2mm solid #e5eaf2;
            padding: 3mm 7mm 0 7mm;
            font-size: 6.5pt;
            color: #8190a5;
            letter-spacing: 1.1pt;
        }
        .rodape b { color: #25344b; }
        /* Posicionado por margem, e não por float: dentro de htmlpagefooter
           o mPDF simplesmente não desenha elemento flutuante. */
        .selo-pag {
            width: 22mm;
            margin: -7mm 0 0 160mm;
            background-color: #246bfe;
            border-radius: 2.1mm;
            padding: 2mm 3mm;
            color: #ffffff;
            font-size: 6.5pt;
            font-weight: bold;
            letter-spacing: 1.5pt;
            text-align: center;
        }
        .miudo {
            font-size: 6pt;
            color: #9aa7b8;
            text-align: center;
            padding-top: 3mm;
        }

        /* ---- abertura (uma vez, no topo do catálogo) ---- */
        .intro-tit { font-size: 13pt; font-weight: bold; color: #111827; }
        .intro-sub { font-size: 8pt; color: #64748b; padding-top: 1.5mm; }
        .contador {
            float: right;
            width: 44mm;
            text-align: center;
            background-color: #f1f5fb;
            border: 0.2mm solid #e4eaf3;
            border-radius: 5mm;
            padding: 2.2mm 4mm;
            font-size: 6.5pt;
            font-weight: bold;
            color: #42536c;
            letter-spacing: 0.9pt;
        }

        /* ---- card (div flutuante: é o que permite canto arredondado) ---- */
        .card {
            float: left;
            page-break-inside: avoid;
            width: 42.6mm;
            margin: 0 1.3mm 2.6mm 1.3mm;
            background-color: #ffffff;
            border: 0.2mm solid #e5eaf2;
            border-radius: 3.4mm;
        }
        /* Primeiro card da linha recuado: alinha a grade com o texto da
           abertura e deixa a sobra igual nos dois lados. */
        .card.inicio { margin-left: 5.4mm; }

        .card-topo {
            background-color: #f8faff;
            border-bottom: 0.2mm solid #e5eaf2;
            border-radius: 3.4mm 3.4mm 0 0;
            padding: 2.6mm 3.2mm;
        }
        .modelo {
            font-size: 6.5pt;
            font-weight: bold;
            color: #205de0;
            letter-spacing: 0.9pt;
            vertical-align: middle;
        }
        .etiqueta {
            font-size: 6pt;
            font-weight: bold;
            color: #64748b;
            letter-spacing: 0.7pt;
            text-align: right;
            vertical-align: middle;
        }

        .card-corpo { padding: 3mm; }
        /* Branca, como no template: a foto de produto já vem com fundo
           branco, e um painel cinza deixaria um quadrado claro no meio dele.
           Sem foto, quem pinta o bloco é o .placeholder. */
        .foto {
            height: 19mm;
            background-color: #ffffff;
            border-radius: 2.1mm;
            text-align: center;
            /* Entrelinha curta: a foto sozinha no bloco ganhava leading e
               deixava o card mais alto que os vizinhos da linha. */
            line-height: 1;
            padding: 2mm 0;
        }
        .placeholder {
            background-color: #f3f6fb;
            border-radius: 2.1mm;
            font-size: 13pt;
            font-weight: bold;
            color: #d9e0ea;
            letter-spacing: 1.4pt;
            /* Mesma altura da área de foto (img de 64px): sem isso o card sem
               foto fica mais baixo que o vizinho que tem. */
            padding: 7.5mm 0;
        }
        .cor {
            font-size: 9pt;
            font-weight: bold;
            color: #27364b;
            letter-spacing: 0.4pt;
            padding: 3.2mm 0 0 0;
        }
        .preco {
            background-color: #246bfe;
            border-radius: 2.1mm;
            text-align: center;
            padding: 2.6mm 0;
            margin-top: 3mm;
        }
        .preco .moeda { font-size: 7pt; color: #dbe7ff; }
        .preco .valor { font-size: 11.5pt; font-weight: bold; color: #ffffff; }
        .sem-preco {
            background-color: #f1f5fb;
            border: 0.2mm dashed #cbd6e6;
            border-radius: 2.1mm;
            text-align: center;
            padding: 2.8mm 0;
            margin-top: 3mm;
            font-size: 7.5pt;
            font-weight: bold;
            color: #8190a5;
            letter-spacing: 0.9pt;
        }
        .meta { width: 100%; margin-top: 3.2mm; }
        .meta td {
            border-top: 0.2mm solid #e5eaf2;
            padding: 2.4mm 0 0 0;
            font-size: 6.5pt;
            color: #7a899e;
            letter-spacing: 0.9pt;
        }
        .meta .sku { text-align: right; font-weight: bold; color: #53647c; font-size: 7.5pt; }
    </style>
</head>
<body>

<htmlpageheader name="topo">
    <div class="hero">
        <table width="100%">
            <tr>
                <td width="26%" class="hero-logo">
                    @if (file_exists($logo))
                        <img src="{{ $logo }}" height="34">
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
                <td width="30%" class="hero-nota">
                    PRODUTOS ORIGINAIS<br>
                    <b>ATUALIZADO EM {{ $gerado->format('d/m/Y') }}</b>
                </td>
            </tr>
        </table>
    </div>
    <div class="hero-faixa"></div>
</htmlpageheader>

<htmlpagefooter name="rodape">
    <div class="rodape">
        <b>ESHOP</b> · PRODUTOS ORIGINAIS E DE ALTA QUALIDADE
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; CATÁLOGO ATACADISTA
    </div>
    <div class="selo-pag">PÁGINA {PAGENO}</div>
    <div class="miudo">Valores e disponibilidade sujeitos a confirmação no momento do pedido.</div>
</htmlpagefooter>

<div style="padding: 0 5.4mm 4mm 5.4mm">
    <div class="contador">{{ $total }} {{ $total === 1 ? 'ITEM' : 'ITENS' }} NESTE CATÁLOGO</div>
    <div class="intro-tit">Produtos em destaque</div>
    <div class="intro-sub">Seleção de modelos e acessórios disponíveis para consulta.</div>
    <div style="clear: both"></div>
</div>

{{-- Grade de 4 cards por linha. O subgrupo é o "modelo" do cabeçalho do card
     (é ele que diz modelo/capacidade) e a marca é a etiqueta à direita; o nome
     do produto costuma ser só a cor. --}}
@foreach ($itens as $i => $produto)
    <div class="card {{ $i % 4 === 0 ? 'inicio' : '' }}">
        <div class="card-topo">
            <table width="100%">
                <tr>
                    <td width="62%" class="modelo" height="26">{{ mb_strtoupper($produto['rotulo']) }}</td>
                    <td width="38%" class="etiqueta">{{ mb_strtoupper($produto['etiqueta']) }}</td>
                </tr>
            </table>
        </div>
        <div class="card-corpo">
            {{-- Altura fixa: com ou sem foto, todos os cards da linha
                 terminam no mesmo lugar. --}}
            <div class="foto">
                @if ($produto['foto'])
                    <img src="{{ $produto['foto'] }}" height="64">
                @else
                    <div class="placeholder">eShop</div>
                @endif
            </div>
            <div class="cor">{{ mb_strtoupper($produto['nome']) }}</div>

            @if ($mostraPreco)
                @if ($produto['preco'] > 0)
                    <div class="preco">
                        <span class="moeda">US$</span> <span class="valor">{{ number_format($produto['preco'], 2, ',', '.') }}</span>
                    </div>
                @else
                    <div class="sem-preco">CONSULTE</div>
                @endif
            @endif

            <table class="meta" width="100%">
                <tr>
                    <td>{{ $mostraEstoque ? 'EST. ' . number_format($produto['estoque'], 0, ',', '.') : 'CÓDIGO' }}</td>
                    <td class="sku">{{ $produto['codigo'] ?? '—' }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Fecha a linha a cada 4: sem isso um card mais alto empurraria o
         próximo pra posição errada na linha seguinte. --}}
    @if ($i % 4 === 3)
        <div style="clear: both"></div>
    @endif

    {{-- Quebra de página explícita a cada 12 (3 linhas), que é o que cabe na
         altura útil: o mPDF ignora `page-break-inside` em elemento flutuante
         e cortaria a última linha ao meio. --}}
    @if ($i % 12 === 11 && !$loop->last)
        <pagebreak />
    @endif
@endforeach
<div style="clear: both"></div>

</body>
</html>
