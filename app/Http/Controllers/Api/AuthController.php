<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginGoogleRequest;
use App\Http\Requests\RegisterGoogleRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Company;
use App\Services\CompanyDefaultsService;
use App\Services\LoginThrottleService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(
        private CompanyDefaultsService $companyDefaultsService,
        private LoginThrottleService $loginThrottleService,
    )
    {
    }

    /**
     * Redirecionar para Google OAuth
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->scopes(['email', 'profile'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * Processar callback do Google OAuth
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $googleEmail = strtolower(trim((string) $googleUser->getEmail()));

            // Encontrar usuário existente
            $user = User::whereRaw('LOWER(email) = ?', [$googleEmail])->first();

            if (!$user) {
                $pendingData = [
                    'name' => $googleUser->getName() ?? $googleEmail,
                    'email' => $googleEmail,
                    'google_id' => $googleUser->getId(),
                ];

                session(['pending_registration' => $pendingData]);
                // ⭐ CRÍTICO: Codificar dados em Base64 e passar via URL
                $encoded = base64_encode(json_encode($pendingData));
                return redirect("/registre-se?google_data={$encoded}");
            }

            // ⭐ CRÍTICO: Criar sessão (para Sanctum SPA stateful) + gerar token
            \Illuminate\Support\Facades\Auth::login($user, remember: true);

            // Evita que uma pendência antiga force redirecionamento para registro.
            session()->forget('pending_registration');
            
            // Gerar token Sanctum para futura autenticação
            $token = $user->createToken('auth_token')->plainTextToken;
            
            // Armazenar na sessão também
            session(['sanctum_token' => $token]);

            return redirect('/oauth/callback');
        } catch (\Throwable $e) {
            Log::warning('Google callback failed.', [
                'message' => $e->getMessage(),
            ]);

            return redirect('/login?error=oauth_failed');
        }
    }

    /**
     * Login via Google OAuth (POST - para compatibilidade)
     */
    public function loginGoogle(LoginGoogleRequest $request)
    {
        try {
            // Validar token com Google
            $response = Http::get('https://www.googleapis.com/oauth2/v1/userinfo', [
                'access_token' => $request->token,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Token inválido',
                ], 401);
            }

            $googleUser = $response->json();

            // Encontrar ou criar usuário
            $user = User::where('email', $googleUser['email'])->first();
            $isNewUser = false;

            if (!$user) {
                $isNewUser = true;
                // Se o usuário não existe, retornar para que faça o registro
                return response()->json([
                    'message' => 'Usuário não encontrado. Por favor, registre-se.',
                    'is_new_user' => true,
                    'google_user' => [
                        'name' => $googleUser['name'] ?? $googleUser['email'],
                        'email' => $googleUser['email'],
                        'id' => $googleUser['id'],
                    ],
                ], 409); // Conflict - precisa fazer registro primeiro
            }

            // Gerar token Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'data' => [
                    'user' => $user->load('company'),
                    'token' => $token,
                    'is_new_user' => false,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Google login failed.', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao autenticar. Tente novamente em instantes.',
            ], 500);
        }
    }

    /**
     * Obter dados do Google pendentes para registro
     */
    public function pendingRegistration(Request $request)
    {
        // ⭐ Ler da sessão ao invés de cookie
        $pending = session('pending_registration');

        if (!$pending) {
            return response()->json([
                'data' => null,
                'pending' => false,
            ]);
        }

        return response()->json([
            'data' => $pending,
            'pending' => true,
        ]);
    }

    /**
     * Registrar novo usuário e empresa via Google OAuth
     */
    public function registerGoogle(RegisterGoogleRequest $request)
    {
        try {
            // Prioridade 1: sessão pendente (fluxo padrão OAuth)
            $pending = session('pending_registration');

            // Fallback: usar payload enviado pelo frontend quando a sessão expirar
            if (!$pending) {
                $googleUser = $request->input('google_user');

                if (is_array($googleUser) && !empty($googleUser['email']) && !empty($googleUser['google_id'])) {
                    $pending = [
                        'name' => $googleUser['name'] ?? $googleUser['email'],
                        'email' => $googleUser['email'],
                        'google_id' => $googleUser['google_id'],
                    ];
                }
            }

            if (!$pending) {
                return response()->json([
                    'message' => 'Registro pendente não encontrado. Por favor, faça login novamente.',
                ], 410);
            }

            if (User::where('email', $pending['email'])->exists()) {
                session()->forget('pending_registration');
                return response()->json([
                    'message' => 'Este email já possui uma conta ativa.',
                ], 409);
            }

            [$company, $user] = DB::transaction(function () use ($request, $pending) {
                $resolvedCompanyName = $this->resolveCompanyName(
                    $request->input('company_name'),
                    $pending['name'] ?? null,
                    $pending['email'] ?? null,
                );

                $company = Company::create([
                    'name' => $resolvedCompanyName,
                    'slug' => $this->generateUniqueCompanySlug($resolvedCompanyName),
                    'use_service_types' => false,
                ]);

                $user = User::create([
                    'company_id' => $company->id,
                    'name' => $pending['name'],
                    'email' => $pending['email'],
                    'google_id' => $pending['google_id'],
                    'role' => 'root',
                    'email_verified_at' => now(),
                ]);

                $company->forceFill([
                    'owner_id' => $user->id,
                ])->save();

                $this->companyDefaultsService->ensureForCompany((int) $company->id);

                return [$company, $user];
            });

            session()->forget('pending_registration');

            // ⭐ CRÍTICO: Criar sessão para Sanctum + gerar token
            \Illuminate\Support\Facades\Auth::login($user, remember: true);
            
            $token = $user->createToken('auth_token')->plainTextToken;
            session(['sanctum_token' => $token]);

            return response()->json([
                'data' => [
                    'user' => $user->load('company'),
                    'is_new_user' => true,
                    'message' => 'Conta criada com sucesso!',
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Google registration failed.', [
                'company_name' => $request->input('company_name'),
                'pending_email' => $pending['email'] ?? null,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Nao foi possivel concluir o cadastro agora. Tente novamente em instantes.',
            ], 500);
        }
    }

    private function generateUniqueCompanySlug(string $companyName): string
    {
        $baseSlug = Str::slug($companyName);

        if ($baseSlug === '') {
            $baseSlug = 'empresa';
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (Company::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function resolveCompanyName(?string $companyName, ?string $googleName, ?string $googleEmail): string
    {
        $baseName = trim((string) $companyName);

        if ($baseName === '') {
            $displayName = trim((string) $googleName);
            $firstName = trim(strtok($displayName, ' ') ?: '');

            if ($firstName !== '') {
                $baseName = 'Empresa de ' . $firstName;
            } elseif (!empty($googleEmail)) {
                $emailPrefix = trim((string) strstr($googleEmail, '@', true));
                $baseName = $emailPrefix !== '' ? 'Empresa de ' . $emailPrefix : 'Minha Empresa';
            } else {
                $baseName = 'Minha Empresa';
            }
        }

        return $this->generateUniqueCompanyName($baseName);
    }

    private function generateUniqueCompanyName(string $baseName): string
    {
        $name = $baseName;
        $suffix = 2;

        while (Company::withTrashed()->where('name', $name)->exists()) {
            $name = $baseName . ' ' . $suffix;
            $suffix++;
        }

        return $name;
    }

    /**
     * Obter usuário atual
     */
    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('company'),
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        
        // Se há um token atual, deleta ele. Senão, deleta todos os tokens
        if ($user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        } else {
            $user->tokens()->delete();
        }

        // Também encerra a sessão web para bloquear acesso às rotas Inertia protegidas.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Desconectado com sucesso',
        ]);
    }

    /**
     * Limpar bloqueio de tentativas de login para um e-mail.
     */
    public function unlockLoginAttempts(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'ip_address' => ['nullable', 'ip'],
        ]);

        $normalizedEmail = strtolower(trim((string) $validated['email']));
        $actor = $request->user();
        $targetUser = User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

        if (!$actor->isMasterAdmin()) {
            if (!$targetUser || $targetUser->company_id !== $actor->company_id) {
                return response()->json([
                    'message' => 'Voce não tem permissao para desbloquear este usuario.',
                ], 403);
            }
        }

        $knownIps = [];

        if (!empty($validated['ip_address'])) {
            $knownIps[] = $validated['ip_address'];
        }

        if ($targetUser) {
            $knownIps = array_merge(
                $knownIps,
                AuditLog::query()
                    ->where('user_id', $targetUser->id)
                    ->whereIn('action', ['login', 'login_failed'])
                    ->whereNotNull('ip_address')
                    ->distinct()
                    ->pluck('ip_address')
                    ->all()
            );
        }

        $knownIps = array_values(array_unique(array_filter($knownIps)));
        $clearedKeys = $this->loginThrottleService->clearAttempts($normalizedEmail, $knownIps);

        return response()->json([
            'data' => [
                'message' => 'Bloqueio de tentativas removido com sucesso.',
                'email' => $normalizedEmail,
                'ip_addresses' => $knownIps,
                'keys_cleared' => $clearedKeys,
            ],
        ]);
    }

    /**
     * Root fazer impersonate de outro usuário
     */
    public function impersonate(Request $request, User $user)
    {
        // Verificar se é root
        if (!$request->user()->isRoot()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // Verificar se ambos pertencem à mesma empresa
        if ($request->user()->company_id !== $user->company_id) {
            return response()->json([
                'message' => 'Usuário não pertence à sua empresa',
            ], 403);
        }

        // Gerar novo token para o usuário
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('company'),
            'token' => $token,
            'message' => 'Impersonação realizada com sucesso',
        ]);
    }
}
