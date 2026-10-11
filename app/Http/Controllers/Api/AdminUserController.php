<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureMasterAdmin($request);

        $users = User::query()
            ->orderByDesc('last_login_at')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'company_id' => $user->company_id,
                    'last_login_at' => $user->last_login_at ? $user->last_login_at->toISOString() : null,
                    'created_at' => $user->created_at?->toISOString(),
                    'updated_at' => $user->updated_at?->toISOString(),
                    'is_master_admin' => $user->isMasterAdmin(),
                ];
            });

        return response()->json([
            'data' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureMasterAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['nullable', 'string', Rule::in(['owner'])],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
            'role' => 'owner',
            'email_verified_at' => now(),
            'company_id' => $request->company_id ?? null,
        ]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'last_login_at' => $user->last_login_at ? $user->last_login_at->toISOString() : null,
            ],
        ], 201);
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->ensureMasterAdmin($request);

        $newPassword = Str::password(12, true, true, false, false);

        $user->forceFill([
            'password' => Hash::make($newPassword),
        ])->save();

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'email' => $user->email,
                'new_password' => $newPassword,
            ],
            'message' => 'Senha redefinida com sucesso.',
        ]);
    }

    private function ensureMasterAdmin(Request $request): void
    {
        if (!$request->user() || !$request->user()->isMasterAdmin()) {
            abort(403, 'Acesso restrito ao administrador master.');
        }
    }
}
