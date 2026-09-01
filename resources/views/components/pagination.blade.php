@props(['paginator'])
@if ($paginator->hasPages() || $paginator->total() > 0)
    <div class="at-pagination-bar">
        <div class="at-pagination-info">
            Showing {{ $paginator->firstItem() ?? 0 }}&ndash;{{ $paginator->lastItem() ?? 0 }} of {{ number_format($paginator->total()) }}
        </div>
        @if ($paginator->hasPages())
            <div class="at-pagination-links">
                @if ($paginator->onFirstPage())
                    <span class="is-disabled">Prev</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}">Prev</a>
                @endif

                @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                    <a href="{{ $url }}" class="{{ $page === $paginator->currentPage() ? 'is-current' : '' }}">{{ $page }}</a>
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}">Next</a>
                @else
                    <span class="is-disabled">Next</span>
                @endif
            </div>
        @endif
    </div>
@endif
