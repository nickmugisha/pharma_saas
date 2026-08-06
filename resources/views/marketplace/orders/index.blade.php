@extends('layouts.marketplace', ['title' => 'My orders — PharmaMarket'])
@section('content')
<section class="marketplace-container py-10">
    <div class="section-heading"><div><span class="eyebrow">Order history</span><h1 class="page-title">My pharmacy orders</h1></div><a href="{{ route('marketplace.catalogue.index') }}">Shop medicines →</a></div>
    <div class="order-card-list mt-8">
        @forelse($orders as $order)
            <a href="{{ route('client.orders.show',$order) }}" class="order-card">
                <div><span class="order-number">{{ $order->order_number }}</span><h2>{{ $order->pharmacy?->name }}</h2><p>{{ $order->branch?->name }} • {{ ucfirst($order->fulfillment_method) }} • {{ $order->items->count() }} item(s)</p></div>
                <div class="order-card-status"><span class="status-chip">{{ str($order->status)->headline() }}</span><strong>{{ number_format((float)$order->grand_total,0) }} BIF</strong><small>{{ $order->placed_at?->format('d M Y H:i') }}</small></div>
            </a>
        @empty
            <div class="empty-panel"><strong>No marketplace orders yet.</strong><p>Choose a pharmacy offer and complete checkout to create your first reservation.</p></div>
        @endforelse
    </div>
    <div class="mt-8">{{ $orders->links() }}</div>
</section>
@endsection
