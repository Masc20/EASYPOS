@extends('errors.layout')

@section('title', $title ?? __('Error'))
@section('code', $code ?? 'Error')
@section('headline', $title ?? __('Terminal Notice'))
@section('message', $message ?? __('An unexpected situation was encountered on this terminal.'))

@section('icon')
    <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
    </svg>
@endsection
