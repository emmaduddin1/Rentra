<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Applications\RespondToApplication;
use App\Actions\Applications\SubmitApplication;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Application\RespondToApplicationRequest;
use App\Http\Requests\Application\StoreApplicationRequest;
use App\Http\Resources\V1\ApplicationResource;
use App\Models\Application;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * SRS 9.3 - POST /api/v1/listings/{id}/applications, GET /api/v1/applications.
 */
class ApplicationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Application::class);

        $user = $request->user();

        $applications = Application::query()
            // BR-010 - an owner sees inquiries on their listings, an applicant
            // sees their own, and nobody sees anything else.
            ->when($user->isOwner(), fn ($query) => $query->forOwner($user))
            ->when($user->isApplicant(), fn ($query) => $query->forApplicant($user))
            ->with(['property.primaryImage', 'applicant'])
            ->latest('submitted_at')
            ->paginate($request->integer('per_page') ?: config('rental.pagination.per_page'));

        return ApplicationResource::collection($applications);
    }

    public function store(
        StoreApplicationRequest $request,
        Property $property,
        SubmitApplication $submitApplication,
    ): ApplicationResource {
        // FR-APP-001/005 - only a publicly visible listing accepts inquiries.
        Gate::authorize('create', [Application::class, $property]);

        $application = $submitApplication->handle(
            applicant: $request->user(),
            property: $property,
            data: $request->validated(),
        );

        return new ApplicationResource($application->load('property'));
    }

    /**
     * FR-APP-006.
     */
    public function update(
        RespondToApplicationRequest $request,
        Application $application,
        RespondToApplication $respond,
    ): ApplicationResource {
        Gate::authorize('updateStatus', $application);

        $respond->handle(
            application: $application,
            owner: $request->user(),
            status: ApplicationStatus::from($request->validated('status')),
            note: $request->validated('owner_note'),
        );

        return new ApplicationResource($application->fresh()->load(['property', 'applicant']));
    }
}
