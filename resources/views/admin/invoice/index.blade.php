@extends('layouts.app')

@section('title', 'Invoice')

@push('styles')
    <link href="{{ asset('assets/libs/invoice/iziToast.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                        <div>
                            <h4 class="mb-0">Invoice</h4>
                            <p class="text-muted mb-0">
                                Monte a invoice no modelo escolhido, edite os campos direto na folha e gere o PDF.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="controllers_invoice" class="mb-3 d-flex flex-wrap gap-2">
                <button class="btn btn-primary add-new-line">Nova Linha</button>
                <button class="btn btn-primary remove-new-line">Remover Ultima Linha</button>
                <button class="btn btn-success salvar-pdf" style="display:none;">Salvar</button>
                <button class="btn btn-secondary historico-pdf" data-tipo="entrada">Historico Entrada</button>
                <button class="btn btn-secondary historico-pdf" data-tipo="saida">Historico Saída</button>
                <button class="btn btn-secondary carregar-pdf">Upload</button>
                <button class="btn btn-secondary buscar-pdf">Buscar</button>
            </div>

            <div class="card">
                <div class="card-body invoice-folha">
<div class="seletor-invoice">
	<div class="seletor-invoice-item" data-id="conteudo_pdf"><img src="{{ asset('assets/images/invoice/modelo1.png') }}"></div>
	<div class="seletor-invoice-item" data-id="conteudo_pdf_2"><img src="{{ asset('assets/images/invoice/modelo2.png') }}"></div>
	<div class="seletor-invoice-item" data-id="conteudo_pdf_3"><img src="{{ asset('assets/images/invoice/modelo3.png') }}"></div>
	<div class="seletor-invoice-item" data-id="conteudo_pdf_4"><img src="{{ asset('assets/images/invoice/modelo4.png') }}"></div>
	<div class="seletor-invoice-item" data-id="conteudo_pdf_5"><img src="{{ asset('assets/images/invoice/modelo5.png') }}"></div>
</div>

{{-- Modelo clássico. No painel legado a folha inteira era montada com
     position:absolute e coordenadas em px (top:90px, left:40%...), que o
     Dompdf posicionava e o mPDF ignora — tudo saía empilhado numa coluna.
     Mesma folha, agora em tabelas, como os modelos 3 e 5. --}}
<div class="pdf-container" id="conteudo_pdf" style="width:100%;font-family:Arial, Helvetica, sans-serif;display:none;color:#000;">
	<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
		<tr>
			<td style="width:60%;border:0;padding:0 0 0 20px;font-size:20px;font-weight:bold;" contenteditable="true">ATHENAS TRADING DISTRI INC</td>
			<td style="width:40%;border:0;padding:0 20px 0 0;font-size:20px;font-weight:bold;text-align:right;" contenteditable="true">Invoice</td>
		</tr>
		<tr>
			<td style="border:0;padding:15px 0 0 20px;font-size:12px;" contenteditable="true">3101 NW 72nd Ave #135 Doral FL 22133</td>
			<td style="border:0;padding:15px 20px 0 0;font-size:12px;text-align:right;">
				Invoice No: <span contenteditable="true">{{ $invoiceCod }}</span>
			</td>
		</tr>
		<tr>
			<td style="border:0;padding:5px 0 0 20px;font-size:12px;" contenteditable="true"></td>
			<td style="border:0;padding:5px 20px 0 0;font-size:12px;text-align:right;">
				Date: <span contenteditable="true">{{ $dataAtual }}</span>
			</td>
		</tr>
	</table>

	<div style="width:100%;height:2px;background-color:#eaeaea;margin-top:10px;"></div>

	{{-- Bill To / Ship To / Tracking --}}
	<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:10px;">
		<tr>
			<td style="width:10%;border:0;padding:0 0 0 20px;font-size:12px;font-weight:bold;">Bill To:</td>
			<td style="width:30%;border:0;padding:0;font-size:12px;" contenteditable="true" id="texto1">{{ $textos1['txt1'] }}</td>
			<td style="width:10%;border:0;padding:0;font-size:12px;font-weight:bold;">Ship To:</td>
			<td style="width:25%;border:0;padding:0;font-size:12px;" contenteditable="true" id="texto2">{{ $textos1['txt2'] }}</td>
			<td style="width:13%;border:0;padding:0;font-size:12px;font-weight:bold;">Tracking No:</td>
			<td style="width:12%;border:0;padding:0 20px 0 0;font-size:12px;text-align:right;" contenteditable="true"></td>
		</tr>
		<tr>
			<td style="border:0;padding:8px 0 0 20px;"></td>
			<td style="border:0;padding:8px 0 0 0;font-size:12px;" contenteditable="true" id="texto3">{{ $textos1['txt3'] }}</td>
			<td style="border:0;padding:8px 0 0 0;"></td>
			<td style="border:0;padding:8px 0 0 0;font-size:12px;" contenteditable="true" id="texto4">{{ $textos1['txt4'] }}</td>
			<td style="border:0;padding:8px 0 0 0;font-size:12px;font-weight:bold;">Ship Via:</td>
			<td style="border:0;padding:8px 20px 0 0;font-size:12px;text-align:right;" contenteditable="true"></td>
		</tr>
		<tr>
			<td style="border:0;padding:8px 0 0 20px;"></td>
			<td style="border:0;padding:8px 0 0 0;font-size:12px;" contenteditable="true" id="texto5">{{ $textos1['txt5'] }}</td>
			<td style="border:0;padding:8px 0 0 0;"></td>
			<td style="border:0;padding:8px 0 0 0;font-size:12px;" contenteditable="true" id="texto6">{{ $textos1['txt6'] }}</td>
			<td style="border:0;padding:8px 0 0 0;font-size:12px;font-weight:bold;" contenteditable="true">Free Shipping</td>
			<td style="border:0;padding:8px 20px 0 0;font-size:12px;text-align:right;" contenteditable="true"></td>
		</tr>
	</table>

	<div style="width:100%;height:2px;background-color:#000000;margin-top:14px;"></div>

	{{-- Itens --}}
	<table style="width:100%;border-collapse:collapse;table-layout:fixed;" class="container_invoice">
		<tr>
			<td style="width:50%;border:0;padding:6px 0 6px 20px;font-size:12px;font-weight:bold;border-bottom:2px solid #000000;">Description</td>
			<td style="width:20%;border:0;padding:6px 0;font-size:12px;font-weight:bold;text-align:right;border-bottom:2px solid #000000;">Quantity</td>
			<td style="width:15%;border:0;padding:6px 0;font-size:12px;font-weight:bold;text-align:right;border-bottom:2px solid #000000;">Rate</td>
			<td style="width:15%;border:0;padding:6px 20px 6px 0;font-size:12px;font-weight:bold;text-align:right;border-bottom:2px solid #000000;">Amount</td>
		</tr>
		<tr class="invoice_item">
			<td style="border:0;border-bottom:2px solid #f4f4f4;padding:10px 0 10px 20px;font-size:12px;vertical-align:top;">
				<div contenteditable="true">PRODUCT</div>
				<div contenteditable="true">COLOR</div>
			</td>
			<td style="border:0;border-bottom:2px solid #f4f4f4;padding:10px 0;font-size:12px;text-align:right;vertical-align:top;">
				<input style="width:100%;border:0;background:transparent;font-size:12px;color:#000;text-align:right;padding:0;" type="text" name="" class="invoice_item_qtd" maxlength="5">
			</td>
			<td style="border:0;border-bottom:2px solid #f4f4f4;padding:10px 0;font-size:12px;text-align:right;vertical-align:top;">
				<input style="width:100%;border:0;background:transparent;font-size:12px;color:#000;text-align:right;padding:0;" type="text" name="" class="invoice_item_preco">
			</td>
			<td style="border:0;border-bottom:2px solid #f4f4f4;padding:10px 20px 10px 0;font-size:12px;text-align:right;vertical-align:top;" class="invoice_item_total">$ 0.00</td>
		</tr>
	</table>

	{{-- Totais --}}
	<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:20px;">
		<tr>
			<td style="width:50%;border:0;padding:0;"></td>
			<td style="width:50%;border:0;padding:0;vertical-align:top;">
				<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
					<tr>
						<td style="width:50%;border:0;padding:0;text-align:right;font-size:12px;">Subtotal</td>
						<td style="width:50%;border:0;padding:0 20px 0 0;text-align:right;font-size:12px;" class="pdf-subtotal">$ 0.00</td>
					</tr>
					<tr>
						<td style="border:0;padding:7px 0 0 0;text-align:right;font-size:12px;" contenteditable="true">TAX 0%</td>
						<td style="border:0;padding:7px 20px 0 0;text-align:right;font-size:12px;">
							<input type="text" name="" value="$ 0.00" class="total_editavel editavel_incremento" style="width:100%;border:0;background:transparent;text-align:right;font-size:12px;color:#000000;padding:0;">
						</td>
					</tr>
					<tr>
						<td style="border:0;padding:7px 0 0 0;text-align:right;font-size:12px;">Shipping</td>
						<td style="border:0;padding:7px 20px 0 0;text-align:right;font-size:12px;">
							<input type="text" name="" value="$ 0.00" class="total_editavel editavel_incremento" style="width:100%;border:0;background:transparent;text-align:right;font-size:12px;color:#000000;padding:0;">
						</td>
					</tr>
					<tr>
						<td style="border:0;padding:7px 0 0 0;text-align:right;font-size:12px;">Total</td>
						<td style="border:0;padding:7px 20px 0 0;text-align:right;font-size:12px;" class="pdf-total-1">$ 0.00</td>
					</tr>
					<tr>
						<td style="border:0;padding:14px 0 0 0;text-align:right;font-size:12px;">Paid</td>
						<td style="border:0;padding:14px 20px 0 0;text-align:right;font-size:12px;">
							<input type="text" name="" value="$ 0.00" class="total_editavel total_decremento" style="width:100%;border:0;background:transparent;text-align:right;font-size:12px;color:#000000;padding:0;">
						</td>
					</tr>
					<tr>
						<td style="border:0;border-top:2px solid #000000;border-bottom:2px solid #000000;padding:7px 0;text-align:right;font-size:16px;font-weight:bold;">Balance Due</td>
						<td style="border:0;border-top:2px solid #000000;border-bottom:2px solid #000000;padding:7px 20px 7px 0;text-align:right;font-size:16px;font-weight:bold;" class="total_final">$ 0.00</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>

<div class="pdf-container pdf-container-2" id="conteudo_pdf_2" style="width:100%;font-family:sans-serif;display:none;">
	<div style="width:100%;height:30px;background-color:#001e4c;display:block;"></div>

	{{-- Logo à direita. No legado era position:absolute, que o mPDF ignora. --}}
	<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
		<tr>
			<td style="width:60%;border:0;padding:0;"></td>
			<td style="width:40%;border:0;padding:0;text-align:right;">
				<img src="{{ asset('assets/images/invoice/logo_virkin.png') }}" style="width:170px;border:0;">
			</td>
		</tr>
	</table>

	<div style="margin-top:20px;font-weight:bold;color:#000;font-size:16px;" contenteditable="true">Virkin International Corporation</div>
	<div style="margin-top:13px;margin-bottom:3px;color:#222;font-size:13px;" contenteditable="true">31 SAGAMORE PARK RD</div>
	<div style="color:#222;font-size:13px;" contenteditable="true">HUDSON, NH 03051</div>

	{{-- Bill To / Ship To / dados da invoice: as linhas eram float no legado. --}}
	<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:50px;">
		<tr>
			<td style="width:34%;border:0;padding:0 0 8px 0;color:#000;font-weight:bold;font-size:16px;">BILL TO</td>
			<td style="width:32%;border:0;padding:0 0 8px 0;color:#000;font-weight:bold;font-size:16px;">SHIP TO</td>
			<td style="width:17%;border:0;padding:0 0 8px 0;color:#000;font-weight:bold;font-size:16px;">INVOICE #</td>
			<td style="width:17%;border:0;padding:3px 0 8px 0;color:#000;text-align:right;font-size:13px;" contenteditable="true">{{ $invoiceCod }}</td>
		</tr>
		<tr>
			<td style="border:0;padding:0;color:#000;font-size:13px;vertical-align:bottom;" contenteditable="true">ELYZE S.A.</td>
			<td style="border:0;padding:0;color:#000;font-size:13px;vertical-align:bottom;" contenteditable="true">Miami</td>
			<td style="border:0;padding:0;color:#000;font-weight:bold;font-size:16px;vertical-align:bottom;">INVOICE DATE</td>
			<td style="border:0;padding:0;color:#000;font-size:13px;text-align:right;vertical-align:bottom;" contenteditable="true">{{ $dataAtual }}</td>
		</tr>
		<tr>
			<td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true">RUC: 80064781-5</td>
			<td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true"></td>
			<td style="border:0;padding:0;"></td>
			<td style="border:0;padding:0;"></td>
		</tr>
		<tr>
			<td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true">CALLE CARUPAYTY C/ AVDA</td>
			<td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true"></td>
			<td style="border:0;padding:0;"></td>
			<td style="border:0;padding:0;"></td>
		</tr>
		<tr>
			<td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true">ADRIAN JARA</td>
			<td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true"></td>
			<td style="border:0;padding:0;"></td>
			<td style="border:0;padding:0;"></td>
		</tr>
		<tr>
			<td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true">CIUDAD DEL ESTE, PARAGUAY</td>
			<td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true"></td>
			<td style="border:0;padding:0;"></td>
			<td style="border:0;padding:0;"></td>
		</tr>
		<tr>
			<td style="border:0;padding:0 0 30px 0;color:#000;font-size:13px;" contenteditable="true">PHONE: +595 994 365877</td>
			<td style="border:0;padding:0 0 30px 0;color:#000;font-size:13px;" contenteditable="true"></td>
			<td style="border:0;padding:0;"></td>
			<td style="border:0;padding:0;"></td>
		</tr>
	</table>

	<div style="width:100%;height:2px;background-color:#000;margin-bottom:17px;"></div>

	<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
		<tr>
			<td style="width:50%;border:0;padding:0 0 17px 0;color:#000;font-weight:bold;font-size:38px;">Invoice Total</td>
			<td style="width:50%;border:0;padding:0 0 17px 0;color:#000;font-weight:bold;font-size:38px;text-align:right;" class="pdf-subtotal">U$ 0,00</td>
		</tr>
	</table>

	<div style="width:100%;height:2px;background-color:#000;margin-bottom:30px;"></div>

	<table style="width:100%;border-collapse:collapse;table-layout:fixed;" class="invoice2-items">
		<tr>
			<td style="width:10%;border:0;padding:0 0 15px 0;font-size:16px;color:#000;font-weight:bold;">QTY</td>
			<td style="width:50%;border:0;padding:0 0 15px 0;font-size:16px;color:#000;font-weight:bold;">DESCRIPTION</td>
			<td style="width:20%;border:0;padding:0 0 15px 0;font-size:16px;color:#000;font-weight:bold;text-align:right;">UNIT PRICE</td>
			<td style="width:20%;border:0;padding:0 0 15px 0;font-size:16px;color:#000;font-weight:bold;text-align:right;">AMOUNT</td>
		</tr>
		<tr class="invoice2-item">
			<td style="border:0;padding:0 0 10px 0;font-size:13px;color:#000;">
				<input style="width:100%;border:0;background:transparent;font-size:13px;color:#000;padding:0;" type="text" name="" class="invoice_item_qtd" maxlength="5">
			</td>
			<td style="border:0;padding:0 0 10px 0;font-size:13px;color:#000;" contenteditable="true">PRODUCT</td>
			<td style="border:0;padding:0 0 10px 0;font-size:13px;color:#000;text-align:right;">
				<input style="width:100%;border:0;background:transparent;font-size:13px;color:#000;text-align:right;padding:0;" type="text" name="" class="invoice_item_preco">
			</td>
			<td class="invoice_item_total" style="border:0;padding:0 0 10px 0;font-size:13px;color:#000;text-align:right;">U$ 0,00</td>
		</tr>
	</table>

	<div style="width:100%;height:30px;background-color:#001e4c;margin-top:30px;"></div>
</div>



<div class="pdf-container pdf-container-3" id="conteudo_pdf_3" style="width:100%;font-family:Arial, Helvetica, sans-serif;display:none;color:#20262d;font-size:11px;">
	<div class="invoice3-sheet" style="margin:0 30px;padding-top:20px;">

		<!-- CABEÇALHO PRINCIPAL -->
		<table style="width:100%;border-collapse:collapse;table-layout:fixed;font-family:Arial, Helvetica, sans-serif;color:#20262d;">
			<tr>
				<td style="width:51%;vertical-align:top;padding:0 20px 0 0;border:0;">

					<table style="width:100%;border-collapse:collapse;table-layout:fixed;border:0;">
						<tr>
							<td style="border:0;padding:14px 0 16px 0;height:72px;vertical-align:top;">
								<img src="{{ asset('assets/images/invoice/logo_pcs.jpg') }}"
									 style="width:128px;height:auto;display:block;border:0;margin:8px 0 0 0;">
							</td>
						</tr>
						<tr><td style="border:0;padding:0;font-size:10.5px;line-height:13px;font-weight:bold;" contenteditable="true">PCS Wireless, LLC</td></tr>
						<tr><td style="border:0;padding:0;font-size:10.5px;line-height:13px;" contenteditable="true">11 Vreeland Road</td></tr>
						<tr><td style="border:0;padding:0;font-size:10.5px;line-height:13px;" contenteditable="true">Florham Park NJ 07932</td></tr>
						<tr><td style="border:0;padding:0;font-size:10.5px;line-height:13px;" contenteditable="true">United States</td></tr>
						<tr><td style="border:0;padding:0;font-size:10.5px;line-height:13px;" contenteditable="true">973-805-7400</td></tr>
					</table>

				</td>

				<td style="width:49%;vertical-align:top;padding:0;border:0;">

					<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
						<tr>
							<td colspan="2" style="border:0;padding:0 0 10px 0;text-align:right;font-size:23px;line-height:25px;font-weight:normal;color:#15191e;padding-top:4px;">
								Sales Order
							</td>
						</tr>
						<tr>
							<td style="width:38%;background:#edf1f4;border:1px solid #aeb6bd;font-weight:bold;padding:4px 7px;font-size:10.5px;">Date</td>
							<td style="width:62%;border:1px solid #aeb6bd;padding:4px 7px;font-size:10.5px;" contenteditable="true">{{ $dataAtual }}</td>
						</tr>
						<tr>
							<td style="background:#edf1f4;border:1px solid #aeb6bd;font-weight:bold;padding:4px 7px;font-size:10.5px;">S/O #</td>
							<td style="border:1px solid #aeb6bd;padding:4px 7px;font-size:10.5px;" contenteditable="true">SO{{ $invoiceCod }}</td>
						</tr>
						<tr>
							<td style="background:#edf1f4;border:1px solid #aeb6bd;font-weight:bold;padding:4px 7px;font-size:10.5px;">P/O #</td>
							<td style="border:1px solid #aeb6bd;padding:4px 7px;font-size:10.5px;" contenteditable="true">Vini</td>
						</tr>
						<tr>
							<td style="background:#edf1f4;border:1px solid #aeb6bd;font-weight:bold;padding:4px 7px;font-size:10.5px;">Total SO Qty</td>
							<td style="border:1px solid #aeb6bd;padding:4px 7px;font-size:10.5px;" class="total_amount_itens">0</td>
						</tr>
						<tr>
							<td style="background:#edf1f4;border:1px solid #aeb6bd;font-weight:bold;padding:4px 7px;font-size:10.5px;">Registration Number</td>
							<td style="border:1px solid #aeb6bd;padding:4px 7px;font-size:10.5px;" contenteditable="true"></td>
						</tr>
						<tr>
							<td style="background:#edf1f4;border:1px solid #aeb6bd;font-weight:bold;padding:4px 7px;font-size:10.5px;">Sales Rep</td>
							<td style="border:1px solid #aeb6bd;padding:4px 7px;font-size:10.5px;" contenteditable="true">Sinuhe Rodriguez</td>
						</tr>
					</table>

				</td>
			</tr>
		</table>


		<!-- CLIENTE / ENTREGA -->
		<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:28px;font-family:Arial, Helvetica, sans-serif;">
			<tr>
				<td style="width:48%;vertical-align:top;padding:0;border:1px solid #b8bfc5;">

					<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
						<tr>
							<td style="background:#edf1f4;border:0;border-bottom:1px solid #b8bfc5;font-weight:bold;font-size:10.5px;padding:5px 8px;">
								Bill To
							</td>
						</tr>
						<tr>
							<td style="border:0;padding:6px 8px 7px 8px;">
								<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
									<tr><td style="border:0;padding:0;font-size:10.5px;line-height:14px;" contenteditable="true" id="modelo_3_1">{{ $textos3['txt1'] }}</td></tr>
									<tr><td style="border:0;padding:0;font-size:10.5px;line-height:14px;" contenteditable="true" id="modelo_3_2">{{ $textos3['txt2'] }}</td></tr>
									<tr><td style="border:0;padding:0;font-size:10.5px;line-height:14px;" contenteditable="true" id="modelo_3_3">{{ $textos3['txt3'] }}</td></tr>
									<tr><td style="border:0;padding:0;font-size:10.5px;line-height:14px;" contenteditable="true" id="modelo_3_4">{{ $textos3['txt4'] }}</td></tr>
									<tr><td style="border:0;padding:0;font-size:10.5px;line-height:14px;" contenteditable="true" id="modelo_3_5">{{ $textos3['txt5'] }}</td></tr>
									<tr><td style="border:0;padding:0;font-size:10.5px;line-height:14px;" contenteditable="true" id="modelo_3_6">{{ $textos3['txt6'] }}</td></tr>
									<tr><td style="border:0;padding:0;font-size:10.5px;line-height:14px;" contenteditable="true" id="modelo_3_7">{{ $textos3['txt7'] }}</td></tr>
								</table>
							</td>
						</tr>
					</table>

				</td>

				<td style="width:4%;border:0;">&nbsp;</td>

				<td style="width:48%;vertical-align:top;padding:0;border:1px solid #b8bfc5;">

					<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
						<tr>
							<td style="background:#edf1f4;border:0;border-bottom:1px solid #b8bfc5;font-weight:bold;font-size:10.5px;padding:5px 8px;">
								Ship To
							</td>
						</tr>
						<tr>
							<td style="border:0;padding:6px 8px 7px 8px;">
								<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
									<tr><td style="border:0;padding:0;font-size:10.5px;line-height:14px;" contenteditable="true" id="modelo_3_8">{{ $textos3['txt8'] }}</td></tr>
									<tr><td style="border:0;padding:0;font-size:10.5px;line-height:14px;" contenteditable="true" id="modelo_3_9">{{ $textos3['txt9'] }}</td></tr>
									<tr><td style="border:0;padding:0;font-size:10.5px;line-height:14px;" contenteditable="true" id="modelo_3_10">{{ $textos3['txt10'] }}</td></tr>
									<tr><td style="border:0;padding:0;font-size:10.5px;line-height:14px;" contenteditable="true" id="modelo_3_11">{{ $textos3['txt11'] }}</td></tr>
									<tr><td style="border:0;padding:0;font-size:10.5px;line-height:14px;" contenteditable="true" id="modelo_3_12">{{ $textos3['txt12'] }}</td></tr>
									<tr><td style="border:0;padding:0;font-size:10.5px;line-height:14px;" contenteditable="true" id="modelo_3_13">{{ $textos3['txt13'] }}</td></tr>
									<tr><td style="border:0;padding:0;font-size:10.5px;line-height:14px;" contenteditable="true" id="modelo_3_14">{{ $textos3['txt14'] }}</td></tr>
								</table>
							</td>
						</tr>
					</table>

				</td>
			</tr>
		</table>


		<!-- PRODUTOS -->
		<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:24px;font-family:Arial, Helvetica, sans-serif;" class="invoice3-items">
			<tr>
				<td style="width:10%;background:#e8edf1;border:1px solid #aeb6bd;font-weight:bold;color:#20262d;font-size:10.5px;padding:6px 8px;">Ordered</td>
				<td style="width:60%;background:#e8edf1;border:1px solid #aeb6bd;font-weight:bold;color:#20262d;font-size:10.5px;padding:6px 8px;">Item Code</td>
				<td style="width:15%;background:#e8edf1;border:1px solid #aeb6bd;font-weight:bold;color:#20262d;font-size:10.5px;padding:6px 8px;text-align:right;">Price</td>
				<td style="width:15%;background:#e8edf1;border:1px solid #aeb6bd;font-weight:bold;color:#20262d;font-size:10.5px;padding:6px 8px;text-align:right;">Amount</td>
			</tr>
			<tr class="invoice3-item">
				<td style="width:10%;border:1px solid #c0c6cb;color:#20262d;font-size:10.5px;padding:6px 8px;">
					<input type="text" class="invoice_item_qtd" maxlength="5" style="width:100%;border:0;background:transparent;font-size:10.5px;color:#20262d;padding:0;">
				</td>
				<td style="width:60%;border:1px solid #c0c6cb;color:#20262d;font-size:10.5px;padding:6px 8px;" contenteditable="true">PRODUCT</td>
				<td style="width:15%;border:1px solid #c0c6cb;color:#20262d;font-size:10.5px;padding:6px 8px;text-align:right;">
					<input type="text" class="invoice_item_preco" style="width:100%;border:0;background:transparent;font-size:10.5px;color:#20262d;text-align:right;padding:0;">
				</td>
				<td style="width:15%;border:1px solid #c0c6cb;color:#20262d;font-size:10.5px;padding:6px 8px;text-align:right;" class="invoice_item_total">$ 0.00</td>
			</tr>
		</table>


		<!-- TERMOS / TOTAIS -->
		<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:24px;font-family:Arial, Helvetica, sans-serif;">
			<tr>
				<td style="width:42%;vertical-align:top;padding:0;border:0;">
					<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
						<tr>
							<td style="width:46%;background:#edf1f4;border:1px solid #b8bfc5;padding:5px 8px;font-size:10.5px;font-weight:bold;">Order Terms:</td>
							<td style="width:54%;border:1px solid #b8bfc5;padding:5px 8px;font-size:10.5px;" contenteditable="true">100% Wire Transfer</td>
						</tr>
						<tr>
							<td style="background:#edf1f4;border:1px solid #b8bfc5;padding:5px 8px;font-size:10.5px;font-weight:bold;">Delivery By:</td>
							<td style="border:1px solid #b8bfc5;padding:5px 8px;font-size:10.5px;" contenteditable="true">{{ $dataAtual }}</td>
						</tr>
					</table>
				</td>

				<td style="width:24%;border:0;">&nbsp;</td>

				<td style="width:34%;vertical-align:top;padding:0;border:0;">
					<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
						<tr>
							<td style="font-size:10.5px;padding:4px 4px;font-weight:bold;border:0;">Subtotal</td>
							<td style="font-size:10.5px;padding:4px 4px;text-align:right;border:0;" class="pdf-subtotal">$ 0.00</td>
						</tr>
						<tr>
							<td style="font-size:10.5px;padding:4px 4px;font-weight:bold;border:0;">Shipping Cost<br><span style="font-weight:normal;">UPS&reg; Ground</span></td>
							<td style="font-size:10.5px;padding:4px 4px;text-align:right;border:0;">
								<input type="text" value="$ 0.00" class="total_editavel editavel_incremento" style="width:100%;border:0;background:transparent;color:#20262d;font-size:10.5px;text-align:right;padding:0;">
							</td>
						</tr>
						<tr>
							<td style="font-size:10.5px;padding:4px 4px;font-weight:bold;border:0;">Tax Total</td>
							<td style="font-size:10.5px;padding:4px 4px;text-align:right;border:0;">
								<input type="text" value="$ 0.00" class="total_editavel editavel_incremento" style="width:100%;border:0;background:transparent;color:#20262d;font-size:10.5px;text-align:right;padding:0;">
							</td>
						</tr>
						<tr>
							<td style="font-size:11.5px;padding:6px 4px 4px 4px;font-weight:bold;border-top:1px solid #7f878f;">Total</td>
							<td style="font-size:11.5px;padding:6px 4px 4px 4px;text-align:right;font-weight:bold;border-top:1px solid #7f878f;" class="total_final">$ 0.00</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>


		<!-- RODAPÉ -->
		<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:76px;font-family:Arial, Helvetica, sans-serif;page-break-inside:avoid;">
			<tr>
				<td style="border-top:1px solid #d1d6da;padding:9px 0 0 0;font-size:9.5px;line-height:12px;color:#343b42;">
					Signature: ______________________________________________
					&nbsp;&nbsp;&nbsp;
					Date: ______________________________
				</td>
			</tr>
			<tr>
				<td style="border:0;padding:6px 0 0 0;font-size:8.5px;line-height:11px;color:#5a6168;">
					*For Returns, please contact rma@pcsww.com to receive instructions for submitting your return through our customer portal.
				</td>
			</tr>
			<tr>
				<td style="border:0;padding:1px 0 0 0;font-size:8.5px;line-height:11px;color:#5a6168;">
					*By transacting with PCS Wireless, LLC you agree to be bound by the terms and conditions accessible at https://www.pcsww.com/sales-terms-and-conditions/
				</td>
			</tr>
		</table>

	</div>
</div>

<div class="pdf-container pdf-container-4" id="conteudo_pdf_4" style="width:100%;font-family:sans-serif;display:none;">
	<div style="color:#305b98;font-size:34px;margin-left:55%;">Invoice</div>

	{{-- Emitente + dados da invoice. Era float no painel legado; virou tabela
	     porque o mPDF não posiciona float (o Dompdf posicionava). --}}
	<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:5px;">
		<tr>
			<td style="width:5%;border:0;padding:0;"></td>
			<td style="width:40%;border:0;padding:0;vertical-align:top;">
				<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
					<tr><td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true">TC INN</td></tr>
					<tr><td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true">3228 NW 72nd Ave Miami FL</td></tr>
					<tr><td style="border:0;padding:0;color:#000;font-size:13px;height:16px;" contenteditable="true"></td></tr>
					<tr><td style="border:0;padding:0;color:#000;font-size:13px;height:16px;" contenteditable="true"></td></tr>
				</table>
			</td>
			<td style="width:10%;border:0;padding:0;"></td>
			<td style="width:45%;border:0;padding:0;vertical-align:top;">
				<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
					<tr>
						<td style="width:48%;border:0;padding:0;color:#000;font-size:13px;font-weight:bold;">Invoice Number:</td>
						<td style="width:52%;border:0;padding:0;color:#000;font-size:13px;" contenteditable="true">{{ $invoiceCod }}</td>
					</tr>
					<tr>
						<td style="border:0;padding:0;color:#000;font-size:13px;font-weight:bold;">Invoice Date:</td>
						<td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true">{{ $dataAtual }}</td>
					</tr>
					<tr>
						<td style="border:0;padding:0;color:#000;font-size:13px;font-weight:bold;">Payment Terms:</td>
						<td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true">Due On Receipt</td>
					</tr>
					<tr>
						<td style="border:0;padding:0;color:#000;font-size:13px;font-weight:bold;">Invoice Due Date:</td>
						<td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true">{{ $dataAtual }}</td>
					</tr>
					<tr>
						<td style="border:0;padding:0;color:#000;font-size:13px;font-weight:bold;">Invoice Amount:</td>
						<td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true">85,000.00</td>
					</tr>
					<tr>
						<td style="border:0;padding:0;color:#000;font-size:13px;font-weight:bold;">Created By:</td>
						<td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true">ORDER Ronxaan</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>

	{{-- Bill To / Ship To --}}
	<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:20px;">
		<tr>
			<td style="width:5%;border:0;padding:0;"></td>
			<td style="width:40%;border:0;padding:0;vertical-align:top;">
				<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
					<tr><td style="border:0;padding:0;color:#000;font-size:13px;font-weight:bold;">Bill To</td></tr>
					<tr><td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true">Armando</td></tr>
					<tr><td style="border:0;padding:0;color:#000;font-size:13px;height:16px;" contenteditable="true"></td></tr>
					<tr><td style="border:0;padding:0;color:#000;font-size:13px;height:16px;" contenteditable="true"></td></tr>
					<tr><td style="border:0;padding:0;color:#000;font-size:13px;height:16px;" contenteditable="true"></td></tr>
				</table>
			</td>
			<td style="width:10%;border:0;padding:0;"></td>
			<td style="width:40%;border:0;padding:0;vertical-align:top;">
				<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
					<tr><td style="border:0;padding:0;color:#000;font-size:13px;font-weight:bold;">Ship To</td></tr>
					<tr><td style="border:0;padding:0;color:#000;font-size:13px;" contenteditable="true">Armando</td></tr>
					<tr><td style="border:0;padding:0;color:#000;font-size:13px;height:16px;" contenteditable="true"></td></tr>
					<tr><td style="border:0;padding:0;color:#000;font-size:13px;height:16px;" contenteditable="true"></td></tr>
					<tr><td style="border:0;padding:0;color:#000;font-size:13px;height:16px;" contenteditable="true"></td></tr>
				</table>
			</td>
			<td style="width:5%;border:0;padding:0;"></td>
		</tr>
	</table>

	<div style="width:100%;">
		<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:30px;" class="invoice4-items">
			<tr>
				<td style="width:60%;background-color:#305b98;border:2px solid #305b98;color:#fff;font-size:13px;padding:2px 7px;height:28px;border-right:0;">Item Name</td>
				<td style="width:10%;background-color:#305b98;border:2px solid #305b98;color:#fff;font-size:13px;padding:2px 7px;height:28px;border-right:0;">Quantity</td>
				<td style="width:15%;background-color:#305b98;border:2px solid #305b98;color:#fff;font-size:13px;padding:2px 7px;height:28px;border-right:0;text-align:right;">Unit Price</td>
				<td style="width:15%;background-color:#305b98;border:2px solid #305b98;color:#fff;font-size:13px;padding:2px 7px;height:28px;text-align:right;">Total</td>
			</tr>
			<tr class="invoice4-item">
				<td style="width:60%;border:2px solid #999999;color:#000;font-size:13px;padding:2px 7px;height:28px;border-top:0;border-right:0;" contenteditable="true">PRODUCT</td>
				<td style="width:10%;border:2px solid #999999;color:#000;font-size:13px;padding:2px 7px;height:28px;border-top:0;border-right:0;text-align:right;"><input type="text" name="" class="invoice_item_qtd" maxlength="5"></td>
				<td style="width:15%;border:2px solid #999999;color:#000;font-size:13px;padding:2px 7px;height:28px;border-top:0;border-right:0;text-align:right;"><input type="text" name="" class="invoice_item_preco"></td>
				<td style="width:15%;border:2px solid #999999;color:#000;font-size:13px;padding:2px 7px;height:28px;border-top:0;text-align:right;" class="invoice_item_total">$ 0.00</td>
			</tr>
		</table>
		<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
			<tr>
				<td style="width:60%;border:0;color:#000;font-size:13px;padding:2px 7px;height:28px;text-align:right;">Total Quantity:</td>
				<td style="width:10%;border:0;color:#000;font-size:13px;padding:2px 7px;height:28px;text-align:right;" class="total_amount_itens">0</td>
				<td style="width:30%;border:0;color:#000;font-size:13px;padding:2px 7px;height:28px;text-align:right;"></td>
			</tr>
		</table>
	</div>

	{{-- Totais, encostados à direita --}}
	<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:30px;">
		<tr>
			<td style="width:75%;border:0;padding:0;"></td>
			<td style="width:25%;border:0;padding:0;vertical-align:top;">
				<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
					<tr>
						<td style="color:#000;padding:2px 7px;font-size:13px;width:50%;font-weight:bold;border:0;">Subtotal</td>
						<td style="color:#000;padding:2px 7px;font-size:13px;width:50%;text-align:right;border:0;" class="pdf-subtotal">$ 0.00</td>
					</tr>
					<tr>
						<td style="color:#000;padding:2px 7px;font-size:13px;width:50%;font-weight:bold;border:0;">Total</td>
						<td style="color:#000;padding:2px 7px;font-size:13px;width:50%;text-align:right;border:0;" class="total_final">$ 0.00</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>



<div class="pdf-container pdf-container-5" id="conteudo_pdf_5" style="width:100%;font-family:Arial,Helvetica,sans-serif;display:none;color:#2d3338;font-size:10px;">
	<style type="text/css">
		@@page { size: letter; margin: 0; }
		#conteudo_pdf_5, #conteudo_pdf_5 * { box-sizing:border-box; }
	</style>

	<!-- PAGINA 1 - SCAL MOBILE v5.1 LOGO + RODAPE -->
	<div class="scal-page-one" style="width:100%;min-height:1056px;background:#fff;">

		<!-- CABECALHO - altura fixa para o Dompdf nao comprimir -->
		<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin:0;padding:0;">
			<tr style="height:147px;">
				<td style="width:34%;height:147px;vertical-align:top;padding:38px 0 0 40px;">
					<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
						<tr><td style="font-size:18px;line-height:20px;font-weight:bold;color:#0077c4;padding:0 0 5px 0;">INVOICE</td></tr>
						<tr><td style="font-size:11px;line-height:13px;font-weight:bold;color:#2f3337;padding:0;white-space:nowrap;" contenteditable="true">SCal Mobile</td></tr>
						<tr><td style="font-size:9.5px;line-height:13px;color:#2f3337;padding:0;white-space:nowrap;" contenteditable="true">8583 Irvine Center Dr #214</td></tr>
						<tr><td style="font-size:9.5px;line-height:13px;color:#2f3337;padding:0;white-space:nowrap;" contenteditable="true">Irvine, CA 92618</td></tr>
					</table>
				</td>
				<td style="width:40%;height:147px;vertical-align:top;padding:53px 0 0 0;">
					<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
						<tr><td style="font-size:9.5px;line-height:15px;color:#2f3337;padding:0;white-space:nowrap;" contenteditable="true">sales@scalmob.com</td></tr>
						<tr><td style="font-size:9.5px;line-height:15px;color:#2f3337;padding:0;white-space:nowrap;" contenteditable="true">+1 (661) 333-8059</td></tr>
						<tr><td style="font-size:9.5px;line-height:15px;color:#2f3337;padding:0;white-space:nowrap;" contenteditable="true">www.scalmob.com</td></tr>
					</table>
				</td>
				<td style="width:26%;height:147px;vertical-align:top;text-align:right;padding:0 38px 0 0;">
					<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
						<tr><td style="height:150px;padding:0;"></td></tr>
						<tr>
							<td style="padding:0;text-align:right;vertical-align:top;">
								<img src="{{ asset('assets/images/invoice/scal_logo.jpg') }}" style="width:90px;height:auto;display:inline-block;border:0;">
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>

		<table style="width:100%;border-collapse:collapse;table-layout:fixed;background:#ebf4fa;margin:0;padding:0;">
			<tr>
				<td style="width:50%;height:160px;vertical-align:top;padding:22px 20px 0 40px;">
					<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
						<tr><td style="height:18px;font-size:10px;font-weight:bold;line-height:14px;padding:0;color:#2f3337;">Bill to</td></tr>
						<tr><td id="modelo_5_1" contenteditable="true" style="height:17px;font-size:10px;line-height:14px;padding:0;color:#2f3337;vertical-align:top;">{{ $textos5['txt1'] }}</td></tr>
						<tr><td id="modelo_5_2" contenteditable="true" style="height:17px;font-size:10px;line-height:14px;padding:0;color:#2f3337;vertical-align:top;">{{ $textos5['txt2'] }}</td></tr>
						<tr><td id="modelo_5_3" contenteditable="true" style="height:17px;font-size:10px;line-height:14px;padding:0;color:#2f3337;vertical-align:top;">{{ $textos5['txt3'] }}</td></tr>
						<tr><td id="modelo_5_4" contenteditable="true" style="height:17px;font-size:10px;line-height:14px;padding:0;color:#2f3337;vertical-align:top;">{{ $textos5['txt4'] }}</td></tr>
						<tr><td id="modelo_5_5" contenteditable="true" style="height:17px;font-size:10px;line-height:14px;padding:0;color:#2f3337;vertical-align:top;">{{ $textos5['txt5'] }}</td></tr>
					</table>
				</td>
				<td style="width:50%;height:160px;vertical-align:top;padding:22px 40px 0 20px;">
					<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
						<tr><td style="height:18px;font-size:10px;font-weight:bold;line-height:14px;padding:0;color:#2f3337;">Ship to</td></tr>
						<tr><td id="modelo_5_6" contenteditable="true" style="height:17px;font-size:10px;line-height:14px;padding:0;color:#2f3337;vertical-align:top;">{{ $textos5['txt6'] }}</td></tr>
						<tr><td id="modelo_5_7" contenteditable="true" style="height:17px;font-size:10px;line-height:14px;padding:0;color:#2f3337;vertical-align:top;">{{ $textos5['txt7'] }}</td></tr>
						<tr><td id="modelo_5_8" contenteditable="true" style="height:17px;font-size:10px;line-height:14px;padding:0;color:#2f3337;vertical-align:top;">{{ $textos5['txt8'] }}</td></tr>
						<tr><td id="modelo_5_9" contenteditable="true" style="height:17px;font-size:10px;line-height:14px;padding:0;color:#2f3337;vertical-align:top;">{{ $textos5['txt9'] }}</td></tr>
						<tr><td id="modelo_5_10" contenteditable="true" style="height:17px;font-size:10px;line-height:14px;padding:0;color:#2f3337;vertical-align:top;">{{ $textos5['txt10'] }}</td></tr>
					</table>
				</td>
			</tr>
			<tr>
				<td colspan="2" style="padding:0 40px;height:1px;">
					<div style="height:1px;border-top:1px dotted #bccbd4;"></div>
				</td>
			</tr>
			<tr>
				<td colspan="2" style="height:106px;vertical-align:top;padding:23px 40px 0 40px;">
					<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
						<tr><td style="height:20px;font-size:10px;font-weight:bold;line-height:14px;padding:0;color:#2f3337;">Invoice details</td></tr>
						<tr><td style="height:17px;font-size:10px;line-height:14px;padding:0;color:#2f3337;">Invoice no.: <span contenteditable="true">{{ $invoiceCod }}</span></td></tr>
						<tr><td style="height:17px;font-size:10px;line-height:14px;padding:0;color:#2f3337;">Invoice date: <span contenteditable="true">{{ $dataAtual }}</span></td></tr>
						<tr><td class="scal-due-date" style="height:17px;font-size:10px;line-height:14px;padding:0;color:#2f3337;">Due date: <span contenteditable="true">{{ $dataAtual }}</span></td></tr>
					</table>
				</td>
			</tr>
		</table>

		<!-- TABELA DE ITENS -->
		<div style="padding:27px 32px 0 32px;">
			<table class="invoice5-items" style="width:100%;border-collapse:collapse;table-layout:fixed;margin:0;">
				<thead style="display:table-header-group;">
					<tr>
						<th style="width:4%;height:29px;font-size:9px;color:#343a3f;padding:5px 2px;text-align:left;border-bottom:1px solid #d7dde1;font-weight:normal;">#</th>
						<th style="width:9%;height:29px;font-size:9px;color:#343a3f;padding:5px 2px;text-align:left;border-bottom:1px solid #d7dde1;font-weight:normal;">Date</th>
						<th style="width:20%;height:29px;font-size:9px;color:#343a3f;padding:5px 2px;text-align:left;border-bottom:1px solid #d7dde1;font-weight:normal;">Product or service</th>
						<th style="width:9%;height:29px;font-size:9px;color:#343a3f;padding:5px 2px;text-align:left;border-bottom:1px solid #d7dde1;font-weight:normal;">SKU</th>
						<th style="width:29%;height:29px;font-size:9px;color:#343a3f;padding:5px 2px;text-align:left;border-bottom:1px solid #d7dde1;font-weight:normal;">Description</th>
						<th style="width:7%;height:29px;font-size:9px;color:#343a3f;padding:5px 2px;text-align:right;border-bottom:1px solid #d7dde1;font-weight:normal;">Qty</th>
						<th style="width:10%;height:29px;font-size:9px;color:#343a3f;padding:5px 2px;text-align:right;border-bottom:1px solid #d7dde1;font-weight:normal;">Rate</th>
						<th style="width:12%;height:29px;font-size:9px;color:#343a3f;padding:5px 2px;text-align:right;border-bottom:1px solid #d7dde1;font-weight:normal;">Amount</th>
					</tr>
				</thead>
				<tbody>
					<tr class="invoice5-item" style="page-break-inside:avoid;height:88px;">
						<td class="scal-row-number" style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;border-bottom:1px solid #e2e6e9;">1.</td>
						<td style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;border-bottom:1px solid #e2e6e9;" contenteditable="true"></td>
						<td style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;border-bottom:1px solid #e2e6e9;font-weight:bold;" contenteditable="true">Sales</td>
						<td style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;border-bottom:1px solid #e2e6e9;" contenteditable="true"></td>
						<td style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;border-bottom:1px solid #e2e6e9;line-height:13.5px;">
							<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
								<tr><td style="font-size:9.5px;line-height:13.5px;padding:0;color:#343a3f;" contenteditable="true">PRODUCT</td></tr>
								<tr><td style="height:18px;padding:0;"></td></tr>
								<tr><td style="font-size:9.5px;line-height:13.5px;padding:0;color:#343a3f;" contenteditable="true">CARRIER UNLOCKED AND</td></tr>
								<tr><td style="font-size:9.5px;line-height:13.5px;padding:0;color:#343a3f;" contenteditable="true">ICLOUD GUARANTEE</td></tr>
							</table>
						</td>
						<td style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;text-align:right;border-bottom:1px solid #e2e6e9;">
							<input style="width:100%;border:0;background:transparent;font-size:9.5px;color:#343a3f;text-align:right;padding:0;" type="text" class="invoice_item_qtd" maxlength="5">
						</td>
						<td style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;text-align:right;border-bottom:1px solid #e2e6e9;">
							<input style="width:100%;border:0;background:transparent;font-size:9.5px;color:#343a3f;text-align:right;padding:0;" type="text" class="invoice_item_preco">
						</td>
						<td class="invoice_item_total" style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;text-align:right;border-bottom:1px solid #e2e6e9;">$ 0.00</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- PAGINA 2 / NOTE TO CUSTOMER - DOMPDF SAFE -->
	<div class="scal-note-section" style="page-break-before:always;width:100%;min-height:1056px;background:#fff;padding:42px 48px 0 48px;">
		<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin:0;">
			<tr>
				<td style="width:61%;vertical-align:top;padding:0 30px 0 0;">
					<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin:0;">
						<tr><td style="height:31px;font-size:16px;line-height:19px;color:#30363b;padding:0;vertical-align:top;font-weight:normal;">Note to customer</td></tr>
						<tr><td style="font-size:10px;line-height:15px;padding:0;color:#30363b;" contenteditable="true">Business Name: SCal Mobile, LLC</td></tr>
						<tr><td style="font-size:10px;line-height:15px;padding:0;color:#30363b;" contenteditable="true">Bank Name: Community Federal Savings Bank</td></tr>
						<tr><td style="font-size:10px;line-height:15px;padding:0;color:#30363b;" contenteditable="true">Account# (USD): 8486649227</td></tr>
						<tr><td style="font-size:10px;line-height:15px;padding:0;color:#30363b;" contenteditable="true">International Swift: CMFGUS33</td></tr>
						<tr><td style="height:18px;padding:0;"></td></tr>
						<tr><td style="font-size:10px;line-height:15px;padding:0;color:#30363b;" contenteditable="true">Address: 8583 Irvine Center Dr 214</td></tr>
						<tr><td style="font-size:10px;line-height:15px;padding:0;color:#30363b;" contenteditable="true">Irvine, CA 92618-4298, US</td></tr>
						<tr><td style="height:18px;padding:0;"></td></tr>
						<tr><td style="font-size:10px;line-height:15px;padding:0;color:#30363b;" contenteditable="true">Bank Address: 210 E Main St, Rogersville TN 37857</td></tr>
						<tr><td style="height:18px;padding:0;"></td></tr>
						<tr><td style="font-size:10px;line-height:15px;padding:0;color:#30363b;" contenteditable="true">For USA Domestic Customers only:</td></tr>
						<tr><td style="font-size:10px;line-height:15px;padding:0;color:#30363b;" contenteditable="true">Routing# (ACH): 026073150</td></tr>
						<tr><td style="font-size:10px;line-height:15px;padding:0;color:#30363b;" contenteditable="true">Routing# (Wire): 026073008</td></tr>
						<tr><td style="height:19px;padding:0;"></td></tr>
						<tr><td style="font-size:10px;line-height:15px;padding:0;color:#30363b;" contenteditable="true">Terms: https://www.scalmob.com/productsalesagreement/v1</td></tr>
					</table>
				</td>
				<td style="width:39%;vertical-align:top;padding:0 0 0 22px;">
					<table style="width:100%;border-collapse:collapse;table-layout:fixed;margin:0;">
						<tr>
							<td style="width:42%;height:32px;font-size:11px;font-weight:bold;color:#30363b;padding:0 0 10px 0;vertical-align:top;border-bottom:1px solid #d9dde0;">Total</td>
							<td class="total_final" style="width:58%;height:32px;font-size:18px;font-weight:bold;color:#30363b;text-align:right;padding:0 0 10px 0;vertical-align:top;border-bottom:1px solid #d9dde0;">$ 0.00</td>
						</tr>
						<tr>
							<td style="font-size:18px;font-weight:bold;color:#f05a00;padding:14px 0 0 0;vertical-align:top;">Overdue</td>
							<td class="scal-overdue-date" style="font-size:10px;color:#30363b;text-align:right;padding:17px 0 0 0;vertical-align:top;" contenteditable="true">{{ $dataAtual }}</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</div>
</div>

                </div>
            </div>

        </div>
    </div>
@endsection

@push('styles')
    <style type="text/css">
        /* Estilos do editor, portados de template/views/invoice.html + painel.css */
        .invoice-folha { background: #fff; }
        .painel-list { width: 50vw; display: grid !important; gap: 9px; max-height: 80vh; overflow-y: auto; }
        .painel-list-item { width: 100%; display: flex; align-items: stretch; gap: 9px; margin: 0 !important; }
        .painel-list-item-info { min-width: 0; flex: 1; display: flex; align-items: center; gap: 11px; min-height: 57px; padding: 10px 14px !important; border: 1px solid rgba(0,0,0,.06); border-radius: 14px !important; background: #fff !important; box-shadow: 0 7px 22px rgba(0,0,0,.025) !important; }
        .painel-list-item-info p { min-width: 0; margin: 0 !important; color: #3d3f46; font-size: 12px !important; line-height: 1.45 !important; }
        .painel-list-item-info-label-first { flex: 0 0 78px; width: 78px !important; color: #2f73f6 !important; font-size: 10px !important; font-weight: 900 !important; letter-spacing: .055em; }
        .painel-list-item-control { flex: 0 0 auto; display: flex !important; overflow: hidden; margin: 0 !important; border: 1px solid rgba(0,0,0,.06); border-radius: 13px !important; background: #fff !important; box-shadow: 0 7px 22px rgba(0,0,0,.025) !important; }
        .painel-list-item-control a { width: 45px !important; min-height: 57px; display: grid !important; place-items: center; color: #73767f; border-left: 1px solid #ededeb; }
        .painel-list-item-control a:first-child { border-left: 0; }
        .painel-list-item-control a:hover { color: #2f73f6; background: rgba(47,115,246,.08); }
        .painel-list-item-control svg { width: 16px; height: 16px; fill: currentColor; stroke: none; }
        .painel-resultado-vazio { padding: 40px; border-radius: 17px; text-align: center; background: #fff; }
        .painel-label-aviso { margin: 0; color: #888b92; font-size: 12px; }
        .trava-invoice { margin-left: auto; cursor: pointer; }
        .painel-parametro-busca { display: flex; width: 100%; align-items: center; justify-content: flex-start; overflow-wrap: anywhere; font-weight: 650 !important; }
        #input-codigo { width: 260px; margin-bottom: 5px; }
        .codigo-item, .index-item, .deletar-item { border: 1px solid #eee; padding: 2px 10px; }
        .deletar-item { font-weight: bold; color: red; cursor: pointer; }
        .index-item { width: 50px; }
        .codigo-lista { max-height: 300px; overflow-y: scroll; font-size: 1rem; }

        /* Modais do iziToast (eram .pix_modal_container no painel legado) */
        .pix_modal_container { display: flex; align-items: center; }
        .pix_modal_container input, .pix_modal_container select { height: 26px; border-radius: .25rem; border: 1px solid #ccc; padding: 4px 10px; }
        .pix_modal_container label { margin-left: 20px; font-size: .875rem; color: #444; margin-bottom: 0; }
        .pix_modal_container label input { margin-left: 10px; }
        .pix_modal_container_2 { flex-direction: column; }
        .pix_modal_container_2 input, .pix_modal_container_2 select { width: 200px !important; margin-left: auto !important; }
        .pix_modal_container_2 label { width: 280px; display: flex; align-items: center; margin-bottom: 10px !important; margin-right: 10px; }

        /* Folha da invoice */
        #conteudo_pdf { position: relative; }
        .pdf-container { width: 100%; }
        .pdf-text { position: absolute; font-size: 12px; color: #000000; }
        .l-0 { left: 0; }
        .r-0 { right: 0; }
        .mr-20 { margin-right: 20px; }
        .ml-20 { margin-left: 20px; }
        .pdf-titulo { display: inline-block; font-size: 20px; font-weight: bold; position: absolute; color: #000000; }
        .pdf-titulo-2 { position: absolute; top: 35px; font-size: 12px; color: #000000; }
        .pdf-titulo-3 { position: absolute; top: 55px; font-size: 12px; color: #000000; }
        .pdf-divisor-1 { position: absolute; height: 2px; background-color: #eaeaea; top: 80px; }
        .seletor-invoice { display: flex; }
        .seletor-invoice-item { display: flex; width: 24%; box-shadow: 0 4px 7px -1px rgba(0, 0, 0, 0.11), 0 2px 4px -1px rgba(0, 0, 0, 0.07); margin-right: 10px; cursor: pointer; transition: .2s all; }
        .seletor-invoice-item:hover { transform: scale(1.02); transform-origin: center; }
        .seletor-invoice-item img { width: 100%; }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/libs/invoice/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/libs/invoice/jquery.mask.min.js') }}"></script>
    <script src="{{ asset('assets/libs/invoice/jquery.priceformat.min.js') }}"></script>
    <script src="{{ asset('assets/libs/invoice/iziToast.min.js') }}"></script>

    <script type="text/javascript">
        /*
         * Rotas do módulo. Os templates com __ID__ / __ARQUIVO__ são montados
         * aqui pra não precisar gerar URL no meio do JS.
         */
        window.INVOICE_ROTAS = {
            csrf: '{{ csrf_token() }}',
            texts: '{{ route('admin.invoice.texts') }}',
            pdf: '{{ route('admin.invoice.pdf') }}',
            upload: '{{ route('admin.invoice.upload') }}',
            history: '{{ route('admin.invoice.history') }}',
            file: '{{ route('admin.invoice.file', ['arquivo' => '__ARQUIVO__']) }}',
            destroy: '{{ route('admin.invoice.destroy', ['invoiceFile' => '__ID__']) }}',
            imais: '{{ route('admin.invoice.imais', ['invoiceFile' => '__ID__']) }}',
            imaisSave: '{{ route('admin.invoice.imais.save', ['invoiceFile' => '__ID__']) }}',
            lock: '{{ route('admin.invoice.lock', ['invoiceFile' => '__ID__']) }}',
            imaisInfo: '{{ route('admin.invoice.imais-info') }}'
        };
    </script>

    <script type="text/javascript">
        $(document).ready(function(){
            var ROTAS = window.INVOICE_ROTAS;

            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': ROTAS.csrf } });

            function urlArquivo(arquivo){
                return ROTAS.file.replace('__ARQUIVO__', encodeURIComponent(arquivo));
            }

            function urlPorId(template, id){
                return template.replace('__ID__', id);
            }

            var formatoPreco = {
                prefix: '$ ',
                centsSeparator: '.',
                thousandsSeparator: ','
            };

            function atualizarNumeracaoScal(){
                $('.invoice5-items > tbody > .invoice5-item').each(function(index){
                    $(this).find('.scal-row-number').text((index + 1) + '.');
                });
            }
            atualizarNumeracaoScal();

            $(document).on('click', '.seletor-invoice-item', function(e){
                $('.seletor-invoice').hide();
                $('#' + $(this).attr('data-id')).css('display', 'block');
                $('.salvar-pdf').show();
            });

            $(".invoice_item_preco, .invoice_item_total, .total_editavel").priceFormat(formatoPreco);

            /*
             * Todos os modelos são tabelas. O navegador embrulha as linhas do
             * HTML num <tbody>, então a linha nova precisa entrar nesse mesmo
             * tbody — se fosse anexada direto na <table>, ela viraria irmã do
             * tbody e o :last-child da remoção pegaria duas linhas de uma vez.
             */
            function adicionaLinha(seletorTabela, html) {
                var $tabela = $(seletorTabela);
                if(!$tabela.length) return;
                var $tbody = $tabela.children('tbody');
                ($tbody.length ? $tbody.last() : $tabela).append(html);
            }

            function removeUltimaLinha(seletorTabela, classeLinha) {
                $(seletorTabela).find(classeLinha).last().remove();
            }

            $(document).on("click", '.add-new-line', function(e){
                e.preventDefault();
                adicionaLinha('.invoice5-items', '<tr class="invoice5-item" style="page-break-inside:avoid;height:88px;"><td class="scal-row-number" style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;border-bottom:1px solid #e2e6e9;"></td><td style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;border-bottom:1px solid #e2e6e9;" contenteditable="true"></td><td style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;border-bottom:1px solid #e2e6e9;font-weight:bold;" contenteditable="true">Sales</td><td style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;border-bottom:1px solid #e2e6e9;" contenteditable="true"></td><td style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;border-bottom:1px solid #e2e6e9;line-height:13.5px;"><table style="width:100%;border-collapse:collapse;table-layout:fixed;"><tr><td style="font-size:9.5px;line-height:13.5px;padding:0;color:#343a3f;" contenteditable="true">PRODUCT</td></tr><tr><td style="height:18px;padding:0;"></td></tr><tr><td style="font-size:9.5px;line-height:13.5px;padding:0;color:#343a3f;" contenteditable="true">CARRIER UNLOCKED AND</td></tr><tr><td style="font-size:9.5px;line-height:13.5px;padding:0;color:#343a3f;" contenteditable="true">ICLOUD GUARANTEE</td></tr></table></td><td style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;text-align:right;border-bottom:1px solid #e2e6e9;"><input style="width:100%;border:0;background:transparent;font-size:9.5px;color:#343a3f;text-align:right;padding:0;" type="text" class="invoice_item_qtd" maxlength="5"></td><td style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;text-align:right;border-bottom:1px solid #e2e6e9;"><input style="width:100%;border:0;background:transparent;font-size:9.5px;color:#343a3f;text-align:right;padding:0;" type="text" class="invoice_item_preco"></td><td class="invoice_item_total" style="font-size:9.5px;color:#343a3f;padding:11px 2px 10px 2px;vertical-align:top;text-align:right;border-bottom:1px solid #e2e6e9;">$ 0.00</td></tr>');
                adicionaLinha('.invoice4-items', '<tr class="invoice4-item"><td style="width:60%;border:2px solid #999999;color:#000;font-size:13px;padding:2px 7px;height:28px;border-top:0;border-right:0;" contenteditable="true">PRODUCT</td><td style="width:10%;border:2px solid #999999;color:#000;font-size:13px;padding:2px 7px;height:28px;border-top:0;border-right:0;text-align:right;"><input type="text" name="" class="invoice_item_qtd" maxlength="5"></td><td style="width:15%;border:2px solid #999999;color:#000;font-size:13px;padding:2px 7px;height:28px;border-top:0;border-right:0;text-align:right;"><input type="text" name="" class="invoice_item_preco"></td><td style="width:15%;border:2px solid #999999;color:#000;font-size:13px;padding:2px 7px;height:28px;border-top:0;text-align:right;" class="invoice_item_total">$ 0.00</td></tr>');
                adicionaLinha('.invoice3-items', '<tr class="invoice3-item"><td style="width:10%;border:1px solid #c0c6cb;color:#20262d;font-size:10.5px;padding:6px 8px;"><input type="text" class="invoice_item_qtd" maxlength="5" style="width:100%;border:0;background:transparent;font-size:10.5px;color:#20262d;padding:0;"></td><td style="width:60%;border:1px solid #c0c6cb;color:#20262d;font-size:10.5px;padding:6px 8px;" contenteditable="true">PRODUCT</td><td style="width:15%;border:1px solid #c0c6cb;color:#20262d;font-size:10.5px;padding:6px 8px;text-align:right;"><input type="text" class="invoice_item_preco" style="width:100%;border:0;background:transparent;font-size:10.5px;color:#20262d;text-align:right;padding:0;"></td><td style="width:15%;border:1px solid #c0c6cb;color:#20262d;font-size:10.5px;padding:6px 8px;text-align:right;" class="invoice_item_total">$ 0.00</td></tr>');
                adicionaLinha('.invoice2-items', '<tr class="invoice2-item"><td style="border:0;padding:0 0 10px 0;font-size:13px;color:#000;"><input style="width:100%;border:0;background:transparent;font-size:13px;color:#000;padding:0;" type="text" name="" class="invoice_item_qtd" maxlength="5"></td><td style="border:0;padding:0 0 10px 0;font-size:13px;color:#000;" contenteditable="true">PRODUCT</td><td style="border:0;padding:0 0 10px 0;font-size:13px;color:#000;text-align:right;"><input style="width:100%;border:0;background:transparent;font-size:13px;color:#000;text-align:right;padding:0;" type="text" name="" class="invoice_item_preco"></td><td class="invoice_item_total" style="border:0;padding:0 0 10px 0;font-size:13px;color:#000;text-align:right;">U$ 0,00</td></tr>');
                adicionaLinha('.container_invoice', '<tr class="invoice_item"><td style="border:0;border-bottom:2px solid #f4f4f4;padding:10px 0 10px 20px;font-size:12px;vertical-align:top;"><div contenteditable="true">PRODUCT</div><div contenteditable="true">COLOR</div></td><td style="border:0;border-bottom:2px solid #f4f4f4;padding:10px 0;font-size:12px;text-align:right;vertical-align:top;"><input style="width:100%;border:0;background:transparent;font-size:12px;color:#000;text-align:right;padding:0;" type="text" name="" class="invoice_item_qtd" maxlength="5"></td><td style="border:0;border-bottom:2px solid #f4f4f4;padding:10px 0;font-size:12px;text-align:right;vertical-align:top;"><input style="width:100%;border:0;background:transparent;font-size:12px;color:#000;text-align:right;padding:0;" type="text" name="" class="invoice_item_preco"></td><td style="border:0;border-bottom:2px solid #f4f4f4;padding:10px 20px 10px 0;font-size:12px;text-align:right;vertical-align:top;" class="invoice_item_total">$ 0.00</td></tr>');

                $(".invoice_item_preco, .invoice_item_total").priceFormat(formatoPreco);
                $('.invoice_item_qtd').mask('######');
                atualizarNumeracaoScal();
            });

            $(document).on("click", '.remove-new-line', function(e){
                e.preventDefault();
                removeUltimaLinha('.container_invoice', '.invoice_item');
                removeUltimaLinha('.invoice2-items', '.invoice2-item');
                removeUltimaLinha('.invoice3-items', '.invoice3-item');
                removeUltimaLinha('.invoice4-items', '.invoice4-item');
                removeUltimaLinha('.invoice5-items', '.invoice5-item');
                atualizarNumeracaoScal();
            });

            $(document).on("click", '.salvar-pdf', function(e){
                e.preventDefault();
                var modalContent = '<form class="pix_modal_container pix_modal_container_2"><label>Nome: <input type="text" value="" name="nome_invoice"></label></form>';
                iziToast.show({
                    title: '',
                    message: modalContent,
                    position: 'center',
                    drag: false,
                    closeOnEscape: true,
                    timeout: false,
                    close: false,
                    color: '#ffffff',
                    buttons: [
                        ['<button><b>Gerar</b></button>', function(instance, toast) {
                            var nomeInvoice = $.trim($(toast).find('input[name="nome_invoice"]').val() || '');
                            var seletorPDF = getInvoiceAtivaSelector();

                            if(!seletorPDF) {
                                iziToast.error({
                                    title: 'Erro',
                                    message: 'Selecione um modelo de invoice.',
                                    position: 'bottomRight'
                                });
                                return;
                            }

                            /*
                             * Abre a aba imediatamente no clique: se esperar o
                             * AJAX terminar, o Chrome bloqueia o popup.
                             */
                            var pdfWindow = window.open('about:blank', '_blank');
                            if(pdfWindow) {
                                try {
                                    pdfWindow.document.write(
                                        '<html><head><title>Gerando PDF...</title></head>' +
                                        '<body style="font-family:Arial,sans-serif;padding:30px;color:#333;">' +
                                        'Gerando invoice, aguarde...</body></html>'
                                    );
                                    pdfWindow.document.close();
                                } catch(ignore) {}
                            }

                            var conteudoPDF = '';
                            try {
                                preparaPDF();
                                conteudoPDF = prepararConteudoPDF(seletorPDF);
                            } catch(err) {
                                if(pdfWindow && !pdfWindow.closed) pdfWindow.close();
                                console.error('Falha ao preparar PDF:', err);
                                iziToast.error({
                                    title: 'Erro',
                                    message: 'Falha ao preparar o conteúdo da invoice para o PDF.',
                                    position: 'bottomRight'
                                });
                                return;
                            }

                            if(!conteudoPDF || conteudoPDF.length < 50) {
                                if(pdfWindow && !pdfWindow.closed) pdfWindow.close();
                                iziToast.error({
                                    title: 'Erro',
                                    message: 'O conteúdo da invoice ficou vazio.',
                                    position: 'bottomRight'
                                });
                                return;
                            }

                            var formData = new FormData();
                            formData.append("nome", nomeInvoice);
                            formData.append("conteudo", conteudoPDF);
                            formData.append("_token", ROTAS.csrf);

                            instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');

                            $.ajax({
                                url: ROTAS.pdf,
                                type: "POST",
                                data: formData,
                                contentType: false,
                                processData: false,
                                dataType: "text",
                                cache: false
                            }).done(function(data){
                                var arquivo = $.trim(String(data || ''));

                                /*
                                 * A rota devolve só o nome do arquivo. Qualquer
                                 * HTML na resposta é erro, não PDF.
                                 */
                                if(!arquivo || arquivo.indexOf('<') !== -1) {
                                    if(pdfWindow && !pdfWindow.closed) pdfWindow.close();
                                    console.error('Resposta inválida ao gerar a invoice:', arquivo);
                                    iziToast.error({
                                        title: 'Erro ao gerar PDF',
                                        message: 'O servidor retornou uma mensagem inválida. Verifique o console do navegador.',
                                        position: 'bottomRight',
                                        timeout: 7000
                                    });
                                    return;
                                }

                                var destino = urlArquivo(arquivo);
                                if(pdfWindow && !pdfWindow.closed) {
                                    pdfWindow.location.href = destino;
                                } else {
                                    window.location.href = destino;
                                }
                            }).fail(function(xhr, status, error){
                                if(pdfWindow && !pdfWindow.closed) pdfWindow.close();

                                var resposta = xhr && xhr.responseText ? $.trim(xhr.responseText) : '';
                                console.error('Erro ao gerar a invoice:', status, error, resposta);

                                iziToast.error({
                                    title: 'Erro ao gerar PDF',
                                    message: resposta
                                        ? 'O servidor não conseguiu gerar o PDF. Consulte o console para ver a resposta.'
                                        : 'Falha na comunicação com o servidor.',
                                    position: 'bottomRight',
                                    timeout: 7000
                                });
                            });
                        }],
                        ['<button>Cancelar</button>', function(instance, toast) {
                            instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                        }]
                    ]
                });
            });

            $('.invoice_item_qtd').mask('######');

            $(document).on("focusout", ".invoice_item_preco, .invoice_item_qtd", function(e){
                var preco = parseFloat($(this).parent().parent().find('.invoice_item_preco').val().replaceAll(',','').replace('$',''));
                var qtd = parseInt($(this).parent().parent().find('.invoice_item_qtd').val());
                $(this).parent().parent().find('.invoice_item_total').text("$ " + (preco * qtd).toFixed(2));
                $(".invoice_item_preco, .invoice_item_total").priceFormat(formatoPreco);

                var subtotal = 0;
                $('.invoice_item_total').each(function(index, value) {
                    subtotal += parseFloat($(this).text().replaceAll(',','').replace('$',''));
                });
                $('.pdf-subtotal').text("$ " + subtotal.toFixed(2));
                $(".pdf-subtotal").priceFormat(formatoPreco);

                var total = 0;
                $('.total_editavel:not(.total_decremento)').each(function(index, value) {
                    total += parseFloat($(this).val().replaceAll(',','').replace('$',''));
                });
                total += parseFloat($('.pdf-subtotal').text().replaceAll(',','').replace('$',''));
                $('.pdf-total-1').text("$ " + total.toFixed(2));
                $(".pdf-total-1").priceFormat(formatoPreco);

                total = parseFloat($('.pdf-total-1').text().replaceAll(',','').replace('$',''));
                total -= parseFloat($('.total_decremento').val().replaceAll(',','').replace('$',''));
                $('.total_final').text("$ " + total.toFixed(2));
                $(".total_final").priceFormat(formatoPreco);

                var soma_total = 0;
                $('.pdf-container:visible .invoice_item_qtd').each(function(index, value){
                    soma_total += parseInt($(this).val());
                });
                $(".total_amount_itens").text(soma_total);
            });

            $(document).on("keyup", ".invoice_item_preco, .invoice_item_qtd", function(e){
                if (e.which == 13) {
                    e.preventDefault();
                    this.blur();
                }
            });

            $(document).on("keypress", ".invoice_item_preco, .invoice_item_qtd", function(e){
                if (e.which == 13) {
                    e.preventDefault();
                }
            });

            function getInvoiceAtivaSelector() {
                var modelos = ['#conteudo_pdf', '#conteudo_pdf_2', '#conteudo_pdf_3', '#conteudo_pdf_4', '#conteudo_pdf_5'];
                for(var i = 0; i < modelos.length; i++) {
                    if($(modelos[i]).length && $(modelos[i]).css('display') != 'none') {
                        return modelos[i];
                    }
                }
                return null;
            }

            function prepararConteudoPDF(seletorPDF) {
                var $clone = $(seletorPDF).clone();
                $clone.css('display', 'block');
                $clone.find('[contenteditable]').removeAttr('contenteditable');
                $clone.find('.bottom-3-repair').css({bottom: 'auto'});

                /*
                 * Todo input vira texto na célula que o contém. O legado tinha
                 * um segundo caminho que copiava o style do input pro pai e
                 * marcava .pdf-text (position:absolute) — era pros modelos 1 e
                 * 2, que hoje também são tabelas, então não existe mais.
                 */
                $clone.find('.invoice_item_qtd, .invoice_item_preco, .total_editavel').each(function(){
                    $(this).parent().text($(this).val() || '');
                });

                $clone.find('input, textarea, select, button').each(function(){
                    var valor = $(this).val() || '';
                    $(this).replaceWith(document.createTextNode(valor));
                });

                return $clone.prop('outerHTML');
            }

            /* Textos de um modelo, indexados de 1 a N como a rota espera. */
            function textosDe(prefixo, total) {
                var txt = {};
                for(var i = 1; i <= total; i++) {
                    txt[i] = $('#' + prefixo + i).text();
                }
                return txt;
            }

            /*
             * Persiste os textos de Bill To / Ship To dos três modelos, pra que
             * a próxima invoice já abra preenchida.
             */
            function preparaPDF() {
                $.post(ROTAS.texts, { modelo: 1, txt: textosDe('texto', 6) });
                $.post(ROTAS.texts, { modelo: 3, txt: textosDe('modelo_3_', 14) });
                $.post(ROTAS.texts, { modelo: 5, txt: textosDe('modelo_5_', 10) });
            }

            $(document).on('change', '.total_editavel:not(.total_decremento)', function(){
                var total = 0;
                $('.total_editavel:not(.total_decremento)').each(function(index, value) {
                    total += parseFloat($(this).val().replaceAll(',','').replace('$',''));
                });
                total += parseFloat($('.pdf-subtotal').text().replaceAll(',','').replace('$',''));
                $('.pdf-total-1').text("$ " + total.toFixed(2));
                if($('#conteudo_pdf_3').css('display') != 'none') {
                    $('.total_final').text("$ " + total.toFixed(2));
                    $(".total_final").priceFormat(formatoPreco);
                }
                $(".pdf-total-1").priceFormat(formatoPreco);
            });

            $(document).on('DOMSubtreeModified', '.pdf-subtotal', function(){
                var total = 0;
                $('.total_editavel:not(.total_decremento)').each(function(index, value) {
                    total += parseFloat($(this).val().replaceAll(',','').replace('$',''));
                });
                total += parseFloat($('.pdf-subtotal').text().replaceAll(',','').replace('$',''));
                $('.pdf-total-1').text("$ " + total.toFixed(2));
                $(".pdf-total-1").priceFormat(formatoPreco);
            });

            $(document).on('DOMSubtreeModified', '.pdf-total-1', function(){
                var total = parseFloat($('.pdf-total-1').text().replaceAll(',','').replace('$',''));
                total -= parseFloat($('.total_decremento').val().replaceAll(',','').replace('$',''));
                $('.total_final').text("$ " + total.toFixed(2));
                $(".total_final").priceFormat(formatoPreco);
            });

            $(document).on('change', '.total_decremento', function(){
                var total = parseFloat($('.pdf-total-1').text().replaceAll(',','').replace('$',''));
                total -= parseFloat($('.total_decremento').val().replaceAll(',','').replace('$',''));
                $('.total_final').text("$ " + total.toFixed(2));
                $(".total_final").priceFormat(formatoPreco);
            });

            /*
             * Último histórico aberto: ao travar/destravar a lista é recarregada,
             * e precisa voltar na mesma aba (entrada ou saída).
             */
            var ultimoTipoHistorico = 'saida';

            function abrirHistorico(tipo) {
                ultimoTipoHistorico = tipo;
                $.get(ROTAS.history, { tipo: tipo }, function(data){
                    iziToast.show({
                        title: '',
                        message: data,
                        position: 'center',
                        drag: false,
                        closeOnEscape: true,
                        timeout: false,
                        close: false,
                        color: '#ffffff',
                        buttons: [
                            ['<button>Fechar</button>', function(instance, toast) {
                                instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                            }]
                        ]
                    });
                });
            }

            $(document).on('click', '.historico-pdf', function(e){
                e.preventDefault();
                abrirHistorico($(this).attr('data-tipo'));
            });

            $(document).on("click", ".deletar", function(e){
                e.preventDefault();
                var temp = $(this);
                iziToast.question({
                    timeout: 20000,
                    overlay: true,
                    color: "#ff6b6b",
                    icon: null,
                    zindex: 1000,
                    title: 'Aviso',
                    message: 'Deseja deletar esse registro?',
                    position: 'center',
                    buttons: [ ['<button>Sim</button>', function (instance, toast) {
                        var id = temp.parent().parent().attr("data-list");
                        $.ajax({
                            url: urlPorId(ROTAS.destroy, id),
                            type: 'POST',
                            data: { _method: 'DELETE' },
                            dataType: 'json'
                        }).done(function(data) {
                            if(data && data.ok) {
                                iziToast.success({ title: 'Sucesso', message: 'Registro deletado', color: '#1dd1a1', timeout: 3000, resetOnHover: false, transitionIn: 'fadeInUp', transitionOut: 'fadeOut', position: 'bottomRight', progressBar: false });
                                temp.parent().parent().remove();
                            } else {
                                iziToast.error({title: 'Erro', message: 'Falha ao deletar registro', position: 'bottomRight'});
                            }
                        }).fail(function(){
                            iziToast.error({title: 'Erro', message: 'Falha ao deletar registro', position: 'bottomRight'});
                        });
                        instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                    }], ['<button>Não</button>', function (instance, toast) {
                        instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                    }]]
                });
            });

            var array_imais;
            $(document).on('click', '.buscar-pdf', function(e){
                e.preventDefault();
                $.get(ROTAS.imaisInfo, function(data){
                    array_imais = data;
                    var modalContent = '<form class="pix_modal_container pix_modal_container_2"><label>IMAI: <input id="imai-input" type="text" value="" name="imai_invoice" maxlength="15"></label><div id="info-imai"></div></form>';
                    iziToast.show({
                        title: '',
                        message: modalContent,
                        position: 'center',
                        drag: false,
                        closeOnEscape: true,
                        timeout: false,
                        close: false,
                        color: '#ffffff',
                        buttons: [
                            ['<button>Cancelar</button>', function(instance, toast) {
                                instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                            }]
                        ]
                    });
                });
            });

            $(document).on('input', '#imai-input', function() {
                var codigo = $('#imai-input').val();
                if(codigo.length === 15) {
                    var encontrada = null;
                    (array_imais || []).forEach(function(item) {
                        if (item.imais.indexOf(codigo) !== -1) {
                            encontrada = item;
                        }
                    });
                    if(encontrada) {
                        $('#info-imai').html('<p><b>Encontrado</b><br>Invoice: ' + encontrada.codigo + (encontrada.nome ? ' — ' + encontrada.nome : '') + '</p>');
                    } else {
                        $('#info-imai').html('<p><b>Não Encontrado</b></p>');
                    }
                }
            });

            $(document).on('click', '.carregar-pdf', function(e){
                e.preventDefault();
                var modalContent = '<form class="pix_modal_container pix_modal_container_2"><label>Nome: <input type="text" value="" name="nome_invoice"></label><label>Arquivo: <input type="file" accept="application/pdf" value="" name="file_arquivo"></label></form>';
                iziToast.show({
                    title: '',
                    message: modalContent,
                    position: 'center',
                    drag: false,
                    closeOnEscape: true,
                    timeout: false,
                    close: false,
                    color: '#ffffff',
                    buttons: [
                        ['<button><b>Gerar</b></button>', function(instance, toast) {
                            var arquivoEscolhido = $(toast).find('input[name="file_arquivo"]')[0].files[0];

                            if(!arquivoEscolhido) {
                                iziToast.error({title: 'Erro', message: 'Escolha um arquivo PDF.', position: 'bottomRight'});
                                return;
                            }

                            var formData = new FormData();
                            formData.append("nome", $(toast).find('input[name="nome_invoice"]').val());
                            formData.append("file_arquivo", arquivoEscolhido);
                            formData.append("_token", ROTAS.csrf);

                            $.ajax({
                                url: ROTAS.upload,
                                type: "POST",
                                data: formData,
                                contentType: false,
                                processData: false,
                                dataType: "text"
                            }).done(function(data){
                                window.open(urlArquivo($.trim(String(data || ''))), '_blank');
                            }).fail(function(){
                                iziToast.error({title: 'Erro', message: 'Falha ao enviar o arquivo.', position: 'bottomRight'});
                            });

                            instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                        }],
                        ['<button>Cancelar</button>', function(instance, toast) {
                            instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                        }]
                    ]
                });
            });

            var index = 0;
            var codigosDigitais = new Set();
            var travado = false;

            function salvarImais(id, instance, toast) {
                $.post(urlPorId(ROTAS.imaisSave, id), { imais: Array.from(codigosDigitais).join('-') }, null, 'json')
                    .done(function(data){
                        if(data && data.ok) {
                            iziToast.success({ title: 'Sucesso', message: 'Registro salvo', color: '#1dd1a1', timeout: 3000, resetOnHover: false, transitionIn: 'fadeInUp', transitionOut: 'fadeOut', position: 'bottomRight', progressBar: false });
                        } else {
                            iziToast.error({title: 'Erro', message: 'Falha ao salvar, tente novamente', position: 'bottomRight'});
                        }
                    })
                    .fail(function(){
                        iziToast.error({title: 'Erro', message: 'Falha ao salvar, tente novamente', position: 'bottomRight'});
                    });

                instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
            }

            $(document).on('click', '.mostrar', function(e){
                e.preventDefault();
                var id = $(this).parent().parent().attr('data-list');
                $('.iziToast-wrapper').remove();

                $.get(urlPorId(ROTAS.imais, id), function(data){
                    travado = !!(data && data.travado);

                    /*
                     * A lista começa zerada a cada invoice aberta — senão os
                     * IMEIs de uma acabariam gravados na outra.
                     */
                    codigosDigitais = new Set();
                    index = 0;

                    var html = '<input type="text" id="input-codigo"><div class="codigo-lista" id="codigo-lista">';

                    ((data && data.imais) || []).forEach(function(parte){
                        index++;
                        codigosDigitais.add(parte);
                        html += '<div style="display:flex;"><div class="index-item">' + index.toString().padStart(3, '0') + '</div><div class="codigo-item">' + parte + '</div><div class="deletar-item">X</div></div>';
                    });

                    html += '</div>';

                    iziToast.show({
                        title: '',
                        message: html,
                        position: 'center',
                        drag: false,
                        closeOnEscape: true,
                        timeout: false,
                        close: false,
                        color: '#ffffff',
                        buttons: [
                            ['<button><b>Salvar</b></button>', function(instance, toast) {
                                salvarImais(id, instance, toast);
                            }],
                            ['<button>Cancelar</button>', function(instance, toast) {
                                instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                            }]
                        ]
                    });
                });
            });

            $(document).on('input', '#input-codigo', function() {
                var codigo = $('#input-codigo').val();
                if (codigo && !codigosDigitais.has(codigo) && codigo.length === 15) {
                    if(!travado) {
                        index++;
                        codigosDigitais.add(codigo);
                        $('#codigo-lista').append('<div style="display:flex;"><div class="index-item">' + index.toString().padStart(3, '0') + '</div><div class="codigo-item">' + codigo + '</div><div class="deletar-item">X</div></div>');
                        $('#input-codigo').val('');
                    } else {
                        $('#input-codigo').val('');
                        iziToast.error({title: 'Erro', message: 'Invoice travada', position: 'bottomRight'});
                    }
                } else if (codigo.length === 15) {
                    $('#input-codigo').val('');
                    iziToast.error({title: 'Erro', message: 'Duplicado', position: 'bottomRight'});
                }
            });

            $(document).on('click', '.deletar-item', function(e){
                e.preventDefault();
                var temp = $(this);
                iziToast.question({
                    timeout: 20000,
                    overlay: true,
                    color: "#ff6b6b",
                    icon: null,
                    zindex: 1000,
                    title: 'Aviso',
                    message: 'Deseja deletar esse registro?',
                    position: 'center',
                    buttons: [ ['<button>Sim</button>', function (instance, toast) {
                        codigosDigitais.delete(temp.parent().find('.codigo-item').text());
                        temp.parent().remove();
                        instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                    }], ['<button>Não</button>', function (instance, toast) {
                        instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                    }]]
                });
            });

            $(document).on('click', '.trava-invoice', function(e){
                e.preventDefault();
                var id = $(this).parent().parent().parent().attr('data-list');
                var proximoEstado = $(this).find('svg').attr('data-reverse');

                $.post(urlPorId(ROTAS.lock, id), { travado: proximoEstado }, null, 'json')
                    .done(function(data){
                        if(data && data.ok) {
                            $('.iziToast-wrapper').remove();
                            abrirHistorico(ultimoTipoHistorico);
                        } else {
                            iziToast.error({title: 'Erro', message: 'Tente novamente', position: 'bottomRight'});
                        }
                    })
                    .fail(function(){
                        iziToast.error({title: 'Erro', message: 'Tente novamente', position: 'bottomRight'});
                    });
            });
        });
    </script>
@endpush
