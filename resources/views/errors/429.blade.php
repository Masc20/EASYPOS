@extends('errors.layout')

@section('title', __('Too Many Requests'))
@section('code', '429')
@section('headline', __('Terminal Rate Limit Exceeded'))
@section('message', __('Too many requests or authentication attempts were made from this terminal. Please pause for a moment before retrying to ensure system security.'))

@section('icon')
    <svg class="h-10 w-10 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 10V3L4 14h7v7l9-11h-7z" />
    </svg>
@endsection
