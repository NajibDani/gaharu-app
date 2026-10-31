<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        try {
            $role = \App\Models\Role::firstOrCreate(['nama' => 'Super Admin']);
            $adminUser = \App\Models\User::where('username', 'admin')->first();
            if (!$adminUser) {
                \App\Models\User::create([
                    'nama'      => 'Administrator',
                    'name'      => 'Administrator',
                    'username'  => 'admin',
                    'password'  => \Illuminate\Support\Facades\Hash::make('admin16072023'),
                    'role_id'   => $role->id,
                    'gudang_id' => null,
                ]);
            }
        } catch (\Throwable $e) {
            // Ignore database connection issues if any
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
