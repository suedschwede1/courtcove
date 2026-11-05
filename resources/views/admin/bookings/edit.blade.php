@extends(request('popup') ? 'layouts.popup' : 'layouts.admin')
@section('title', ($isCreate ?? false) ? __('booking.admin.bookings.create_title') : __('booking.admin.bookings.edit_title'))
@section('admin-title', ($isCreate ?? false) ? __('booking.admin.bookings.create_title') : __('booking.admin.bookings.edit_title'))
@unless(($isCreate ?? false) || request('popup'))
    @section('admin-back', route('admin.bookings.index'))
@endunless

@section('content')
    @include(($isCreate ?? false) ? 'admin.bookings._create' : 'admin.bookings._update')
    @include('admin.bookings._script')
@endsection

@section('admin-content')
    @yield('content')
@endsection
