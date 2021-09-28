<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\CentralLogics\CouponLogic;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Coupon;
use App\Models\OrderDetail;
use App\Models\Food;
use App\Models\Restaurant;
use App\Models\ItemCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Zone;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function track_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $order = Order::with(['restaurant', 'delivery_man.rating'])->withCount('details')->where(['id' => $request['order_id'], 'user_id' => $request->user()->id])->first();
        if($order)
        {
            $order['restaurant'] = $order['restaurant']?Helpers::restaurant_data_formatting($order['restaurant']):$order['restaurant'];   
            $order['delivery_address'] = $order['delivery_address']?json_decode($order['delivery_address']):$order['delivery_address'];   
            $order['delivery_man'] = $order['delivery_man']?Helpers::deliverymen_data_formatting([$order['delivery_man']]):$order['delivery_man'];
            unset($order['details']);
        }
        else
        {
            return response()->json([
                'errors' => [
                    ['code' => 'schedule_at', 'message' => trans('messages.not_found')]
                ]
            ], 404);
        }
        return response()->json($order, 200);
    }

    public function place_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_amount' => 'required',
            'payment_method'=>'required|in:cash_on_delivery,digital_payment',
            'order_type' => 'required|in:take_away,delivery',
            'restaurant_id' => 'required',
            'distance' => 'required_if:order_type,delivery',
            'address' => 'required_if:order_type,delivery',
            'longitude' => 'required_if:order_type,delivery',
            'latitude' => 'required_if:order_type,delivery',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }


        $coupon = null;
        $delivery_charge = null;
        $schedule_at = $request->schedule_at?\Carbon\Carbon::parse($request->schedule_at):now();
        if($request->schedule_at && $schedule_at < now())
        {
            return response()->json([
                'errors' => [
                    ['code' => 'order_time', 'message' => trans('messages.you_can_not_schedule_a_order_in_past')]
                ]
            ], 406);
        }
        $restaurant = Restaurant::with('discount')->whereTime('opening_time','<=',$schedule_at->format('H:i'))->whereTime('closeing_time','>=',$schedule_at->format('H:i'))->where('id', $request->restaurant_id)->first();
        if(!$restaurant)
        {
            return response()->json([
                'errors' => [
                    ['code' => 'order_time', 'message' => trans('messages.restaurant_is_closed_at_order_time')]
                ]
            ], 406);
        }
        if($request->schedule_at && !$restaurant->schedule_order)
        {
            return response()->json([
                'errors' => [
                    ['code' => 'schedule_at', 'message' => trans('messages.schedule_order_not_available')]
                ]
            ], 406);
        }

        if ($request['coupon_code']) {
            $coupon = Coupon::active()->where(['code' => $request['coupon_code']])->first();
            if (isset($coupon)) {
                $staus = CouponLogic::is_valide($coupon, $request->user()->id ,$request['restaurant_id']);
                if($staus==407)
                {
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => trans('messages.coupon_expire')]
                        ]
                    ], 407);
                }
                else if($staus==406)
                {
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => trans('messages.coupon_usage_limit_over')]
                        ]
                    ], 406);
                }
                else if($staus==404)
                {
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => trans('messages.not_found')]                            
                        ]
                    ], 404);
                }
                if($coupon->coupon_type == 'free_delivery')
                {
                    $delivery_charge = 0;
                    $coupon = null;
                }
            } else {
                return response()->json([
                    'errors' => [
                        ['code' => 'coupon', 'message' => 'not found!']
                    ]
                ], 401);
            }
        }
        $per_km_shipping_charge = (float)BusinessSetting::where(['key' => 'per_km_shipping_charge'])->first()->value;
        $minimum_shipping_charge = (float)BusinessSetting::where(['key' => 'minimum_shipping_charge'])->first()->value;
        $original_delivery_charge = ($request->distance * $per_km_shipping_charge > $minimum_shipping_charge) ? $request->distance * $per_km_shipping_charge : $minimum_shipping_charge;
        if($request['order_type'] != 'take_away' && !$restaurant->free_delivery && $delivery_charge == null)
        {
            $delivery_charge = ($request->distance * $per_km_shipping_charge > $minimum_shipping_charge) ? $request->distance * $per_km_shipping_charge : $minimum_shipping_charge;
        }

        if($request->latitude && $request->longitude)
        {
            $point = new Point($request->latitude,$request->longitude);
            $zone = Zone::where('id', $restaurant->zone_id)->contains('coordinates', $point)->first();
            if(!$zone)
            {
                $errors = [];
                array_push($errors, ['code' => 'coordinates', 'message' => trans('messages.out_of_coverage')]);
                return response()->json([
                    'errors' => $errors
                ], 403);
            }
        }

        $address = [
            'contact_person_name' => $request->contact_person_name?$request->contact_person_name:$request->user()->f_name.' '.$request->user()->f_name,
            'contact_person_number' => $request->contact_person_number?$request->contact_person_number:$request->user()->phone,
            'address_type' => $request->address_type?$request->address_type:'Delivery',
            'address' => $request->address,
            'longitude' => (string)$request->longitude,
            'latitude' => (string)$request->latitude,
        ];

        $total_addon_price = 0;
        $product_price = 0;
        $restaurant_discount_amount = 0;

        $order_details = [];
        $order = new Order();
        $order->id = 100000 + Order::all()->count() + 1;
        $order->user_id = $request->user()->id;
        $order->order_amount = $request['order_amount'];
        $order->payment_status = 'unpaid';
        //$order->order_status = $request['payment_method']=='digital_payment'?'failed':'pending';
        $order->order_status = 'pending';
        $order->coupon_code = $request['coupon_code'];
        $order->payment_method = $request->payment_method;
        $order->transaction_reference = null;
        $order->order_note = $request['order_note'];
        $order->order_type = $request['order_type'];
        $order->restaurant_id = $request['restaurant_id'];
        $order->delivery_charge = $delivery_charge??0;
        $order->original_delivery_charge = $original_delivery_charge;
        $order->delivery_address = json_encode($address);
        $order->schedule_at = $schedule_at;
        $order->scheduled = $request->schedule_at?1:0;
        $order->otp = rand(1000, 9999);
        $order->pending = now();
        $order->created_at = now();
        $order->updated_at = now();
        foreach ($request['cart'] as $c) {
            if ($c['item_campaign_id'] != null) {
                $product = ItemCampaign::find($c['item_campaign_id']);
                if ($product) {
                    if (count(json_decode($product['variations'], true)) > 0) {
                        $price = Helpers::variation_price($product, json_encode($c['variation']));
                    } else {
                        $price = $product['price'];
                    }
                    $product->tax = $restaurant->tax;
                    $product = Helpers::product_data_formatting($product);
                    $addon_data = Helpers::calculate_addon_price(\App\Models\AddOn::whereIn('id',$c['add_on_ids'])->get(), $c['add_on_qtys']);
                    $or_d = [
                        'food_id' => null,
                        'item_campaign_id' => $c['item_campaign_id'],
                        'food_details' => json_encode($product),
                        'quantity' => $c['quantity'],
                        'price' => $price,
                        'tax_amount' => Helpers::tax_calculate($product, $price),
                        'discount_on_food' => Helpers::product_discount_calculate($product, $price, $restaurant),
                        'discount_type' => 'discount_on_product',
                        'variant' => json_encode($c['variant']),
                        'variation' => json_encode($c['variation']),
                        'add_ons' => json_encode($addon_data['addons']),
                        'total_add_on_price' => $addon_data['total_add_on_price'],
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    $order_details[] = $or_d;
                    $total_addon_price += $or_d['total_add_on_price'];
                    $product_price += $price*$or_d['quantity'];
                    $restaurant_discount_amount += $or_d['discount_on_food']*$or_d['quantity'];
                } else {
                    return response()->json([
                        'errors' => [
                            ['code' => 'campaign', 'message' => 'not found!']
                        ]
                    ], 401);
                }
            } else {
                $product = Food::find($c['food_id']);
                if ($product) {
                    if (count(json_decode($product['variations'], true)) > 0) {
                        $price = Helpers::variation_price($product, json_encode($c['variation']));
                    } else {
                        $price = $product['price'];
                    }
                    $product->tax = $restaurant->tax;
                    $product = Helpers::product_data_formatting($product);
                    $addon_data = Helpers::calculate_addon_price(\App\Models\AddOn::whereIn('id',$c['add_on_ids'])->get(), $c['add_on_qtys']);
                    $or_d = [
                        'food_id' => $c['food_id'],
                        'item_campaign_id' => null,
                        'food_details' => json_encode($product),
                        'quantity' => $c['quantity'],
                        'price' => $price,
                        'tax_amount' => Helpers::tax_calculate($product, $price),
                        'discount_on_food' => Helpers::product_discount_calculate($product, $price, $restaurant),
                        'discount_type' => 'discount_on_product',
                        'variant' => json_encode($c['variant']),
                        'variation' => json_encode($c['variation']),
                        'add_ons' => json_encode($addon_data['addons']),
                        'total_add_on_price' => $addon_data['total_add_on_price'],
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    $total_addon_price += $or_d['total_add_on_price'];
                    $product_price += $price*$or_d['quantity'];
                    $restaurant_discount_amount += $or_d['discount_on_food']*$or_d['quantity'];
                    $order_details[] = $or_d;
                } else {
                    return response()->json([
                        'errors' => [
                            ['code' => 'food', 'message' => 'not found!']
                        ]
                    ], 401);
                }
            }

        }
        $restaurant_discount = Helpers::get_restaurant_discount($restaurant);
        if(isset($restaurant_discount))
        {
            if($product_price + $total_addon_price < $restaurant_discount['min_purchase'])
            {
                $restaurant_discount_amount = 0;
            }

            if($restaurant_discount_amount > $restaurant_discount['max_discount'])
            {
                $restaurant_discount_amount = $restaurant_discount['max_discount'];
            }
        }
        $coupon_discount_amount = $coupon ? CouponLogic::get_discount($coupon, $product_price + $total_addon_price - $restaurant_discount_amount) : 0; 
        $total_price = $product_price + $total_addon_price - $restaurant_discount_amount - $coupon_discount_amount;
        

        $tax = $restaurant->tax;
        $total_tax_amount= ($tax > 0)?(($total_price * $tax)/100):0;

        //service charge altered by Arnold
        $low_value =\App\Models\BusinessSetting::where(['key' => 'low_value'])->first()->value;
        $high_value =\App\Models\BusinessSetting::where(['key' => 'high_value'])->first()->value;
        $low_fraction_numerator =\App\Models\BusinessSetting::where(['key' => 'low_fraction_numerator'])->first()->value;
        $low_fraction_denominator =\App\Models\BusinessSetting::where(['key' => 'low_fraction_denominator'])->first()->value;
        $intermediate_fraction_numerator =\App\Models\BusinessSetting::where(['key' => 'intermediate_fraction_numerator'])->first()->value;
        $intermediate_fraction_denominator =\App\Models\BusinessSetting::where(['key' => 'intermediate_fraction_denominator'])->first()->value;
        $high_fraction_numerator =\App\Models\BusinessSetting::where(['key' => 'high_fraction_numerator'])->first()->value;
        $high_fraction_denominator =\App\Models\BusinessSetting::where(['key' => 'high_fraction_denominator'])->first()->value;
        $risk_allowance =\App\Models\BusinessSetting::where(['key' => 'risk_allowance'])->first()->value;
        $delivery_deduction =\App\Models\BusinessSetting::where(['key' => 'delivery_deduction'])->first()->value;

        $service_charge=0;
        if($total_price <= $low_value){
            $service_charge= (($low_fraction_numerator/$low_fraction_denominator) * $total_price);
        }elseif($total_price > $low_value and  $total_price<=$high_value ){
            $service_charge= (($intermediate_fraction_numerator/$intermediate_fraction_denominator) * $total_price);
        }else{
            $service_charge= (($high_fraction_numerator/$high_fraction_denominator) * $total_price);
        }
        $total_price= $total_price + $service_charge;
        //$order->service_charge = $service_charge;
        
        //end

        if($restaurant->minimum_order > $product_price + $total_addon_price )
        {
            return response()->json([
                'errors' => [
                    ['code' => 'order_time', 'message' => trans('messages.you_need_to_order_at_least', ['amount'=>$restaurant->minimum_order.' '.Helpers::currency_code()])]
                ]
            ], 406);
        }

        $free_delivery_over = BusinessSetting::where('key', 'free_delivery_over')->first()->value;
        if(isset($free_delivery_over))
        {
            if($free_delivery_over <= $product_price + $total_addon_price)
            {
                $order->delivery_charge = 0;
            }
        }
        try {
            $order->coupon_discount_amount = $coupon_discount_amount;
            $order->coupon_discount_title = $coupon ? $coupon->title : ''; 

            $order->restaurant_discount_amount= $restaurant_discount_amount;
            $order->total_tax_amount= $total_tax_amount;
            $order->order_amount = $total_price + $total_tax_amount + $order->delivery_charge;
            $risk_allowance=round((($risk_allowance/100)* $order->delivery_charge),2);
            $delivery_accumulation=round((($delivery_deduction/100)* $order->delivery_charge),2);
            $order->cal_delivery= round($order->delivery_charge +$risk_allowance -$delivery_accumulation,2);
            $order->delivery_net= round($order->delivery_charge - $delivery_accumulation,2);
            $order->risk_allowance= $risk_allowance;
            $order->delivery_accumulation= $delivery_accumulation;
            $order->save();
            foreach ($order_details as $key => $item) {
                $order_details[$key]['order_id'] = $order->id;
            }
            OrderDetail::insert($order_details);
            $fcm_token = $request->user()->cm_firebase_token;
            $value = Helpers::order_status_update_message('pending');
            try {
                if ($value && $order->order_status == 'pending') {
                    $data = [
                        'title' => 'Order',
                        'description' => $value,
                        'order_id' => $order->id,
                        'image' => '',
                        'type'=>'order_status',
                    ];
                    Helpers::send_push_notif_to_device($fcm_token, $data);
                    DB::table('user_notifications')->insert([
                        'data'=> json_encode($data),
                        'user_id'=>$request->user()->id,
                        'created_at'=>now(),
                        'updated_at'=>now()
                    ]);
                }
                if($order->order_type == 'delivery' && !$order->scheduled && $order->order_status == 'pending')
                {
                    $data = [
                        'title' => 'Order',
                        'description' => 'New order placed',
                        'order_id' => $order->id,
                        'image' => '',
                    ];
                    Helpers::send_push_notif_to_topic($data, $restaurant->zone->deliveryman_wise_topic, 'order_request');
                }

            } catch (\Exception $e) {
                info($e);
            }
            return response()->json([
                'message' => trans('messages.order_placed_successfully'),
                'order_id' => $order->id,
                'total_ammount' => $total_price+$order->delivery_charge+$total_tax_amount
            ], 200);
        } catch (\Exception $e) {
            return response()->json([$e], 403);
        }

        return response()->json([
            'errors' => [
                ['code' => 'order_time', 'message' => trans('messages.failed_to_place_order')]
            ]
        ], 403);
    }

    public function get_order_list(Request $request)
    {
        $orders = Order::with(['restaurant', 'delivery_man.rating'])->withCount('details')->where(['user_id' => $request->user()->id])->get()->map(function ($data) {
            $data['delivery_address'] = $data['delivery_address']?json_decode($data['delivery_address']):$data['delivery_address'];   
            $data['restaurant'] = $data['restaurant']?Helpers::restaurant_data_formatting($data['restaurant']):$data['restaurant'];
            $data['delivery_man'] = $data['delivery_man']?Helpers::deliverymen_data_formatting([$data['delivery_man']]):$data['delivery_man'];
            return $data;
        });
        return response()->json($orders, 200);
    }


    public function get_running_orders(Request $request)
    {
        $orders = Order::with(['restaurant', 'delivery_man.rating'])->withCount('details')->where(['user_id' => $request->user()->id])->whereNotIn('order_status', ['delivered','canceled','refund_requested','refunded','failed'])->get()->map(function ($data) {
            $data['delivery_address'] = $data['delivery_address']?json_decode($data['delivery_address']):$data['delivery_address'];   
            $data['restaurant'] = $data['restaurant']?Helpers::restaurant_data_formatting($data['restaurant']):$data['restaurant'];
            $data['delivery_man'] = $data['delivery_man']?Helpers::deliverymen_data_formatting([$data['delivery_man']]):$data['delivery_man'];
            return $data;
        });
        return response()->json($orders, 200);
    }

    public function get_order_details(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $details = OrderDetail::whereHas('order', function($query)use($request){
            return $query->where('user_id', $request->user()->id);
        })->where(['order_id' => $request['order_id']])->get();
        if ($details->count() > 0) {
            $details = $details = Helpers::order_details_data_formatting($details);
            return response()->json($details, 200);
        } else {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => 'not found!']
                ]
            ], 401);
        }
    }

    public function cancel_order(Request $request)
    {
        $order = Order::where(['user_id' => $request->user()->id, 'id' => $request['order_id']])->first();
        if(!$order){
                return response()->json([
                    'errors' => [
                        ['code' => 'order', 'message' => 'not found!']
                    ]
                ], 401);
        }
        else if ($order->order_status == 'pending') {

            $order->order_status = 'canceled';
            $order->canceled = now();
            $order->save();            
            return response()->json(['message' => 'Order canceled'], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => 'You can not cancle after confirmed!']
            ]
        ], 401);
    }

    public function refund_request(Request $request)
    {
        $order = Order::where(['user_id' => $request->user()->id, 'id' => $request['order_id']])->first();
        if(!$order){
                return response()->json([
                    'errors' => [
                        ['code' => 'order', 'message' => 'not found!']
                    ]
                ], 401);
        }
        else if ($order->order_status == 'delivered') {

            $order->order_status = 'refund_requested';
            $order->refund_requested = now();
            $order->save();            
            return response()->json(['message' => 'Refund request placed succfully!'], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => 'You can not request for refund before delivery!']
            ]
        ], 401);
    }

    public function update_payment_method(Request $request)
    {
        $order = Order::where(['user_id' => $request->user()->id, 'id' => $request['order_id']])->first();
        if ($order) {
            Order::where(['user_id' => $request->user()->id, 'id' => $request['order_id']])->update([
                'payment_method' => 'cash_on_delivery', 'order_status'=>'pending', 'pending'=> now()
            ]);

            $fcm_token = $request->user()->cm_firebase_token;
            $value = Helpers::order_status_update_message('pending');
            try {
                if ($value) {
                    $data = [
                        'title' => 'Order',
                        'description' => $value,
                        'order_id' => $order->id,
                        'image' => '',
                        'type'=>'order_status',
                    ];
                    Helpers::send_push_notif_to_device($fcm_token, $data);
                    DB::table('user_notifications')->insert([
                        'data'=> json_encode($data),
                        'user_id'=>$request->user()->id,
                        'created_at'=>now(),
                        'updated_at'=>now()
                    ]);
                }
                if($order->order_type == 'delivery' && !$order->scheduled)
                {
                    $data = [
                        'title' => 'Order',
                        'description' => 'New order placed',
                        'order_id' => $order->id,
                        'image' => '',
                    ];
                    Helpers::send_push_notif_to_topic($data, $order->restaurant->zone->deliveryman_wise_topic, 'order_request');
                }

            } catch (\Exception $e) {
                info($e);
            }
            return response()->json(['message' => trans('messages.payment').' '.trans('messages.method').' '.trans('messages.updated_successfully')], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => trans('messages.not_found')]
            ]
        ], 401);
    }
}
