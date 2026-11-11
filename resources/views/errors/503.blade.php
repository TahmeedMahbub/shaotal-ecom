@extends('errors.layout')
@section('code', '503')
@section('title', "We'll be back soon")
@section('message', "Our store is briefly down for maintenance. Please check back in a few minutes.")
@section('actions')
  <a href="/" class="btn btn-primary">Try Again</a>
@endsection
