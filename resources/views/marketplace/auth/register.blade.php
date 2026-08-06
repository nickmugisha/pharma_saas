@extends('layouts.marketplace', ['title' => 'Create client account — PharmaMarket'])
@section('content')
<section class="auth-shell">
    <div class="auth-card auth-card-wide">
        <div class="auth-brand"><span class="brand-mark">+</span><div><strong>Join PharmaMarket</strong><small>One account across participating pharmacies</small></div></div>
        <h1>Create your account and wallet</h1>
        <p>Your zero-balance internal wallet is created automatically. Wallet funding and payment are handled securely inside the platform.</p>
        <form method="POST" action="{{ route('client.register.store') }}" class="auth-form grid gap-5 md:grid-cols-2">
            @csrf
            <label>Full name<input type="text" name="name" value="{{ old('name') }}" required autocomplete="name"></label>
            <label>Phone number<input type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel" placeholder="+257 ..."></label>
            <label class="md:col-span-2">Email address<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
            <label>Password<input type="password" name="password" required autocomplete="new-password"></label>
            <label>Confirm password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
            <label class="check-row md:col-span-2"><input type="checkbox" name="terms" value="1" required><span>I agree to responsible medicine ordering and pharmacy prescription review where required.</span></label>
            <button class="marketplace-button marketplace-button-primary md:col-span-2" type="submit">Create account and wallet</button>
        </form>
        <p class="auth-switch">Already registered? <a href="{{ route('client.login') }}">Sign in</a></p>
    </div>
</section>
@endsection
