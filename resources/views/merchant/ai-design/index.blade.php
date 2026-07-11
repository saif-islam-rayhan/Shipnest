@extends('layouts.merchant')

@section('title', 'AI Design')
@section('page-title', 'AI Design')

@section('content')
    @include('partials.ai-design-studio', [
        'generateUrl' => route('merchant.ai-design.generate'),
        'createProductUrl' => $createProductUrl,
    ])
@endsection
