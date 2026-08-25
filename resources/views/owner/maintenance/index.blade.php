<x-layouts.app
    :title="__('Maintenance')"
    :heading="__('Maintenance')"
    :subheading="__('Issues raised across your properties.')"
>
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('Open requests')" :value="$openCount" />
    </div>

    <form method="GET" action="{{ route('owner.maintenance.index') }}" class="card p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-form.field :label="__('Status')" name="status">
                <x-form.select
                    name="status"
                    :placeholder="__('Any status')"
                    :selected="$filters['status'] ?? null"
                    :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()"
                />
            </x-form.field>

            <x-form.field :label="__('Priority')" name="priority">
                <x-form.select
                    name="priority"
                    :placeholder="__('Any priority')"
                    :selected="$filters['priority'] ?? null"
                    :options="collect($priorities)->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all()"
                />
            </x-form.field>

            <x-form.field :label="__('Property')" name="property_id">
                <x-form.select
                    name="property_id"
                    :placeholder="__('All properties')"
                    :selected="$filters['property_id'] ?? null"
                    :options="$properties->pluck('title', 'id')->all()"
                />
            </x-form.field>

            <div class="flex items-end">
                <x-form.button type="submit" variant="secondary">{{ __('Filter') }}</x-form.button>
            </div>
        </div>
    </form>

    <section class="mt-6">
        @if ($requests->isEmpty())
            <x-empty-state
                :title="__('No maintenance requests.')"
                :description="__('Requests your renters raise appear here, newest first.')"
            />
        @else
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Issue') }}</th>
                                <th scope="col">{{ __('Property') }}</th>
                                <th scope="col">{{ __('Renter') }}</th>
                                <th scope="col">{{ __('Priority') }}</th>
                                <th scope="col">{{ __('Status') }}</th>
                                <th scope="col" class="text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $request)
                                <tr>
                                    <td>
                                        <a href="{{ route('owner.maintenance.show', $request) }}" class="font-medium text-slate-900 hover:underline">
                                            {{ $request->title }}
                                        </a>
                                        <span class="block text-xs text-slate-500">{{ $request->created_at->format('d M Y') }}</span>
                                    </td>
                                    <td class="text-slate-600">{{ $request->tenancy->property->title }}</td>
                                    <td class="text-slate-600">{{ $request->tenancy->renter->name }}</td>
                                    <td>
                                        <x-badge :variant="$request->priority->badge()">{{ $request->priority->label() }}</x-badge>
                                    </td>
                                    <td>
                                        <x-badge :variant="$request->status->badge()">{{ $request->status->label() }}</x-badge>
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('owner.maintenance.show', $request) }}" class="btn btn-secondary btn-sm">
                                            {{ __('Open') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">{{ $requests->links() }}</div>
        @endif
    </section>
</x-layouts.app>
