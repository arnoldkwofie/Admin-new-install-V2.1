<?php

namespace App\Http\Controllers\Api\V1;

ini_set('memory_limit', '-1');

use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\Http\Controllers\Controller;
use App\Models\DeliveryHistory;
use App\Models\DeliveryMan;
use App\Models\Admin;
use App\Models\Order;
use App\Models\RestaurantWallet;
use App\Models\AdminWallet;
use App\Models\DeliveryManWallet;
use App\Models\Notification;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

Carbon::setWeekStartsAt(Carbon::SUNDAY);
Carbon::setWeekEndsAt(Carbon::SATURDAY);


class DeliverymanController extends Controller
{

    public function get_profile(Request $request)
    {
        $dm = DeliveryMan::with(['rating'])->where(['auth_token' => $request['token']])->first();
        $dm['avg_rating'] = (double)(!empty($dm->rating[0])?$dm->rating[0]->average:0);
        $dm['rating_count'] = (double)(!empty($dm->rating[0])?$dm->rating[0]->rating_count:0);
        $dm['order_count'] =(integer)$dm->orders->count();
        $dm['todays_order_count'] =(integer)$dm->todaysorders->count();
        $dm['this_week_order_count'] =(integer)$dm->this_week_orders->count();
        $dm['member_since_days'] =(integer)$dm->created_at->diffInDays();
        $dm['cash_in_hands'] =$dm->wallet?$dm->wallet->collected_cash:0;
        
        $dm['actual_earning'] =$dm->wallet?$dm->wallet->earning:0;
        $dm['risk_allowance'] =$dm->wallet?$dm->wallet->risk_allowance:0;
        $dm['total_earning'] =$dm->wallet?$dm->wallet->total_earning:0;
        $dm['total_withdrawn'] =$dm->wallet?$dm->wallet->total_withdrawn:0;

        $dm['balance'] =$dm->wallet?$dm->wallet->total_earning - $dm->wallet->total_withdrawn:0;
        $dm['todays_earning'] =(float)$dm->todays_earning()->sum('cal_delivery');
        $dm['this_week_earning'] =(float)$dm->this_week_earning()->sum('cal_delivery');
        $dm['this_month_earning'] =(float)$dm->this_month_earning()->sum('cal_delivery');
        unset($dm['orders']);
        unset($dm['rating']);
        unset($dm['todaysorders']);
        unset($dm['this_week_orders']);
        unset($dm['wallet']);
        return response()->json($dm, 200);
    }
    
    public function update_profile(Request $request)
    {
        $dm = DeliveryMan::with(['rating'])->where(['auth_token' => $request['token']])->first();
        $validator = Validator::make($request->all(), [
            'f_name' => 'required',
            'l_name' => 'required',
            'email' => 'required|unique:delivery_men,email,'.$dm->id,
            'password'=>'nullable|min:6',
        ], [
            'f_name.required' => 'First name is required!',
            'l_name.required' => 'Last name is required!',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $image = $request->file('image');

        if ($request->has('image')) {
            $imageName = Helpers::update('delivery-man/', $dm->image, 'png', $request->file('image'));
        } else {
            $imageName = $dm->image;
        }

        if ($request['password'] != null && strlen($request['password']) > 5) {
            $pass = bcrypt($request['password']);
        } else {
            $pass = $dm->password;
        }
        $dm->f_name = $request->f_name;
        $dm->l_name = $request->l_name;
        $dm->email = $request->email;
        $dm->image = $imageName;
        $dm->password = $pass;
        $dm->updated_at = now();
        $dm->save();

        return response()->json(['message' => 'successfully updated!'], 200);
    }

    public function activeStatus(Request $request)
    {
        $dm = DeliveryMan::with(['rating'])->where(['auth_token' => $request['token']])->first();
        $dm->active = $dm->active?0:1;
        $dm->save();
        return response()->json(['message' => 'Active status updated successfully'], 200);
    }

    public function get_current_orders(Request $request)
    {
        $dm = DeliveryMan::where(['auth_token' => $request['token']])->first();
        $orders = Order::with(['customer', 'restaurant'])
        ->whereIn('order_status', ['accepted','confirmed','pending', 'processing', 'picked_up', 'handover'])
        ->where(['delivery_man_id' => $dm['id']])
        ->orderBy('schedule_at', 'desc')
        ->get();
        $orders= Helpers::order_data_formatting($orders, true);
        return response()->json($orders, 200);
    }
    public function get_latest_orders(Request $request)
    {
        $dm = DeliveryMan::where(['auth_token' => $request['token']])->first();

        $orders = Order::whereHas('restaurant', function($q)use($dm){
            $q->where('zone_id', $dm->zone_id);
        })
        ->with(['customer', 'restaurant'])
        ->whereIn('order_status', ['pending', 'confirmed'])
        ->delivery()
        ->OrderScheduledIn(30)
        ->whereNull('delivery_man_id')
        ->orderBy('schedule_at', 'desc')
        ->get();
        $orders= Helpers::order_data_formatting($orders, true);
        return response()->json($orders, 200);
    }

    public function accept_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $dm=DeliveryMan::where(['auth_token' => $request['token']])->first();
        $order = Order::where('id', $request['order_id'])->whereIn('order_status', ['pending', 'confirmed'])->first();
        if(!$order)
        {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => 'Can not accept!']
                ]
            ], 404);
        }
        $order->order_status = 'accepted';
        $order->delivery_man_id = $dm->id;
        $order->accepted = now();
        $order->save();

        $dm->available = 0;
        $dm->save();

        $fcm_token=$order->customer->cm_firebase_token;

        $value = Helpers::order_status_update_message('accepted');
        try {
            if($value)
            {
                $data = [
                    'title' => 'Order',
                    'description' => $value,
                    'order_id' => $order['id'],
                    'image' => '',
                    'type'=> 'order_status'
                ];
                Helpers::send_push_notif_to_device($fcm_token, $data);
            }

        } catch (\Exception $e) {

        }

        return response()->json(['message' => 'Order accepted successfully'], 200);

    }
    public function record_location_data(Request $request)
    {
        $dm = DeliveryMan::where(['auth_token' => $request['token']])->first();
        $order = Order::whereIn('order_status', ['accepted','confirmed','pending', 'processing', 'picked_up'])
        ->when($request['order_id'], function($q)use($request){
            return $q->where('id', $request['order_id']);
        })
        ->where('delivery_man_id', $dm['id'])
        ->first();
        DB::table('delivery_histories')->insert([
            'order_id' => $order?$order->id:null,
            'delivery_man_id' => $dm['id'],
            'longitude' => $request['longitude'],
            'latitude' => $request['latitude'],
            'time' => now(),
            'location' => $request['location'],
            'created_at' => now(),
            'updated_at' => now()
        ]);
        return response()->json(['message' => 'location recorded'], 200);
    }

    public function get_order_history(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $dm = DeliveryMan::where(['auth_token' => $request['token']])->first();

        $history = DeliveryHistory::where(['order_id' => $request['order_id'], 'delivery_man_id' => $dm['id']])->get();
        return response()->json($history, 200);
    }

    public function update_order_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'status' => 'required|in:confirmed,canceled,picked_up,delivered',
        ]);

        $validator->sometimes('otp', 'required', function ($request) {
            return (Config::get('order_delivery_verification')==1 && $request['status']=='delivered');
        });

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $dm = DeliveryMan::where(['auth_token' => $request['token']])->first();
        
        $order = Order::where(['id' => $request['order_id'], 'delivery_man_id' => $dm['id']])->first();
        
        if($order->order_status !="pending" && $request['status'] == 'canceled')
        {
            return response()->json([
                'errors' => [
                    ['code' => 'delivery-man', 'message' => 'You can not cancle a order after confirmed!']
                ]
            ], 401);
        }

        if(Config::get('order_delivery_verification')==1 && $request['status']=='delivered' && $order->otp != $request['otp'])
        {
            return response()->json([
                'errors' => [
                    ['code' => 'otp', 'message' => 'Not matched']
                ]
            ], 406);
        }
        if ($request->status == 'delivered') 
        {
            if($order->transaction == null && $order->payment_method=='cash_on_delivery'&& $order->payment_status=='unpaid')
            {
                if(OrderLogic::create_transaction($order,'deliveryman', null))
                {
                    $order->payment_status = 'paid';
                }
                else
                {
                    return response()->json([
                        'errors' => [
                            ['code' => 'error', 'message' => trans('messages.faield_to_create_order_transaction')]
                        ]
                    ], 406);
                }
            }
            if($order->transaction)
            {
                $order->transaction->update(['delivery_man_id'=>$dm->id]);
            }

            $order->details->each(function($item, $key){
                if($item->food)
                {
                    $item->food->increment('order_count');
                }
            });
            $order->customer->increment('order_count');
            if($dm->earning == 1)
            {
                $dmWallet = DeliveryManWallet::firstOrNew(
                    ['delivery_man_id' => $dm->id]
                );
              
                $dmWallet->earning=$dmWallet->earning + $order->delivery_net;
                $dmWallet->risk_allowance=$dmWallet->risk_allowance + $order->risk_allowance;
                $dmWallet->total_earning = $dmWallet->total_earning + $order->cal_delivery;
                $dmWallet->save();
            }
            
            $dm->available = 1;
            $dm->save();

        }
        

        $order->order_status = $request['status'];
        $order[$request['status']] = now();
        $order->save();

        $fcm_token=$order->customer->cm_firebase_token;
        $status = $request['status']=='delivered'?'delivery_boy_delivered':$request['status'];
        $value = Helpers::order_status_update_message($status);

        try {
            if ($value){
                $data=[
                    'title'=>'Order',
                    'description'=>$value,
                    'order_id'=>$order['id'],
                    'image'=>'',
                    'type'=> 'order_status'
                ];
                Helpers::send_push_notif_to_device($fcm_token,$data);
                DB::table('user_notifications')->insert([
                    'data'=> json_encode($data),
                    'user_id'=>$order->customer->id,
                    'created_at'=>now(),
                    'updated_at'=>now()
                ]);
            }

            if($request['status']=='confirmed')
            {
                $data=[
                    'title'=>'Order',
                    'description'=>trans('messages.new_order'),
                    'order_id'=>$order['id'],
                    'image'=>'',
                    'type'=> 'order_status'
                ];
                $vendor_fcm_token = $order->restaurant->vendor->cm_firebase_token;
                Helpers::send_push_notif_to_device($vendor_fcm_token,$data);
                DB::table('user_notifications')->insert([
                    'data'=> json_encode($data),
                    'vendor_id'=>$order->restaurant->vendor->id,
                    'created_at'=>now(),
                    'updated_at'=>now()
                ]);
            }
        } catch (\Exception $e) {

        }

        return response()->json(['message' => 'Status updated'], 200);
    }

    public function get_order_details(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $dm = DeliveryMan::where(['auth_token' => $request['token']])->first();

        $order = Order::with(['details'])->where(['delivery_man_id' => $dm['id'], 'id' => $request['order_id']])->first();
        if(!$order)
        {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => trans('messages.not_found')]
                ]
            ], 401);
        }
        $details = Helpers::order_details_data_formatting($order->details);
        return response()->json($details, 200);
    }

    public function get_all_orders(Request $request)
    {
        $dm = DeliveryMan::where(['auth_token' => $request['token']])->first();

        $orders = Order::with(['customer', 'restaurant'])
        ->where(['delivery_man_id' => $dm['id']])
        ->orderBy('schedule_at', 'desc')
        ->get();
        $orders= Helpers::order_data_formatting($orders, true);
        return response()->json($orders, 200);
    }

    public function get_last_location(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $last_data = DeliveryHistory::where(['order_id' => $request['order_id']])->latest()->first();
        return response()->json($last_data, 200);
    }

    public function order_payment_status_update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'status' => 'required|in:paid'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $dm = DeliveryMan::where(['auth_token' => $request['token']])->first();

        if (Order::where(['delivery_man_id' => $dm['id'], 'id' => $request['order_id']])->first()) {
            Order::where(['delivery_man_id' => $dm['id'], 'id' => $request['order_id']])->update([
                'payment_status' => $request['status']
            ]);
            return response()->json(['message' => 'Payment status updated'], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => 'not found!']
            ]
        ], 404);
    }

    public function update_fcm_token(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $dm = DeliveryMan::where(['auth_token' => $request['token']])->first();

        DeliveryMan::where(['id' => $dm['id']])->update([
            'fcm_token' => $request['fcm_token']
        ]);

        return response()->json(['message'=>'successfully updated!'], 200);
    }

    public function get_notifications(Request $request){

        $dm = DeliveryMan::where(['auth_token' => $request['token']])->first();

        $notifications = Notification::active()->where(function($q) use($dm){
                $q->whereNull('zone_id')->orWhere('zone_id', $dm->zone_id);
            })->where('tergat', 'deliveryman')->where('created_at', '>=', \Carbon\Carbon::today()->subDays(7))->get();

        $user_notifications = UserNotification::where('delivery_man_id', $dm->id)->where('created_at', '>=', \Carbon\Carbon::today()->subDays(7))->get();
        
        $notifications->append('data');

        $notifications =  $notifications->merge($user_notifications);
        try {
            return response()->json($notifications, 200);
        } catch (\Exception $e) {
            return response()->json([], 200);
        }
    }

}
