@extends('layouts.marketplace', ['title' => 'Client sign in — PharmaMarket'])
@section('content')
<section class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand"><span class="brand-mark">+</span><div><strong>Welcome back</strong><small>Sign in to your client marketplace</small></div></div>
        <h1>Continue to your medicines and orders</h1>
        <p>Browsing is public. An account and wallet are required to reserve or order.</p>
        <form method="POST" action="{{ route('client.login.store') }}" class="auth-form">
            @csrf
            <label>Email address<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"></label>
            <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
            <label class="check-row"><input type="checkbox" name="remember" value="1"><span>Keep me signed in</span></label>
            <button class="marketplace-button marketplace-button-primary w-full" type="submit">Sign in</button>
        </form>
        <p class="auth-switch">New to PharmaMarket? <a href="{{ route('client.register') }}">Create your account and wallet</a></p>
    </div>
</section>
@endsection
