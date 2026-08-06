@extends('layouts.marketplace', ['title' => $order->order_number.' — PharmaMarket'])
@section('content')
<section class="marketplace-container py-10">
    <nav class="breadcrumb"><a href="{{ route('client.orders.index') }}">My orders</a><span>›</span><span>{{ $order->order_number }}</span></nav>
    <div class="order-hero mt-6"><div><span class="eyebrow eyebrow-light">{{ $order->order_number }}</span><h1>{{ $order->pharmacy?->name }}</h1><p>{{ $order->branch?->name }} • {{ ucfirst($order->fulfillment_method) }}</p></div><div class="text-right"><span class="status-chip status-large">{{ str($order->status)->headline() }}</span><strong>{{ number_format((float)$order->grand_total,0) }} BIF</strong></div></div>

    <div class="order-detail-grid mt-8">
        <div class="space-y-6">
            <section class="checkout-panel"><h2>Medicines</h2><div class="space-y-4 mt-5">@foreach($order->items as $item)<div class="order-line"><div><strong>{{ $item->medicine_name }}</strong><small>{{ collect([$item->strength,$item->dosage_form])->filter()->implode(' • ') }} • Qty {{ $item->quantity }}</small>@include('marketplace.partials.mode-badge',['mode'=>$item->online_sale_mode])</div><div class="text-right"><strong>{{ number_format((float)$item->line_total,0) }} BIF</strong><small>{{ str($item->prescription_review_status)->headline() }}</small></div></div>@endforeach</div></section>

            <section class="checkout-panel"><h2>Order progress</h2><div class="timeline mt-5">@foreach($order->events as $event)<div class="timeline-item"><span></span><div><strong>{{ $event->title }}</strong><p>{{ $event->description }}</p><small>{{ $event->occurred_at?->format('d M Y H:i') }} • {{ $event->actorUser?->name ?? 'System' }}</small></div></div>@endforeach</div></section>
        </div>

        <aside class="space-y-6">
            <section class="checkout-summary"><h2>Summary</h2><div class="summary-row"><span>Products</span><strong>{{ number_format((float)$order->subtotal,0) }} BIF</strong></div><div class="summary-row"><span>Delivery</span><strong>{{ number_format((float)$order->delivery_fee,0) }} BIF</strong></div><div class="summary-total"><span>Total</span><strong>{{ number_format((float)$order->grand_total,0) }} BIF</strong></div><div class="summary-row"><span>Payment</span><strong>{{ str($order->payment_status)->headline() }}</strong></div><div class="summary-row"><span>Wallet</span><strong>{{ $order->wallet?->wallet_number }}</strong></div>@if($order->walletPaymentTransaction)<div class="summary-row"><span>Transaction</span><strong>{{ $order->walletPaymentTransaction->transaction_number }}</strong></div>@endif</section>

            @if($order->status==='awaiting_wallet_payment')
                <section class="prescription-notice prescription-notice-blue"><strong>Stock temporarily reserved</strong><p>Reservation expires {{ $order->reservation_expires_at?->diffForHumans() }}. Your wallet balance is {{ number_format((float)$order->wallet?->available_balance,2) }} BIF.</p>
                    @if((float)$order->wallet?->available_balance + 0.005 >= (float)$order->grand_total)
                        <form method="POST" action="{{ route('client.orders.pay',$order) }}" class="mt-4">@csrf<button class="marketplace-button marketplace-button-primary w-full" type="submit">Pay {{ number_format((float)$order->grand_total,0) }} BIF from wallet</button></form>
                    @else
                        <a href="{{ route('client.wallet.index') }}" class="marketplace-button marketplace-button-soft mt-4 w-full">Request wallet funding</a>
                    @endif
                </section>
            @elseif($order->status==='awaiting_prescription_review')
                <section class="prescription-notice"><strong>Pharmacy review pending</strong><p>Stock will be reserved only after the selected pharmacy approves the prescription requirements.</p></section>
            @endif

            @if(in_array($order->status,['awaiting_prescription_review','awaiting_wallet_payment'],true))
                <form method="POST" action="{{ route('client.orders.cancel',$order) }}">@csrf<button class="marketplace-button marketplace-button-danger w-full" type="submit">Cancel order</button></form>
            @endif
        </aside>
    </div>
</section>
@endsection
