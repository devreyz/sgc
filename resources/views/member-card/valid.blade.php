<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carteirinha Válida ✓</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, {{ $tenant->primary_color ?? '#10b981' }} 0%, {{ $tenant->secondary_color ?? '#059669' }} 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .validation-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }

        .validation-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .validation-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
        }

        .validation-header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .validation-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .validation-body {
            padding: 30px;
        }

        .info-section {
            margin-bottom: 25px;
        }

        .info-section h2 {
            color: {{ $tenant->primary_color ?? '#10b981' }};
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 15px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 15px;
            color: #1f2937;
            font-weight: 600;
        }

        .tenant-info {
            background: #f9fafb;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .tenant-name {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .tenant-logo {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .tenant-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .validation-footer {
            background: #f9fafb;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            background: #d1fae5;
            color: #065f46;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }

        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="validation-card">
        <div class="validation-header">
            <div class="validation-icon">✓</div>
            <h1>Carteirinha Válida</h1>
            <p>Esta carteira é autêntica e está ativa</p>
        </div>

        <div class="validation-body">
            <div class="info-section">
                <h2>Dados do Associado</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Nome Completo</span>
                        <span class="info-value">{{ $user->name }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Matrícula</span>
                        <span class="info-value">{{ $associate->member_code ?? $associate->registration_number ?? str_pad($associate->id, 6, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    @if($associate->cpf_cnpj)
                    <div class="info-item">
                        <span class="info-label">CPF/CNPJ</span>
                        <span class="info-value">{{ $associate->cpf_cnpj }}</span>
                    </div>
                    @endif
                    <div class="info-item">
                        <span class="info-label">Membro Desde</span>
                        <span class="info-value">{{ $associate->admission_date ? $associate->admission_date->format('d/m/Y') : $associate->created_at->format('d/m/Y') }}</span>
                    </div>
                    @if($associate->city)
                    <div class="info-item">
                        <span class="info-label">Município</span>
                        <span class="info-value">{{ $associate->city }}/{{ $associate->state }}</span>
                    </div>
                    @endif
                </div>
                <span class="status-badge">✓ Associado Ativo</span>
            </div>

            <div class="tenant-info">
                <div class="tenant-name">
                    @if($tenant->logo)
                        <div class="tenant-logo">
                            <img src="{{ Storage::url($tenant->logo) }}" alt="{{ $tenant->name }}">
                        </div>
                    @endif
                    <div>
                        <h3 style="font-size: 16px; color: #1f2937; margin-bottom: 4px;">{{ $tenant->name }}</h3>
                        @if($tenant->city)
                            <p style="font-size: 12px; color: #6b7280;">{{ $tenant->city }}/{{ $tenant->state }}</p>
                        @endif
                    </div>
                </div>
                @if($tenant->phone || $tenant->email)
                <div style="margin-top: 12px; font-size: 13px; color: #6b7280;">
                    @if($tenant->phone)
                        <div>📞 {{ $tenant->phone }}</div>
                    @endif
                    @if($tenant->email)
                        <div>✉️ {{ $tenant->email }}</div>
                    @endif
                </div>
                @endif
            </div>
        </div>

        <div class="validation-footer">
            ⚠️ Esta carteirinha é intransferível e de uso pessoal<br>
            Validado em {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</body>
</html>
