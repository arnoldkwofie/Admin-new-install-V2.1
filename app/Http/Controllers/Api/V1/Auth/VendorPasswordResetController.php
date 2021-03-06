<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;

class VendorPasswordResetController extends Controller
{
    public function reset_password_request(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $vendor = Vendor::Where(['email' => $request['email']])->first();

        if (isset($vendor)) {
            $token = rand(1000,9999);
            DB::table('password_resets')->updateOrInsert([
                'email' => $vendor['email'],
                'token' => $token,
                'created_at' => now(),
            ]);
            Mail::to($vendor['email'])->send(new \App\Mail\PasswordResetMail($token));
            return response()->json(['message' => 'Email sent successfully.'], 200);
        }
        return response()->json(['errors' => [
            ['code' => 'not-found', 'message' => 'Phone number not found!']
        ]], 404);
    }

    public function verify_token(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|exists:vendors,email',
            'reset_token'=> 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $data = DB::table('password_resets')->where(['token' => $request['reset_token'],'email'=>$request->email])->first();
        if (isset($data)) {
            return response()->json(['message'=>"OTP found, you can proceed"], 200);
        }
        return response()->json(['errors' => [
            ['code' => 'reset_token', 'message' => 'Invalid OTP.']
        ]], 400);
    }

    public function reset_password_submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|exists:vendors,email',
            'reset_token'=> 'required',
            'password'=> 'required|min:6',
            'confirm_password'=> 'required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $data = DB::table('password_resets')->where(['email'=>$request['email'],'token' => $request['reset_token']])->first();
        if (isset($data)) {
            if ($request['password'] == $request['confirm_password']) {
                DB::table('vendors')->where(['email' => $data->email])->update([
                    'password' => bcrypt($request['confirm_password'])
                ]);
                DB::table('password_resets')->where(['token' => $request['reset_token']])->delete();
                return response()->json(['message' => 'Password changed successfully.'], 200);
            }
            return response()->json(['errors' => [
                ['code' => 'mismatch', 'message' => 'Password did,t match!']
            ]], 401);
        }
        return response()->json(['errors' => [
            ['code' => 'invalid', 'message' => trans('messages.invalid_otp')]
        ]], 400);
    }
}
