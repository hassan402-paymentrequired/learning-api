{{--
    Legacy layout alias — all notifications should extend emails.layouts.base directly.
--}}
@extends('emails.layouts.base')

@section('content')
    @yield('body')
@endsection
