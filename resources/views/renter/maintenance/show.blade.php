<x-layouts.app
    :title="$request->title"
    :heading="$request->title"
    :subheading="$request->tenancy->property->title"
    :back="['href' => route('renter.maintenance.index'), 'label' => __('Maintenance')]"
>
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-badge :variant="$request->status->badge()">{{ $request->status->label() }}</x-badge>
        <x-badge :variant="$request->priority->badge()">{{ $request->priority->label() }}</x-badge>
        <span class="text-sm text-slate-500">{{ __('Reported :date', ['date' => $request->created_at->format('d M Y')]) }}</span>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Description') }}</h2>
                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $request->description }}</p>

                @if ($request->attachments->isNotEmpty())
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Attachments') }}</h3>
                        <ul class="mt-2 space-y-1.5">
                            @foreach ($request->attachments as $attachment)
                                <li>
                                    <a
                                        href="{{ route('maintenance-attachments.download', $attachment) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="link text-sm"
                                    >{{ $attachment->original_name ?? __('Attachment') }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Timeline') }}</h2>
                <div class="mt-4">
                    <x-maintenance-timeline :request="$request" />
                </div>

                {{-- FR-MNT-004 - the renter can comment; only the owner moves status. --}}
                <form method="POST" action="{{ route('renter.maintenance.comments.store', $request) }}" class="mt-6 border-t border-slate-100 pt-4">
                    @csrf

                    <x-form.field :label="__('Add a comment')" name="comment">
                        <x-form.textarea name="comment" rows="3" />
                    </x-form.field>

                    <x-form.button type="submit" variant="secondary" class="mt-3">{{ __('Post comment') }}</x-form.button>
                </form>
            </section>
        </div>

        <aside>
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Details') }}</h2>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">{{ __('Status') }}</dt>
                        <dd><x-badge :variant="$request->status->badge()">{{ $request->status->label() }}</x-badge></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">{{ __('Priority') }}</dt>
                        <dd>{{ $request->priority->label() }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">{{ __('Reported by') }}</dt>
                        <dd>{{ $request->creator?->name ?? '—' }}</dd>
                    </div>
                    @if ($request->completed_at)
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">{{ __('Completed') }}</dt>
                            <dd>{{ $request->completed_at->format('d M Y') }}</dd>
                        </div>
                    @endif
                </dl>

                <p class="mt-4 text-xs text-slate-500">
                    {{ __('Only your owner can change the status of a request.') }}
                </p>
            </section>
        </aside>
    </div>
</x-layouts.app>
