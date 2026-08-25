<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\MaintenanceRequest;
use App\Models\MonthlyRecord;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * SRS 13 - the owner can see the audited trail of what happened to their own
 * portfolio: publication, assignment, termination, submission, approval,
 * rejection, reopening, and maintenance status changes (BR-008).
 *
 * BR-010 - the trail is filtered to subjects this owner controls, plus their
 * own actions. NFR-SEC-008 - the log never contained secrets to begin with.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        // The trail is portfolio-wide, so it rides on the property permission.
        Gate::authorize('viewAny', Property::class);

        $owner = $request->user();

        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->with('user')
            ->where(fn (Builder $query) => $query
                ->where('user_id', $owner->id)
                ->orWhere(fn (Builder $q) => $this->subjectScope($q, Property::class, $this->propertyIds($owner)))
                ->orWhere(fn (Builder $q) => $this->subjectScope($q, Tenancy::class, $this->tenancyIds($owner)))
                ->orWhere(fn (Builder $q) => $this->subjectScope($q, MonthlyRecord::class, $this->recordIds($owner)))
                ->orWhere(fn (Builder $q) => $this->subjectScope($q, MaintenanceRequest::class, $this->maintenanceIds($owner)))
                ->orWhere(fn (Builder $q) => $this->subjectScope($q, Application::class, $this->applicationIds($owner)))
            )
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', 'like', "{$action}%"))
            ->latest('created_at')
            ->paginate(config('rental.pagination.per_page'))
            ->withQueryString();

        return view('owner.audit-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => AuditLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
        ]);
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @param  Collection<int, int>  $ids
     */
    protected function subjectScope(Builder $query, string $type, $ids): void
    {
        $query->where('subject_type', $type)->whereIn('subject_id', $ids);
    }

    /** @return Collection<int, int> */
    protected function propertyIds(User $owner)
    {
        return Property::withTrashed()->ownedBy($owner)->pluck('id');
    }

    /** @return Collection<int, int> */
    protected function tenancyIds(User $owner)
    {
        return Tenancy::withTrashed()->forOwner($owner)->pluck('id');
    }

    /** @return Collection<int, int> */
    protected function recordIds(User $owner)
    {
        return MonthlyRecord::withTrashed()->forOwner($owner)->pluck('id');
    }

    /** @return Collection<int, int> */
    protected function maintenanceIds(User $owner)
    {
        return MaintenanceRequest::withTrashed()->forOwner($owner)->pluck('id');
    }

    /** @return Collection<int, int> */
    protected function applicationIds(User $owner)
    {
        return Application::forOwner($owner)->pluck('id');
    }
}
