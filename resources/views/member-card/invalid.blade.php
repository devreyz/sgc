<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carteirinha Inválida ✗</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
            text-align: center;
        }

        .validation-header {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 50px 30px;
        }

        .validation-icon {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 50px;
        }

        .validation-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .validation-header p {
            opacity: 0.9;
            font-size: 16px;
        }

        .validation-body {
            padding: 40px 30px;
        }

        .message {
            font-size: 16px;
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .alert-box {
            background: #fef2f2;
            border: 2px solid #fecaca;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .alert-title {
            font-size: 14px;
            font-weight: bold;
            color: #991b1b;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .alert-text {
            font-size: 13px;
            color: #7f1d1d;
            line-height: 1.5;
        }

        .reasons {
            text-align: left;
            margin-top: 20px;
        }

        .reasons h3 {
            font-size: 14px;
            color: #374151;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .reasons ul {
            list-style: none;
        }

        .reasons li {
            padding: 8px 0 8px 24px;
            color: #6b7280;
            font-size: 13px;
            position: relative;
        }

        .reasons li:before {
            content: "•";
            position: absolute;
            left: 8px;
            color: #ef4444;
            font-size: 18px;
        }

        .validation-footer {
            background: #f9fafb;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="validation-card">
        <div class="validation-header">
            <div class="validation-icon">✗</div>
            <h1>Carteirinha Inválida</h1>
            <p>Não foi possível validar esta carteira</p>
        </div>

        <div class="validation-body">
            <div class="alert-box">
                <div class="alert-title">⚠️ Atenção</div>
                <div class="alert-text">{{ $message }}</div>
            </div>

            <div class="message">
                Esta carteirinha não pôde ser validada. Se você é um associado e está com problemas para validar sua carteira, entre em contato com a administração de sua cooperativa ou associação.
            </div>

            <div class="reasons">
                <h3>Possíveis razões para invalidação:</h3>
                <ul>
                    <li>QR Code adulterado ou falsificado</li>
                    <li>Carteirinha de outra organização</li>
                    <li>Associação inativa ou suspensa</li>
                    <li>Carteirinha expirada ou cancelada</li>
                    <li>Dados de validação não encontrados no sistema</li>
                </ul>
            </div>
        </div>

        <div class="validation-footer">
            Validado em {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</body>
</html>
