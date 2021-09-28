<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\CentralLogics\OrderLogic;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SenangPayController extends Controller
{
    public function return_senang_pay(Request $request)
    {
        $order = Order::where(['id' => $request['order_id']])->first();
        if ($request['status_id'] == 1) {
            DB::table('orders')
                ->where('id', $request['order_id'])
                ->update([
                    'payment_method'        => 'senang_pay',
                    'transaction_reference' => $request['transaction_id'],
                    'order_status'          => 'confirmed',
                    'order_note'            => 'Senang pay, Hash : ' . $request['hash'],
                    'payment_status'        => 'paid',
                    'confirmed'             => now(),
                    'updated_at'            => now(),
                ]);
            OrderLogic::create_transaction($order, 'admin', null);
            if ($order->callback != null) {
                return redirect($order->callback . '&status=success');
            } else {
                return \redirect()->route('payment-success');
            }
        }
        else
        {
            DB::table('orders')
            ->where('id', $request['order_id'])
            ->update([
                'payment_method'        => 'senang_pay',
                'order_status'          => 'failed',
                'failed'             => now(),
                'updated_at'            => now(),
            ]);
        }
        if ($order->callback != null) {
            return redirect($order->callback . '&status=fail');
        } else {
            return \redirect()->route('payment-fail');
        }
    }
}
