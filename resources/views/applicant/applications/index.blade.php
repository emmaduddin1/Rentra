<x-layouts.app
    :title="__('My applications')"
    :heading="__('My applications')"
    :subheading="__('Inquiries you have sent, and how owners responded.')"
>
    @if ($applications->isEmpty())
        <x-empty-state
            :title="__('You have not applied for anything yet.')"
            :description="__('Browse the available listings and send an inquiry to the owner.')"
        >
            <a href="{{ route('listings.index') }}" class="btn btn-primary">
                {{ __('Browse listings') }}
            </a>
        </x-empty-state>
    @else
        <ul class="space-y-4">
            @foreach ($applications as $application)
                <li class="card p-4">
                    <div class="flex flex-wrap items-start gap-4">
                        <span class="h-20 w-28 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                            @if ($application->property->primaryImage)
                                <img src="{{ $application->property->primaryImage->url() }}" alt="" loading="lazy" class="h-full w-full object-cover">
                            @endif
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <a href="{{ route('listings.show', $application->property) }}" class="font-medium text-slate-900 hover:underline">
                                        {{ $application->property->title }}
                                    </a>
                                    <p class="text-sm text-slate-600">{{ $application->property->area }}</p>
                                </div>
                                <x-badge :variant="$application->status->badge()">{{ $application->status->label() }}</x-badge>
                            </div>

                            <p class="mt-2 text-xs text-slate-500">
                                {{ __('Sent :date', ['date' => $application->submitted_at->format('d M Y')]) }}
                                @if ($application->responded_at)
                                    · {{ __('Answered :date', ['date' => $application->responded_at->format('d M Y')]) }}
                                @endif
                            </p>

                            @if ($application->owner_note)
                                <p class="mt-2 rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    <span class="font-medium">{{ __('Owner:') }}</span> {{ $application->owner_note }}
                                </p>
                            @endif

                            @if ($application->status === \App\Enums\ApplicationStatus::Pending)
                                <form method="POST" action="{{ route('applicant.applications.destroy', $application) }}" class="mt-3">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-700 hover:text-red-800">{{ __('Withdraw application') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            {{ $applications->links() }}
        </div>
    @endif
</x-layouts.app>
