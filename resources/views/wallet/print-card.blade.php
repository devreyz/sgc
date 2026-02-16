@php
    $abbreviateName = function($name, $limit = 40) {
        if (mb_strlen($name) <= $limit) return $name;
        $parts = explode(' ', $name);
        if (count($parts) <= 1) return mb_substr($name, 0, $limit);
        
        // Mantém primeiro e último nome sempre
        $firstName = array_shift($parts);
        $lastName = array_pop($parts);
        
        // Abrevia apenas o que for estritamente necessário no meio
        // Primeiro tenta manter nomes do meio com até 3 letras (de, da, dos)
        $middleNames = array_map(function($n) use (&$limit, $firstName, $lastName) {
            // Se o nome for curto (de, da, etc), tenta manter
            if (mb_strlen($n) <= 3) return $n;
            return mb_substr($n, 0, 1) . '.';
        }, $parts);
        
        $final = $firstName . ' ' . implode(' ', $middleNames) . ' ' . $lastName;
        
        // Se ainda assim for grande, corta o excesso
        return (mb_strlen($final) > $limit) ? mb_substr($final, 0, $limit-3) . '...' : $final;
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carteirinha de Associado - {{ $tenant->name }}</title>
    <!-- Fonte que trata corretamente uppercase com acentos -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;700;800&display=swap" rel="stylesheet">
    <!-- QR Code SVG library -->
    <script src="https://cdn.jsdelivr.net/gh/lrsjng/kjua@0.9.0/dist/kjua.min.js"></script>
    <!-- Barcode library -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <!-- jsPDF and html2canvas for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        body {
            font-family: 'Noto Sans', 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', sans-serif;
            background: #f0f2f5;
            padding: 20mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .print-area {
            width: 85.6mm;
            display: flex;
            flex-direction: column;
            gap: 10mm;
        }

        .card {
            width: 85.6mm;
            height: 53.98mm;
            background: white;
            border-radius: 3.5mm;
            overflow: hidden;
            box-shadow: 0 4mm 12mm rgba(0,0,0,0.15);
            position: relative;
            flex-shrink: 0;
            border: 0.1mm solid transparent;
        }

        .card.cut-border {
            border: 0.3mm dashed rgba(0,0,0,0.3);
        }

        /* FRENTE */
        .card-front {
            background: linear-gradient(135deg, 
                {{ $tenant->primary_color ?? '#10b981' }} 0%, 
                {{ $tenant->secondary_color ?? '#059669' }} 100%);
            color: white;
            padding: 4.5mm;
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .card-front::before {
            content: '';
            position: absolute;
            top: -15mm;
            right: -15mm;
            width: 45mm;
            height: 45mm;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            z-index: 0;
        }

        .header {
            display: flex;
            align-items: center;
            gap: 3mm;
            margin-bottom: 2.5mm;
            z-index: 1;
        }

        .logo-container {
            width: 12mm;
            height: 12mm;
            background: white;
            border-radius: 2mm;
            padding: 1.2mm;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1mm 3mm rgba(0,0,0,0.1);
            overflow: hidden; /* Garante que o conteúdo não saia dos cantos arredondados */
        }

        .logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 1mm; /* Bordas arredondadas na própria imagem */
        }

        .tenant-title {
            flex: 1;
        }

        .tenant-title h1 {
            font-size: 2.3mm; /* Reduzido levemente para comportar 3 linhas */
            font-weight: 800;
            /* mantemos a exibição com quebra automática (até 3 linhas) */
            display: -webkit-box;
            -webkit-line-clamp: 3; /* Limita a 3 linhas */
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-wrap: break-word;
            line-height: 1.0; /* Linhas mais próximas para caber mais */
            letter-spacing: 0.12mm;
            margin-bottom: 0.2mm;
            text-shadow: 0 0.5mm 1mm rgba(0,0,0,0.2);
            text-transform: none; /* deixamos a transformação para o servidor com mb_strtoupper */
            font-family: 'Noto Sans', inherit;
        }

        .tenant-subtitle {
            font-size: 2.1mm; /* Aumentado conforme solicitado */
            font-weight: 700;
            opacity: 0.95;
            letter-spacing: 0.1mm;
            font-family: 'Noto Sans', inherit;
        }

        .content {
            flex: 1;
            display: grid;
            grid-template-columns: 17.5mm 1fr; /* Foto reduzida para criar mais respiro */
            gap: 3.5mm;
            z-index: 1;
            align-items: center; /* Centraliza verticalmente a foto e info */
            padding-bottom: 12mm; /* Espaço para a faixa fixa na base */
        }

        .photo-container {
            width: 17.5mm;
            height: 23.33mm; /* altura fixa em mm para html2canvas preservar proporção no PDF */
            background: #e5e7eb;
            border-radius: 1.5mm;
            border: 0.6mm solid white;
            overflow: hidden;
            box-shadow: 0 2mm 5mm rgba(0,0,0,0.2);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
        }

        .photo-container img.photo-img {
          position: absolute;
            height: 100%;
            
            object-position: center center;
            display: block;
        }

        .photo-initials {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: rgba(15,23,42,0.65);
            font-size: 8mm;
        }

        .info-grid {
            display: flex;
            flex-direction: column;
            gap: 1mm;
        }

        .name-label {
            font-size: 2.8mm; /* Mantém legibilidade */
            font-weight: 800;
            line-height: 1.05;
            text-shadow: 0 0.5mm 1mm rgba(0,0,0,0.2);
            text-align: center; /* Centralizado na base */
            margin-top: 2mm;
            display: block;
            width: 100%;
        }

        .card-front { position: relative; }

        .name-stripe {
            position: absolute;
            left: -4.5mm; /* compensa padding do card */
            right: -4.5mm;
            bottom: 0;
            background: #ffffff;
            padding: 3.2mm 4.5mm; /* mais espaço vertical para evitar crop */
            box-sizing: border-box;
            border-radius: 0 0 3.5mm 3.5mm; /* curva inferior alinhada ao cartão */
            overflow: hidden;
        }

        .name-stripe .name-label {
            color: #000000; /* Nome em preto */
            font-size: 2.7mm; /* ligeiro ajuste para caber melhor */
            font-weight: 800;
            text-transform: uppercase;
            display: -webkit-box;
            -webkit-line-clamp: 2; /* no máximo 2 linhas */
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin: 0;
            line-height: 1.12; /* evita crop nas linhas inferiores */
            padding-top: 0.2mm;
            padding-bottom: 0.2mm;
        }

        .details-box {
            background: rgba(255,255,255,0.98); /* Mais opaco para melhor contraste */
            color: #0f172a; /* Texto escuro sobre fundo claro */
            border-radius: 1.2mm;
            padding: 1.2mm 1.6mm;
            border: 0.2mm solid rgba(15,23,42,0.06);
            min-height: 23.33mm; /* Igual altura da foto reduzida */
            display: flex;
            flex-direction: column;
            justify-content: center; /* Centraliza conteúdo verticalmente */
        }

        .data-row {
            display: grid;
            grid-template-columns: auto 1fr;
            column-gap: 6mm;
            align-items: center;
            font-size: 1.9mm; /* ligeiro ajuste para melhor legibilidade */
            margin-bottom: 0.9mm;
            padding-bottom: 0;
            color: #0f172a;
        }

        .data-row:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }

        .label { font-weight: 700; color: #334155; }
        .value { font-weight: 800; color: #0b1220; }

        .footer-front {
            display: flex;
            justify-content: space-between;
            font-size: 1.8mm;
            font-weight: 600;
            opacity: 0.9;
            margin-top: 2mm;
            z-index: 1;
        }

        /* VERSO */
        .card-back {
            background: white;
            color: #1f2937;
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .mag-stripe {
            width: 100%;
            height: 8mm;
            background: #111827;
            margin-top: 3mm;
            margin-bottom: 3mm;
        }

        .back-body {
            flex: 1;
            padding: 0 4.5mm 4.5mm;
            display: flex;
            gap: 4mm;
            overflow: hidden;
        }

        .back-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 1.5mm;
        }

        .back-header-strip {
            background: linear-gradient(90deg, 
                {{ $tenant->primary_color ?? '#10b981' }} 0%, 
                {{ $tenant->secondary_color ?? '#059669' }} 100%);
            color: white;
            font-size: 2.4mm;
            font-weight: 800;
            padding: 1.2mm 3mm;
            border-radius: 1mm;
            margin-bottom: 1.5mm;
            text-align: center;
        }

        .extra-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5mm 3mm;
        }

        .field {
            display: flex;
            flex-direction: column;
        }

        .field.wide { grid-column: span 2; }

        .f-label {
            font-size: 1.8mm;
            color: {{ $tenant->primary_color ?? '#10b981' }};
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 0.3mm;
        }

        .f-value {
            font-size: 2.2mm;
            font-weight: 600;
            color: #374151;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .back-right {
            width: 22mm; /* Reduzido de 24mm */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            gap: 1.5mm;
        }

        .qr-wrapper {
            width: 22mm; /* Reduzido de 24mm */
            height: 22mm;
            background: white;
            padding: 1.2mm;
            border: 0.5mm solid {{ $tenant->primary_color ?? '#10b981' }};
            border-radius: 1.5mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #qrcode { width: 100%; height: 100%; }
        #qrcode svg { width: 100% !important; height: 100% !important; }

        .barcode-container {
            margin-top: auto;
            width: 100%;
            height: 8mm; /* Ajustado de 10mm */
            display: flex;
            justify-content: center;
        }

        #barcode {
            max-width: 100%;
            height: 100%;
        }

        .back-footer-info {
            font-size: 1.6mm; /* Reduzido de 1.8mm */
            color: #6b7280;
            text-align: center;
            border-top: 0.2mm solid #e5e7eb;
            margin-top: 1.5mm;
            padding-top: 1.5mm;
            line-height: 1.2;
            word-break: break-all; /* Evita que nomes gigantes quebrem o layout */
        }

        .v-official {
            font-size: 2mm;
            font-weight: 700;
            color: {{ $tenant->primary_color ?? '#10b981' }};
            margin-top: 1mm;
            text-transform: uppercase;
        }

        /* CONTROLES PREVIEW */
        .controls {
            margin: 10mm auto;
            background: white;
            padding: 6mm;
            border-radius: 3mm;
            box-shadow: 0 2mm 6mm rgba(0,0,0,0.1);
            max-width: 120mm;
            text-align: center;
        }

        .btn-pdf {
            background: #ef4444;
            color: white;
            padding: 3mm 8mm;
            border: none;
            border-radius: 2mm;
            font-size: 4mm;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 1mm 3mm rgba(239, 68, 68, 0.3);
        }

        .btn-pdf:hover { background: #dc2626; transform: translateY(-0.5mm); }

        .btn-close {
            background: #6b7280;
            color: white;
            padding: 3mm 8mm;
            border: none;
            border-radius: 2mm;
            font-size: 4mm;
            margin-left: 3mm;
            cursor: pointer;
        }

        #loading {
            display: none;
            margin-top: 4mm;
            color: #6b7280;
            font-weight: 600;
        }

        .rotated {
            transform: rotate(180deg);
        }

        @media print {
            body { background: white; padding: 0; }
            .controls { display: none !important; }
            .card { box-shadow: none; page-break-after: always; }
        }
    </style>
</head>
<body>
    <div class="print-area">
        <!-- FRENTE -->
        <div class="card" id="card-front-capture">
            <div class="card-front">
                <div class="header">
                    @if($tenant->logo)
                    <div class="logo-container">
                        <img src="{{ Storage::url($tenant->logo) }}" alt="Logo">
                    </div>
                    @endif
                    <div class="tenant-title">
                        <h1>{{ $tenant->name }}</h1>
                        <p class="tenant-subtitle">Carteira de Identificação do Associado</p>
                    </div>
                </div>

                <div class="content">
                    <div class="photo-container">
                        @if($user->avatar)
                            <img class="photo-img" src="{{ Storage::url($user->avatar) }}" alt="Foto" crossorigin="anonymous" loading="eager" decoding="async">
                        @else
                            <div class="photo-initials" style="display: flex; align-items: center; justify-content: center; height: 100%;">
                                {{ strtoupper(substr($user->name, 0, 2)) }}
                            </div>
                        @endif
                    </div>

                    <div class="info-grid">
                        <div class="details-box">
                            <div class="data-row">
                                <span class="label">MATRÍCULA:</span>
                                <span class="value">{{ $associate->member_code ?? $associate->registration_number ?? str_pad($associate->id, 6, '0', STR_PAD_LEFT) }}</span>
                            </div>
                            <div class="data-row">
                                <span class="label">CPF/CNPJ:</span>
                                <span class="value">{{ $associate->cpf_cnpj ?? 'N/A' }}</span>
                            </div>
                            <div class="data-row">
                                <span class="label">ADMISSÃO:</span>
                                <span class="value">{{ $associate->admission_date ? $associate->admission_date->format('d/m/Y') : $associate->created_at->format('d/m/Y') }}</span>
                            </div>
                            <div class="data-row">
                                <span class="label">EMAIL:</span>
                                <span class="value" style="font-size: 2mm;">{{ $user->email }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="name-stripe">
                    <div class="name-label" data-abbr="{{ mb_strtoupper($abbreviateName($user->name, 45), 'UTF-8') }}">{{ mb_strtoupper($user->name, 'UTF-8') }}</div>
                </div>
                </div>

                <!-- footer removido: validade apresentada em ADMISSÃO / RENOVAÇÃO -->
            </div>
        </div>

        <!-- VERSO -->
        <div class="card" id="card-back-capture">
            <div class="card-back">
                <div class="mag-stripe"></div>
                
                <div class="back-body">
                    <div class="back-left">
                        <div class="back-header-strip">DADOS ADICIONAIS</div>
                        
                        <div class="extra-info-grid">
                            @if($associate->property_name)
                            <div class="field wide">
                                <span class="f-label">Estabelecimento</span>
                                <span class="f-value">{{ $associate->property_name }}</span>
                            </div>
                            @endif

                            @if($associate->city)
                            <div class="field">
                                <span class="f-label">Município</span>
                                <span class="f-value">{{ $associate->city }}/{{ $associate->state }}</span>
                            </div>
                            @endif

                            @if($associate->property_area)
                            <div class="field">
                                <span class="f-label">Área Tot (ha)</span>
                                <span class="f-value">{{ number_format($associate->property_area, 2, ',', '.') }}</span>
                            </div>
                            @endif

                            @if($associate->dap_caf)
                            <div class="field">
                                <span class="f-label">DAP / CAF</span>
                                <span class="f-value">{{ $associate->dap_caf }}</span>
                            </div>
                            @endif

                            @if($tenant->cnpj)
                            <div class="field">
                                <span class="f-label">CNPJ</span>
                                <span class="f-value">{{ $tenant->cnpj }}</span>
                            </div>
                            @endif
                        </div>

                        <div class="barcode-container">
                            <svg id="barcode"></svg>
                        </div>
                    </div>

                    <div class="back-right">
                        <div class="qr-wrapper">
                            <div id="qrcode"></div>
                        </div>
                        <div class="v-official">Validação Oficial</div>
                    </div>
                </div>

                <div class="back-footer-info">
                    <div style="font-size: 2mm; color: #24303f; font-weight: 700;">{{ $tenant->name }}</div>
                    <div style="font-size: 1.8mm; color: #374151;">{{ $tenant->phone ?? 'N/A' }} {{ $tenant->email ? ' | ' . $tenant->email : '' }}</div>
                    <div style="font-size: 1.6mm; color: #4b5563; margin-top: 1mm;">Emitido digitalmente via {{ parse_url(config('app.url'), PHP_URL_HOST) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="controls">
        <h3>Preview da Carteirinha</h3>
        <p style="font-size: 3.5mm; color: #6b7280; margin-bottom: 4mm;">O verso será invertido automaticamente no PDF para permitir a dobra correta.</p>
        
        <div style="margin-bottom: 5mm; display: flex; align-items: center; justify-content: center; gap: 3mm;">
            <input type="checkbox" id="toggleBorder" style="width: 5mm; height: 5mm; cursor: pointer;">
            <label for="toggleBorder" style="font-size: 3.8mm; font-weight: 600; cursor: pointer;">Adicionar borda pontilhada para recorte</label>
        </div>

        <button id="generatePdfBtn" class="btn-pdf">📄 GERAR PDF PARA IMPRESSÃO</button>
        <button onclick="window.close()" class="btn-close">Fechar</button>
        <div id="loading">⏳ Processando imagens em alta resolução...</div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Gerar QR Code
            const validationUrl = "{{ $validationUrl }}";
            const qrcode = kjua({
                text: validationUrl,
                render: 'svg',
                crisp: true,
                minVersion: 6,
                ecLevel: 'H',
                size: 200,
                fill: '{{ $tenant->primary_color ?? "#10b981" }}',
                back: '#ffffff',
                rounded: 10,
                quiet: 1,
                mode: 'plain'
            });
            document.getElementById("qrcode").appendChild(qrcode);

            // Ajuste do nome do associado: só aplicar abreviação se ocupar mais de 2 linhas
            function adjustMemberName() {
                const nameEl = document.querySelector('.name-label');
                if (!nameEl) return;
                const style = window.getComputedStyle(nameEl);
                // Se line-height for 'normal', estimar por font-size
                let lineHeight = parseFloat(style.lineHeight);
                if (isNaN(lineHeight)) {
                    lineHeight = parseFloat(style.fontSize) * 1.1;
                }
                const height = nameEl.getBoundingClientRect().height;
                const lines = Math.round(height / lineHeight);
                if (lines > 2) {
                    const abbr = nameEl.getAttribute('data-abbr');
                    if (abbr) nameEl.textContent = abbr;
                }
            }

            // Checa após fontes carregarem (duas tentativas rápidas)
            setTimeout(adjustMemberName, 120);
            setTimeout(adjustMemberName, 600);

            // 2. Gerar Código de Barras
            const memberCode = "{{ $associate->member_code ?? $associate->registration_number ?? str_pad($associate->id, 6, '0', STR_PAD_LEFT) }}";
            JsBarcode("#barcode", memberCode, {
                format: "CODE128",
                width: 1.2,
                height: 35,
                displayValue: true,
                fontSize: 10,
                margin: 0,
                background: "transparent"
            });

            // 3. Gerar PDF
            document.getElementById('generatePdfBtn').addEventListener('click', async function() {
                const btn = this;
                const loading = document.getElementById('loading');
                const showBorder = document.getElementById('toggleBorder').checked;

                btn.disabled = true;
                btn.style.opacity = '0.7';
                loading.style.display = 'block';

                try {
                    const { jsPDF } = window.jspdf;
                    const pdf = new jsPDF({
                        orientation: 'portrait',
                        unit: 'mm',
                        format: 'a4'
                    });

                    const frontElem = document.getElementById('card-front-capture');
                    const backElem = document.getElementById('card-back-capture');

                    // Aplicar borda se selecionado
                    if (showBorder) {
                        frontElem.classList.add('cut-border');
                        backElem.classList.add('cut-border');
                    }

                    // Rotacionar o verso ANTES da captura
                    backElem.classList.add('rotated');

                    // Captura Frontal (High Quality)
                    const canvasFront = await html2canvas(frontElem, {
                        scale: 4,
                        useCORS: true,
                        backgroundColor: null,
                        imageTimeout: 0
                    });

                    // Captura Traseira (Invertida)
                    const canvasBack = await html2canvas(backElem, {
                        scale: 4,
                        useCORS: true,
                        backgroundColor: '#ffffff',
                        imageTimeout: 0
                    });

                    // Resetar estados na tela
                    backElem.classList.remove('rotated');
                    frontElem.classList.remove('cut-border');
                    backElem.classList.remove('cut-border');

                    const imgFront = canvasFront.toDataURL('image/png', 1.0);
                    const imgBack = canvasBack.toDataURL('image/png', 1.0);

                    const xPos = (210 - 85.6) / 2;
                    pdf.addImage(imgFront, 'PNG', xPos, 30, 85.6, 53.98);
                    // Adiciona a traseira imediatamente após a frente (sem gap) para permitir dobra perfeita
                    pdf.addImage(imgBack, 'PNG', xPos, 30 + 53.98, 85.6, 53.98);

                    pdf.save('Carteirinha_{{ Str::slug($user->name) }}.pdf');

                } catch (e) {
                    console.error(e);
                    alert('Erro ao gerar PDF: ' + e.message);
                } finally {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    loading.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>

