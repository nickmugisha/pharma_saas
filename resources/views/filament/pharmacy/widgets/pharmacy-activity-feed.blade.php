<x-filament-widgets::widget>
    @php
    $context = $this->getContextSummary();
@endphp

    <x-filament::section>
        <x-slot name="heading">Operational activity</x-slot>
        <x-slot name="description">Only events relevant to {{ $context['role'] }} are shown for {{ $context['branch'] }}.</x-slot>

        <style>
            .ph-activity-context {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 9px;
                margin-bottom: 16px;
                padding: 12px 14px;
                border: 1px solid #bbf7d0;
                border-radius: 14px;
                background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
                color: #166534;
                font-size: 13px;
            }

            .ph-activity-context strong { font-weight: 800; }

            .ph-activity-role {
                margin-left: auto;
                padding: 5px 10px;
                border: 1px solid #bbf7d0;
                border-radius: 999px;
                background: #ffffff;
                font-weight: 700;
                color: #047857;
                box-shadow: 0 3px 10px rgba(5, 150, 105, .08);
            }

            .ph-activity-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .ph-activity-card {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 15px;
                border: 1px solid #e5e7eb;
                border-radius: 16px;
                background: linear-gradient(145deg, rgba(255,255,255,.98), rgba(248,250,252,.92));
                box-shadow: 0 6px 20px rgba(15, 23, 42, .05);
            }

            .ph-activity-icon {
                width: 40px;
                height: 40px;
                min-width: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 12px;
                border: 1px solid var(--ph-tone-border);
                background: var(--ph-tone-bg);
                color: var(--ph-tone-text);
            }

            .ph-activity-body { min-width: 0; flex: 1; }

            .ph-activity-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
            }

            .ph-activity-title {
                margin: 0;
                font-size: 14px;
                line-height: 1.35;
                font-weight: 700;
                color: #111827;
            }

            .ph-activity-time {
                flex: none;
                font-size: 12px;
                line-height: 1.35;
                color: #6b7280;
                white-space: nowrap;
            }

            .ph-activity-description {
                margin: 5px 0 0;
                font-size: 13px;
                line-height: 1.5;
                color: #4b5563;
                overflow-wrap: anywhere;
            }

            .ph-activity-empty {
                grid-column: 1 / -1;
                padding: 28px;
                border: 1px dashed #cbd5e1;
                border-radius: 16px;
                text-align: center;
                font-size: 13px;
                color: #64748b;
            }

            .dark .ph-activity-context {
                border-color: rgba(34,197,94,.24);
                background: linear-gradient(135deg, rgba(5,46,22,.78), rgba(6,78,59,.62));
                color: #bbf7d0;
            }

            .dark .ph-activity-role {
                border-color: rgba(34,197,94,.24);
                background: rgba(255,255,255,.08);
                color: #a7f3d0;
            }

            .dark .ph-activity-card {
                border-color: rgba(255,255,255,.10);
                background: linear-gradient(145deg, rgba(17,24,39,.96), rgba(15,23,42,.92));
            }

            .dark .ph-activity-title { color: #f9fafb; }
            .dark .ph-activity-time { color: #9ca3af; }
            .dark .ph-activity-description { color: #d1d5db; }
            .dark .ph-activity-empty { border-color: #374151; color: #9ca3af; }

            @media (max-width: 900px) {
                .ph-activity-grid { grid-template-columns: 1fr; }
                .ph-activity-role { margin-left: 0; }
            }
        </style>

        <div class="ph-activity-context">
            <strong>{{ $context['pharmacy'] }}</strong>
            <span>•</span>
            <span>{{ $context['branch'] }}</span>
            <span class="ph-activity-role">{{ $context['role'] }}</span>
        </div>

        <div class="ph-activity-grid">
            @forelse ($this->getActivities() as $activity)
                @php
                    [$toneBg, $toneText, $toneBorder] = match ($activity['tone']) {
                        'success' => ['#ecfdf5', '#047857', '#a7f3d0'],
                        'danger' => ['#fef2f2', '#b91c1c', '#fecaca'],
                        'warning' => ['#fffbeb', '#b45309', '#fde68a'],
                        default => ['#eff6ff', '#1d4ed8', '#bfdbfe'],
                    };
                @endphp

                <article class="ph-activity-card">
                    <div
                        class="ph-activity-icon"
                        style="--ph-tone-bg: {{ $toneBg }}; --ph-tone-text: {{ $toneText }}; --ph-tone-border: {{ $toneBorder }};"
                    >
                        <x-filament::icon
                            :icon="$activity['icon']"
                            style="width: 20px; height: 20px;"
                        />
                    </div>

                    <div class="ph-activity-body">
                        <div class="ph-activity-head">
                            <p class="ph-activity-title">{{ $activity['title'] }}</p>
                            <span class="ph-activity-time">{{ $activity['relative_time'] }}</span>
                        </div>
                        <p class="ph-activity-description">{{ $activity['description'] }}</p>
                    </div>
                </article>
            @empty
                <div class="ph-activity-empty">
                    No activity is available for this role yet.
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
