@props(['record', 'category', 'deletable' => false])

@php
    // NFR-SEC-007 - the file itself is only reachable through the authorised
    // download route, never as a public URL. That route streams inline with the
    // stored mime type, so an <img> pointing at it renders the photo without
    // the private disk ever becoming public.
    $proofs = $record->proofs->where('category', $category);
    $images = $proofs->filter->isImage();
    $documents = $proofs->reject->isImage();
@endphp

@if ($proofs->isEmpty())
    <p class="text-xs text-slate-500">{{ __('No proof attached.') }}</p>
@else
    {{-- SRS 5.3 step 14 - the owner verifies the receipt by looking at it, so
         photos are shown here rather than hidden behind a download link. --}}
    @if ($images->isNotEmpty())
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ($images as $proof)
                <li class="group relative">
                    <a
                        href="{{ route('monthly-proofs.download', $proof) }}"
                        target="_blank"
                        rel="noopener"
                        class="block overflow-hidden rounded-lg border border-slate-200 bg-slate-100 transition hover:border-slate-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
                        title="{{ __('Open :name full size', ['name' => $proof->original_name ?? __('image')]) }}"
                    >
                        <img
                            src="{{ route('monthly-proofs.download', $proof) }}"
                            alt="{{ $proof->original_name ?? __('Payment proof') }}"
                            loading="lazy"
                            class="aspect-4/3 w-full object-cover transition group-hover:scale-105"
                        >
                    </a>

                    <p class="mt-1 truncate text-xs text-slate-500" title="{{ $proof->original_name }}">
                        {{ $proof->original_name ?? __('Attachment') }}
                    </p>

                    @if ($deletable)
                        <form
                            method="POST"
                            action="{{ route('renter.monthly-records.proofs.destroy', [$record, $proof]) }}"
                            class="absolute top-1.5 right-1.5"
                        >
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="flex size-7 items-center justify-center rounded-full bg-white/90 text-red-700 shadow-sm transition hover:bg-white hover:text-red-800"
                                aria-label="{{ __('Remove :name', ['name' => $proof->original_name ?? __('image')]) }}"
                            >
                                <x-icon name="close" class="size-4" />
                            </button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    {{-- A PDF receipt has nothing to show as a thumbnail, so it stays a row. --}}
    @if ($documents->isNotEmpty())
        <ul @class(['space-y-1.5', 'mt-3' => $images->isNotEmpty()])>
            @foreach ($documents as $proof)
                <li class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2">
                    <a
                        href="{{ route('monthly-proofs.download', $proof) }}"
                        target="_blank"
                        rel="noopener"
                        class="min-w-0 truncate link text-sm"
                    >
                        {{ $proof->original_name ?? __('Attachment') }}
                    </a>

                    <span class="flex shrink-0 items-center gap-3">
                        <span class="text-xs text-slate-500">{{ __('Document') }}</span>

                        @if ($deletable)
                            <form method="POST" action="{{ route('renter.monthly-records.proofs.destroy', [$record, $proof]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-700 hover:text-red-800">{{ __('Remove') }}</button>
                            </form>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
@endif
