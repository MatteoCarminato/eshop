<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#198754">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Entrada Rápida">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Entrada Rápida</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --brand: #198754;
            --brand-dark: #146c43;
        }

        html,
        body {
            background: #f4f6f9;
            -webkit-tap-highlight-color: transparent;
            overscroll-behavior-y: contain;
        }

        body {
            padding-bottom: env(safe-area-inset-bottom);
            font-size: 16px;
        }

        .app-header {
            background: var(--brand);
            color: #fff;
            padding: 1rem 1.25rem calc(1rem + env(safe-area-inset-top, 0px));
            padding-top: calc(1rem + env(safe-area-inset-top, 0px));
            box-shadow: 0 2px 6px rgba(0, 0, 0, .1);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .app-header h1 {
            font-size: 1.25rem;
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .app-container {
            max-width: 480px;
            margin: 0 auto;
            padding: 1rem;
        }

        .form-label {
            font-weight: 600;
            font-size: .9rem;
            color: #495057;
            margin-bottom: .35rem;
        }

        .form-control,
        .form-select {
            font-size: 1.05rem;
            padding: .75rem .9rem;
            border-radius: 12px;
            border: 1.5px solid #dee2e6;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 .2rem rgba(25, 135, 84, .15);
        }

        .input-amount {
            font-size: 2rem !important;
            font-weight: 600;
            text-align: center;
            letter-spacing: .5px;
        }

        .toggle-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .5rem;
        }

        .toggle-btn {
            border: 1.5px solid #dee2e6;
            background: #fff;
            border-radius: 12px;
            padding: .85rem .5rem;
            font-weight: 600;
            font-size: 1rem;
            color: #495057;
            transition: all .15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
        }

        .toggle-btn:active {
            transform: scale(.97);
        }

        .toggle-btn.active {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
        }

        .btn-submit {
            background: var(--brand);
            color: #fff;
            border: 0;
            border-radius: 14px;
            padding: 1rem;
            width: 100%;
            font-size: 1.15rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(25, 135, 84, .3);
            transition: all .15s;
        }

        .btn-submit:active {
            transform: scale(.98);
        }

        .btn-submit:disabled {
            opacity: .55;
            box-shadow: none;
        }

        .card-section {
            background: #fff;
            border-radius: 16px;
            padding: 1.1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
        }

        .small-help {
            font-size: .78rem;
            color: #6c757d;
        }

        .toast-container {
            position: fixed;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1080;
            width: 92%;
            max-width: 460px;
        }

        .toast {
            border-radius: 12px;
        }

        .fee-row {
            display: flex;
            gap: .5rem;
            align-items: stretch;
        }

        .fee-row .form-control {
            flex: 1;
        }

        .btn-fetch {
            background: #fff;
            border: 1.5px solid var(--brand);
            color: var(--brand);
            border-radius: 12px;
            padding: 0 .9rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-fetch:active {
            background: var(--brand);
            color: #fff;
        }
    </style>
</head>

<body>
    <header class="app-header">
        <h1><i class="ri-wallet-3-line"></i> Entrada Rápida</h1>
    </header>

    <main class="app-container">
        <form id="quickForm" autocomplete="off" novalidate>
            @csrf

            <div class="card-section">
                <label for="client_id" class="form-label">Cliente</label>
                <select id="client_id" name="client_id" class="form-select" required>
                    <option value="">— selecione —</option>
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}" data-spread="{{ $c->spread_points }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="card-section">
                <label class="form-label">Moeda</label>
                <div class="toggle-group" id="currencyGroup">
                    <button type="button" class="toggle-btn active" data-currency="BRL">
                        <i class="ri-money-cny-circle-line"></i> R$ Reais
                    </button>
                    <button type="button" class="toggle-btn" data-currency="USD">
                        <i class="ri-money-dollar-circle-line"></i> US$ Dólar
                    </button>
                </div>
                <input type="hidden" name="currency" id="currency" value="BRL">
            </div>

            <div class="card-section">
                <label for="amount" class="form-label">Valor</label>
                <input type="text" inputmode="decimal" id="amount" name="amount" class="form-control input-amount"
                    placeholder="0,00" required>
            </div>

            <div class="card-section" id="feeSection">
                <label for="fee" class="form-label d-flex align-items-center justify-content-between">
                    <span>Cotação USD/BRL</span>
                    <span id="feeStatus" class="small-help"></span>
                </label>
                <div class="fee-row">
                    <input type="text" inputmode="decimal" id="fee" name="fee" class="form-control"
                        placeholder="4,9311">
                    <button type="button" class="btn-fetch" id="btnFetchRate" title="Buscar cotação atual">
                        <i class="ri-refresh-line"></i>
                    </button>
                </div>
                <div class="small-help mt-2">Cotação base + spread do cliente.</div>
            </div>

            <div class="card-section">
                <label class="form-label">Forma de Pagamento</label>
                <div class="toggle-group" id="paymentGroup">
                    {{-- preenchido por JS --}}
                </div>
                <input type="hidden" name="payment_method" id="payment_method" value="pix">
            </div>

            <button type="submit" class="btn-submit" id="btnSubmit">
                <i class="ri-add-circle-line"></i> Adicionar Entrada
            </button>
        </form>
    </main>

    <div class="toast-container" id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const $ = (s) => document.querySelector(s);

            const clientSel = $('#client_id');
            const currencyInput = $('#currency');
            const currencyGroup = $('#currencyGroup');
            const amountInput = $('#amount');
            const feeSection = $('#feeSection');
            const feeInput = $('#fee');
            const feeStatus = $('#feeStatus');
            const btnFetchRate = $('#btnFetchRate');
            const paymentGroup = $('#paymentGroup');
            const paymentInput = $('#payment_method');
            const form = $('#quickForm');
            const btnSubmit = $('#btnSubmit');
            const toastContainer = $('#toastContainer');

            // ---------- Helpers ----------
            const parseNum = (v) => {
                if (!v) return null;
                const n = parseFloat(String(v).replace(/\./g, '').replace(',', '.'));
                return isNaN(n) ? null : n;
            };
            const fmt = (n, d = 4) => n.toLocaleString('pt-BR', { minimumFractionDigits: d, maximumFractionDigits: d });

            function toast(msg, type = 'success') {
                const el = document.createElement('div');
                el.className = `toast align-items-center text-bg-${type} border-0 show mb-2`;
                el.setAttribute('role', 'alert');
                el.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
                toastContainer.appendChild(el);
                const t = new bootstrap.Toast(el, { delay: 3500 });
                t.show();
                el.addEventListener('hidden.bs.toast', () => el.remove());
            }

            // ---------- Moeda toggle ----------
            currencyGroup.querySelectorAll('.toggle-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    currencyGroup.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currencyInput.value = btn.dataset.currency;
                    updatePaymentOptions();
                    updateFeeVisibility();
                    if (btn.dataset.currency === 'BRL') tryAutoFetchRate();
                });
            });

            // ---------- Pagamento ----------
            function updatePaymentOptions() {
                const isBRL = currencyInput.value === 'BRL';
                const opts = isBRL
                    ? [['pix', 'Pix', 'ri-secure-payment-line'], ['dinheiro', 'Dinheiro', 'ri-money-cny-box-line']]
                    : [['efetivo', 'Efetivo', 'ri-money-dollar-box-line'], ['usdt', 'USDT', 'ri-coin-line']];
                paymentGroup.innerHTML = '';
                opts.forEach(([val, label, icon], i) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'toggle-btn' + (i === 0 ? ' active' : '');
                    b.dataset.payment = val;
                    b.innerHTML = `<i class="${icon}"></i> ${label}`;
                    b.addEventListener('click', () => {
                        paymentGroup.querySelectorAll('.toggle-btn').forEach(x => x.classList.remove('active'));
                        b.classList.add('active');
                        paymentInput.value = val;
                    });
                    paymentGroup.appendChild(b);
                });
                paymentInput.value = opts[0][0];
            }

            // ---------- Cotação ----------
            function updateFeeVisibility() {
                feeSection.style.display = currencyInput.value === 'BRL' ? '' : 'none';
            }

            async function tryAutoFetchRate() {
                if (currencyInput.value !== 'BRL') return;
                if (feeInput.value && parseNum(feeInput.value) > 0) return; // já preenchido
                fetchRate();
            }

            async function fetchRate() {
                const opt = clientSel.selectedOptions[0];
                const spread = opt ? parseFloat(opt.dataset.spread || '0') : 0;
                feeStatus.textContent = 'buscando…';
                feeStatus.className = 'small-help text-muted';
                try {
                    const res = await fetch('{{ route('admin.wallet.usd-brl-rate') }}', { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Falha');
                    const rate = parseFloat(data.rate) + (spread * 0.01);
                    feeInput.value = fmt(rate, 4);
                    feeStatus.textContent = `base ${fmt(data.rate, 4)} + spread ${spread}pts`;
                    feeStatus.className = 'small-help text-success';
                } catch (e) {
                    feeStatus.textContent = 'falha — preencha manual';
                    feeStatus.className = 'small-help text-danger';
                }
            }

            btnFetchRate.addEventListener('click', fetchRate);
            clientSel.addEventListener('change', () => {
                if (currencyInput.value === 'BRL') {
                    feeInput.value = '';
                    tryAutoFetchRate();
                }
            });

            // Mascara simples no valor (vírgula decimal)
            amountInput.addEventListener('input', (e) => {
                let v = e.target.value.replace(/[^0-9,]/g, '');
                const parts = v.split(',');
                if (parts.length > 2) v = parts[0] + ',' + parts.slice(1).join('');
                e.target.value = v;
            });

            // ---------- Submit ----------
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!clientSel.value) return toast('Selecione o cliente', 'warning');
                const amt = parseNum(amountInput.value);
                if (!amt || amt <= 0) return toast('Informe um valor válido', 'warning');
                let fee = null;
                if (currencyInput.value === 'BRL') {
                    fee = parseNum(feeInput.value);
                    if (!fee || fee <= 0) return toast('Cotação obrigatória para BRL', 'warning');
                }

                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="ri-loader-4-line"></i> Enviando…';

                const fd = new FormData();
                fd.append('_token', csrf);
                fd.append('client_id', clientSel.value);
                fd.append('currency', currencyInput.value);
                fd.append('amount', amt.toFixed(2));
                fd.append('payment_method', paymentInput.value);
                if (fee) fd.append('fee', fee.toFixed(4));

                try {
                    const res = await fetch('{{ url('admin/wallet/deposit') }}', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' / ') : 'Erro ao salvar');
                        throw new Error(msg);
                    }
                    const clientName = clientSel.selectedOptions[0].text;
                    const sym = currencyInput.value === 'BRL' ? 'R$' : 'US$';
                    toast(`✓ ${sym} ${fmt(amt, 2)} → ${clientName}`, 'success');
                    // reset (mantém cliente p/ múltiplas entradas seguidas)
                    amountInput.value = '';
                    amountInput.focus();
                } catch (err) {
                    toast(err.message || 'Erro inesperado', 'danger');
                } finally {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '<i class="ri-add-circle-line"></i> Adicionar Entrada';
                }
            });

            // ---------- Init ----------
            updatePaymentOptions();
            updateFeeVisibility();
        })();
    </script>
</body>

</html>
