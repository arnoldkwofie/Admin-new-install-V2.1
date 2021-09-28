<?php

namespace App\Http\Controllers\Vendor;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\Models\Order;
use App\Models\Admin;
use App\Models\RestaurantWallet;
use App\Models\AdminWallet;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function list($status)
    {
        Order::where(['checked' => 0])->where('restaurant_id',\App\CentralLogics\Helpers::get_restaurant_id())->update(['checked' => 1]);
        
        $orders = Order::with(['customer'])
        ->when($status == 'searching_for_deliverymen', function($query){
            return $query->SearchingForDeliveryman();
        })
        ->when($status == 'confirmed' or $status == 'accepted', function($query){
            return $query->whereIn('order_status',['confirmed', 'accepted'])->whereNotNull('confirmed');
        })
        //arnold
        ->when($status == 'accepted', function($query){
            return $query->whereIn('order_status',['confirmed', 'accepted'])->whereNotNull('confirmed');
        })
        //end
        ->when($status == 'pending_take_away', function($query){
            return $query->where('order_status','pending')->where('order_type', 'take_away');
        })
        ->when($status == 'cooking', function($query){
            return $query->where('order_status','processing');
        })
        ->when($status == 'food_on_the_way', function($query){
            return $query->where('order_status','picked_up');
        })
        ->when($status == 'delivered', function($query){
            return $query->Delivered();
        })
        ->when($status == 'ready_for_delivery', function($query){
            return $query->where('order_status','handover');
        })
        ->when($status == 'refund_requested', function($query){
            return $query->RefundRequest();
        })
        ->when($status == 'returned', function($query){
            return $query->where('order_status','returned');
        })
        ->when($status == 'scheduled', function($query){
            return $query->Scheduled()->where(function($q){
                $q->whereNotIn('order_status',['pending','failed','canceled', 'refund_requested', 'refunded'])->orWhere(function($query){
                        $query->where('order_status','pending')->where('order_type', 'take_away');
                    });
                });
        })
        ->when($status == 'all', function($query){
            return $query->where(function($q){
                    $q->whereNotIn('order_status',['pending','failed','canceled', 'refund_requested', 'refunded'])->orWhere(function($query){
                    $query->where('order_status','pending')->where('order_type', 'take_away');
                });
            });
        })
        ->when(($status != 'scheduled' && $status != 'all'), function($query){
            return $query->OrderScheduledIn(30);
        })
        ->where('restaurant_id',\App\CentralLogics\Helpers::get_restaurant_id())
        ->orderBy('schedule_at', 'desc')
        ->paginate(25);

        $status = trans('messages.'.$status);
        return view('vendor-views.order.list', compact('orders', 'status'));
    }

    public function search(Request $request){
        $key = explode(' ', $request['search']);
        $orders=Order::where(['restaurant_id'=>Helpers::get_restaurant_id()])->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('id', 'like', "%{$value}%")
                    ->orWhere('order_status', 'like', "%{$value}%")
                    ->orWhere('transaction_reference', 'like', "%{$value}%");
            }
        })->get();
        return response()->json([
            'view'=>view('vendor-views.order.partials._table',compact('orders'))->render()
        ]);
    }

    public function details($id)
    {
        $order = Order::with('details')->where(['id' => $id, 'restaurant_id' => Helpers::get_restaurant_id()])->first();
        if (isset($order)) {
            return view('vendor-views.order.order-view', compact('order'));
        } else {
            Toastr::info('No more orders!');
            return back();
        }
    }

    public function status(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'order_status' => 'required|in:confirmed,processing,handover,delivered'
        ],[
            'id.required' => 'Order id is required!'
        ]);

        $order = Order::where(['id' => $request->id, 'restaurant_id' => Helpers::get_restaurant_id()])->first();

        if($order->delivered != null)
        {
            Toastr::warning(trans('messages.cannot_change_status_after_delivered'));
            return back();
        }

        if($request['order_status']=='confirmed' && $order->order_type != 'take_away')
        {
            Toastr::warning(trans('messages.already_confirmed'));
            return back();
        }

        if($request['order_status']=='delivered' && $order->order_type != 'take_away')
        {
            Toastr::warning(trans('messages.you_can_not_delivered_delivery_order'));
            return back();
        }

        if ($request->order_status == 'delivered') {
            $order_delivery_verification = (boolean)\App\Models\BusinessSetting::where(['key' => 'order_delivery_verification'])->first()->value;
            if($order_delivery_verification)
            {
                if($request->otp)
                {
                    if($request->otp != $order->otp)
                    {
                        Toastr::warning(trans('messages.order_varification_code_not_matched'));
                        return back();
                    }
                }
                else
                {
                    Toastr::warning(trans('messages.order_varification_code_is_required'));
                    return back();
                }
            }

            if($order->transaction  == null)
            {
                $ol = OrderLogic::create_transaction($order,'restaurant', null);

                if(!$ol)
                {
                    Toastr::warning(trans('messages.faield_to_create_order_transaction'));
                    return back();
                }
            }

            $order->payment_status = 'paid';

            $order->details->each(function($item, $key){
                if($item->food)
                {
                    $item->food->increment('order_count');
                }
            });
            $order->customer->increment('order_count');
        } 

        $order->order_status = $request->order_status;
        $order[$request['order_status']] = now();
        $order->save();

        $fcm_token = $order->customer->cm_firebase_token;
        $value = Helpers::order_status_update_message($request->order_status);
        try {
            if ($value) {
                $data = [
                    'title' => 'Order',
                    'description' => $value,
                    'order_id' => $order['id'],
                    'image' => '',
                    'type'=>'order_status'
                ];
                Helpers::send_push_notif_to_device($fcm_token, $data);
                DB::table('user_notifications')->insert([
                    'data'=> json_encode($data),
                    'user_id'=>$order->customer->id,
                    'created_at'=>now(),
                    'updated_at'=>now()
                ]);
            }
            if(in_array($request->order_status, ['processing', 'handover']) && $order->delivery_man)
            {
                $data = [
                    'title' => 'Order',
                    'description' => $request->order_status=='processing'?trans('messages.Proceed_for_cooking'):trans('messages.ready_for_delivery'),
                    'order_id' => $order['id'],
                    'image' => '',
                    'type'=>'order_status'
                ];
                Helpers::send_push_notif_to_device($order->delivery_man->fcm_token, $data);
                DB::table('user_notifications')->insert([
                    'data'=> json_encode($data),
                    'delivery_man_id'=>$order->delivery_man->id,
                    'created_at'=>now(),
                    'updated_at'=>now()
                ]);
            }
        } catch (\Exception $e) {
            Toastr::warning(trans('messages.push_notification_faild'));
        }

        Toastr::success(trans('messages.order').' '.trans('messages.status_updated'));
        return back();
    }

    public function add_delivery_man($order_id, $delivery_man_id)
    {
        if ($delivery_man_id == 0) {
            return response()->json([], 401);
        }
        $order = Order::where(['id' => $order_id, 'restaurant_id' => Helpers::get_restaurant_id()])->first();
        $order->delivery_man_id = $delivery_man_id;
        $order->save();

        $fcm_token = $order->delivery_man->fcm_token;
        $value = Helpers::order_status_update_message('del_assign');
        try {
            if ($value) {
                $data = [
                    'title' => 'Order',
                    'description' => $value,
                    'image' => '',
                ];
                Helpers::send_push_notif_to_device($fcm_token, $data);
            }
        } catch (\Exception $e) {

        }

        Toastr::success('Order deliveryman added!');
        return response()->json([], 200);
    }

    public function payment_status(Request $request)
    {
        $order = Order::where(['id' => $request->id, 'restaurant_id' => Helpers::get_restaurant_id()])->first();
        if ($request->payment_status == 'paid' && $order['transaction_reference'] == null && $order['payment_method'] != 'cash_on_delivery') {
            Toastr::warning('Add your payment reference code first!');
            return back();
        }
        if ($request->payment_status == 'paid' && $order->transaction == null) {

            $comission = $order->restaurant->comission;
            $comission_amount = ($order->order_amount / 100) * $comission;
            try{
                DB::table('wallet_histories')->insert([
                    'vendor_id'  => $order->restaurant->vendor->id,
                    'amount'     => $order->order_amount - $comission_amount,
                    'order_id'   => $order->id,
                    'payment'    => 'received',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('admin_wallet_histories')->insert([
                    'admin_id'   => Admin::where('role_id', 1)->first()->id,
                    'amount'     => $comission_amount,
                    'order_id'   => $order->id,
                    'payment'    => 'received',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $vendor_wallet = RestaurantWallet::where('vendor_id', $order->restaurant->vendor->id)->first();
                $admmin_wallet = AdminWallet::where('admin_id', Admin::where('role_id', 1)->first()->id)->first();
                if ( $vendor_wallet == false) {
                    $vendor_wallet = new RestaurantWallet();
                    $vendor_wallet->vendor_id  = $order->restaurant->vendor->id;
                    $vendor_wallet->balance    = 0;
                    $vendor_wallet->withdrawn  = 0;
                    $vendor_wallet->created_at = now();
                    $vendor_wallet->updated_at = now();
                    $vendor_wallet->save();
                }
                if ($admmin_wallet == false) {
                    $admmin_wallet = new AdminWallet();
                    $admmin_wallet->admin_id = Admin::where('role_id', 1)->first()->id;
                    $admmin_wallet->balance = 0;
                    $admmin_wallet->withdrawn  = 0;
                    $admmin_wallet->created_at = now();
                    $admmin_wallet->updated_at = now();
                    $admmin_wallet->save();
                }

                $vendor_wallet->increment('balance', $order->order_amount - $comission_amount);
                $admmin_wallet->increment('balance', $comission_amount);
            }
            catch (\Exception $e){
                return $e;
            }
            

        } 
        $order->payment_status = $request->payment_status;
        $order->save();

        Toastr::success('Payment status updated!');
        return back();
    }

    public function update_shipping(Request $request, $id)
    {
        $request->validate([
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => 'required',
            'address' => 'required'
        ]);

        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => $request->address_type,
            'address' => $request->address,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'created_at' => now(),
            'updated_at' => now()
        ];

        DB::table('customer_addresses')->where('id', $id)->update($address);
        Toastr::success('Delivery address updated!');
        return back();
    }

    public function generate_invoice($id)
    {
        $order = Order::where(['id' => $id, 'restaurant_id' => Helpers::get_restaurant_id()])->first();
        return view('vendor-views.order.invoice', compact('order'));
    }

    public function add_payment_ref_code(Request $request, $id)
    {
        Order::where(['id' => $id, 'restaurant_id' => Helpers::get_restaurant_id()])->update([
            'transaction_reference' => $request['transaction_reference']
        ]);

        Toastr::success('Payment reference code is added!');
        return back();
    }
}
