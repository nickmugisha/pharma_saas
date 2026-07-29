<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class AuthenticationEventSubscriber
{
    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->saveQuietly();

        $this->record(
            event: 'login_success',
            user: $event->user,
            email: $event->user->email,
        );
    }

    public function handleFailed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        $email = $event->credentials['email'] ?? null;

        $this->record(
            event: 'login_failed',
            user: $user,
            email: is_string($email) ? $email : null,
        );
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->record(
            event: 'logout',
            user: $user,
            email: $user?->email,
        );
    }

    private function record(
        string $event,
        ?User $user,
        ?string $email,
    ): void {
        LoginHistory::create([
            'user_id' => $user?->id,
            'email' => $email,
            'event' => $event,
            'panel' => $this->resolvePanel(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function resolvePanel(): ?string
    {
        $panelId = Filament::getCurrentPanel()?->getId();

        if (in_array($panelId, ['super-admin', 'pharmacy'], true)) {
            return $panelId;
        }

        $paths = [
            request()->path(),
            parse_url((string) request()->header('referer'), PHP_URL_PATH),
        ];

        foreach ($paths as $path) {
            $firstSegment = explode('/', trim((string) $path, '/'))[0] ?? null;

            if (in_array($firstSegment, ['super-admin', 'pharmacy'], true)) {
                return $firstSegment;
            }
        }

        return null;
    }
}