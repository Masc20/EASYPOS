@extends('errors.layout')

@section('title', __('Access Forbidden'))
@section('code', '403')
@section('headline', __('Station Access Restricted'))
@section('message', __('Your employee account does not have permission to view or operate this area. Floor staff terminals are isolated to their designated operational stations.'))

@section('icon')
    <svg class="h-10 w-10 text-brand-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
    </svg>
@endsection
