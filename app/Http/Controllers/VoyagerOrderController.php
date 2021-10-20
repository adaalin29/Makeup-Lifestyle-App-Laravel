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

use App\Order;
use Storage;
use Validator;

class VoyagerOrderController extends \TCG\Voyager\Http\Controllers\VoyagerBaseController
{
    use BreadRelationshipParser;
    
    public function view_invoice($order_id){
      $order = Order::findOrFail($order_id);
      $order_invoice = json_decode($order->invoice)[0];
      $invoice_file_name = $order_invoice->original_name;
      return Storage::disk('local')->response('invoices/'.$order->id_user."/".$invoice_file_name, $invoice_file_name);
    }
}
