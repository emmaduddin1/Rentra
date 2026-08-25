<x-layouts.app
    :title="__('Maintenance')"
    :heading="__('Maintenance')"
    :subheading="__('Issues you have reported and where they stand.')"
>
    <x-slot:actions>
        <x-form.button :href="route('renter.maintenance.create')" icon="plus">{{ __('Report an issue') }}</x-form.button>
    </x-slot:actions>

    <div class="mb-6">
        <form method="GET" action="{{ route('renter.maintenance.index') }}" class="card flex flex-wrap items-end gap-3 p-4">
            <x-form.field :label="__('Status')" name="status">
                <x-form.select
                    name="status"
                    :placeholder="__('Any status')"
                    :selected="$filters['status'] ?? null"
                    :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()"
                />
            </x-form.field>
            <x-form.button type="submit" variant="secondary" icon="search">{{ __('Filter') }}</x-form.button>
        </form>
    </div>

    @if ($requests->isEmpty())
        <x-empty-state
            :title="__('Nothing reported.')"
            :description="__('If something needs fixing in your property, report it here and your owner is notified.')"
        >
            <a href="{{ route('renter.maintenance.create') }}" class="btn btn-primary">
                {{ __('Report an issue') }}
            </a>
        </x-empty-state>
    @else
        <ul class="space-y-3">
            @foreach ($requests as $request)
                <li class="card p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('renter.maintenance.show', $request) }}" class="font-medium text-slate-900 hover:underline">
                                {{ $request->title }}
                            </a>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $request->tenancy->property->title }} &middot; {{ $request->created_at->format('d M Y') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :variant="$request->priority->badge()">{{ $request->priority->label() }}</x-badge>
                            <x-badge :variant="$request->status->badge()">{{ $request->status->label() }}</x-badge>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $requests->links() }}</div>
    @endif
</x-layouts.app>
