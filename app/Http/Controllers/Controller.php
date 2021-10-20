<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Auth;
use App\Account;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Mail\TrimiteFactura;
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    public function checkSmartBill(){
        $user = Auth::guard('api')->user();
        $order = \App\Order::where('id_order', '58175076')->first();
        $user = Account::find($order->id_user);
        Mail::to($user->email)->send(new TrimiteFactura($order));
        // \App\Http\Controllers\Api\PaymentController::generare_factura($order);
    }
}
