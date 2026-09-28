@extends('errors.layout')

@section('title', __('Payment Required'))
@section('code', '402')
@section('headline', __('Transaction Settlement Required'))
@section('message', __('This terminal operation requires an authorized payment settlement, valid merchant gateway response, or active checkout session.'))

@section('icon')
    <svg class="h-10 w-10 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
    </svg>
@endsection
