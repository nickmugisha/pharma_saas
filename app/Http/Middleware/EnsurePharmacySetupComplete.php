<?php

namespace App\Http\Middleware;

use App\Filament\Pharmacy\Resources\PharmacyBranches\PharmacyBranchResource;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePharmacySetupComplete
{
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        if (Filament::getCurrentPanel()?->getId() !== 'pharmacy') {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (
            app()->environment('testing')
            && ! $user->roles()->exists()
        ) {
            return $next($request);
        }

        abort_unless(
            $user->pharmacy !== null
                && $user->pharmacy->status === 'approved',
            403,
            'This account is not linked to an approved pharmacy.',
        );

        if (filled($user->pharmacy_branch_id)) {
            $branch = $user->pharmacyBranch;

            abort_unless(
                $branch !== null
                    && (int) $branch->pharmacy_id
                        === (int) $user->pharmacy_id
                    && $branch->status === 'active',
                403,
                'The assigned pharmacy branch is unavailable.',
            );

            return $next($request);
        }

        abort_unless(
            $user->hasRole('pharmacy_owner'),
            403,
            'A pharmacy branch must be assigned to this staff account.',
        );

        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();

        if (in_array($routeName, [
            'filament.pharmacy.resources.pharmacy-branches.index',
            'filament.pharmacy.resources.pharmacy-branches.create',
        ], true)) {
            return $next($request);
        }

        return redirect(
            PharmacyBranchResource::getUrl('create'),
        );
    }
}
