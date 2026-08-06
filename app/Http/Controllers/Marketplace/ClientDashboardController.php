<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\ClientProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->client($request);
        $user->load([
            'clientProfile',
            'wallet',
            'clientAddresses',
            'marketplaceOrders' => fn ($query) => $query->latest()->limit(5),
            'clientPrescriptions' => fn ($query) => $query->latest()->limit(5),
        ]);

        return view('marketplace.client.dashboard', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->client($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'sex' => ['nullable', 'in:female,male,other,prefer_not_to_say'],
            'preferred_language' => ['required', 'in:fr,en'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ]);

        $user->update(['name' => trim($data['name'])]);

        ClientProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'phone' => trim($data['phone']),
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'sex' => $data['sex'] ?? null,
                'preferred_language' => $data['preferred_language'],
                'marketing_opt_in' => $request->boolean('marketing_opt_in'),
                'status' => 'active',
                'last_seen_at' => now(),
            ],
        );

        return back()->with('success', 'Your profile has been updated.');
    }

    private function client(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active && $user->hasRole('client'), 403);
        return $user;
    }
}
