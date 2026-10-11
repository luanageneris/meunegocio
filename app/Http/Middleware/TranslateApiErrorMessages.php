<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TranslateApiErrorMessages
{
    /**
     * @var array<string, string>
     */
    private const MESSAGE_MAP = [
        'user must be associated with a company.' => 'Usuário deve estar associado a uma empresa.',
        'unauthorized' => 'Não autorizado',
        'forbidden' => 'Acesso negado',
        'unauthenticated.' => 'Não autenticado.',
        'unauthorized. only root users can perform this action.' => 'Não autorizado. Apenas usuários root podem executar esta ação.',
        'these credentials do not match our records.' => 'As credenciais informadas nao correspondem aos nossos registros.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);

        if (!is_array($payload)) {
            return $response;
        }

        $translatedPayload = $this->translateArray($payload);

        if ($translatedPayload !== $payload) {
            $response->setData($translatedPayload);
        }

        return $response;
    }

    /**
     * @param mixed[] $payload
     * @return mixed[]
     */
    private function translateArray(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_string($value) && in_array($key, ['message', 'error'], true)) {
                $payload[$key] = $this->translateMessage($value);
                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->translateArray($value);
            }
        }

        return $payload;
    }

    private function translateMessage(string $message): string
    {
        $normalized = mb_strtolower(trim($message));

        return self::MESSAGE_MAP[$normalized] ?? $message;
    }
}
