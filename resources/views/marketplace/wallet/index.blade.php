@extends('layouts.marketplace', ['title' => 'My wallet — PharmaMarket'])
@section('content')
<section class="dashboard-banner">
    <div class="marketplace-container flex flex-col justify-between gap-6 py-10 md:flex-row md:items-center">
        <div>
            <span class="eyebrow eyebrow-light">Internal wallet</span>
            <h1>{{ $wallet->wallet_number }}</h1>
            <p>Fund your account, review every ledger entry and pay marketplace orders securely.</p>
        </div>
        <div class="wallet-balance-hero">
            <span>Available balance</span>
            <strong>{{ number_format((float) $wallet->available_balance, 2) }} BIF</strong>
            <small>{{ str($wallet->status)->headline() }}</small>
        </div>
    </div>
</section>

<section class="marketplace-container py-10">
    <div class="dashboard-grid">
        <section class="dashboard-panel">
            <div class="panel-heading">
                <div>
                    <h2>Request wallet funding</h2>
                    <p>Funding is simulated and becomes spendable only after finance approval.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('client.wallet.funding.store') }}" class="form-grid">
                @csrf
                <label>
                    Amount
                    <input type="number" name="amount" min="1000" max="5000000" step="100" value="{{ old('amount', 50000) }}" required>
                </label>

                <label>
                    Funding method
                    <select name="funding_method" required>
                        <option value="demo_credit" @selected(old('funding_method') === 'demo_credit')>Demo credit</option>
                        <option value="cash_deposit" @selected(old('funding_method') === 'cash_deposit')>Cash deposit</option>
                        <option value="mobile_money" @selected(old('funding_method') === 'mobile_money')>Mobile money</option>
                        <option value="bank_transfer" @selected(old('funding_method') === 'bank_transfer')>Bank transfer</option>
                    </select>
                </label>

                <label class="md:col-span-2">
                    External reference
                    <input name="external_reference" value="{{ old('external_reference') }}" placeholder="Optional simulated transaction reference">
                </label>

                <label class="md:col-span-2">
                    Note
                    <textarea name="notes" rows="3" placeholder="Optional note for finance review">{{ old('notes') }}</textarea>
                </label>

                <button class="marketplace-button marketplace-button-primary md:col-span-2" type="submit">
                    Submit funding request
                </button>
            </form>
        </section>

        <section class="dashboard-panel">
            <div class="panel-heading">
                <div>
                    <h2>Funding requests</h2>
                    <p>Pending requests do not change your balance.</p>
                </div>
            </div>

            <div class="wallet-request-list">
                @forelse($fundingRequests as $fundingRequest)
                    <article class="wallet-request-card">
                        <div>
                            <strong>{{ $fundingRequest->request_number }}</strong>
                            <small>{{ str($fundingRequest->funding_method)->headline() }} • {{ $fundingRequest->requested_at?->format('d M Y H:i') }}</small>
                        </div>
                        <div class="text-right">
                            <strong>{{ number_format((float) $fundingRequest->amount, 2) }} BIF</strong>
                            <span class="status-chip">{{ str($fundingRequest->status)->headline() }}</span>
                        </div>
                        @if($fundingRequest->rejection_reason)
                            <p class="wallet-request-reason">{{ $fundingRequest->rejection_reason }}</p>
                        @endif
                    </article>
                @empty
                    <p class="empty-mini">No funding request has been submitted.</p>
                @endforelse
            </div>

            <div class="mt-6">{{ $fundingRequests->links() }}</div>
        </section>
    </div>

    <section class="dashboard-panel mt-8">
        <div class="panel-heading">
            <div>
                <h2>Immutable wallet ledger</h2>
                <p>Your balance is derived from credits minus debits. Entries cannot be edited or deleted.</p>
            </div>
        </div>

        <div class="order-table-wrap">
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Transaction</th>
                        <th>Type</th>
                        <th>Direction</th>
                        <th>Amount</th>
                        <th>Balance after</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        <tr>
                            <td>
                                <strong>{{ $transaction->transaction_number }}</strong>
                                <small>{{ $transaction->description }}</small>
                            </td>
                            <td>{{ str($transaction->type)->headline() }}</td>
                            <td>
                                <span class="status-chip {{ $transaction->direction === 'credit' ? 'status-active' : '' }}">
                                    {{ str($transaction->direction)->headline() }}
                                </span>
                            </td>
                            <td>{{ number_format((float) $transaction->amount, 2) }} BIF</td>
                            <td><strong>{{ number_format((float) $transaction->balance_after, 2) }} BIF</strong></td>
                            <td>{{ $transaction->occurred_at?->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-mini">No wallet transaction yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $transactions->links() }}</div>
    </section>
</section>
@endsection
