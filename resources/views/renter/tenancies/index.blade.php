<x-layouts.app
    :title="__('My property')"
    :heading="__('My property')"
    :subheading="__('Your current tenancy and everything you have rented before.')"
>
    @if ($tenancies->isEmpty())
        <x-empty-state
            :title="__('No tenancy yet.')"
            :description="__('Your property owner assigns a tenancy to your account. It appears here as soon as they do.')"
        />
    @else
        <ul class="space-y-4">
            @foreach ($tenancies as $tenancy)
                <li class="card p-4">
                    <div class="flex flex-wrap items-start gap-4">
                        <span class="h-20 w-28 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                            @if ($tenancy->property->primaryImage)
                                <img src="{{ $tenancy->property->primaryImage->url() }}" alt="" loading="lazy" class="h-full w-full object-cover">
                            @endif
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <a href="{{ route('renter.tenancies.show', $tenancy) }}" class="font-medium text-slate-900 hover:underline">
                                        {{ $tenancy->property->title }}
                                    </a>
                                    <p class="text-sm text-slate-600">{{ $tenancy->property->area }}</p>
                                </div>
                                <x-badge :variant="$tenancy->status->badge()">{{ $tenancy->status->label() }}</x-badge>
                            </div>

                            <p class="mt-2 text-xs text-slate-500">
                                {{ $tenancy->start_date->format('d M Y') }} &ndash;
                                {{ $tenancy->end_date?->format('d M Y') ?? __('present') }} &middot;
                                <x-money :amount="$tenancy->agreed_rent" /> {{ __('/ month') }} &middot;
                                {{ trans_choice(':count record|:count records', $tenancy->monthly_records_count, ['count' => $tenancy->monthly_records_count]) }}
                            </p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
