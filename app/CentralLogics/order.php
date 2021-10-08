<?php

namespace App\CentralLogics;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\AdminWallet;
use App\Models\RestaurantWallet;
use App\Models\DeliveryManWallet;
use App\Models\Food;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrderLogic
{
    public static function track_order($order_id)
    {
        return Helpers::order_data_formatting(Order::with(['details', 'delivery_man.rating'])->where(['id' => $order_id])->first(), false);
    }

    public static function place_order($customer_id, $email, $customer_info, $cart, $payment_method, $discount, $coupon_code = null)
    {
        try {
            $or = [
                'id' => 100000 + Order::all()->count() + 1,
                'user_id' => $customer_id,
                'order_amount' => CartManager::cart_grand_total($cart) - $discount,
                'payment_status' => 'unpaid',
                'order_status' => 'pending',
                'payment_method' => $payment_method,
                'transaction_ref' => null,
                'discount_amount' => $discount,
                'coupon_code' => $coupon_code,
                'discount_type' => $discount == 0 ? null : 'coupon_discount',
                'shipping_address' => $customer_info['address_id'],
                'created_at' => now(),
                'updated_at' => now()
            ];

            $o_id = DB::table('orders')->insertGetId($or);

            foreach ($cart as $c) {
                $product = Food::where('id', $c['id'])->first();
                $or_d = [
                    'order_id' => $o_id,
                    'food_id' => $c['id'],
                    'seller_id' => $product->added_by == 'seller' ? $product->user_id : '0',
                    'product_details' => $product,
                    'qty' => $c['quantity'],
                    'price' => $c['price'],
                    'tax' => $c['tax'] * $c['quantity'],
                    'discount' => $c['discount'] * $c['quantity'],
                    'discount_type' => 'discount_on_product',
                    'variant' => $c['variant'],
                    'variation' => json_encode($c['variations']),
                    'delivery_status' => 'pending',
                    'shipping_method_id' => $c['shipping_method_id'],
                    'payment_status' => 'unpaid',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                DB::table('order_details')->insert($or_d);
            }

            Mail::to($email)->send(new \App\Mail\OrderPlaced($o_id));
        } catch (\Exception $e) {

        }

        return $o_id;
    }

    public static function create_transaction($order, $received_by=false, $status = null)
    {
        $comission = $order->restaurant->comission==null?\App\Models\BusinessSetting::where('key','admin_commission')->first()->value:$order->restaurant->comission;
        $order_amount = $order->order_amount - $order->delivery_charge - $order->total_tax_amount;
        

        //Admin Commission Calculated by Arnold Altered
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



        if($order_amount<=($low_value + (($low_fraction_numerator/$low_fraction_denominator)*$low_value))){
            $comission_amount = $order_amount *($low_fraction_numerator/($low_fraction_denominator + 1));
        }elseif($order_amount> ($low_value + (($low_fraction_numerator/$low_fraction_denominator)*$low_value)) and $order_amount<=($high_value + (($intermediate_fraction_numerator/$intermediate_fraction_denominator)*$high_value))){
            $comission_amount = $order_amount*($intermediate_fraction_numerator/($intermediate_fraction_denominator + 1));
        }else{
            $comission_amount = $order_amount * ($high_fraction_numerator/($high_fraction_denominator + 1));
        }
        //$comission_amount = $comission?($order_amount/ 100) * $comission:0;

        //deduct payment gateway earning to get net admin earning
        
        $restaurant_amount= $order_amount + $order->total_tax_amount - $comission_amount;
        $total_amount = $order->order_amount;
        $payment_gateway_amount= round(($payment_gateway/100) * $total_amount,2);

        $comission_amount=round($comission_amount - $payment_gateway_amount,2);

        $restaurant_accumulation= round(($restaurant_deduction/100)*$restaurant_amount,2);
        $restaurant_amount=round($restaurant_amount- $restaurant_accumulation,2);
        
        //End
        
        try{
            OrderTransaction::insert([
                'vendor_id' =>$order->restaurant->vendor->id,
                'delivery_man_id'=>$order->delivery_man_id,
                'order_id' =>$order->id,
                'order_amount'=>$order->order_amount,
                'restaurant_amount'=>$restaurant_amount,
                'admin_commission'=>round($comission_amount + $restaurant_accumulation + $order->delivery_accumulation - $order->risk_allowance,2),
                'payment_gateway_amount'=>$payment_gateway_amount,
                'delivery_charge'=>$order->delivery_charge,
                
                'cal_delivery'=>$order->cal_delivery,
                'delivery_net'=>$order->delivery_net,
                'risk_allowance'=>$order->risk_allowance,
                'delivery_accumulation'=>$order->delivery_accumulation,

                'original_delivery_charge'=>$order->original_delivery_charge,
                'tax'=>$order->total_tax_amount,
                'received_by'=> $received_by?$received_by:'admin',
                'status'=> $status,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $adminWallet = AdminWallet::firstOrNew(
                ['admin_id' => Admin::where('role_id', 1)->first()->id]
            );

            $vendorWallet = RestaurantWallet::firstOrNew(
                ['vendor_id' => $order->restaurant->vendor->id]
            );
            
            //restaurant net
            $vendorWallet->total_earning = $vendorWallet->total_earning+($restaurant_amount);
           
            //delivery net
            $adminWallet->delivery_charge = $adminWallet->delivery_charge+$order->delivery_charge;

            //admin net
            $adminWallet->total_commission_earning = $adminWallet->total_commission_earning+$comission_amount+$restaurant_accumulation-$order->risk_allowance + $order->delivery_accumulation;

            


            try
            {
                DB::beginTransaction();
                if($received_by=='admin')
                {
                    $adminWallet->digital_received = $adminWallet->digital_received+$order->order_amount;
                }
                else if($received_by=='restaurant')
                {
                    $vendorWallet->collected_cash = $vendorWallet->collected_cash+$order->order_amount;
                }
                else if($received_by==false)
                {
                    $adminWallet->manual_received = $adminWallet->manual_received+$order->order_amount;
                    // DB::table('account_transactions')->insert([
                    //     'from_type'=>'customer',
                    //     'from_id'=>$order->user_id,
                    //     'current_balance'=> 0,
                    //     'amount'=> $order->order_amount,
                    //     'method'=>'CASH',
                    //     'created_at' => now(),
                    //     'updated_at' => now()
                    // ]);
                }
                else if($received_by=='deliveryman')
                {
                    $dmWallet = DeliveryManWallet::firstOrNew(
                        ['delivery_man_id' => $order->delivery_man_id]
                    );
                    $dmWallet->collected_cash=$dmWallet->collected_cash+$order->order_amount;
                    $dmWallet->save();
                }
                $adminWallet->save();
                $vendorWallet->save();
                DB::commit();
            }
            catch(\Exception $e)
            {
                DB::rollBack();
                info($e);
                return false;
            }
        }
        catch(\Exception $e){
            info($e);
            return false;
        }

        return true;
    }

    public static function refund_order($order)
    {
        $order_transaction = $order->transaction;
        if($order_transaction == null || $order->restaurant == null)
        {
            return false;
        }
        $received_by = $order_transaction->received_by;

        $adminWallet = AdminWallet::firstOrNew(
            ['admin_id' => Admin::where('role_id', 1)->first()->id]
        );

        $vendorWallet = RestaurantWallet::firstOrNew(
            ['vendor_id' => $order->restaurant->vendor->id]
        );

        $refund_amount = $order->order_amount;
        
        $sub_refund_amount =$refund_amount- $order->delivery_charge;
        
        //by Arnold
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

        
        
        if($order->order_status == 'delivered')
        {
           //$refund_amount = $refund_amount - $order->delivery_charge;
            $status = '';
            return false;
            
        }
        else
        {
            $adminWallet->total_commission_earning = $adminWallet->total_commission_earning - $admin_refund;

        $vendorWallet->total_earning = $vendorWallet->total_earning - $restaurant_refund;

        $refund_amount= $refund_amount-$comission_amount;

        $status = 'refunded_with_delivery_charge';
        
      
         $adminWallet->delivery_charge = $adminWallet->delivery_charge - $order_transaction->delivery_charge;
        }
        try
        {
            DB::beginTransaction();
            if($received_by=='admin')
            {
                if($order->delivery_man_id && $order->payment_method != "cash_on_delivery")
                {
                    $adminWallet->digital_received = $adminWallet->digital_received - $refund_amount;
                }
                else
                {
                    $adminWallet->manual_received = $adminWallet->manual_received - $refund_amount;
                }
                
            }
            else if($received_by=='restaurant')
            {
                $vendorWallet->collected_cash = $vendorWallet->collected_cash - $refund_amount;
            }

                // DB::table('account_transactions')->insert([
                //     'from_type'=>'customer',
                //     'from_id'=>$order->user_id,
                //     'current_balance'=> 0,
                //     'amount'=> $refund_amount,
                //     'method'=>'CASH',
                //     'created_at' => now(),
                //     'updated_at' => now()
                // ]);
 
            else if($received_by=='deliveryman')
            {
                $dmWallet = DeliveryManWallet::firstOrNew(
                    ['delivery_man_id' => $order->delivery_man_id]
                );
                $dmWallet->collected_cash=$dmWallet->collected_cash - $refund_amount;
                $dmWallet->save();
            }
            $order_transaction->status = $status;
            $order_transaction->save();
            $adminWallet->save();
            $vendorWallet->save();
            DB::commit();
        }
        catch(\Exception $e)
        {
            DB::rollBack();
            info($e);
            return false;
        }
        return true;

    }

    // public static function increase_order_count($food, $user)
    // {
    //     try
    //     {
    //         $food->increment('order_count');
    //         $user->increment('order_count');
    //     }
    // }
}
