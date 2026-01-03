<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginGoogleRequest;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
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
            
            // Encontrar ou criar usuário
            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                // Determinar empresa (padrão: Xerografia & Cia)
                $company = Company::first();

                if (!$company) {
                    return redirect('/login?error=company_not_found');
                }

                // Criar novo usuário
                $user = User::create([
                    'company_id' => $company->id,
                    'name' => $googleUser->getName() ?? $googleUser->getEmail(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'role' => 'owner',
                    'email_verified_at' => now(),
                ]);
            }

            // Gerar token Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;
            $userData = $user->load('company')->toArray();

            // Redirecionar para página de callback com token na URL
            return redirect('/auth/google/callback?' . http_build_query([
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

            if (!$user) {
                // Determinar empresa
                $company = null;
                if ($request->company_id) {
                    $company = Company::find($request->company_id);
                } else {
                    // Se não especificar, usa a primeira empresa (Xerografia & Cia para MVP)
                    $company = Company::first();
                }

                if (!$company) {
                    return response()->json([
                        'message' => 'Empresa não encontrada',
                    ], 404);
                }

                // Criar novo usuário
                $user = User::create([
                    'company_id' => $company->id,
                    'name' => $googleUser['name'] ?? $googleUser['email'],
                    'email' => $googleUser['email'],
                    'google_id' => $googleUser['id'],
                    'role' => 'owner',
                    'email_verified_at' => now(),
                ]);
            }

            // Gerar token Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => $user->load('company'),
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao autenticar: ' . $e->getMessage(),
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
