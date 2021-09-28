@extends('layouts.admin.app')

@section('title','Settings')

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">{{__('messages.config')}}</h1>
                </div>
            </div>
        </div>
        <!-- End Page Header -->
        <div class="row gx-2 gx-lg-3">
        @php($map_api_key=\App\Models\BusinessSetting::where(['key'=>'map_api_key'])->first())
        @php($map_api_key=$map_api_key?$map_api_key->value:null)

        {{--@php($app_minimum_version=\App\Models\BusinessSetting::where(['key'=>'app_minimum_version'])->first())
        @php($app_minimum_version=$app_minimum_version?$app_minimum_version->value:null)

        @php($app_url=\App\Models\BusinessSetting::where(['key'=>'app_url'])->first())
        @php($app_url=$app_url?$app_url->value:null)--}}
        
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <form action="{{env('APP_MODE')!='demo'?route('admin.business-settings.config-update'):'javascript:'}}" method="post"
                      enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group mb-2">
                                <label class="input-label" style="padding-left: 10px">{{__('messages.map_api_key')}}</label>
                                <input type="text" placeholder="Map api key" class="form-control" name="map_api_key"
                                    value="{{env('APP_MODE')!='demo'?$map_api_key??'':''}}" required>
                            </div>
                        </div>
                    </div>
                    {{--<div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label  class="input-label" style="padding-left: 10px">{{__('messages.app_minimum_version')}}</label>
                                <input type="number" placeholder="{{__('messages.app_minimum_version')}}" class="form-control" name="app_minimum_version"
                                    value="{{env('APP_MODE')!='demo'?$app_minimum_version??'':''}}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label class="input-label" style="padding-left: 10px">{{__('messages.app_url')}}</label>
                                <input type="text" placeholder="{{__('messages.app_url')}}" class="form-control" name="app_url"
                                    value="{{env('APP_MODE')!='demo'?$app_url??'':''}}" required>
                            </div>
                        </div>
                    </div>--}}


                        <button type="{{env('APP_MODE')!='demo'?'submit':'button'}}" onclick="{{env('APP_MODE')!='demo'?'':'call_demo()'}}" class="btn btn-primary mb-2">{{__('messages.save')}}</button>
                    
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script_2')

@endpush
