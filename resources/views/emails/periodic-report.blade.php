<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Periódico</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <h2 style="margin-bottom: 8px;">
        {{ $reportType === 'monthly' ? 'Relatório mensal em PDF' : 'Relatório anual em PDF' }}
    </h2>
    <p>Olá, {{ $user->name }}.</p>
    <p>
        O relatório referente ao período <strong>{{ $reportPeriodLabel }}</strong> foi gerado e anexado a este e-mail.
    </p>
    <p>
        O arquivo está protegido com o PIN de 4 caracteres definido em Minha Conta.
    </p>
    <p>
        Por segurança, este e-mail não exibe dados financeiros detalhados no corpo da mensagem.
    </p>
    <hr style="margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;" />
    <p style="font-size: 12px; color: #4b5563;">
        Você pode cancelar ou alterar esta notificação a qualquer momento em
        <a href="{{ $unsubscribeUrl }}">Minha Conta</a>.
    </p>
</body>
</html>
