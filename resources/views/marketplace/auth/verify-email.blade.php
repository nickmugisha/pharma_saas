@extends('layouts.marketplace', ['title' => 'Verify your email — PharmaMarket'])
@section('content')
<section class="auth-shell">
    <div class="auth-card text-center">
        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-3xl font-black text-emerald-700">@</div>
        <h1>Verify your email</h1>
        <p>We sent a secure verification link to <strong>{{ auth()->user()->email }}</strong>. Verification is required before reservations and orders.</p>
        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">@csrf<button class="marketplace-button marketplace-button-primary" type="submit">Resend verification email</button></form>
        <form method="POST" action="{{ route('client.logout') }}" class="mt-3">@csrf<button class="text-sm font-bold text-slate-500" type="submit">Sign out</button></form>
    </div>
</section>
@endsection
