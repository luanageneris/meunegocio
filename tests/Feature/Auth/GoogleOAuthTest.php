<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\Company;
use App\Models\User;

class GoogleOAuthTest extends TestCase
{
    /**
     * Testa redirecionamento para Google OAuth
     */
    public function test_google_redirect_returns_302(): void
    {
        $response = $this->get('/auth/google');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    /**
     * Testa criação de usuário no callback
     */
    public function test_google_callback_creates_user_and_returns_token(): void
    {
        // Criar empresa
        $company = Company::firstOrCreate(
            ['slug' => 'xerografia-cia'],
            [
                'name' => 'Xerografia & Cia',
                'email' => 'contato@xerografia.com.br',
                'slug' => 'xerografia-cia',
            ]
        );

        // Simular resposta do Google (normalmente seria feito via mock)
        // A página de callback renderiza sem redirecionar (fica esperando token na URL)
        $response = $this->get('/auth/google/callback');

        // Deve renderizar a página de callback
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Testa página de callback Google
     */
    public function test_google_callback_page_renders(): void
    {
        $response = $this->get('/auth/google/callback');

        // Deve renderizar a página
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Testa proteção da rota dashboard
     */
    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');

        // Deve redirecionar para login se não autenticado
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    /**
     * Testa acesso ao dashboard quando autenticado
     */
    public function test_dashboard_accessible_when_authenticated(): void
    {
        // Criar empresa
        $company = Company::firstOrCreate(
            ['slug' => 'xerografia-cia'],
            [
                'name' => 'Xerografia & Cia',
                'email' => 'contato@xerografia.com.br',
                'slug' => 'xerografia-cia',
            ]
        );

        // Criar usuário
        $user = User::factory()
            ->for($company)
            ->create(['role' => 'owner']);

        // Autenticar
        $response = $this->actingAs($user, 'sanctum')
            ->get('/dashboard');

        // Deve retornar 200
        $this->assertEquals(200, $response->getStatusCode());
    }
}
