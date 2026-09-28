@extends('errors.layout')

@section('title', __('Not Found'))
@section('code', '404')
@section('headline', __('Page or Resource Not Found'))
@section('message', __('The requested URL, menu item, or terminal view could not be located. It may have been relocated, deleted, or entered incorrectly.'))

@section('icon')
    <svg class="h-10 w-10 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
@endsection
