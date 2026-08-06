<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Platform activity</x-slot>
        <x-slot name="description">A concise live trail across accounts, wallets, pharmacies and marketplace orders.</x-slot>

        <style>
            .pm-activity-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .pm-activity-card {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 15px;
                border: 1px solid #e5e7eb;
                border-radius: 16px;
                background: linear-gradient(145deg, rgba(255,255,255,.98), rgba(248,250,252,.92));
                box-shadow: 0 6px 20px rgba(15, 23, 42, .05);
            }

            .pm-activity-icon {
                width: 40px;
                height: 40px;
                min-width: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 12px;
                border: 1px solid var(--pm-tone-border);
                background: var(--pm-tone-bg);
                color: var(--pm-tone-text);
            }

            .pm-activity-body {
                min-width: 0;
                flex: 1;
            }

            .pm-activity-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
            }

            .pm-activity-title {
                margin: 0;
                font-size: 14px;
                line-height: 1.35;
                font-weight: 700;
                color: #111827;
            }

            .pm-activity-time {
                flex: none;
                font-size: 12px;
                line-height: 1.35;
                color: #6b7280;
                white-space: nowrap;
            }

            .pm-activity-description {
                margin: 5px 0 0;
                font-size: 13px;
                line-height: 1.5;
                color: #4b5563;
                overflow-wrap: anywhere;
            }

            .pm-activity-empty {
                grid-column: 1 / -1;
                padding: 28px;
                border: 1px dashed #cbd5e1;
                border-radius: 16px;
                text-align: center;
                font-size: 13px;
                color: #64748b;
            }

            .dark .pm-activity-card {
                border-color: rgba(255,255,255,.10);
                background: linear-gradient(145deg, rgba(17,24,39,.96), rgba(15,23,42,.92));
            }

            .dark .pm-activity-title { color: #f9fafb; }
            .dark .pm-activity-time { color: #9ca3af; }
            .dark .pm-activity-description { color: #d1d5db; }
            .dark .pm-activity-empty { border-color: #374151; color: #9ca3af; }

            @media (max-width: 900px) {
                .pm-activity-grid { grid-template-columns: 1fr; }
            }
        </style>

        <div class="pm-activity-grid">
            @forelse ($this->getActivities() as $activity)
                @php
                    [$toneBg, $toneText, $toneBorder] = match ($activity['tone']) {
                        'success' => ['#ecfdf5', '#047857', '#a7f3d0'],
                        'danger' => ['#fef2f2', '#b91c1c', '#fecaca'],
                        'warning' => ['#fffbeb', '#b45309', '#fde68a'],
                        default => ['#eff6ff', '#1d4ed8', '#bfdbfe'],
                    };
                @endphp

                <article class="pm-activity-card">
                    <div
                        class="pm-activity-icon"
                        style="--pm-tone-bg: {{ $toneBg }}; --pm-tone-text: {{ $toneText }}; --pm-tone-border: {{ $toneBorder }};"
                    >
                        <x-filament::icon
                            :icon="$activity['icon']"
                            style="width: 20px; height: 20px;"
                        />
                    </div>

                    <div class="pm-activity-body">
                        <div class="pm-activity-head">
                            <p class="pm-activity-title">{{ $activity['title'] }}</p>
                            <span class="pm-activity-time">{{ $activity['relative_time'] }}</span>
                        </div>
                        <p class="pm-activity-description">{{ $activity['description'] }}</p>
                    </div>
                </article>
            @empty
                <div class="pm-activity-empty">
                    No platform activity has been recorded yet.
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
