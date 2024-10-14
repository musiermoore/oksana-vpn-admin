@props(['title' => 'Latvia VPN'])

@extends('layouts.app')

@section('title', $title)

@section('content')
    {{ $slot }}
@endsection
