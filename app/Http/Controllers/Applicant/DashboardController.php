<?php

namespace App\Http\Controllers\Applicant;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Favorite;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SRS 11.4 - the applicant portal: application counters, saved listings, and a
 * shortcut into the feed (FR-APP-001).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $applicant = $request->user();

        return view('applicant.dashboard', [
            'stats' => [
                'applications' => Application::forApplicant($applicant)->count(),
                'pending_applications' => Application::forApplicant($applicant)
                    ->where('status', ApplicationStatus::Pending->value)
                    ->count(),
                'favorites' => Favorite::where('applicant_id', $applicant->id)->count(),
            ],
            'latestListings' => Property::publiclyVisible()
                ->with('primaryImage')
                ->latest('published_at')
                ->limit(6)
                ->get(),
            'favoriteIds' => $applicant->favorites()->pluck('property_id')->all(),
            'recentApplications' => Application::forApplicant($applicant)
                ->with('property')
                ->latest('submitted_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
