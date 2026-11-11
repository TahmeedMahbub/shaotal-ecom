@extends('errors.layout')
@section('code', '419')
@section('title', "Page expired")
@section('message', "For your security this page expired. Please go back, refresh the page and try again.")
@section('actions')
  <a href="/" class="btn btn-primary" onclick="if (history.length > 1) { history.back(); return false; }">Go Back</a>
  <a href="/" class="btn btn-outline">Back to Home</a>
@endsection
