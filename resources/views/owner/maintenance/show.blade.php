<x-layouts.app
    :title="$request->title"
    :heading="$request->title"
    :subheading="__(':property - :renter', [
        'property' => $request->tenancy->property->title,
        'renter' => $request->tenancy->renter->name,
    ])"
    :back="['href' => route('owner.maintenance.index'), 'label' => __('Maintenance')]"
>
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-badge :variant="$request->status->badge()">{{ $request->status->label() }}</x-badge>
        <x-badge :variant="$request->priority->badge()">{{ $request->priority->label() }}</x-badge>
        <span class="text-sm text-slate-500">{{ __('Reported :date', ['date' => $request->created_at->format('d M Y')]) }}</span>

        <a href="{{ route('owner.tenancies.show', $request->tenancy) }}" class="ms-auto link-muted text-sm">
            {{ __('Open tenancy') }}
        </a>
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

                <form method="POST" action="{{ route('owner.maintenance.comments.store', $request) }}" class="mt-6 border-t border-slate-100 pt-4">
                    @csrf

                    <x-form.field :label="__('Add a comment')" name="comment">
                        <x-form.textarea name="comment" rows="3" />
                    </x-form.field>

                    <x-form.button type="submit" variant="secondary" class="mt-3">{{ __('Post comment') }}</x-form.button>
                </form>
            </section>
        </div>

        <aside class="space-y-6">
            {{-- FR-MNT-003 / SRS 12 - only legal transitions are offered. --}}
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Update status') }}</h2>

                @if ($allowedStatuses === [])
                    <p class="mt-2 text-sm text-slate-600">
                        {{ __('This request is :status and cannot change further.', ['status' => $request->status->label()]) }}
                    </p>
                @else
                    <form method="POST" action="{{ route('owner.maintenance.status', $request) }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PATCH')

                        <x-form.field :label="__('New status')" name="status" required>
                            <x-form.select
                                name="status"
                                :options="collect($allowedStatuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()"
                            />
                        </x-form.field>

                        <x-form.field :label="__('Comment')" name="comment" :hint="__('Shown to the renter on the timeline.')">
                            <x-form.textarea name="comment" rows="3" />
                        </x-form.field>

                        <x-form.button type="submit" class="w-full">{{ __('Save status') }}</x-form.button>
                    </form>
                @endif
            </section>

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Details') }}</h2>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">{{ __('Renter') }}</dt>
                        <dd>
                            <a href="{{ route('owner.renters.show', $request->tenancy->renter) }}" class="link">
                                {{ $request->tenancy->renter->name }}
                            </a>
                        </dd>
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
            </section>
        </aside>
    </div>
</x-layouts.app>
