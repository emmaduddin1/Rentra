@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $start = max(1, min($current - 1, $last - 2));
        $end = min($last, max($current + 1, 3));
        $pages = collect([1, ...range($start, $end), $last])->unique()->sort()->values();
        $previous = 0;
    @endphp

    <div class="audit-pagination-footer">
        <p class="audit-pagination-summary">
            {{ __('Showing :first to :last of :total results', [
                'first' => $paginator->firstItem(),
                'last' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]) }}
        </p>

        <nav class="audit-pagination" aria-label="{{ __('Audit log pages') }}">
        @if ($paginator->onFirstPage())
            <span class="audit-pagination-link" aria-disabled="true" aria-label="{{ __('Previous') }}"><x-icon name="chevron-left" class="size-4" /></span>
        @else
            <a class="audit-pagination-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Previous') }}"><x-icon name="chevron-left" class="size-4" /></a>
        @endif

        @foreach ($pages as $page)
            @if ($previous && $page > $previous + 1)
                <span class="audit-pagination-link" aria-hidden="true">…</span>
            @endif

            @if ($page === $current)
                <span class="audit-pagination-link" aria-current="page" aria-label="{{ __('Page :page', ['page' => $page]) }}">{{ $page }}</span>
            @else
                <a class="audit-pagination-link" href="{{ $paginator->url($page) }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
            @endif

            @php $previous = $page; @endphp
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="audit-pagination-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Next') }}"><x-icon name="chevron-right" class="size-4" /></a>
        @else
            <span class="audit-pagination-link" aria-disabled="true" aria-label="{{ __('Next') }}"><x-icon name="chevron-right" class="size-4" /></span>
        @endif
        </nav>
    </div>
@endif
