@props(['record', 'category', 'deletable' => false])

@php
    // NFR-SEC-007 - the file itself is only reachable through the authorised
    // download route, never as a public URL.
    $proofs = $record->proofs->where('category', $category);
@endphp

@if ($proofs->isEmpty())
    <p class="text-xs text-slate-500">{{ __('No proof attached.') }}</p>
@else
    <ul class="space-y-1.5">
        @foreach ($proofs as $proof)
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
                    <span class="text-xs text-slate-500">{{ $proof->isImage() ? __('Image') : __('Document') }}</span>

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
