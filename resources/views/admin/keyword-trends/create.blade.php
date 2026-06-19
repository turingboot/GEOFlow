@extends('admin.layouts.app')

@section('content')
    <div>
        <div class="admin-hero">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.keyword-trends.index') }}" class="text-white/70 hover:text-white"><i data-lucide="arrow-left" class="h-5 w-5"></i></a>
                <div><h1 class="admin-hero-title">{{ __('admin.keyword_trends.create_heading') }}</h1></div>
            </div>
        </div>
        <div class="admin-card">
            <div class="admin-card-body">
                @include('admin.keyword-trends._form')
            </div>
        </div>
    </div>
@endsection
