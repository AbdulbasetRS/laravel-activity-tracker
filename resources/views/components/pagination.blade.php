@props(['paginator'])
@if ($paginator->hasPages() || $paginator->total() > 0)
    <div class="at-pagination-bar">
        <div class="at-pagination-info">
            Showing {{ $paginator->firstItem() ?? 0 }}&ndash;{{ $paginator->lastItem() ?? 0 }} of {{ number_format($paginator->total()) }}
        </div>
        @if ($paginator->hasPages())
            <nav class="at-pagination-links" aria-label="Activities pagination">
                @if ($paginator->onFirstPage())
                    <span class="is-disabled" aria-disabled="true">Prev</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" data-at-ajax-link data-at-page-link>Prev</a>
                @endif

                @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                    <a href="{{ $url }}" data-at-ajax-link data-at-page-link class="{{ $page === $paginator->currentPage() ? 'is-current' : '' }}" @if ($page === $paginator->currentPage()) aria-current="page" @endif>{{ $page }}</a>
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" data-at-ajax-link data-at-page-link>Next</a>
                @else
                    <span class="is-disabled" aria-disabled="true">Next</span>
                @endif
            </nav>
        @endif
    </div>
@endif
