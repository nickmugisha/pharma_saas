<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\ClientAddress;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClientAddressController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $this->client($request);
        $data = $this->validated($request);
        $data['user_id'] = $user->id;
        $data['is_default'] = $request->boolean('is_default')
            || ! $user->clientAddresses()->exists();

        ClientAddress::create($data);

        return back()->with('success', 'Delivery address added.');
    }

    public function update(Request $request, ClientAddress $address): RedirectResponse
    {
        $user = $this->client($request);
        abort_unless($address->user_id === $user->id, 404);

        $data = $this->validated($request);
        $data['is_default'] = $request->boolean('is_default');
        $address->update($data);

        return back()->with('success', 'Delivery address updated.');
    }

    public function destroy(Request $request, ClientAddress $address): RedirectResponse
    {
        $user = $this->client($request);
        abort_unless($address->user_id === $user->id, 404);
        $address->delete();

        if (! $user->clientAddresses()->where('is_default', true)->exists()) {
            $user->clientAddresses()->oldest()->first()?->update(['is_default' => true]);
        }

        return back()->with('success', 'Delivery address removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'recipient_name' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:50'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'delivery_instructions' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function client(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active && $user->hasRole('client'), 403);
        return $user;
    }
}
