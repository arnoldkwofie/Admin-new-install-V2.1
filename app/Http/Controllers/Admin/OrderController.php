<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Admin;
use App\Models\Zone;
use App\Models\RestaurantWallet;
use App\Models\AdminWallet;
use App\Models\DeliveryManWallet;
use App\Models\DeliveryMan;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function list($status, Request $request)
    {
        if (session()->has('zone_filter') == false) {
            session()->put('zone_filter', 0);
        }

        if(session()->has('order_filter'))
        {
            $request = json_decode(session('order_filter'));
        }
        
        Order::where(['admin_checked' => 0])->update(['admin_checked' => 1]);

        $orders = Order::with(['customer', 'restaurant'])
        ->when(isset($request->zone), function($query)use($request){
            return $query->whereHas('restaurant', function($q)use($request){
                return $q->whereIn('zone_id',$request->zone);
            });
        })
        ->when($status == 'scheduled', function($query){
            return $query->whereRaw('created_at <> schedule_at');
        })
        ->when($status == 'searching_for_deliverymen', function($query){
            return $query->SearchingForDeliveryman();
        })
        ->when($status == 'pending', function($query){
            return $query->Pending();
        })
        ->when($status == 'accepted', function($query){
            return $query->AccepteByDeliveryman();
        })
        ->when($status == 'processing', function($query){
            return $query->Preparing();
        })
        ->when($status == 'food_on_the_way', function($query){
            return $query->FoodOnTheWay();
        })
        ->when($status == 'delivered', function($query){
            return $query->Delivered();
        })
        ->when($status == 'canceled', function($query){
            return $query->Canceled();
        })
        ->when($status == 'failed', function($query){
            return $query->failed();
        })
        ->when($status == 'refunded', function($query){
            return $query->Refunded();
        })
        ->when($status == 'scheduled', function($query){
            return $query->Scheduled();
        })
        ->when($status == 'on_going', function($query){
            return $query->Ongoing();
        })
        ->when(($status != 'all' && $status != 'scheduled' && $status != 'canceled' && $status != 'refund_requested' && $status != 'refunded'), function($query){
            return $query->OrderScheduledIn(30);
        })
        ->when(isset($request->vendor), function($query)use($request){
            return $query->whereHas('restaurant', function($query)use($request){
                return $query->whereIn('id',$request->vendor);
            });
        })
        ->when(isset($request->orderStatus) && $status == 'all', function($query)use($request){
            return $query->whereIn('order_status',$request->orderStatus);
        })
        ->when(isset($request->scheduled) && $status == 'all', function($query){
            return $query->scheduled();
        })
        ->when(isset($request->order_type), function($query)use($request){
            return $query->where('order_type', $request->order_type);
        })
        ->when(isset($request->from_date)&&isset($request->to_date)&&$request->from_date!=null&&$request->to_date!=null, function($query)use($request){
            return $query->whereBetween('created_at', [$request->from_date." 00:00:00",$request->to_date." 23:59:59"]);
        })
        ->orderBy('schedule_at', 'desc')
        ->paginate(25);
        $orderstatus = isset($request->orderStatus)?$request->orderStatus:[];
        $scheduled =isset($request->scheduled)?$request->scheduled:0;
        $vendor_ids =isset($request->vendor)?$request->vendor:[];
        $zone_ids =isset($request->zone)?$request->zone:[];
        $from_date =isset($request->from_date)?$request->from_date:null;
        $to_date =isset($request->to_date)?$request->to_date:null;
        $order_type =isset($request->order_type)?$request->order_type:null;
        $total = $orders->total();

        return view('admin-views.order.list', compact('orders', 'status', 'orderstatus', 'scheduled', 'vendor_ids', 'zone_ids', 'from_date', 'to_date', 'total', 'order_type'));
    }
    
    public function dispatch_list($status, Request $request)
    {

        if(session()->has('order_filter'))
        {
            $request = json_decode(session('order_filter'));
            $zone_ids = isset($request->zone)?$request->zone:0;
        }
        
        Order::where(['checked' => 0])->update(['checked' => 1]);
        
        $orders = Order::with(['customer', 'restaurant'])
        ->when(isset($request->zone), function($query)use($request){
            return $query->whereHas('restaurant', function($query)use($request){
                return $query->whereIn('zone_id',$request->zone);
            });
        })
        ->when($status == 'searching_for_deliverymen', function($query){
            return $query->SearchingForDeliveryman();
        })
        ->when($status == 'on_going', function($query){
            return $query->Ongoing();
        })
        ->when(isset($request->vendor), function($query)use($request){
            return $query->whereHas('restaurant', function($query)use($request){
                return $query->whereIn('id',$request->vendor);
            });
        })
        ->when(isset($request->from_date)&&isset($request->to_date)&&$request->from_date!=null&&$request->to_date!=null, function($query)use($request){
            return $query->whereBetween('created_at', [$request->from_date." 00:00:00",$request->to_date." 23:59:59"]);
        })
        ->OrderScheduledIn(30)
        ->orderBy('schedule_at', 'desc')
        ->paginate(25);

        $orderstatus = isset($request->orderStatus)?$request->orderStatus:[];
        $scheduled =isset($request->scheduled)?$request->scheduled:0;
        $vendor_ids =isset($request->vendor)?$request->vendor:[];
        $zone_ids =isset($request->zone)?$request->zone:[];
        $from_date =isset($request->from_date)?$request->from_date:null;
        $to_date =isset($request->to_date)?$request->to_date:null;
        $total = $orders->total();

        return view('admin-views.order.distaptch_list', compact('orders', 'status', 'orderstatus', 'scheduled', 'vendor_ids', 'zone_ids', 'from_date', 'to_date', 'total'));
    }

    public function details($id)
    {
        $order = Order::with('details')->where(['id' => $id])->first();
        if (isset($order)) {
            $deliveryMen = DeliveryMan::where('zone_id',$order->restaurant->zone_id)->available()->active()->get();
            $deliveryMen=Helpers::deliverymen_list_formatting($deliveryMen);
            return view('admin-views.order.order-view', compact('order', 'deliveryMen'));
        } else {
            Toastr::info('No more orders!');
            return back();
        }
    }

    public function search(Request $request){
        $key = explode(' ', $request['search']);
        $orders=Order::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('id', 'like', "%{$value}%")
                ->orWhere('order_status', 'like', "%{$value}%")
                ->orWhere('transaction_reference', 'like', "%{$value}%");
            }
        })->limit(50)->get();
        return response()->json([
            'view'=>view('admin-views.order.partials._table',compact('orders'))->render()
        ]);
    }

    public function status(Request $request)
    {
        $order = Order::find($request->id);

        if ($order['delivery_man_id'] == null && $request->order_status == 'out_for_delivery') {
            Toastr::warning(trans('messages.please_assign_deliveryman_first'));
            return back();
        }

        if ($request->order_status == 'delivered' && $order['transaction_reference'] == null && $order['payment_method'] != 'cash_on_delivery') {
            Toastr::warning(trans('messages.add_your_paymen_ref_first'));
            return back();
        }

        if ($request->order_status == 'delivered') {

            if($order->transaction  == null)
            {
                if($order->payment_method="cash_on_delivery")
                {
                    if($order->order_type=='take_away')
                    {
                        $ol = OrderLogic::create_transaction($order,'restaurant', null);
                    }
                    else if($order->delivery_man_id)
                    {
                        $ol =  OrderLogic::create_transaction($order,'deliveryman', null);
                    }
                    else if($order->user_id)
                    {
                        $ol =  OrderLogic::create_transaction($order, false, null);
                    }
                }
                else
                {
                    $ol = OrderLogic::create_transaction($order,'admin', null);
                }
                if(!$ol)
                {
                    Toastr::warning(trans('messages.faield_to_create_order_transaction'));
                    return back();
                }
            }
            else if($order->delivery_man_id)
            {
                $order->transaction->update(['delivery_man_id'=>$order->delivery_man_id]);
            }

            $order->payment_status = 'paid';
            if($order->delivery_man)
            {
                $dm = $order->delivery_man;
                $dm->available = 1;
                $dm->save();
                
                if($dm->earning == 1)
                {
                    $dmWallet = DeliveryManWallet::firstOrNew(
                        ['delivery_man_id' => $dm->id]
                    );
                    $dmWallet->total_earning = $dmWallet->total_earning + $order->original_delivery_charge;
                    $dmWallet->save();
                }

            }
            $order->details->each(function($item, $key){
                if($item->food)
                {
                    $item->food->increment('order_count');
                }
            });
            $order->customer->increment('order_count');
        }
        else if($request->order_status == 'refunded')
        {
            if($order->payment_method == "cash_on_delivery" || $order->payment_status=="unpaid")
            {
                Toastr::warning(trans('messages.you_can_not_refund_a_cod_order'));
                return back();
            }

            $rt = OrderLogic::refund_order($order);

            if(!$rt)
            {
                Toastr::warning(trans('messages.faield_to_create_order_transaction'));
                return back();
            }

        } 

        $order->order_status = $request->order_status;
        $order[$request->order_status] = now();
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
                    'type'=>'order_status',
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

        Toastr::success(trans('messages.order').trans('messages.status_updated'));
        return back();
    }

    public function add_delivery_man($order_id, $delivery_man_id)
    {
        if ($delivery_man_id == 0) {
            return response()->json([
                    'errors'=>[
                        ['delivery_man_id'=> trans('messages.deliveryman').' '.trans('messages.not_found')]
                    ]
                ], 404);
        }
        $order = Order::find($order_id);

        $deliveryman = DeliveryMan::where('id', $delivery_man_id)->available()->active()->first();
        if($deliveryman)
        {
            if($order->delivery_man)
            {
                $dm = $order->delivery_man;
                $dm->available = 1;
                $dm->save();

                $data = [
                    'title' => 'Order',
                    'description' => trans('messages.you_are_unassigned_from_a_order.'),
                    'order_id' => '',
                    'image' => '',
                    'type'=> 'assign'
                ];
                Helpers::send_push_notif_to_device($dm->fcm_token, $data);

                DB::table('user_notifications')->insert([
                    'data'=> json_encode($data),
                    'delivery_man_id'=>$dm->id,
                    'created_at'=>now(),
                    'updated_at'=>now()
                ]);
            }
            $order->delivery_man_id = $delivery_man_id;
            $order->order_status = isset($order->accepted)?$order->order_status:'accepted';
            $order->accepted = now();
            $order->save();

            $deliveryman->available = 0;
            $deliveryman->save();
            $fcm_token = $order->customer->cm_firebase_token;
            $value = Helpers::order_status_update_message('accepted');
            try {
                if ($value) {
                    $data = [
                        'title' => 'Order',
                        'description' => $value,
                        'order_id' => $order['id'],
                        'image' => '',
                        'type'=> 'order_status'
                    ];
                    Helpers::send_push_notif_to_device($fcm_token, $data);
                    
                    DB::table('user_notifications')->insert([
                        'data'=> json_encode($data),
                        'user_id'=>$order->customer->id,
                        'created_at'=>now(),
                        'updated_at'=>now()
                    ]);
                }
                $data = [
                    'title' => 'Order',
                    'description' => trans('messages.you_are_assigned_to_a_order.'),
                    'order_id' => $order['id'],
                    'image' => '',
                    'type'=> 'assign'
                ];
                Helpers::send_push_notif_to_device($deliveryman->fcm_token, $data);
                DB::table('user_notifications')->insert([
                    'data'=> json_encode($data),
                    'delivery_man_id'=>$deliveryman->id,
                    'created_at'=>now(),
                    'updated_at'=>now()
                ]);

            } catch (\Exception $e) {
                info($e);
                Toastr::warning(trans('messages.push_notification_faild'));
            }
            return response()->json([], 200);
        }
        return response()->json(['message'=> 'Deliveryman not available!'], 400);

    }

    public function update_shipping(Request $request,Order $order)
    {
        $request->validate([
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => 'required',
        ]);
        if($request->latitude && $request->longitude)
        {
            $point = new Point($request->latitude,$request->longitude);
            $zone = Zone::where('id', $order->restaurant->zone_id)->contains('coordinates', $point)->first();
            if(!$zone)
            {
                Toastr::error(trans('messages.out_of_coverage'));
                return back();
            }
        }
        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => $request->address_type,
            'address' => $request->address,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude
        ];

        $order->delivery_address = json_encode($address);
        $order->save();
        Toastr::success('Delivery address updated!');
        return back();
    }

    public function generate_invoice($id)
    {
        $order = Order::where('id', $id)->first();
        return view('admin-views.order.invoice', compact('order'));
    }

    public function add_payment_ref_code(Request $request, $id)
    {
        Order::where(['id' => $id])->update([
            'transaction_reference' => $request['transaction_reference']
        ]);

        Toastr::success('Payment reference code is added!');
        return back();
    }

    public function restaurnt_filter($id)
    {
        session()->put('restaurnt_filter', $id);
        return back();
    }

    public function filter(Request $request)
    {
        $request->validate([
            'from_date' => 'required_if:to_date,true',
            'to_date' => 'required_if:from_date,true',
        ]);
        session()->put('order_filter', json_encode($request->all()));
        return back();
    }
    public function filter_reset(Request $request)
    {
        session()->forget('order_filter');
        return back();
    }
}
