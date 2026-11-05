@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $start = max(1, min($current - 1, $last - 2));
        $end = min($last, max($current + 1, 3));
        $pages = collect([1, ...range($start, $end), $last])->unique()->sort()->values();
        $previous = 0;
    @endphp
    <div class="notification-pagination-footer">
        <p class="notification-pagination-summary">
            {{ __('Showing :first to :last of :total results', [
                'first' => $paginator->firstItem(),
                'last' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]) }}
        </p>
        <nav class="notification-pagination" aria-label="{{ __('Notification pages') }}">
        @if ($paginator->onFirstPage())
            <span class="notification-page-link" aria-disabled="true" aria-label="{{ __('Previous') }}"><x-icon name="chevron-left" class="size-4" /></span>
        @else
            <a class="notification-page-link" href="{{ $paginator->url(min($current - 1, $last)) }}" rel="prev" aria-label="{{ __('Previous') }}"><x-icon name="chevron-left" class="size-4" /></a>
        @endif

        @foreach ($pages as $page)
            @if ($previous && $page > $previous + 1)
                <span class="notification-page-link" data-pagination-extra aria-hidden="true">…</span>
            @endif
            @if ($page === $current)
                <span class="notification-page-link" aria-current="page" aria-label="{{ __('Page :page', ['page' => $page]) }}">{{ $page }}</span>
            @else
                <a class="notification-page-link" @if ($last > 4 && ($page < $start || $page > $end)) data-pagination-extra @endif href="{{ $paginator->url($page) }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
            @endif
            @php $previous = $page; @endphp
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="notification-page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Next') }}"><x-icon name="chevron-right" class="size-4" /></a>
        @else
            <span class="notification-page-link" aria-disabled="true" aria-label="{{ __('Next') }}"><x-icon name="chevron-right" class="size-4" /></span>
        @endif
        </nav>
    </div>
@endif
