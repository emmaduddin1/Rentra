<x-layouts.app
    :title="__('Applications')"
    :heading="__('Applications')"
    :subheading="__('Inquiries renters have sent about your listings.')"
>
    <form method="GET" action="{{ route('owner.applications.index') }}" class="card p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-form.field :label="__('Status')" name="status">
                <x-form.select
                    name="status"
                    :placeholder="__('Any status')"
                    :selected="$filters['status'] ?? null"
                    :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()"
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
                <x-form.button type="submit">{{ __('Filter') }}</x-form.button>
            </div>
        </div>
    </form>

    <section class="mt-6">
        @if ($applications->isEmpty())
            <x-empty-state
                :title="__('No applications yet.')"
                :description="__('Applications appear here as soon as a renter contacts you about a published listing.')"
            />
        @else
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Renter') }}</th>
                                <th scope="col">{{ __('Property') }}</th>
                                <th scope="col">{{ __('Submitted') }}</th>
                                <th scope="col">{{ __('Status') }}</th>
                                <th scope="col" class="text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($applications as $application)
                                <tr>
                                    <td>
                                        <span class="font-medium text-slate-900">{{ $application->renter->name }}</span>
                                        @if ($application->contact_phone)
                                            <span class="block text-xs text-slate-500">{{ $application->contact_phone }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('owner.properties.show', $application->property) }}" class="text-slate-700 hover:underline">
                                            {{ $application->property->title }}
                                        </a>
                                    </td>
                                    <td class="whitespace-nowrap text-slate-600">{{ $application->submitted_at->format('d M Y') }}</td>
                                    <td><x-badge :variant="$application->status->badge()">{{ $application->status->label() }}</x-badge></td>
                                    <td class="text-right">
                                        <a href="{{ route('owner.applications.show', $application) }}" class="btn btn-secondary btn-sm">
                                            {{ __('Review') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                {{ $applications->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>
