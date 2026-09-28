@extends('errors.layout')

@section('title', __('Bad Request'))
@section('code', '400')
@section('headline', __('Invalid Request Payload'))
@section('message', __('The terminal request could not be processed due to malformed parameters, invalid syntax, or corrupted transaction data.'))

@section('icon')
    <svg class="h-10 w-10 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
@endsection
