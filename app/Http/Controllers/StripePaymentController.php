<?php

namespace App\Http\Controllers;

use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\Models\Order;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Stripe\Charge;
use Stripe\Stripe;
use App\Models\BusinessSetting;
use PHPUnit\Exception;


class StripePaymentController extends Controller
{
    public function payment_process_3d()
    {
        $tran = Str::random(6) . '-' . rand(1, 1000);
        $order_id = session('order_id');
        session()->put('transaction_ref', $tran);
        $order = Order::with(['details'])->where(['id' => $order_id])->first();
        $config = Helpers::get_business_settings('stripe');
        Stripe::setApiKey($config['api_key']);
        header('Content-Type: application/json');

        $YOUR_DOMAIN = url('/');

        $products = [];
        foreach ($order->details as $detail) {
            array_push($products, [
                'name' => $detail->food?$detail->food['name']:$detail->campaign['name'],
                'image' => 'def.png'
            ]);
        }

        $checkout_session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => Helpers::currency_code(),
                    'unit_amount' => $order->order_amount * 100,
                    'product_data' => [
                        'name' => BusinessSetting::where(['key' => 'business_name'])->first()->value,
                        'images' => [asset('storage/app/public/company') . '/' . BusinessSetting::where(['key' => 'logo'])->first()->value],
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $YOUR_DOMAIN . '/pay-stripe/success',
            'cancel_url' => url()->previous(),
        ]);

        return response()->json(['id' => $checkout_session->id]);
    }

    public function success()
    {
        DB::table('orders')
            ->where('id', session('order_id'))
            ->update(['order_status' => 'confirmed', 'payment_method' => 'stripe', 'transaction_reference' => session('transaction_ref'), 'payment_status' => 'paid','confirmed' => now()]);
        try {
            $order = Order::find(session('order_id'));
            $fcm_token = $order->customer->cm_firebase_token;
            $value = Helpers::order_status_update_message('confirmed');
            OrderLogic::create_transaction($order, 'admin', null);
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
        } catch (\Exception $e) {
            DB::table('orders')
            ->where('id', session('order_id'))
            ->update(['order_status' => 'failed',  'payment_status' => 'unpaid', 'failed'=>now()]);
        }

        return response()->json(['message' => 'Payment succeeded'], 200);
    }

    public function fail()
    {
        DB::table('orders')
        ->where('id', session('order_id'))
        ->update(['order_status' => 'failed',  'payment_status' => 'unpaid', 'failed'=>now()]);
        return response()->json(['message' => 'Payment failed'], 403);
    }
}
