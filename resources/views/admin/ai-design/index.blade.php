@extends('layouts.admin')

@section('title', 'AI Mode')
@section('page-title', 'AI Mode')

@section('content')
    @include('partials.ai-design-studio', [
        'generateUrl' => $generateUrl,
        'createProductUrl' => $createProductUrl,
    ])
@endsection
