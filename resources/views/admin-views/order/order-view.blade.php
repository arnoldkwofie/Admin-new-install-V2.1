@extends('layouts.admin.app')

@section('title','Order Details')

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header d-print-none">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb breadcrumb-no-gutter">
                            <li class="breadcrumb-item">
                                <a class="breadcrumb-link"
                                   href="{{route('admin.order.list',['status'=>'all'])}}">
                                    Orders
                                </a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{__('messages.order')}} {{__('messages.details')}}</li>
                        </ol>
                    </nav>

                    <div class="d-sm-flex align-items-sm-center">
                        <h1 class="page-header-title">{{__('messages.order')}} #{{$order['id']}}</h1>

                        @if($order['payment_status']=='paid')
                            <span class="badge badge-soft-success ml-sm-3">
                                <span class="legend-indicator bg-success"></span>{{__('messages.paid')}}
                            </span>
                        @else
                            <span class="badge badge-soft-danger ml-sm-3">
                                <span class="legend-indicator bg-danger"></span>{{__('messages.unpaid')}}
                            </span>
                        @endif

                        @if($order['order_status']=='pending')
                            <span class="badge badge-soft-info ml-2 ml-sm-3 text-capitalize">
                              <span class="legend-indicator bg-info text"></span>{{__('messages.pending')}}
                            </span>
                        @elseif($order['order_status']=='confirmed')
                            <span class="badge badge-soft-info ml-2 ml-sm-3 text-capitalize">
                              <span class="legend-indicator bg-info"></span>{{__('messages.confirmed')}}
                            </span>
                        @elseif($order['order_status']=='processing')
                            <span class="badge badge-soft-warning ml-2 ml-sm-3 text-capitalize">
                              <span class="legend-indicator bg-warning"></span>{{__('messages.processing')}}
                            </span>
                        @elseif($order['order_status']=='picked_up')
                            <span class="badge badge-soft-warning ml-2 ml-sm-3 text-capitalize">
                              <span class="legend-indicator bg-warning"></span>{{__('messages.out_for_delivery')}}
                            </span>
                        @elseif($order['order_status']=='delivered')
                            <span class="badge badge-soft-success ml-2 ml-sm-3 text-capitalize">
                              <span class="legend-indicator bg-success"></span>{{__('messages.delivered')}}
                            </span>
                        @elseif($order['order_status']=='failed')
                            <span class="badge badge-soft-danger ml-2 ml-sm-3 text-capitalize">
                                <span class="legend-indicator text-capitalize bg-danger"></span>{{__('messages.payment')}} {{ __('messages.failed')}}
                            </span>
                        @else
                            <span class="badge badge-soft-danger ml-2 ml-sm-3 text-capitalize">
                              <span class="legend-indicator bg-danger"></span>{{str_replace('_',' ',$order['order_status'])}}
                            </span>
                        @endif
                        <span class="ml-2 ml-sm-3">
                                <i class="tio-date-range"></i> {{date('d M Y H:i:s',strtotime($order['created_at']))}}
                        </span>
                    </div>

                    <div class="mt-2">
                        <a class="text-body mr-3"
                           href={{route('admin.order.generate-invoice',[$order['id']])}}>
                            <i class="tio-print mr-1"></i> {{__('messages.print')}} {{__('messages.invoice')}}
                        </a>

                        <!-- Unfold -->
                    {{--<div class="hs-unfold ml-1">
                            <h5>
                                <i class="tio-shop"></i>
                                {{__('messages.restaurant')}} : <label
                                    class="badge badge-secondary">{{$order->restaurant?$order->restaurant->name:'Restaurant deleted!'}}</label>
                            </h5>
                        </div>--}}
                        @php
                        
                        $low_value =\App\Models\BusinessSetting::where(['key' => 'low_value'])->first()->value;
                        $high_value =\App\Models\BusinessSetting::where(['key' => 'high_value'])->first()->value;
                        $low_fraction_numerator =\App\Models\BusinessSetting::where(['key' => 'low_fraction_numerator'])->first()->value;
                        $low_fraction_denominator =\App\Models\BusinessSetting::where(['key' => 'low_fraction_denominator'])->first()->value;
                        $intermediate_fraction_numerator =\App\Models\BusinessSetting::where(['key' => 'intermediate_fraction_numerator'])->first()->value;
                        $intermediate_fraction_denominator =\App\Models\BusinessSetting::where(['key' => 'intermediate_fraction_denominator'])->first()->value;
                        $high_fraction_numerator =\App\Models\BusinessSetting::where(['key' => 'high_fraction_numerator'])->first()->value;
                        $high_fraction_denominator =\App\Models\BusinessSetting::where(['key' => 'high_fraction_denominator'])->first()->value;
                        $restaurant_deduction =\App\Models\BusinessSetting::where(['key' => 'restaurant_deduction'])->first()->value;
                        $payment_gateway =\App\Models\BusinessSetting::where(['key' => 'payment_gateway'])->first()->value;

                        $refund_amount = $order->order_amount;
        
                            $sub_refund_amount =$refund_amount- $order->delivery_charge;
                            
                            if($sub_refund_amount<=($low_value + (($low_fraction_numerator/$low_fraction_denominator)*$low_value))){
                                $comission_amount = $sub_refund_amount *($low_fraction_numerator/($low_fraction_denominator + 1));
                            }elseif($sub_refund_amount> ($low_value + (($low_fraction_numerator/$low_fraction_denominator)*$low_value)) and $sub_refund_amount<=($high_value + (($intermediate_fraction_numerator/$intermediate_fraction_denominator)*$high_value))){
                                $comission_amount = $sub_refund_amount*($intermediate_fraction_numerator/($intermediate_fraction_denominator + 1));
                            }else{
                                $comission_amount = $sub_refund_amount * ($high_fraction_numerator/($high_fraction_denominator + 1));
                            }

                            $food_price= $sub_refund_amount -$comission_amount;
                            $admin_refund=(($restaurant_deduction/100)*$food_price);
                            $restaurant_refund= $food_price- (($restaurant_deduction/100)*$food_price);
                            $refund_amount= $refund_amount-$comission_amount;
                            if($order->order_status == 'delivered')
                            {
                                $refund_amount = $refund_amount - $order->delivery_charge;
                                
                            }
                        @endphp
                        <div class="hs-unfold ml-1">
                            <h5>
                                <button class="btn btn-xs btn-secondary"  data-toggle="modal" data-target="#locationModal" ><i class="tio-map"></i> {{__('messages.show_locations_on_map')}}</button>
                                @if($order->payment_method != 'cash_on_delivery' && $order->payment_status == 'paid' && $order->order_status != 'refunded')
                                <button class="btn btn-xs btn-danger"  onclick="route_alert('{{route('admin.order.status',['id'=>$order['id'],'order_status'=>'refunded'])}}','{{__('messages.you_want_to_refund_this_order', ['amount'=> $refund_amount.' '.\App\CentralLogics\Helpers::currency_code()])}}', '{{__('messages.are_you_sure_want_to_refund')}}')" ><i class="tio-money"></i> {{__('messages.refund_this_order')}}</button>
                                @endif
                            </h5>
                        </div>

                        <div class="hs-unfold float-right">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                                        id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true"
                                        aria-expanded="false">
                                    {{__('messages.status')}}
                                </button>
                                @php($order_delivery_verification = (boolean)\App\Models\BusinessSetting::where(['key' => 'order_delivery_verification'])->first()->value)
                                <div class="dropdown-menu text-capitalize" aria-labelledby="dropdownMenuButton">
                                    <a class="dropdown-item {{$order['order_status']=='pending'?'active':''}}"
                                       onclick="route_alert('{{route('admin.order.status',['id'=>$order['id'],'order_status'=>'pending'])}}','Change status to pending ?')"
                                       href="javascript:">{{__('messages.pending')}}</a>
                                    <a class="dropdown-item {{$order['order_status']=='confirmed'?'active':''}}"
                                       onclick="route_alert('{{route('admin.order.status',['id'=>$order['id'],'order_status'=>'confirmed'])}}','Change status to confirmed ?')"
                                       href="javascript:">{{__('messages.confirmed')}}</a>
                                    <a class="dropdown-item {{$order['order_status']=='processing'?'active':''}}"
                                       onclick="route_alert('{{route('admin.order.status',['id'=>$order['id'],'order_status'=>'processing'])}}','Change status to processing ?')"
                                       href="javascript:">{{__('messages.processing')}}</a>
                                    <a class="dropdown-item {{$order['order_status']=='handover'?'active':''}}"
                                       onclick="route_alert('{{route('admin.order.status',['id'=>$order['id'],'order_status'=>'handover'])}}','Change status to handover ?')"
                                       href="javascript:">{{__('messages.handover')}}</a>
                                    <a class="dropdown-item {{$order['order_status']=='picked_up'?'active':''}}"
                                       onclick="route_alert('{{route('admin.order.status',['id'=>$order['id'],'order_status'=>'picked_up'])}}','Change status to out for delivery ?')"
                                       href="javascript:">{{__('messages.out_for_delivery')}}</a>
                                    <a class="dropdown-item {{$order['order_status']=='delivered'?'active':''}}"
                                       onclick="route_alert('{{route('admin.order.status',['id'=>$order['id'],'order_status'=>'delivered'])}}','Change status to delivered (payment status will be paid if not)?')"
                                       href="javascript:">{{__('messages.delivered')}}</a>
                                    <a class="dropdown-item {{$order['order_status']=='canceled'?'active':''}}"
                                       onclick="route_alert('{{route('admin.order.status',['id'=>$order['id'],'order_status'=>'canceled'])}}','Change status to canceled ?')"
                                       href="javascript:">{{__('messages.canceled')}}</a>
                                </div>
                            </div>
                        </div>

                        <!-- End Unfold -->
                    </div>
                </div>

                <div class="col-sm-auto">
                    <a class="btn btn-icon btn-sm btn-ghost-secondary rounded-circle mr-1"
                       href="{{route('admin.order.details',[$order['id']-1])}}"
                       data-toggle="tooltip" data-placement="top" title="Previous order">
                        <i class="tio-arrow-backward"></i>
                    </a>
                    <a class="btn btn-icon btn-sm btn-ghost-secondary rounded-circle"
                       href="{{route('admin.order.details',[$order['id']+1])}}" data-toggle="tooltip"
                       data-placement="top" title="Next order">
                        <i class="tio-arrow-forward"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- End Page Header -->

        <div class="row" id="printableArea">
            <div class="col-lg-8 mb-3 mb-lg-0">
                <!-- Card -->
                <div class="card mb-3 mb-lg-5">
                    <!-- Header -->
                    <div class="card-header" style="display: block!important;">
                        <div class="row">
                            <div class="col-12 pb-2 border-bottom">
                                <h4 class="card-header-title">
                                    {{__('messages.order')}} {{__('messages.details')}}
                                    <span
                                        class="badge badge-soft-dark rounded-circle ml-1">{{$order->details->count()}}</span>
                                </h4>
                            </div>
                            <div class="col-6 pt-2">
                                <h6 style="color: #8a8a8a;">
                                    {{__('messages.order')}} {{__('messages.note')}} : {{$order['order_note']}}
                                </h6>
                            </div>
                            <div class="col-6 pt-2">
                                <div class="text-right">
                                    <h6 class="text-capitalize" style="color: #8a8a8a;">
                                        {{__('messages.payment')}} {{__('messages.method')}} : {{str_replace('_',' ',$order['payment_method'])}}
                                    </h6>
                                    <h6 class="" style="color: #8a8a8a;">
                                        @if($order['transaction_reference']==null)
                                            {{__('messages.reference')}} {{__('messages.code')}} :
                                            <button class="btn btn-outline-primary btn-sm" data-toggle="modal"
                                                    data-target=".bd-example-modal-sm">
                                                {{__('messages.add')}}
                                            </button>
                                        @else
                                            {{__('messages.reference')}} {{__('messages.code')}} : {{$order['transaction_reference']}}
                                        @endif
                                    </h6>
                                    <h6 class="text-capitalize" style="color: #8a8a8a;">{{__('messages.order')}} {{__('messages.type')}}
                                        : <label style="font-size: 10px"
                                                 class="badge badge-soft-primary">{{str_replace('_',' ',$order['order_type'])}}</label>
                                    </h6>
                                    @if($order->schedule_at && $order->scheduled)
                                    <h6 class="text-capitalize" style="color: #8a8a8a;">{{__('messages.scheduled_at')}}
                                        : <label style="font-size: 10px"
                                                 class="badge badge-soft-primary">{{date('d M Y H:i:s',strtotime($order['schedule_at']))}}</label>
                                    </h6>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- End Header -->

                    <!-- Body -->
                    <div class="card-body">
                    @php($sub_total=0)
                    @php($add_ons_cost=0)
                    @foreach($order->details as $detail)
                        @if($detail->food)
                            <!-- Media -->
                                <div class="media">
                                    <div class="avatar avatar-xl mr-3">
                                        <img class="img-fluid"
                                             src="{{asset('storage/app/public/product')}}/{{$detail->food['image']}}"
                                             onerror="this.src='{{asset('public/assets/admin/img/160x160/img2.jpg')}}'"
                                             alt="Image Description">
                                    </div>

                                    <div class="media-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <strong> {{$detail->food['name']}}</strong><br>

                                                @if(count(json_decode($detail['variation'],true))>0)
                                                    <strong><u>{{__('messages.variation')}} : </u></strong>
                                                    @foreach(json_decode($detail['variation'],true)[0] as $key1 =>$variation)
                                                        <div class="font-size-sm text-body">
                                                            <span>{{$key1}} :  </span>
                                                            <span class="font-weight-bold">{{$variation}}</span>
                                                        </div>
                                                    @endforeach
                                                @endif

                                                @foreach(json_decode($detail['add_ons'],true) as $key2 =>$addon)
                                                    @if($key2==0)<strong><u>{{__('messages.addons')}} : </u></strong>@endif
                                                    <div class="font-size-sm text-body">
                                                        <span>{{$addon['name']}} :  </span>
                                                        <span class="font-weight-bold">
                                                            {{$addon['quantity']}} x {{\App\CentralLogics\Helpers::format_currency($addon['price'])}}
                                                        </span>
                                                    </div>
                                                    @php($add_ons_cost+=$addon['price']*$addon['quantity'])
                                                @endforeach
                                            </div>

                                            <div class="col col-md-2 align-self-center">
                                                <h6>{{\App\CentralLogics\Helpers::format_currency($detail['price'])}}</h6>
                                            </div>
                                            <div class="col col-md-1 align-self-center">
                                                <h5>{{$detail['quantity']}}</h5>
                                            </div>

                                            <div class="col col-md-3 align-self-center text-right">
                                                @php($amount=($detail['price'])*$detail['quantity'])
                                                <h5>{{\App\CentralLogics\Helpers::format_currency($amount)}}</h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @php($sub_total+=$amount)
                            <!-- End Media -->
                                <hr>
                        
                        @elseif($detail->campaign)
                            <!-- Media -->
                                <div class="media">
                                    <div class="avatar avatar-xl mr-3">
                                        <img class="img-fluid"
                                             src="{{asset('storage/app/public/campaign')}}/{{$detail->campaign['image']}}"
                                             onerror="this.src='{{asset('public/assets/admin/img/160x160/img2.jpg')}}'"
                                             alt="Image Description">
                                    </div>

                                    <div class="media-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <strong> {{$detail->campaign['title']}}</strong><br>

                                                @if(count(json_decode($detail['variation'],true))>0)
                                                    <strong><u>{{__('messages.variation')}} : </u></strong>
                                                    @foreach(json_decode($detail['variation'],true)[0] as $key1 =>$variation)
                                                        <div class="font-size-sm text-body">
                                                            <span>{{$key1}} :  </span>
                                                            <span class="font-weight-bold">{{$variation}}</span>
                                                        </div>
                                                    @endforeach
                                                @endif

                                                @foreach(json_decode($detail['add_ons'],true) as $key2 =>$addon)
                                                    @if($key2==0)<strong><u>{{__('messages.addons')}} : </u></strong>@endif
                                                    <div class="font-size-sm text-body">
                                                        <span>{{$addon['name']}} :  </span>
                                                        <span class="font-weight-bold">
                                                            {{$addon['quantity']}} x {{\App\CentralLogics\Helpers::format_currency($addon['price'])}}
                                                        </span>
                                                    </div>
                                                    @php($add_ons_cost+=$addon['price']*$addon['quantity'])
                                                @endforeach
                                            </div>

                                            <div class="col col-md-2 align-self-center">
                                                <h6>{{\App\CentralLogics\Helpers::format_currency($detail['price']) }}</h6>
                                            </div>
                                            <div class="col col-md-1 align-self-center">
                                                <h5>{{$detail['quantity']}}</h5>
                                            </div>

                                            <div class="col col-md-3 align-self-center text-right">
                                                @php($amount=($detail['price'])*$detail['quantity'])
                                                <h5>{{\App\CentralLogics\Helpers::format_currency($amount)}}</h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @php($sub_total+=$amount)
                            <!-- End Media -->
                                <hr>
                        @endif
                    @endforeach

                        <div class="row justify-content-md-end mb-3">
                            <div class="col-md-9 col-lg-8">
                                <dl class="row text-sm-right">
                                    <dt class="col-sm-6">{{__('messages.items')}} {{__('messages.price')}}:</dt>
                                    <dd class="col-sm-6">{{\App\CentralLogics\Helpers::format_currency($sub_total)}}</dd>
                                    <dt class="col-sm-6">{{__('messages.addon')}} {{__('messages.cost')}}:</dt>
                                    <dd class="col-sm-6">
                                        {{\App\CentralLogics\Helpers::format_currency($add_ons_cost)}}
                                        <hr>
                                    </dd>

                                    <dt class="col-sm-6">{{__('messages.subtotal')}}:</dt>
                                    <dd class="col-sm-6">
                                        {{\App\CentralLogics\Helpers::format_currency($sub_total+$add_ons_cost)}}</dd>
                                    <dt class="col-sm-6">{{__('messages.discount')}}:</dt>
                                    <dd class="col-sm-6">
                                        - {{\App\CentralLogics\Helpers::format_currency($order['restaurant_discount_amount'])}}</dd>
                                    <dt class="col-sm-6">{{__('messages.coupon')}} {{__('messages.discount')}}:</dt>
                                    <dd class="col-sm-6">
                                        - {{\App\CentralLogics\Helpers::format_currency($order['coupon_discount_amount'])}}</dd>
                                    <dt class="col-sm-6">{{__('messages.vat/tax')}}:</dt>
                                    <dd class="col-sm-6">
                                        + {{\App\CentralLogics\Helpers::format_currency($order['total_tax_amount'])}}</dd>
                                    <dt class="col-sm-6">{{__('messages.delivery')}} {{__('messages.fee')}}:</dt>
                                    <dd class="col-sm-6">
                                            @php($del_c=$order['delivery_charge'])
                                        + {{\App\CentralLogics\Helpers::format_currency($del_c)}}
                                        <hr>
                                    </dd>

                                    <dt class="col-sm-6">{{__('messages.total')}}:</dt>
                                    <dd class="col-sm-6">{{\App\CentralLogics\Helpers::format_currency($sub_total+$del_c+$order['total_tax_amount']+$add_ons_cost-$order['coupon_discount_amount'] - $order['restaurant_discount_amount'])}}</dd>
                                </dl>
                                <!-- End Row -->
                            </div>
                        </div>
                        <!-- End Row -->
                    </div>
                    <!-- End Body -->
                </div>
                <!-- End Card -->
            </div>

            <div class="col-lg-4">
                @if($order['order_type']!='take_away')
                <!-- Card -->
                <div class="card mb-2">
                    <!-- Header -->
                    <div class="card-header">
                        <h4 class="card-header-title">{{__('messages.deliveryman')}}</h4>
                        @if($order->delivery_man && !isset($order->delivered))  
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#myModal">
                            {{__('messages.change')}}
                        </button>
                        @endif
                    </div>
                    <!-- End Header -->

                    <!-- Body -->
                    
                    <div class="card-body">
                    @if($order->delivery_man)    
                        <a class="media align-items-center  deco-none" href="{{route('admin.delivery-man.preview',[$order->delivery_man['id']])}}">                            
                            <div class="avatar avatar-circle mr-3">
    
                                    <img class="avatar-img" style="width: 75px"
                                    onerror="this.src='{{asset('public/assets/admin/img/160x160/img1.jpg')}}'"
                                    src="{{asset('storage/app/public/delivery-man/'.$order->delivery_man->image)}}"
                                    alt="Image Description">
                            </div>
                            <div class="media-body">
                                <span class="text-body text-hover-primary">{{$order->delivery_man['f_name'].' '.$order->delivery_man['l_name']}}</span><br>
                                <span class="badge badge-ligh">{{$order->delivery_man->orders->count()}} {{__('messages.orders_delivered')}}</span>
                            </div>
                        </a>

                        <hr>

                        <div class="d-flex justify-content-between align-items-center">
                            <h5>{{__('messages.contact')}} {{__('messages.info')}}</h5>
                        </div>

                        <ul class="list-unstyled list-unstyled-py-2">
                            <li>
                                <i class="tio-online mr-2"></i>
                                {{$order->delivery_man['email']}}
                            </li>
                            <li>
                                <a class="deco-none" href="tel:{{$order->delivery_man['phone']}}">
                                    <i class="tio-android-phone-vs mr-2"></i>
                                {{$order->delivery_man['phone']}}</a> 
                            </li>
                        </ul>

                        <hr>
                        @php($address=$order->dm_last_location)
                        <div class="d-flex justify-content-between align-items-center">
                            <h5>{{__('messages.last')}} {{__('messages.location')}}</h5>
                        </div>
                        @if(isset($address))
                        <span class="d-block">
                            <a target="_blank"
                                href="http://maps.google.com/maps?z=12&t=m&q=loc:{{$address['latitude']}}+{{$address['longitude']}}">
                                <i class="tio-map"></i> {{$address['location']}}<br>
                            </a>
                        </span>
                        @else
                        <span class="d-block text-lowercase qcont">
                            {{__('messages.location').' '.__('messages.not_found')}}
                        </span>
                        @endif
           
                    @else
                        <div class="w-100 text-center">
                            <div class="hs-unfold">
                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#myModal" data-lat='21.03' data-lng='105.85'>
                                    {{__('messages.assign_delivery_mam_manually')}}
                                </button>
                            </div>
                        </div>
                    @endif    
                    </div>
                    
                <!-- End Body -->
                </div>
                <!-- End Card -->
                @endif
                <!-- Customer Card -->
                <div class="card mb-2">
                    <!-- Header -->
                    <div class="card-header">
                        <h4 class="card-header-title">{{__('messages.customer')}}</h4>
                    </div>
                    <!-- End Header -->

                    <!-- Body -->
                    @if($order->customer)
                        <div class="card-body">
     
                            <a class="media align-items-center deco-none" href="{{route('admin.customer.view',[$order->customer['id']])}}">    
                                <div class="avatar avatar-circle mr-3">
                                    
                                    <img class="avatar-img" style="width: 75px"
                                    onerror="this.src='{{asset('public/assets/admin/img/160x160/img1.jpg')}}'"
                                    src="{{asset('storage/app/public/profile/'.$order->customer->image)}}"
                                    alt="Image Description">

                                </div>
                                <div class="media-body">
                                    <span class="text-body text-hover-primary">{{$order->customer['f_name'].' '.$order->customer['l_name']}}</span><br>
                                    <span class="badge badge-ligh">{{$order->customer->orders->count()}} {{__('messages.orders')}}</span>
                                </div>
            
                            </a>

                            <hr>

                            <div class="d-flex justify-content-between align-items-center">
                                <h5>{{__('messages.contact')}} {{__('messages.info')}}</h5>
                            </div>

                            <ul class="list-unstyled list-unstyled-py-2">
                                <li>
                                    <i class="tio-online mr-2"></i>
                                    {{$order->customer['email']}}
                                </li>
                                <li>
                                    <a class="deco-none" href="tel:{{$order->customer['phone']}}">
                                        <i class="tio-android-phone-vs mr-2"></i>
                                        {{$order->customer['phone']}}
                                    </a>
                                </li>
                            </ul>

                            @if($order->delivery_address)
                                <hr>
                                @php($address=json_decode($order->delivery_address,true))
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5>{{__('messages.delivery')}} {{__('messages.info')}}</h5>
                                    @if(isset($address))
                                        <a class="link" data-toggle="modal" data-target="#shipping-address-modal"
                                           href="javascript:">{{__('messages.edit')}}</a>
                                    @endif
                                </div>
                                @if(isset($address))
                                    <span class="d-block">
                                    {{__('messages.name')}}: {{$address['contact_person_name']}}<br>
                                    {{__('messages.contact')}}:<a class="deco-none" href="tel:{{$address['contact_person_number']}}"> {{$address['contact_person_number']}}</a><br>
                                    @if(isset($address['address']))
                                    @if(isset($address['latitude']) && isset($address['longitude']))
                                    <a target="_blank"
                                       href="http://maps.google.com/maps?z=12&t=m&q=loc:{{$address['latitude']}}+{{$address['longitude']}}">
                                       <i class="tio-map"></i>{{$address['address']}}<br>
                                    </a>
                                    @else
                                       <i class="tio-map"></i>{{$address['address']}}<br>
                                    @endif

                                    @endif
                                </span>
                                @endif
                            @endif
                        </div>
                    @endif
                <!-- End Body -->
                </div>
                <!-- End Card -->
                <!-- Restaurant Card -->
                <div class="card">
                    <!-- Header -->
                    <div class="card-header">
                        <h4 class="card-header-title">{{__('messages.restaurant')}}</h4>
                    </div>
                    <!-- End Header -->

                    <!-- Body -->
                    @if($order->restaurant)
                    <div class="card-body">
                        <a class="media align-items-center deco-none" href="{{route('admin.vendor.view',[$order->restaurant['id']])}}">
                            <div class="avatar avatar-circle mr-3">
                                <img
                                    class="avatar-img" style="width: 75px"
                                    onerror="this.src='{{asset('public/assets/admin/img/160x160/img1.jpg')}}'"
                                    src="{{asset('storage/app/public/restaurant/'.$order->restaurant->logo)}}"
                                    alt="Image Description">
                            </div>
                            <div class="media-body">
                                <span class="text-body text-hover-primary">{{$order->restaurant->name}}</span><br>
                                <span class="badge badge-ligh">{{$order->restaurant->orders->count()}} {{__('messages.orders_served')}}</span>
                            </div>
                        </a>
                        <hr>

                        <div class="d-flex justify-content-between align-items-center">
                            <h5>{{__('messages.contact')}} {{__('messages.info')}}</h5>
                        </div>

                        <ul class="list-unstyled list-unstyled-py-2">
                            <li>
                                <i class="tio-online mr-2"></i>
                                {{$order->restaurant['email']}}
                            </li>
                            <li>
                                <a class="deco-none" href="tel:{{$order->restaurant['phone']}}">
                                    <i class="tio-android-phone-vs mr-2"></i>
                                    {{$order->restaurant['phone']}}
                                </a>
                            </li>
                        </ul>
                        <hr>
                        <span class="d-block">
                            <a target="_blank"
                                href="http://maps.google.com/maps?z=12&t=m&q=loc:{{$order->restaurant['latitude']}}+{{$order->restaurant['longitude']}}">
                                <i class="tio-map"></i> {{$order->restaurant['address']}}<br>
                            </a>
                        </span>
                    </div>
                    @endif
                    <!-- End Body -->
                </div>
                <!-- End Card -->
            </div>
        </div>
        <!-- End Row -->
    </div>

    <!-- Modal -->
    <div class="modal fade bd-example-modal-sm" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title h4" id="mySmallModalLabel">{{__('messages.reference')}} {{__('messages.code')}} {{__('messages.add')}}</h5>
                    <button type="button" class="btn btn-xs btn-icon btn-ghost-secondary" data-dismiss="modal"
                            aria-label="Close">
                        <i class="tio-clear tio-lg"></i>
                    </button>
                </div>

                <form action="{{route('admin.order.add-payment-ref-code',[$order['id']])}}" method="post">
                    @csrf
                    <div class="modal-body">
                        <!-- Input Group -->
                        <div class="form-group">
                            <input type="text" name="transaction_reference" class="form-control"
                                   placeholder="EX : Code123" required>
                        </div>
                        <!-- End Input Group -->
                        <button class="btn btn-primary">{{__('messages.submit')}}</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
    <!-- End Modal -->

    <!-- Modal -->
    <div id="shipping-address-modal" class="modal fade" tabindex="-1" role="dialog"
         aria-labelledby="exampleModalTopCoverTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <!-- Header -->
                <div class="modal-top-cover bg-dark text-center">
                    <figure class="position-absolute right-0 bottom-0 left-0" style="margin-bottom: -1px;">
                        <svg preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" x="0px" y="0px"
                             viewBox="0 0 1920 100.1">
                            <path fill="#fff" d="M0,0c0,0,934.4,93.4,1920,0v100.1H0L0,0z"/>
                        </svg>
                    </figure>

                    <div class="modal-close">
                        <button type="button" class="btn btn-icon btn-sm btn-ghost-light" data-dismiss="modal"
                                aria-label="Close">
                            <svg width="16" height="16" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                                <path fill="currentColor"
                                      d="M11.5,9.5l5-5c0.2-0.2,0.2-0.6-0.1-0.9l-1-1c-0.3-0.3-0.7-0.3-0.9-0.1l-5,5l-5-5C4.3,2.3,3.9,2.4,3.6,2.6l-1,1 C2.4,3.9,2.3,4.3,2.5,4.5l5,5l-5,5c-0.2,0.2-0.2,0.6,0.1,0.9l1,1c0.3,0.3,0.7,0.3,0.9,0.1l5-5l5,5c0.2,0.2,0.6,0.2,0.9-0.1l1-1 c0.3-0.3,0.3-0.7,0.1-0.9L11.5,9.5z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <!-- End Header -->

                <div class="modal-top-cover-icon">
                    <span class="icon icon-lg icon-light icon-circle icon-centered shadow-soft">
                      <i class="tio-location-search"></i>
                    </span>
                </div>

                @if(isset($address))
                    <form action="{{route('admin.order.update-shipping',[$order['id']])}}"
                          method="post">
                        @csrf
                        <div class="modal-body">
                            <div class="row mb-3">
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{__('messages.type')}}
                                </label>
                                <div class="col-md-10 js-form-message">
                                    <input type="text" class="form-control" name="address_type"
                                           value="{{$address['address_type']}}" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{__('messages.contact')}}
                                </label>
                                <div class="col-md-10 js-form-message">
                                    <input type="text" class="form-control" name="contact_person_number"
                                           value="{{$address['contact_person_number']}}" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{__('messages.name')}}
                                </label>
                                <div class="col-md-10 js-form-message">
                                    <input type="text" class="form-control" name="contact_person_name"
                                           value="{{$address['contact_person_name']}}" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{__('messages.address')}}
                                </label>
                                <div class="col-md-10 js-form-message">
                                    <input type="text" class="form-control" name="address"
                                           value="{{$address['address']}}"
                                           >
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{__('messages.latitude')}}
                                </label>
                                <div class="col-md-4 js-form-message">
                                    <input type="text" class="form-control" name="latitude"
                                           value="{{$address['latitude']}}"
                                           >
                                </div>
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{__('messages.longitude')}}
                                </label>
                                <div class="col-md-4 js-form-message">
                                    <input type="text" class="form-control" name="longitude"
                                           value="{{$address['longitude']}}" >
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-white" data-dismiss="modal">{{__('messages.close')}}</button>
                            <button type="submit" class="btn btn-primary">{{__('messages.save')}} {{__('messages.changes')}}</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
    <!-- End Modal -->

    <!--Dm assign Modal -->
    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="myModalLabel">{{__('messages.assign')}} {{__('messages.deliveryman')}}</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-5 my-2">
                            <ul class="list-group overflow-auto" style="max-height:400px;">
                                @foreach ($deliveryMen as $dm)
                                    <li class="list-group-item"> 
                                        <span class="dm_list" role='button' data-id="{{$dm['id']}}">
                                            <img class="avatar avatar-sm avatar-circle mr-1" onerror="this.src='asset('public/assets/admin/img/160x160/img1.jpg')'" src="{{asset('storage/app/public/delivery-man')}}/{{$dm['image']}}" alt="{{$dm['name']}}">
                                            {{$dm['name']}}
                                        </span>    

                                        <a class="btn btn-primary btn-xs float-right" onclick="addDeliveryMan({{$dm['id']}})">{{__('messages.assign')}}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="col-md-7 modal_body_map">
                            <div class="location-map" id="dmassign-map">
                                <div style="width: 600px; height: 400px;" id="map_canvas"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Modal -->

    <!--Show locations on map Modal -->
    <div class="modal fade" id="locationModal" tabindex="-1" role="dialog" aria-labelledby="locationModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="locationModalLabel">{{__('messages.location')}} {{__('messages.data')}}</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 modal_body_map">
                            <div class="location-map" id="location-map">
                                <div style="width: 100%; height: 400px;" id="location_map_canvas"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Modal -->
@endsection

@push('script_2')
    <script src="https://maps.googleapis.com/maps/api/js?key={{\App\Models\BusinessSetting::where('key', 'map_api_key')->first()->value}}&v=3"></script>
    <script>
        function addDeliveryMan(id) {
            $.ajax({
                type: "GET",
                url: '{{url('/')}}/admin/order/add-delivery-man/{{$order['id']}}/' + id,
                success: function (data) {
                    location.reload();
                    console.log(data)
                    toastr.success('Successfully added', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                },
                error: function (response) {
                    console.log(response);
                    toastr.error(response.responseJSON.message, {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }
            });
        }

        function last_location_view() {
            toastr.warning('Only available when order is out for delivery!', {
                CloseButton: true,
                ProgressBar: true
            });
        }
    </script>
    <script>
        var deliveryMan = <?php echo(json_encode($deliveryMen)); ?>;
        var map = null;
        var myLatlng = new google.maps.LatLng({{$order->restaurant->latitude}}, {{$order->restaurant->longitude}});
        var dmbounds = new google.maps.LatLngBounds (null);
        var locationbounds = new google.maps.LatLngBounds (null);
        var dmMarkers = [];
        dmbounds.extend(myLatlng);
        locationbounds.extend(myLatlng);
        var myOptions = {
            center: myLatlng,
            zoom: 13,
            mapTypeId: google.maps.MapTypeId.ROADMAP,

            panControl: true,
            mapTypeControl: false,
            panControlOptions: {
                position: google.maps.ControlPosition.RIGHT_CENTER
            },
            zoomControl: true,
            zoomControlOptions: {
                style: google.maps.ZoomControlStyle.LARGE,
                position: google.maps.ControlPosition.RIGHT_CENTER
            },
            scaleControl: false,
            streetViewControl: false,
            streetViewControlOptions: {
                position: google.maps.ControlPosition.RIGHT_CENTER
            }
        };
        function initializeGMap() {
            
            map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);

            var infowindow = new google.maps.InfoWindow();

            var Restaurantmarker = new google.maps.Marker({
                position: new google.maps.LatLng({{$order->restaurant->latitude}}, {{$order->restaurant->longitude}}),
                map: map,
                title: "{{$order->restaurant->name}}",
                icon: "{{asset('public/assets/admin/img/restaurant_map.png')}}"
            });

            google.maps.event.addListener(Restaurantmarker, 'click', (function(Restaurantmarker) {
                return function() {
                    infowindow.setContent("<div style='float:left'><img style='max-height:40px;wide:auto;' src='{{asset('storage/app/public/restaurant/'.$order->restaurant->logo)}}'></div><div style='float:right; padding: 10px;'><b>{{$order->restaurant->name}}</b><br/> {{$order->restaurant->address}}</div>");
                    infowindow.open(map, Restaurantmarker);
                }
            })(Restaurantmarker));
            map.fitBounds(dmbounds);
            for (var i = 0; i < deliveryMan.length; i++) {                  
                if(deliveryMan[i].lat)
                {
                    // var contentString = "<div style='float:left'><img style='max-height:40px;wide:auto;' src='{{asset('storage/app/public/delivery-man')}}/"+deliveryMan[i].image+"'></div><div style='float:right; padding: 10px;'><b>"+deliveryMan[i].name+"</b><br/> "+deliveryMan[i].location+"</div>";
                    var point = new google.maps.LatLng(deliveryMan[i].lat, deliveryMan[i].lng);
                    dmbounds.extend(point);
                    map.fitBounds(dmbounds);
                    var marker = new google.maps.Marker({
                        position: point,
                        map: map,
                        title: deliveryMan[i].location,
                        icon: "{{asset('public/assets/admin/img/delivery_boy_map.png')}}"
                    });
                    dmMarkers[deliveryMan[i].id]=marker;
                    google.maps.event.addListener(marker, 'click', (function(marker, i) {
                        return function() {
                            infowindow.setContent("<div style='float:left'><img style='max-height:40px;wide:auto;' src='{{asset('storage/app/public/delivery-man')}}/"+deliveryMan[i].image+"'></div><div style='float:right; padding: 10px;'><b>"+deliveryMan[i].name+"</b><br/> "+deliveryMan[i].location+"</div>");
                            infowindow.open(map, marker);
                        }
                    })(marker, i));
                }

            };
        }
        $(document).ready(function() {

            // Re-init map before show modal
            $('#myModal').on('shown.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                $("#dmassign-map").css("width", "100%");
                $("#map_canvas").css("width", "100%");
            });

            // Trigger map resize event after modal shown
            $('#myModal').on('shown.bs.modal', function() {
                initializeGMap();
                google.maps.event.trigger(map, "resize");
                map.setCenter(myLatlng);
            });

            
            function initializegLocationMap() {
                map = new google.maps.Map(document.getElementById("location_map_canvas"), myOptions);

                var infowindow = new google.maps.InfoWindow();
            
                @if($order->customer && isset($address))
                var marker = new google.maps.Marker({
                    position: new google.maps.LatLng({{$address['latitude']}}, {{$address['longitude']}}),
                    map: map,
                    title: "{{$order->customer->f_name}} {{$order->customer->l_name}}",
                    icon: "{{asset('public/assets/admin/img/customer_location.png')}}"
                });

                google.maps.event.addListener(marker, 'click', (function(marker) {
                    return function() {
                        infowindow.setContent("<div style='float:left'><img style='max-height:40px;wide:auto;' src='{{asset('storage/app/public/profile/'.$order->customer->image)}}'></div><div style='float:right; padding: 10px;'><b>{{$order->customer->f_name}} {{$order->customer->l_name}}</b><br/>{{$address['address']}}</div>");
                        infowindow.open(map, marker);
                    }
                })(marker));
                locationbounds.extend(marker.getPosition());
                @endif
                @if($order->delivery_man && $order->dm_last_location)
                var dmmarker = new google.maps.Marker({
                    position: new google.maps.LatLng({{$order->dm_last_location['latitude']}}, {{$order->dm_last_location['longitude']}}),
                    map: map,
                    title: "{{$order->delivery_man->f_name}}  {{$order->delivery_man->l_name}}",
                    icon: "{{asset('public/assets/admin/img/delivery_boy_map.png')}}"
                });

                google.maps.event.addListener(dmmarker, 'click', (function(dmmarker) {
                    return function() {
                        infowindow.setContent("<div style='float:left'><img style='max-height:40px;wide:auto;' src='{{asset('storage/app/public/delivery-man/'.$order->delivery_man->image)}}'></div><div style='float:right; padding: 10px;'><b>{{$order->delivery_man->f_name}}  {{$order->delivery_man->l_name}}</b><br/> {{$order->dm_last_location['location']}}</div>");
                        infowindow.open(map, dmmarker);
                    }
                })(dmmarker));
                locationbounds.extend(dmmarker.getPosition());
                @endif

                @if ($order->restaurant)
                var Retaurantmarker = new google.maps.Marker({
                    position: new google.maps.LatLng({{$order->restaurant->latitude}}, {{$order->restaurant->longitude}}),
                    map: map,
                    title: "{{$order->restaurant->name}}",
                    icon: "{{asset('public/assets/admin/img/restaurant_map.png')}}"
                });

                google.maps.event.addListener(Retaurantmarker, 'click', (function(Retaurantmarker) {
                    return function() {
                        infowindow.setContent("<div style='float:left'><img style='max-height:40px;wide:auto;' src='{{asset('storage/app/public/restaurant/'.$order->restaurant->logo)}}'></div><div style='float:right; padding: 10px;'><b>{{$order->restaurant->name}}</b><br/> {{$order->restaurant->address}}</div>");
                        infowindow.open(map, Retaurantmarker);
                    }
                })(Retaurantmarker));
                locationbounds.extend(Retaurantmarker.getPosition());
                @endif
                
                google.maps.event.addListenerOnce(map, 'idle', function() {
                    map.fitBounds(locationbounds);
                });
            }

            // Re-init map before show modal
            $('#locationModal').on('shown.bs.modal', function(event) {
                initializegLocationMap();
            });


            $('.dm_list').on('click', function() {
                var id = $(this).data('id');
                map.panTo(dmMarkers[id].getPosition());
                map.setZoom(13);
                dmMarkers[id].setAnimation(google.maps.Animation.BOUNCE);
                window.setTimeout(() => {
                    dmMarkers[id].setAnimation(null);
                }, 3);
            });
        });
    </script>
@endpush
