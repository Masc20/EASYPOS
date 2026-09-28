@extends('errors.layout')

@php
    $code = isset($exception) ? $exception->getStatusCode() : 500;
@endphp

@section('title', __('Server Fault'))
@section('code', (string) $code)
@section('headline', __('Server Infrastructure Fault'))
@section('message', __('A backend server error or gateway communication fault was encountered. Please contact your shift supervisor or system administrator.'))

@section('icon')
    <svg class="h-10 w-10 text-brand-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
    </svg>
@endsection
