@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $start = max(1, min($current - 1, $last - 2));
        $end = min($last, max($current + 1, 3));
        $pages = collect([1, ...range($start, $end), $last])->unique()->sort()->values();
        $previous = 0;
    @endphp
    <nav class="notification-pagination" aria-label="{{ __('Notification pages') }}">
        @if ($paginator->onFirstPage())
            <span class="notification-page-link" aria-disabled="true">{{ __('Previous') }}</span>
        @else
            <a class="notification-page-link" href="{{ $paginator->url(min($current - 1, $last)) }}" rel="prev">{{ __('Previous') }}</a>
        @endif

        @foreach ($pages as $page)
            @if ($previous && $page > $previous + 1)
                <span class="px-1 text-sm text-slate-500" aria-hidden="true">…</span>
            @endif
            @if ($page === $current)
                <span class="notification-page-link" aria-current="page" aria-label="{{ __('Page :page', ['page' => $page]) }}">{{ $page }}</span>
            @else
                <a class="notification-page-link" href="{{ $paginator->url($page) }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
            @endif
            @php $previous = $page; @endphp
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="notification-page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Next') }}</a>
        @else
            <span class="notification-page-link" aria-disabled="true">{{ __('Next') }}</span>
        @endif
    </nav>
@endif
