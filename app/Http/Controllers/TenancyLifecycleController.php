<?php

namespace App\Http\Controllers;

use App\Actions\Properties\PublishProperty;
use App\Actions\Tenancies\EndTenancy;
use App\Actions\Tenancies\RequestTenancyTermination;
use App\Actions\Tenancies\RespondToInvitation;
use App\Actions\Tenancies\RespondToTermination;
use App\Enums\PropertyStatus;
use App\Enums\TenancyStatus;
use App\Http\Requests\Tenancy\EndTenancyRequest;
use App\Http\Requests\Tenancy\RequestTerminationRequest;
use App\Http\Requests\Tenancy\RespondTerminationRequest;
use App\Models\Tenancy;
use App\Models\TenancyTerminationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TenancyLifecycleController extends Controller
{
    public function end(
        EndTenancyRequest $request,
        Tenancy $tenancy,
        EndTenancy $endTenancy,
        PublishProperty $publishProperty,
    ): RedirectResponse {
        Gate::authorize('end', $tenancy);

        $nextStep = $request->validated('next_step');
        $closedTenancy = $endTenancy->handle(
            $tenancy,
            $request->user(),
            today(),
            PropertyStatus::Available,
            $request->validated('reason'),
        );
        $publishProperty->handle($tenancy->property->fresh(), $nextStep === 'publish');

        $closedLabel = $closedTenancy->status === TenancyStatus::Cancelled
            ? __('Contract cancelled before its start date.')
            : __('Contract ended.');

        if ($nextStep === 'assign') {
            return redirect()
                ->route('owner.tenancies.create', ['property_id' => $tenancy->property_id])
                ->with('status', $closedLabel.' '.__('The property is available; choose the next renter to invite.'));
        }

        return redirect()
            ->route('owner.properties.show', $tenancy->property_id)
            ->with('status', $closedLabel.' '.__('The property has been published again.'));
    }

    public function accept(Request $request, Tenancy $tenancy, RespondToInvitation $action): RedirectResponse
    {
        Gate::authorize('acceptInvitation', $tenancy);
        $action->accept($tenancy, $request->user());

        return back()->with('status', __('Invitation accepted. This is now your active tenancy.'));
    }

    public function reject(Request $request, Tenancy $tenancy, RespondToInvitation $action): RedirectResponse
    {
        Gate::authorize('rejectInvitation', $tenancy);
        $action->reject($tenancy, $request->user(), $request->validate(['reason' => ['nullable', 'string', 'max:1000']])['reason'] ?? null);

        return redirect()->route('renter.tenancies.index')->with('status', __('Invitation rejected.'));
    }

    public function withdraw(Request $request, Tenancy $tenancy, RespondToInvitation $action): RedirectResponse
    {
        Gate::authorize('withdrawInvitation', $tenancy);
        $action->withdraw($tenancy, $request->user(), $request->validate(['reason' => ['nullable', 'string', 'max:1000']])['reason'] ?? null);

        return back()->with('status', __('Invitation withdrawn.'));
    }

    public function requestTermination(RequestTerminationRequest $request, Tenancy $tenancy, RequestTenancyTermination $action): RedirectResponse
    {
        Gate::authorize('requestTermination', $tenancy);
        $action->handle($tenancy, $request->user(), $request->validated());

        return back()->with('status', __('Termination request sent. The tenancy remains active until an end date is accepted.'));
    }

    public function respondTermination(RespondTerminationRequest $request, TenancyTerminationRequest $termination, RespondToTermination $action): RedirectResponse
    {
        Gate::authorize('respondTermination', [Tenancy::class, $termination]);
        $action->handle($termination, $request->user(), $request->validated());

        return back()->with('status', __('Termination response recorded.'));
    }
}
