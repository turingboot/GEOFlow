@extends('admin.layouts.app')

@section('content')
    <div>
        <div class="admin-hero">
            <div>
                <h1 class="admin-hero-title">{{ __('admin.gsc.edit_heading') }}</h1>
                <p class="admin-hero-sub">{{ __('admin.gsc.page_subtitle') }}</p>
            </div>
        </div>
        <div class="admin-card p-6">
            @include('admin.google-search-console._form')
        </div>
    </div>
@endsection
