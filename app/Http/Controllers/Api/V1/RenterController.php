<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RenterController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Tenancy::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $renters = User::role(UserRole::Renter)
            ->active()
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
            ))
            ->orderBy('name')
            ->orderBy('email')
            ->paginate($request->integer('per_page') ?: config('rental.pagination.per_page'));

        return UserResource::collection($renters);
    }
}
