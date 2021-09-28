@extends('layouts.vendor.app')

@section('title','Settings')

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">{{__('messages.restaurant')}} {{__('messages.setup')}}</h1>
                </div>
            </div>
        </div>
        <!-- End Page Header -->
        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <div class="card h-100">
                    <div class="card-header">
                        {{__('messages.restaurant')}} {{__('messages.settings')}}
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-xl-4 col-lg-4 col-md-6 col-sm-6 col-12">
                                <div class="form-group">
                                    <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border border-secondary rounded px-4 form-control" for="schedule_order">
                                        <span class="pr-2">{{__('messages.scheduled')}} {{__('messages.order')}}:</span> 
                                        <input type="checkbox" class="toggle-switch-input" onclick="location.href='{{route('vendor.business-settings.toggle-settings',[$restaurant->id,$restaurant->schedule_order?0:1, 'schedule_order'])}}'" id="schedule_order" {{$restaurant->schedule_order?'checked':''}}>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-xl-4 col-lg-4 col-md-6 col-sm-6 col-12">
                                <div class="form-group">
                                    <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border border-secondary rounded px-4 form-control" for="delivery">
                                        <span class="pr-2">{{__('messages.delivery')}}:</span> 
                                        <input type="checkbox" name="delivery" class="toggle-switch-input" onclick="location.href='{{route('vendor.business-settings.toggle-settings',[$restaurant->id,$restaurant->delivery?0:1, 'delivery'])}}'" id="delivery" {{$restaurant->delivery?'checked':''}}>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-xl-4 col-lg-4 col-md-6 col-sm-6 col-12">
                                <div class="form-group">
                                    <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border border-secondary rounded px-4 form-control" for="take_away">
                                        <span class="pr-2 text-capitalize">{{__('messages.take_away')}}:</span> 
                                        <input type="checkbox" class="toggle-switch-input" onclick="location.href='{{route('vendor.business-settings.toggle-settings',[$restaurant->id,$restaurant->take_away?0:1, 'take_away'])}}'" id="take_away" {{$restaurant->take_away?'checked':''}}>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <form action="{{route('vendor.business-settings.update-setup',[$restaurant['id']])}}" method="post"
                            enctype="multipart/form-data">
                            @csrf 
                            <div class="row">
                                <div class="col-sm-4 col-12">
                                    <div class="form-group">
                                        <label class="input-label text-capitalize" for="title">{{__('messages.opening')}} {{__('messages.time')}}</label>
                                        <input type="time" id="closeing_time" class="form-control" name="opening_time" value="{{$restaurant->opening_time?$restaurant->opening_time->format('H:i:s'):''}}">
                                    </div>
                                </div>
                                <div class="col-sm-4 col-12">
                                    <label class="input-label text-capitalize" for="title">{{__('messages.closing')}} {{__('messages.time')}}</label>
                                    <input type="time" id="closeing_time" class="form-control"  name="closeing_time" value="{{$restaurant->closeing_time?$restaurant->closeing_time->format('H:i:s'):''}}">
                                </div>
                                <div class="col-sm-4 col-12">
                                    <div class="form-group">
                                        <label class="input-label text-capitalize" for="title">{{__('messages.minimum')}} {{__('messages.order')}} {{__('messages.amount')}}</label>
                                        <input type="number" name="minimum_order" step="0.01" min="0" max="100000" class="form-control" placeholder="100" value="{{$restaurant->minimum_order??'0'}}"> 
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">{{__('messages.update')}}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        function readURL(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();

                reader.onload = function (e) {
                    $('#viewer').attr('src', e.target.result);
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#customFileEg1").change(function () {
            readURL(this);
        });
    </script>
@endpush
