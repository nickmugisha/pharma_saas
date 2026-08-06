<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Recruitment command center
        </x-slot>

        <x-slot name="description">
            {{ $this->getScopeLabel() }}. Updates refresh automatically.
        </x-slot>

        <x-slot name="headerEnd">
            <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
                <a
                    href="{{ $this->getIndexUrl() }}"
                    style="display:inline-flex;align-items:center;padding:.55rem .8rem;border:1px solid rgb(203 213 225);border-radius:.7rem;font-size:.78rem;font-weight:700;text-decoration:none;"
                >
                    Manage staff
                </a>

                <a
                    href="{{ $this->getCreateUrl() }}"
                    style="display:inline-flex;align-items:center;padding:.55rem .8rem;border-radius:.7rem;background:rgb(5 150 105);color:white;font-size:.78rem;font-weight:800;text-decoration:none;"
                >
                    + Recruit employee
                </a>
            </div>
        </x-slot>

        @php
            $events = $this->getEvents();
        @endphp

        @if($events === [])
            <div style="padding:2rem;text-align:center;border:1px dashed rgb(203 213 225);border-radius:1rem;color:rgb(100 116 139);">
                <strong style="display:block;color:rgb(15 23 42);margin-bottom:.35rem;">
                    No recruitment activity yet
                </strong>
                Recruit the first employee to begin the permanent audit timeline.
            </div>
        @else
            <div style="display:grid;gap:.65rem;">
                @foreach($events as $event)
                    @php
                        $tone = match($event['tone']) {
                            'success' => ['#ecfdf5', '#047857'],
                            'danger' => ['#fef2f2', '#b91c1c'],
                            default => ['#eff6ff', '#0369a1'],
                        };
                    @endphp

                    <article style="display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.85rem;padding:.9rem 1rem;border:1px solid rgb(226 232 240);border-radius:1rem;background:white;">
                        <span
                            aria-hidden="true"
                            style="display:grid;width:2.45rem;height:2.45rem;place-items:center;border-radius:.8rem;background:{{ $tone[0] }};color:{{ $tone[1] }};font-weight:900;"
                        >
                            {{ $event['tone'] === 'danger' ? '!' : '✓' }}
                        </span>

                        <div style="min-width:0;">
                            <strong style="display:block;color:rgb(15 23 42);font-size:.88rem;">
                                {{ $event['title'] }}
                            </strong>
                            <span style="display:block;color:rgb(100 116 139);font-size:.77rem;margin-top:.2rem;white-space:normal;">
                                {{ $event['description'] }}
                            </span>
                        </div>

                        <div style="text-align:right;color:rgb(100 116 139);font-size:.7rem;">
                            <strong style="display:block;color:rgb(51 65 85);font-size:.72rem;">
                                {{ $event['actor'] }}
                            </strong>
                            {{ $event['relative_time'] }}
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
