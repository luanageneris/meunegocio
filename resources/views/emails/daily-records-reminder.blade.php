<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Lembrete de Registros</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <h2 style="margin-bottom: 8px;">Lembrete de registros financeiros</h2>
    <p>Olá, {{ $user->name }}.</p>
    <p>Você ainda não registrou nenhuma entrada ou saída na plataforma MeuNegócio.</p>
    <p>Para manter seus relatórios consistentes, registre as entradas e saídas do dia.</p>
    <p style="margin: 20px 0;">
        <a
            href="{{ $platformUrl }}"
            style="display: inline-block; background-color: #ea580c; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: 600;"
        >
            Acessar plataforma
        </a>
    </p>
    <hr style="margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;" />
    <p style="font-size: 12px; color: #4b5563;">
        Você pode cancelar ou alterar esta notificação a qualquer momento em
        <a href="{{ $unsubscribeUrl }}">Minha Conta</a>.
    </p>
</body>
</html>
