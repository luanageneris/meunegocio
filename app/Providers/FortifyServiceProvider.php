<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\IpLocationService;
use App\Services\LoginThrottleService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureLoginTracking();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => false,
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        $loginThrottle = app(LoginThrottleService::class);
        $ipLocationService = app(IpLocationService::class);

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) use ($loginThrottle, $ipLocationService) {
            $email = (string) $request->input(Fortify::username());
            $throttleKey = $loginThrottle->makeThrottleKey($email, $request->ip());

            return Limit::perMinutes(LoginThrottleService::DECAY_MINUTES, LoginThrottleService::MAX_ATTEMPTS)
                ->by($throttleKey)
                ->response(function (Request $request, array $headers) use ($ipLocationService) {
                    $seconds = (int) ($headers['Retry-After'] ?? LoginThrottleService::DECAY_MINUTES * 60);
                    $minutes = (int) ceil($seconds / 60);
                    $message = "Muitas tentativas de login. Tente novamente em {$minutes} minuto(s).";

                    $email = strtolower(trim((string) $request->input(Fortify::username())));
                    if ($email !== '') {
                        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

                        if ($user && $user->company_id) {
                            $ipAddress = $request->ip();

                            AuditLog::create([
                                'company_id' => $user->company_id,
                                'user_id' => $user->id,
                                'action' => 'login_failed',
                                'model' => User::class,
                                'model_id' => $user->id,
                                'old_values' => null,
                                'new_values' => [
                                    'event' => 'login_locked',
                                    'attempted_at' => now()->toISOString(),
                                    'ip_address' => $ipAddress,
                                    'location' => $ipLocationService->resolve($ipAddress),
                                    'retry_after_seconds' => $seconds,
                                ],
                                'changes' => null,
                                'ip_address' => $ipAddress,
                                'user_agent' => $request->userAgent(),
                                'endpoint' => $request->path() ?? 'login',
                            ]);
                        }
                    }

                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => $message,
                            'errors' => [
                                Fortify::username() => [$message],
                            ],
                        ], 429, $headers);
                    }

                    return back()
                        ->withInput($request->only(Fortify::username()))
                        ->withErrors([Fortify::username() => $message]);
                });
        });
    }

    private function configureLoginTracking(): void
    {
        $ipLocationService = app(IpLocationService::class);

        Event::listen(Login::class, function (Login $event) use ($ipLocationService): void {
            $user = $event->user;

            if (!$user instanceof User) {
                return;
            }

            $request = request();
            $ipAddress = $request?->ip();

            $user->forceFill([
                'last_login_at' => now(),
            ])->saveQuietly();

            if (!$user->company_id) {
                return;
            }

            AuditLog::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'action' => 'login',
                'model' => User::class,
                'model_id' => $user->id,
                'old_values' => null,
                'new_values' => [
                    'event' => 'login_success',
                    'login_at' => now()->toISOString(),
                    'ip_address' => $ipAddress,
                    'location' => $ipLocationService->resolve($ipAddress),
                ],
                'changes' => null,
                'ip_address' => $ipAddress,
                'user_agent' => $request?->userAgent(),
                'endpoint' => $request?->path() ?? 'login',
            ]);
        });

        Event::listen(Failed::class, function (Failed $event) use ($ipLocationService): void {
            $credentials = $event->credentials;
            $email = strtolower(trim((string) ($credentials[Fortify::username()] ?? '')));

            if ($email === '') {
                return;
            }

            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if (!$user || !$user->company_id) {
                return;
            }

            $request = request();
            $ipAddress = $request?->ip();

            AuditLog::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'action' => 'login_failed',
                'model' => User::class,
                'model_id' => $user->id,
                'old_values' => null,
                'new_values' => [
                    'event' => 'login_failed',
                    'attempted_at' => now()->toISOString(),
                    'ip_address' => $ipAddress,
                    'location' => $ipLocationService->resolve($ipAddress),
                ],
                'changes' => null,
                'ip_address' => $ipAddress,
                'user_agent' => $request?->userAgent(),
                'endpoint' => $request?->path() ?? 'login',
            ]);
        });

    }
}
