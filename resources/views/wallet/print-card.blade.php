@extends('layouts.bento')

@section('title', 'Carteirinha de Associado')
@section('page-title', 'Carteirinha de Associado - ' . $tenant->name)

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

@section('navigation')
@endsection

@section('content')
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

        .print-preview-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        @media (max-width: 768px) {
            .print-preview-container {
                padding: 1rem 0.5rem;
            }
        }

        .print-area {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 10mm;
            margin: 0 auto 2rem;
            align-items: center;
            justify-content: center;
        }

        /* Em telas largas, mostrar frente e verso lado a lado */
        @media (min-width: 1024px) {
            .print-area {
                flex-direction: row;
                gap: 10mm;
                max-width: calc(85.6mm * 2 + 10mm);
                align-items: flex-start;
                justify-content: center;
            }
            .card { margin: 0; }
        }

        @media (max-width: 480px) {
            .print-area {
                max-width: 100%;
                transform: scale(0.9);
                transform-origin: top center;
            }
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
            padding: 3mm 4.5mm; /* Reduzido padding vertical */
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .header-card {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 10mm; /* Logo mais compactado */
            margin-bottom: 2mm;
            z-index: 1;
        }

        .logo-container {
            height: 10mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-container img {
            max-height: 10mm;
            object-fit: contain;
        }

        .tenant-stripe {
            background: #ffffff;
            width: calc(100% + 9mm);
            margin-left: -4.5mm;
            padding: 1.2mm 4.5mm; /* Mais compacto */
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-start;
            gap: 3mm;
            box-sizing: border-box;
            z-index: 1;
            margin-bottom: 2mm;
        }

        .tenant-stripe .logo-container {
            width: 12mm;
            height: 12mm;
            flex: 0 0 auto;
        }

        .tenant-title {
            flex: 1;
            text-align: left;
        }

        .tenant-title h1 {
            font-size: 2.9mm;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.05;
            text-align: left;
        }

        .tenant-subtitle {
            font-size: 2.3mm;
            font-weight: 700;
            color: {{ $tenant->primary_color ?? '#10b981' }};
            text-transform: uppercase;
            margin-top: 0.1mm;
            text-align: left;
        }

        .content {
            flex: 1;
            display: grid;
            grid-template-columns: 16mm 1fr;
            gap: 3mm;
            z-index: 1;
            align-items: center;
            padding-bottom: 9mm; /* Espaço para faixa inferior (8mm height + margem) */
        }

        .photo-container {
            width: 16mm;
            height: 21.33mm;
            background: #e5e7eb;
            border-radius: 1mm;
            overflow: hidden;
            box-shadow: 0 1mm 3mm rgba(0,0,0,0.25);
            position: relative;
            box-sizing: border-box;
            border: 0.4mm solid white;
        }

        .photo-container img.photo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .details-box {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 1.25mm;
            padding: 1.2mm 1.5mm;
            border: 0.15mm solid rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* manter conteúdo e fixar o último (email) embaixo */
            min-height: 21.33mm;
            gap: 0.5mm;
        }

        /* Estilo específico para a linha de email: centralizada e com wrap */
        .data-row.email-row .label {
            display: block;
            width: 100%;
            text-align: center;
            font-size: 2.0mm;
        }

        .data-row.email-row .value {
            display: block;
            width: 100%;
            text-align: center;
            white-space: normal; /* permite wrap */
            word-break: break-word;
            overflow: visible;
            max-width: none; /* garantir que não seja limitado pela regra genérica */
            font-size: 2.0mm; /* ajustar conforme necessário */
            font-weight: 700;
            color: #000000;
            margin-top: 0.2mm;
        }
        .data-row.email-row {
            flex-direction: column; /* empilha label + valor */
            align-items: center;
        }

        /* Verso: permitir wrap no campo de email detalhado */
        .field.email-field .f-value {
            white-space: normal;
            word-break: break-word;
            overflow: visible;
            font-size: 2.4mm;
        }
        /* Email maior no verso, posicionado embaixo do back-left, mas acima do footer */
        .email-back {
            margin-top: auto; /* empurra para baixo dentro de .back-left */
            text-align: center;
            font-size: 2.8mm; /* um pouco maior conforme solicitado */
            font-weight: 700;
            color: #000000;
            white-space: normal;
            word-break: break-word;
            padding-top: 0.8mm;
            padding-bottom: 0.6mm;
        }

        .data-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 2.5mm; /* Ajustado para aproveitar espaço disponível */
            line-height: 1.15;
            padding-bottom: 0.3mm;
            border-bottom: 0.1mm solid rgba(0, 0, 0, 0.05);
        }

        .data-row:last-child {
            border-bottom: none;
        }

        .label {
            font-weight: 600;
            color: #475569;
            font-size: 2.5mm; /* pequeno aumento */
        }

        .value {
            font-weight: 800;
            color: #000000;
            text-align: right;
            max-width: 70%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .name-stripe {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ffffff;
            height: 8.5mm;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4.5mm;
            box-sizing: border-box;
            z-index: 2;
            border-top: 0.3mm solid rgba(0, 0, 0, 0.1);
        }

        .name-stripe .name-label {
            color: #000000;
            font-size: 3.2mm;
            font-weight: 800;
            text-transform: uppercase;
            text-align: center;
            width: 100%;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.1;
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
            height: 9mm;
            background: #111827;
            margin-top: 0mm;
            margin-bottom: 3mm;
            display: flex;
            align-items: center;
            justify-content: flex-start; /* alinhado à esquerda */
            padding-left: 4.5mm; /* pequeno padding interno */
            padding-right: 4.5mm;
            box-sizing: border-box;
        }

        .barcode-plate {
            background: #ffffff;
            width: fit; /* largura fixa do plate igual ao código de barras */
            height: 9mm; /* mesma altura da faixa preta */
            padding: 0 1mm; /* sem padding para que o código ocupe toda altura */
            border-radius: 1mm;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0.4mm 1mm rgba(0,0,0,0.06);
            box-sizing: border-box;
            border-radius: 0mm;
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

        .extra-info-list {
            display: flex;
            flex-direction: column;
            gap: 1.2mm;
        }

        .field {
            display: flex;
            flex-direction: column;
        }

        .field.wide { grid-column: span 2; }

        .f-label {
            font-size: 2.0mm;
            color: {{ $tenant->primary_color ?? '#10b981' }};
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 0.2mm;
        }

        .f-value {
            font-size: 2.6mm; /* Aumentado para aproveitar espaço */
            font-weight: 700;
            color: #000000;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .back-right {
            width: 22mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            gap: 1.5mm;
        }

        .qr-wrapper {
            width: 22mm;
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

        #mag-barcode {
            width: 100%; /* ocupa toda a largura da placa */
            height: 100%; /* ocupa toda a altura da placa (9mm) */
            display: block;
        }

        .back-footer-info {
            font-size: 1.6mm;
            color: #6b7280;
            text-align: center;
            border-top: 0.2mm solid #e5e7eb;
            margin-top: 1.5mm;
            padding-top: 1.5mm;
            line-height: 1.2;
            word-break: break-all;
        }

        .v-official {
            font-size: 2mm;
            font-weight: 700;
            color: {{ $tenant->primary_color ?? '#10b981' }};
            margin-top: 1mm;
            text-transform: uppercase;
        }

        /* CONTROLES PREVIEW - Estilo Bento */
        .preview-header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem 0; /* adiciona padding ao header */
            animation: fadeInDown 0.6s ease-out;
        }

        .preview-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 0.5rem;
        }

        .preview-header p {
            font-size: 0.95rem;
            color: var(--color-text-muted);
        }

        .controls-card {
            background: var(--color-surface);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--color-border);
            margin: 2rem auto 0;
            max-width: 600px;
            animation: fadeInUp 0.6s ease-out 0.2s backwards;
        }

        @media (max-width: 640px) {
            .controls-card {
                padding: 1rem;
                margin: 1rem auto 0;
            }
        }

        .control-group {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: rgba(16, 185, 129, 0.05);
            border-radius: var(--radius-md);
        }

        .control-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--color-primary);
        }

        .control-group label {
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            color: var(--color-text);
            user-select: none;
        }

        @media (max-width: 480px) {
            .control-group {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
        }

        .button-group {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-pdf {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            color: white;
            padding: 0.875rem 2rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-pdf:active {
            transform: translateY(0);
        }

        .btn-pdf:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-close {
            background: var(--color-surface);
            color: var(--color-text-muted);
            padding: 0.875rem 2rem;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-close:hover {
            background: rgba(0, 0, 0, 0.05);
            color: var(--color-text);
            border-color: var(--color-text-muted);
        }

        @media (max-width: 480px) {
            .btn-pdf, .btn-close {
                padding: 0.75rem 1.5rem;
                font-size: 0.9rem;
                width: 100%;
            }
        }

        #loading {
            display: none;
            margin-top: 1rem;
            text-align: center;
            color: var(--color-text-muted);
            font-weight: 600;
            font-size: 0.95rem;
            animation: pulse 1.5s ease-in-out infinite;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .rotated {
            transform: rotate(180deg);
        }

        @media print {
            .print-preview-container { background: white; padding: 0; }
            .controls-card, .preview-header { display: none !important; }
            .card { box-shadow: none; page-break-after: always; }
            .print-area { margin: 0; }
        }
    </style>

<div class="print-preview-container">
    <div class="preview-header">
        <h2>Preview da Carteirinha</h2>
        <p>Visualize e gere o PDF da carteirinha para impressão</p>
    </div>

    <div class="print-area">
        <!-- FRENTE -->
        <div class="card" id="card-front-capture">
            <div class="card-front">
                <div class="tenant-stripe">
                    @if($tenant->logo)
                    <div class="logo-container">
                        <img src="{{ Storage::url($tenant->logo) }}" alt="Logo">
                    </div>
                    @endif
                    <div class="tenant-title">
                        <h1>{{ mb_strtoupper($tenant->name, 'UTF-8') }}</h1>
                        <p class="tenant-subtitle">CARTEIRA DE IDENTIFICAÇÃO DO ASSOCIADO</p>
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
                            <div class="data-row email-row">
                                <span class="value" style="font-size: 2.2mm;">{{ $user->email }}</span>
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
                <!-- VERSO -->
        <div class="card" id="card-back-capture">
            <div class="card-back">
                <div class="back-meta" style="text-align:center; font-size:2.4mm; color:#4b5563;">Emitido digitalmente via {{ parse_url(config('app.url'), PHP_URL_HOST) }}</div>

                <div class="mag-stripe">
                    <div class="barcode-plate">
                        <svg id="mag-barcode"></svg>
                    </div>
                </div>
                
                <div class="back-body">
                    <div class="back-left">
                        <div class="back-header-strip">DADOS ADICIONAIS</div>
                        
                        <div class="extra-info-list">
                            @if($associate->city)
                            <div class="field">
                                <span class="f-label">Município</span>
                                <span class="f-value">{{ $associate->city }}/{{ $associate->state }}</span>
                            </div>
                            @endif

                            @if($tenant->cnpj)
                            <div class="field">
                                <span class="f-label">CNPJ</span>
                                <span class="f-value">{{ $tenant->cnpj }}</span>
                            </div>
                            @endif

                            <div style="height:4mm"></div>

                            <div class="field">
                                <span class="f-label">MATRÍCULA</span>
                                <span class="f-value">{{ $associate->member_code ?? $associate->registration_number ?? str_pad($associate->id, 6, '0', STR_PAD_LEFT) }}</span>
                            </div>

                            <div class="field">
                                <span class="f-label">CPF/CNPJ</span>
                                <span class="f-value">{{ $associate->cpf_cnpj ?? 'N/A' }}</span>
                            </div>

                            <div class="field">
                                <span class="f-label">ADMISSÃO</span>
                                <span class="f-value">{{ $associate->admission_date ? $associate->admission_date->format('d/m/Y') : $associate->created_at->format('d/m/Y') }}</span>
                            </div>

                            <div class="email-back">{{ $user->email }}</div>
                        </div>
                    </div>

                    <div class="back-right">
                        <div class="qr-wrapper">
                            <div id="qrcode"></div>
                        </div>
                    </div>
                </div>

                <div class="back-footer-info" style="padding: 1.2mm 4.5mm 1.5mm 4.5mm; box-sizing: border-box;">
                    <div style="font-size: 2.2mm; color: #24303f; font-weight: 700; text-align: center;">{{ $tenant->name }}</div>
                    <div style="font-size: 1.9mm; color: #374151; text-align: center;">{{ $tenant->phone ?? '(00) 0000-0000' }}{{ $tenant->email ? ' | ' . $tenant->email : '' }}</div>
                </div>
            </div>
        </div>
        </div>

    
    </div>

    <div class="controls-card">
        <div class="control-group">
            <input type="checkbox" id="toggleBorder">
            <label for="toggleBorder">Adicionar borda pontilhada para recorte</label>
        </div>

        <div class="button-group">
            <button id="generatePdfBtn" class="btn-pdf">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                GERAR PDF PARA IMPRESSÃO
            </button>
            <button id="downloadImgBtn" class="btn-pdf" style="background: linear-gradient(135deg,var(--color-secondary),var(--color-primary));">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="14" rx="2" ry="2"></rect>
                    <path d="M8 21h8"></path>
                    <path d="M12 17v4"></path>
                </svg>
                BAIXAR IMAGENS (ALTA RESOLUÇÃO)
            </button>
            <button onclick="window.close()" class="btn-close">Fechar</button>
        </div>

        <div id="loading">⏳ Processando imagens em alta resolução...</div>
    </div>
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
            JsBarcode("#mag-barcode", memberCode, {
                format: "CODE128",
                width: 1.1,
                height: 36,
                displayValue: false, // não mostrar o número embaixo
                margin: 0,
                lineColor: '#000000', // barras pretas normais
                background: '#ffffff' // manter em branco para leitura
            });
            // Ajusta dimensões do SVG do código para a placa branca
            try {
                const svg = document.getElementById('mag-barcode');
                if (svg) {
                    svg.setAttribute('preserveAspectRatio', 'xMinYMid meet');
                    svg.style.width = '100%';
                    svg.style.height = '100%';
                }
            } catch (e) { /* silently ignore */ }

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

            // 4. Baixar imagens em alta resolução (frente e verso)
            document.getElementById('downloadImgBtn').addEventListener('click', async function() {
                const btn = this;
                const loading = document.getElementById('loading');
                btn.disabled = true;
                loading.style.display = 'block';
                try {
                    const frontElem = document.getElementById('card-front-capture');
                    const backElem = document.getElementById('card-back-capture');

                    // Rotacionar o verso ANTES da captura (para manter orientação correta)
                    backElem.classList.add('rotated');

                    // Capturas em alta resolução
                    const canvasFront = await html2canvas(frontElem, {
                        scale: 4,
                        useCORS: true,
                        backgroundColor: null,
                        imageTimeout: 0
                    });

                    const canvasBack = await html2canvas(backElem, {
                        scale: 4,
                        useCORS: true,
                        backgroundColor: '#ffffff',
                        imageTimeout: 0
                    });

                    // Resetar rotação
                    backElem.classList.remove('rotated');

                    // Forçar download das imagens
                    function downloadDataURL(dataURL, filename) {
                        const a = document.createElement('a');
                        a.href = dataURL;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                    }

                    const imgFront = canvasFront.toDataURL('image/png', 1.0);
                    const imgBack = canvasBack.toDataURL('image/png', 1.0);

                    downloadDataURL(imgFront, 'Carteirinha_Frente_{{ Str::slug($user->name) }}.png');
                    // Pequeno delay para dar tempo ao navegador iniciar o download
                    setTimeout(() => downloadDataURL(imgBack, 'Carteirinha_Verso_{{ Str::slug($user->name) }}.png'), 300);

                } catch (e) {
                    console.error(e);
                    alert('Erro ao baixar imagens: ' + (e.message || e));
                } finally {
                    btn.disabled = false;
                    loading.style.display = 'none';
                }
            });
        });
    </script>
@endsection

