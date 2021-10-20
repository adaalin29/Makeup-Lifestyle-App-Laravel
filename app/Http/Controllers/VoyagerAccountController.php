<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use TCG\Voyager\Database\Schema\SchemaManager;
use TCG\Voyager\Events\BreadDataAdded;
use TCG\Voyager\Events\BreadDataDeleted;
use TCG\Voyager\Events\BreadDataRestored;
use TCG\Voyager\Events\BreadDataUpdated;
use TCG\Voyager\Events\BreadImagesDeleted;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Controllers\Traits\BreadRelationshipParser;
use Storage;
use Validator;
use App\Order;
use App\Abonament;
use App\Account;
use Carbon\Carbon;

class VoyagerAccountController extends \TCG\Voyager\Http\Controllers\VoyagerBaseController
{
    // public function update(Request $request, $id)
    // {
    //     $orders = Order::where('id_user',$id)->where('status','platita')->orderBy('created_at','desc')->get();
    //     $abonament = Abonament::first();
    //     $user = Account::find($id);
    //     $expired_modified = false;
    //     if($user->exp_date != $request->exp_date){
    //         $expired_modified = true;
    //     }
    //     // dd($check,$expired_modified);
    //     if($orders && count($orders)>0 && $expired_modified){
    //         $order = $orders[0];
    //         $orderCheck = $order;
    //         $exp_date = $request->exp_date;

    //         $created_date = Carbon::parse($exp_date)->subMonth()->format('Y-m-d');

    //         $startDate = Carbon::parse($order->created_at)->format('Y-m-d');
    //         $endDate = Carbon::parse($order->created_at)->addMonth()->format('Y-m-d');
    //         $check = Carbon::parse($created_date)->between($startDate,$endDate);
            
    //         if($check){
    //             $order->created_at = $created_date;
    //             $order->updated_at = $created_date;
    //             $order->save();
                

    //         }else{
    //             $order = new Order;
    //             $order->created_at = $created_date;
    //             $order->updated_at = $created_date;
    //             $order->name = $request->name;
    //             $order->email = $request->email;
    //             $order->id_subscription = 1;
    //             $order->total = $abonament->pret_abonament;
    //             $order->id_order = -1;
    //             $order->status = 'platita';
    //             $order->subscription_name = $abonament->name .' - Adaugat din admin';
    //             $order->tip = $abonament->tip;
    //             $order->id_user= $id;
    //             $order->invoice = 'Adaugat din admin';
    //             $order->save();
    //             $user->id_abonament = 1;
    //         }
    //         // dd($orderCheck->toArray(),$request->all(),$check,$order->toArray());
    //     }else{
    //         if($expired_modified ){
    //             $exp_date = $request->exp_date;
    //             $created_date = Carbon::parse($exp_date)->subMonth()->format('Y-m-d');
    //             $order = new Order;
    //             $order->created_at = $created_date;
    //             $order->updated_at = $created_date;
    //             $order->name = $request->name;
    //             $order->email = $request->email;
    //             $order->id_subscription = 1;
    //             $order->total = $abonament->pret_abonament;
    //             $order->id_order = -1;
    //             $order->status = 'platita';
    //             $order->subscription_name = $abonament->name .' - Adaugat din admin';
    //             $order->tip = $abonament->tip;
    //             $order->id_user= $id;
    //             $order->invoice = 'Adaugat din admin';
    //             $order->save();
    //         }
    //     }
    //     $slug = $this->getSlug($request);

    //     $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

    //     // Compatibility with Model binding.
    //     $id = $id instanceof \Illuminate\Database\Eloquent\Model ? $id->{$id->getKeyName()} : $id;

    //     $model = app($dataType->model_name);
    //     if ($dataType->scope && $dataType->scope != '' && method_exists($model, 'scope'.ucfirst($dataType->scope))) {
    //         $model = $model->{$dataType->scope}();
    //     }
    //     if ($model && in_array(SoftDeletes::class, class_uses_recursive($model))) {
    //         $data = $model->withTrashed()->findOrFail($id);
    //     } else {
    //         $data = $model->findOrFail($id);
    //     }

    //     // Check permission
    //     $this->authorize('edit', $data);

    //     // Validate fields with ajax
    //     $val = $this->validateBread($request->all(), $dataType->editRows, $dataType->name, $id)->validate();
    //     $this->insertUpdateData($request, $slug, $dataType->editRows, $data);
    //     Account::where('id',$user->id)->update([
    //               'id_abonament'=>1,
    //             ]);

    //     event(new BreadDataUpdated($dataType, $data));
    //     if (auth()->user()->can('browse', app($dataType->model_name))) {
    //         $redirect = redirect()->route("voyager.{$dataType->slug}.index");
    //     } else {
    //         $redirect = redirect()->back();
    //     }

    //     return $redirect->with([
    //         'message'    => __('voyager::generic.successfully_updated')." {$dataType->getTranslatedAttribute('display_name_singular')}",
    //         'alert-type' => 'success',
    //     ]);
    // }
}
