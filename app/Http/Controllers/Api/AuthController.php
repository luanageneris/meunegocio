<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginGoogleRequest;
use App\Http\Requests\RegisterGoogleRequest;
use App\Models\User;
use App\Models\Company;
use App\Services\CompanyDefaultsService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(private CompanyDefaultsService $companyDefaultsService)
    {
    }

    /**
     * Redirecionar para Google OAuth
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->scopes(['email', 'profile'])
            ->redirect();
    }

    /**
     * Processar callback do Google OAuth
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            // Encontrar usuário existente
            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                $pendingData = [
                    'name' => $googleUser->getName() ?? $googleUser->getEmail(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'expires_at' => now()->addMinutes(10)->timestamp,
                ];

                $encrypted = encrypt(json_encode($pendingData));
                $cookie = cookie(
                    'pending_registration',
                    $encrypted,
                    10,
                    null,
                    null,
                    false,
                    true,
                    false,
                    'Lax'
                );

                return redirect('/registre-se')->withCookie($cookie);
            }

            // Gerar token Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;
            $userData = $user->load('company')->toArray();

            // Redirecionar para página de processamento OAuth com token na URL
            return redirect('/oauth/callback?' . http_build_query([
                'token' => $token,
                'user' => json_encode($userData),
            ]));
        } catch (\Exception $e) {
            return redirect('/login?error=' . urlencode($e->getMessage()));
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
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao autenticar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obter dados do Google pendentes para registro
     */
    public function pendingRegistration(Request $request)
    {
        $cookie = $request->cookie('pending_registration');

        if (!$cookie) {
            return response()->json([
                'message' => 'Nenhum registro pendente encontrado.',
            ], 404);
        }

        try {
            $pending = json_decode(decrypt($cookie), true);
        } catch (DecryptException | \Exception $e) {
            Cookie::queue(Cookie::forget('pending_registration'));
            return response()->json([
                'message' => 'Registro pendente inválido ou expirado.',
            ], 410);
        }

        if (($pending['expires_at'] ?? 0) < now()->timestamp) {
            Cookie::queue(Cookie::forget('pending_registration'));
            return response()->json([
                'message' => 'Registro pendente expirado.',
            ], 410);
        }

        return response()->json([
            'data' => $pending,
        ]);
    }

    /**
     * Registrar novo usuário e empresa via Google OAuth
     */
    public function registerGoogle(RegisterGoogleRequest $request)
    {
        try {
            $cookie = $request->cookie('pending_registration');

            if (!$cookie) {
                return response()->json([
                    'message' => 'Registro pendente não encontrado ou expirado.',
                ], 410);
            }

            try {
                $pending = json_decode(decrypt($cookie), true);
            } catch (DecryptException $e) {
                Cookie::queue(Cookie::forget('pending_registration'));
                return response()->json([
                    'message' => 'Registro pendente inválido ou expirado.',
                ], 410);
            }

            if (($pending['expires_at'] ?? 0) < now()->timestamp) {
                Cookie::queue(Cookie::forget('pending_registration'));
                return response()->json([
                    'message' => 'Registro pendente expirado.',
                ], 410);
            }

            if (User::where('email', $pending['email'])->exists()) {
                Cookie::queue(Cookie::forget('pending_registration'));
                return response()->json([
                    'message' => 'Este email já possui uma conta ativa.',
                ], 409);
            }

            // Criar nova empresa
            $company = Company::create([
                'name' => $request->company_name,
            ]);

            // Criar novo usuário root para essa empresa
            $user = User::create([
                'company_id' => $company->id,
                'name' => $pending['name'],
                'email' => $pending['email'],
                'google_id' => $pending['google_id'],
                'role' => 'root',
                'email_verified_at' => now(),
            ]);

            $this->companyDefaultsService->ensureForCompany((int) $company->id);

            Cookie::queue(Cookie::forget('pending_registration'));

            // Gerar token Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'data' => [
                    'user' => $user->load('company'),
                    'token' => $token,
                    'is_new_user' => true,
                    'message' => 'Conta criada com sucesso!',
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao registrar: ' . $e->getMessage(),
            ], 500);
        }
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

        return response()->json([
            'message' => 'Desconectado com sucesso',
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
