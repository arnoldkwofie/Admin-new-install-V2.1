<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Currency;
use App\Models\Zone;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ConfigController extends Controller
{
    public function configuration()
    {
        $currency_symbol = Currency::where(['currency_code' => Helpers::currency_code()])->first()->currency_symbol;
        $cod = json_decode(BusinessSetting::where(['key' => 'cash_on_delivery'])->first()->value, true);
        $digital_payment = json_decode(BusinessSetting::where(['key' => 'digital_payment'])->first()->value, true);
        $default_location=\App\Models\BusinessSetting::where('key','default_location')->first();
        $default_location=$default_location->value?json_decode($default_location->value, true):0;
        // $dp = json_decode(BusinessSetting::where(['key' => 'digital_payment'])->first()->value, true);
        return response()->json([
            'business_name' => BusinessSetting::where(['key' => 'business_name'])->first()->value,
            // 'business_open_time' => BusinessSetting::where(['key' => 'business_open_time'])->first()->value,
            // 'business_close_time' => BusinessSetting::where(['key' => 'business_close_time'])->first()->value,
            'logo' => BusinessSetting::where(['key' => 'logo'])->first()->value,
            'address' => BusinessSetting::where(['key' => 'address'])->first()->value,
            'phone' => BusinessSetting::where(['key' => 'phone'])->first()->value,
            'email' => BusinessSetting::where(['key' => 'email_address'])->first()->value,
            // 'restaurant_location_coverage' => Branch::where(['id'=>1])->first(['longitude','latitude','coverage']),
            // 'minimum_order_value' => (float)BusinessSetting::where(['key' => 'minimum_order_value'])->first()->value,
            'base_urls' => [
                'product_image_url' => asset('storage/app/public/product'),
                'customer_image_url' => asset('storage/app/public/profile'),
                'banner_image_url' => asset('storage/app/public/banner'),
                'category_image_url' => asset('storage/app/public/category'),
                'review_image_url' => asset('storage/app/public/review'),
                'notification_image_url' => asset('storage/app/public/notification'),
                'restaurant_image_url' => asset('storage/app/public/restaurant'),
                'vendor_image_url' => asset('storage/app/public/vendor'),
                'restaurant_cover_photo_url' => asset('storage/app/public/restaurant/cover'),
                'delivery_man_image_url' => asset('storage/app/public/delivery-man'),
                'chat_image_url' => asset('storage/app/public/conversation'),
                'campaign_image_url' => asset('storage/app/public/campaign'),
                'business_logo_url' => asset('storage/app/public/business')
            ],
            'country' => BusinessSetting::where(['key' => 'country'])->first()->value,
            'default_location'=> [ 'lat'=> $default_location?$default_location['lat']:'23.757989', 'lng'=> $default_location?$default_location['lng']:'90.360587' ],
            'currency_symbol' => $currency_symbol,
            'currency_symbol_direction' => BusinessSetting::where(['key' => 'currency_symbol_position'])->first()->value,
            'app_minimum_version' => (float)BusinessSetting::where(['key' => 'app_minimum_version'])->first()->value,
            'app_url' => BusinessSetting::where(['key' => 'app_url'])->first()->value,
            'customer_verification' => (boolean)BusinessSetting::where(['key' => 'customer_verification'])->first()->value,
            'schedule_order' => (boolean)BusinessSetting::where(['key' => 'schedule_order'])->first()->value,
            'order_delivery_verification' => (boolean)BusinessSetting::where(['key' => 'order_delivery_verification'])->first()->value,
            'cash_on_delivery' => (boolean)($cod['status'] == 1 ? true : false),
            'digital_payment' => (boolean)($digital_payment['status'] == 1 ? true : false),
            'terms_and_conditions' => BusinessSetting::where(['key' => 'terms_and_conditions'])->first()->value,
            'privacy_policy' => BusinessSetting::where(['key' => 'privacy_policy'])->first()->value,
            'about_us' => BusinessSetting::where(['key' => 'about_us'])->first()->value,
            'per_km_shipping_charge' => (double)BusinessSetting::where(['key' => 'per_km_shipping_charge'])->first()->value,
            'minimum_shipping_charge' => (double)BusinessSetting::where(['key' => 'minimum_shipping_charge'])->first()->value,
            'free_delivery_over'=>BusinessSetting::where('key', 'free_delivery_over')->first()->value,
            'demo'=>(boolean)(env('APP_MODE')=='demo'?true:false),
            'maintenance_mode' => (boolean)Helpers::get_business_settings('maintenance_mode') ?? 0,
            'low_value' => (double)BusinessSetting::where(['key' => 'low_value'])->first()->value,
            'high_value' => (double)BusinessSetting::where(['key' => 'high_value'])->first()->value,
            'low_fraction_numerator' => (double)BusinessSetting::where(['key' => 'low_fraction_numerator'])->first()->value,
            'low_fraction_denominator' => (double)BusinessSetting::where(['key' => 'low_fraction_denominator'])->first()->value,
            'intermediate_fraction_numerator' => (double)BusinessSetting::where(['key' => 'intermediate_fraction_numerator'])->first()->value,
            'intermediate_fraction_denominator' => (double)BusinessSetting::where(['key' => 'intermediate_fraction_denominator'])->first()->value,
            'high_fraction_numerator' => (double)BusinessSetting::where(['key' => 'high_fraction_numerator'])->first()->value,
            'high_fraction_denominator' => (double)BusinessSetting::where(['key' => 'high_fraction_denominator'])->first()->value,
            'first_distance_percent' => (double)BusinessSetting::where(['key' => 'first_distance_percent'])->first()->value,
            'second_distance_percent' => (double)BusinessSetting::where(['key' => 'second_distance_percent'])->first()->value,
            'third_distance_percent' => (double)BusinessSetting::where(['key' => 'third_distance_percent'])->first()->value,
            
        ]);
    }

    public function get_zone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required',
            'lng' => 'required',
        ]);

        if ($validator->errors()->count()>0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $point = new Point($request->lat,$request->lng);
        $zones = Zone::contains('coordinates', $point)->get();
        if(count($zones)<1)
        {
            return response()->json(['message'=>trans('messages.service_not_available_in_this_area')], 404);
        }
        foreach($zones as $zone)
        {
            if($zone->status)
            {
                return response()->json(['zone_id'=>$zone->id], 200);
            }
        }
        return response()->json(['message'=>trans('messages.we_are_temporarily_unavailable_in_this_area')], 403);
    }

    public function place_api_autocomplete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search_text' => 'required',
        ]);

        if ($validator->errors()->count()>0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $api_key = BusinessSetting::where(['key' => 'map_api_key'])->first();
        $api_key = isset($api_key)?$api_key->value:'';
        $response = Http::get('https://maps.googleapis.com/maps/api/place/autocomplete/json?input='.$request['search_text'].'&key='.$api_key);
        return $response->json();
    }


    public function distance_api(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origin_lat' => 'required',
            'origin_lng' => 'required',
            'destination_lat' => 'required',
            'destination_lng' => 'required',
        ]);

        if ($validator->errors()->count()>0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $api_key = BusinessSetting::where(['key' => 'map_api_key'])->first();
        $api_key = isset($api_key)?$api_key->value:'';
        $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json?origins='.$request['origin_lat'].','.$request['origin_lng'].'&destinations='.$request['destination_lat'].','.$request['destination_lng'].'&key='.$api_key);
        return $response->json();
    }


    public function place_api_details(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'placeid' => 'required',
        ]);

        if ($validator->errors()->count()>0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $api_key = BusinessSetting::where(['key' => 'map_api_key'])->first();
        $api_key = isset($api_key)?$api_key->value:'';
        $response = Http::get('https://maps.googleapis.com/maps/api/place/details/json?placeid='.$request['placeid'].'&key='.$api_key);
        return $response->json();
    }
}
