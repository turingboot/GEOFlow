@php
    $paginationRows = $rows->appends([
        'range_days' => $activeRangeDays,
        'tab' => $tab['key'],
        'per_page' => $activePerPage,
    ]);
    $currentPage = $paginationRows->currentPage();
    $lastPage = $paginationRows->lastPage();
    $windowStart = max(1, $currentPage - 1);
    $windowEnd = min($lastPage, max(3, $currentPage + 1));
    $windowStart = max(1, min($windowStart, max(1, $lastPage - 2)));
    $pageNumbers = range($windowStart, $windowEnd);
@endphp

<div class="mt-3 flex flex-wrap items-center justify-end gap-3 border-t border-gray-100 pt-3 text-xs text-gray-500" data-gsc-pagination>
    <form method="GET" action="{{ route('admin.google-search-console.show', $property->id) }}" class="flex items-center gap-2">
        <input type="hidden" name="range_days" value="{{ $activeRangeDays }}">
        <input type="hidden" name="tab" value="{{ $tab['key'] }}">
        <span>&#27599;&#39029;</span>
        <select name="per_page" class="h-8 rounded-md border border-gray-200 bg-white px-2 text-xs">
            @foreach ([10, 20, 50, 100] as $size)
                <option value="{{ $size }}" @selected($activePerPage === $size)>{{ $size }}</option>
            @endforeach
        </select>
        <span>&#26465;</span>
    </form>

    @if ($lastPage > 1)
        <nav class="flex flex-wrap items-center gap-1" aria-label="Pagination">
            @if ($paginationRows->onFirstPage())
                <span class="inline-flex h-8 items-center rounded-md border border-gray-100 px-3 text-gray-300">&#19978;&#19968;&#39029;</span>
            @else
                <a href="{{ $paginationRows->previousPageUrl() }}" class="inline-flex h-8 items-center rounded-md border border-gray-200 px-3 text-gray-600 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600">&#19978;&#19968;&#39029;</a>
            @endif

            @if ($windowStart > 1)
                <a href="{{ $paginationRows->url(1) }}" class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-gray-200 px-2 text-gray-600 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600">1</a>
                @if ($windowStart > 2)
                    <span class="inline-flex h-8 items-center px-1 text-gray-400">...</span>
                @endif
            @endif

            @foreach ($pageNumbers as $pageNumber)
                @if ($pageNumber === $currentPage)
                    <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-md bg-blue-600 px-2 font-medium text-white">{{ $pageNumber }}</span>
                @else
                    <a href="{{ $paginationRows->url($pageNumber) }}" class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-gray-200 px-2 text-gray-600 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600">{{ $pageNumber }}</a>
                @endif
            @endforeach

            @if ($windowEnd < $lastPage)
                @if ($windowEnd < $lastPage - 1)
                    <span class="inline-flex h-8 items-center px-1 text-gray-400">...</span>
                @endif
                <a href="{{ $paginationRows->url($lastPage) }}" class="inline-flex h-8 items-center rounded-md border border-gray-200 px-3 text-gray-600 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600">&#26368;&#21518;&#39029;</a>
            @endif

            @if ($paginationRows->hasMorePages())
                <a href="{{ $paginationRows->nextPageUrl() }}" class="inline-flex h-8 items-center rounded-md border border-gray-200 px-3 text-gray-600 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600">&#19979;&#19968;&#39029;</a>
            @else
                <span class="inline-flex h-8 items-center rounded-md border border-gray-100 px-3 text-gray-300">&#19979;&#19968;&#39029;</span>
            @endif
        </nav>
    @endif
</div>
