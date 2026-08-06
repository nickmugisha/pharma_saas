<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\ClientProfile;
use App\Models\ClientWallet;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientAuthController extends Controller
{
    public function showLogin(Request $request): View
    {
        $this->rememberSafeIntendedUrl($request);
        return view('marketplace.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
        }

        $request->session()->regenerate();
        $user = $request->user();

        if (
            ! $user instanceof User
            || ! $user->is_active
            || ! $user->hasRole('client')
        ) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'This account cannot access the client marketplace.',
            ]);
        }

        return redirect()->intended(route('client.dashboard'));
    }

    public function showRegister(Request $request): View
    {
        $this->rememberSafeIntendedUrl($request);
        return view('marketplace.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50'],
            'password' => ['required', 'confirmed', 'min:8'],
            'terms' => ['accepted'],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
            ]);

            $user->assignRole('client');

            ClientProfile::create([
                'user_id' => $user->id,
                'phone' => trim($data['phone']),
                'status' => 'active',
            ]);

            ClientWallet::create([
                'user_id' => $user->id,
                'status' => 'active',
                'currency' => 'BIF',
            ]);

            return $user;
        });

        Auth::login($user);
        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice')
            ->with('success', 'Your account and wallet were created. Verify your email to reserve medicines.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('marketplace.home');
    }

    private function rememberSafeIntendedUrl(Request $request): void
    {
        $redirect = $request->query('redirect');

        if (is_string($redirect) && str_starts_with($redirect, url('/'))) {
            $request->session()->put('url.intended', $redirect);
        }
    }
}
