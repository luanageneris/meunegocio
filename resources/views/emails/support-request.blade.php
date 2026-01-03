<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #FCD34D 0%, #F97316 100%);
            color: white;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            margin-bottom: 20px;
        }
        .user-info {
            background-color: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .user-info strong {
            color: #333;
        }
        .message-box {
            background-color: #f9f9f9;
            border-left: 4px solid #FCD34D;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .message-box h3 {
            margin-top: 0;
            color: #333;
        }
        .message-box p {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .footer {
            border-top: 1px solid #ddd;
            margin-top: 20px;
            padding-top: 10px;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Nova Mensagem de Suporte</h1>
        </div>

        <div class="content">
            <div class="user-info">
                <strong>De:</strong> {{ $user->name }} ({{ $user->email }})<br>
                <strong>Empresa:</strong> {{ $user->company?->name ?? 'N/A' }}<br>
                <strong>Data:</strong> {{ now()->format('d/m/Y H:i') }}
            </div>

            <div class="message-box">
                <h3>{{ $supportSubject }}</h3>
                <p>{{ $supportMessage }}</p>
            </div>
        </div>

        <div class="footer">
            <p>Mensagem automática do sistema Meu Negócio - Plataforma de Controle Financeiro</p>
        </div>
    </div>
</body>
</html>
