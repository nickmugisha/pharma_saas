@extends('layouts.marketplace', ['title' => 'My account — PharmaMarket'])
@section('content')
<section class="dashboard-banner">
    <div class="marketplace-container flex flex-col justify-between gap-6 py-10 md:flex-row md:items-center">
        <div><span class="eyebrow eyebrow-light">Client account</span><h1>Hello, {{ $user->name }}</h1><p>Manage your wallet, profile, addresses, prescriptions and orders.</p></div>
        <div class="flex gap-3"><a href="{{ route('marketplace.catalogue.index') }}" class="marketplace-button marketplace-button-light">Continue shopping</a><a href="{{ route('client.orders.index') }}" class="marketplace-button marketplace-button-outline-light">View orders</a></div>
    </div>
</section>

<section class="marketplace-container py-10">
    <div class="dashboard-metrics">
        <div class="metric-card"><span>Wallet</span><strong>{{ number_format((float)$user->wallet?->available_balance, 2) }} BIF</strong><small>{{ $user->wallet?->wallet_number ?? 'Not available' }}</small></div>
        <div class="metric-card"><span>Orders</span><strong>{{ $user->marketplaceOrders()->count() }}</strong><small>Across all pharmacies</small></div>
        <div class="metric-card"><span>Prescriptions</span><strong>{{ $user->clientPrescriptions()->count() }}</strong><small>Secure private documents</small></div>
        <div class="metric-card"><span>Addresses</span><strong>{{ $user->clientAddresses->count() }}</strong><small>Pickup or delivery ready</small></div>
    </div>

    <div class="dashboard-grid mt-8">
        <section class="dashboard-panel">
            <div class="panel-heading"><div><h2>Profile</h2><p>Your platform-wide client identity.</p></div></div>
            <form method="POST" action="{{ route('client.profile.update') }}" class="form-grid">@csrf @method('PATCH')
                <label>Full name<input name="name" value="{{ old('name', $user->name) }}" required></label>
                <label>Phone<input name="phone" value="{{ old('phone', $user->clientProfile?->phone) }}" required></label>
                <label>Date of birth<input type="date" name="date_of_birth" value="{{ old('date_of_birth', $user->clientProfile?->date_of_birth?->format('Y-m-d')) }}"></label>
                <label>Sex<select name="sex"><option value="">Prefer not to say</option>@foreach(['female'=>'Female','male'=>'Male','other'=>'Other','prefer_not_to_say'=>'Prefer not to say'] as $value=>$label)<option value="{{ $value }}" @selected(old('sex',$user->clientProfile?->sex)===$value)>{{ $label }}</option>@endforeach</select></label>
                <label>Preferred language<select name="preferred_language"><option value="fr" @selected(($user->clientProfile?->preferred_language ?? 'fr')==='fr')>French</option><option value="en" @selected($user->clientProfile?->preferred_language==='en')>English</option></select></label>
                <label class="check-row self-end"><input type="checkbox" name="marketing_opt_in" value="1" @checked($user->clientProfile?->marketing_opt_in)><span>Receive marketplace updates</span></label>
                <button class="marketplace-button marketplace-button-primary md:col-span-2" type="submit">Save profile</button>
            </form>
        </section>

        <section class="dashboard-panel">
            <div class="panel-heading"><div><h2>Internal wallet</h2><p>Required for marketplace payment.</p></div><span class="status-chip status-active">{{ ucfirst($user->wallet?->status ?? 'missing') }}</span></div>
            <div class="wallet-card"><span>Available balance</span><strong>{{ number_format((float)$user->wallet?->available_balance, 2) }} BIF</strong><small>{{ $user->wallet?->wallet_number }}</small></div>
            <div class="info-note mt-5">Your balance is calculated from an immutable credit and debit ledger. Funding requests require finance approval.</div>
            <a href="{{ route('client.wallet.index') }}" class="marketplace-button marketplace-button-primary mt-5 w-full">Open wallet and funding</a>
        </section>
    </div>

    <div class="dashboard-grid mt-8">
        <section class="dashboard-panel">
            <div class="panel-heading"><div><h2>Delivery addresses</h2><p>Add a destination before choosing delivery.</p></div></div>
            <div class="space-y-3">
                @forelse($user->clientAddresses as $address)
                    <div class="address-card"><div><strong>{{ $address->label }} @if($address->is_default)<span class="status-chip status-active">Default</span>@endif</strong><p>{{ $address->recipient_name }} • {{ $address->phone }}</p><small>{{ $address->address_line_1 }}, {{ $address->city }}</small></div><form method="POST" action="{{ route('client.addresses.destroy',$address) }}">@csrf @method('DELETE')<button type="submit" class="danger-link">Remove</button></form></div>
                @empty
                    <p class="empty-mini">No delivery address yet.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('client.addresses.store') }}" class="form-grid mt-6">@csrf
                <label>Label<input name="label" value="Home" required></label><label>Recipient<input name="recipient_name" value="{{ $user->name }}" required></label>
                <label>Phone<input name="phone" value="{{ $user->clientProfile?->phone }}" required></label><label>Address<input name="address_line_1" required></label>
                <label>City<input name="city" value="Bujumbura" required></label><label>Province<input name="province"></label>
                <input type="hidden" name="country" value="Burundi"><label class="check-row md:col-span-2"><input type="checkbox" name="is_default" value="1"><span>Use as default address</span></label>
                <button class="marketplace-button marketplace-button-soft md:col-span-2" type="submit">Add address</button>
            </form>
        </section>

        <section class="dashboard-panel">
            <div class="panel-heading"><div><h2>Prescription vault</h2><p>Private documents for restricted medicines.</p></div></div>
            <div class="space-y-3">
                @forelse($user->clientPrescriptions as $prescription)
                    <a href="{{ route('client.prescriptions.download',$prescription) }}" class="prescription-file"><span>Rx</span><div><strong>{{ $prescription->original_name }}</strong><small>{{ $prescription->prescription_number }} • {{ str($prescription->status)->headline() }}</small></div></a>
                @empty
                    <p class="empty-mini">No prescription documents uploaded.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('client.prescriptions.store') }}" enctype="multipart/form-data" class="form-grid mt-6">@csrf
                <label>Prescriber<input name="prescriber_name"></label><label>Facility<input name="prescriber_facility"></label>
                <label>Issue date<input type="date" name="issued_at"></label><label>Valid until<input type="date" name="valid_until"></label>
                <label class="md:col-span-2">PDF or image<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" required></label>
                <button class="marketplace-button marketplace-button-soft md:col-span-2" type="submit">Upload securely</button>
            </form>
        </section>
    </div>

    <section class="dashboard-panel mt-8">
        <div class="panel-heading"><div><h2>Recent orders</h2><p>Each pharmacy manages its own order independently.</p></div><a href="{{ route('client.orders.index') }}">View all →</a></div>
        <div class="order-table-wrap"><table class="order-table"><thead><tr><th>Order</th><th>Pharmacy</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
            @forelse($user->marketplaceOrders as $order)<tr><td><strong>{{ $order->order_number }}</strong><small>{{ $order->placed_at?->format('d M Y H:i') }}</small></td><td>{{ $order->pharmacy?->name }}</td><td><span class="status-chip">{{ str($order->status)->headline() }}</span></td><td>{{ number_format((float)$order->grand_total,0) }} BIF</td><td><a href="{{ route('client.orders.show',$order) }}">View</a></td></tr>@empty<tr><td colspan="5" class="empty-mini">No orders yet.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</section>
@endsection
