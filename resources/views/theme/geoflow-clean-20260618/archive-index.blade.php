@extends('theme.geoflow-clean-20260618.layout')

@section('content')
    <div class="gc-container gc-page">
        <h1 class="gc-page-title gc-page-title-spaced">{{ __('site.archive_title') }}</h1>

        @if(count($archives) === 0)
            <p class="gc-page-desc">{{ __('site.archive_empty') }}</p>
        @else
            <ul class="gc-archive-list">
                @foreach($archives as $row)
                    <li class="gc-archive-item">
                        <a href="{{ route('site.archive.month', ['year' => $row['year'], 'month' => $row['month']]) }}">
                            {{ $row['year'] }}-{{ $row['month'] }}
                        </a>
                        <span class="gc-archive-count">({{ $row['count'] }})</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
