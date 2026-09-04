@extends('layouts.base')
@section('title','جزئیات درخواست')@section('page-title','جزئیات درخواست')
@section('content')
<div class="mb-3"><a class="btn btn-outline-secondary" href="{{ route('admin.requests.index') }}"><i class="bi bi-arrow-right"></i> بازگشت به گزارش درخواست‌ها</a></div>
@include('partials.request-detail')
@endsection
