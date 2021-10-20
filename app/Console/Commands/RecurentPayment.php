<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Order;
use App\Abonament;
use App\Account;
use Carbon\Carbon;

use SoapClient;
use \stdClass;
use ReceiptValidator\iTunes\Validator as iTunesValidator;

class RecurentPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'executa:plataRecurenta';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Executa plata recurenta pentru toti userii care au is_recurency setat pe 1';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
      $current_date = Carbon::now();
      $current_date = Carbon::parse($current_date)->format('Y-m-d');  
      $users_android = Account::where('is_recurency', 1)->where('exp_date', '<', $current_date)->where('platform', 'android')->get();
//       dd($users_android);
      if($users_android){
        foreach($users_android as $user){
          $order=new Order;
          $order->name = $user->name;
          $order->id_user = $user->id;
          $order->email = $user->email;
          $order->id_subscription = $user->id_abonament;
          $order->status = 'asteptare';

          $subscription = Abonament::find($user->id_abonament);
          $order->subscription_name = $subscription->name;
          $order->total = $subscription->pret_abonament;
          $order->tip = $subscription->tip;
          $order->id_order = null;
          $order->save();
    //           Retrive order id, create order_id for mobilPay 
          $id_comanda_salvata = $order->id;
          $order_id_generated = $id_comanda_salvata.\App\Http\Controllers\Api\PaymentController::generateRandomId(5);
    //           Create new order object and update order_id
          $order = Order::find($id_comanda_salvata);
          $order->id_order = $order_id_generated;
          $order->save();
           \App\Http\Controllers\Api\PaymentController::procesare_plata_card_recurent($order);
        }
      }
//       $ios_users = Account::where('exp_date', '<', $current_date)->where('is_recurency', 1)->whereNotNull('hash_receipt')->where('platform', 'ios')->get();
//       dd($ios_users);
      
//       exit;
//       if($ios_users){
//         foreach($ios_users as $user){
//           $order = new Order;
//           $order->name = $user->name;
//           $order->id_user = $user->id;
//           $order->email = $user->email;
//           $order->id_subscription = $user->id_abonament;
//           $order->status = 'asteptare';

//           $subscription = Abonament::find($user->id_abonament);
//           $order->subscription_name = $subscription->name;
//           $order->total = $subscription->pret_abonament;
//           $order->tip = $subscription->tip;
//           $order->id_order = null;
//           $order->save();
//     //           Retrive order id, create order_id for mobilPay 
//           $id_comanda_salvata = $order->id;
//           $order_id_generated = $id_comanda_salvata.\App\Http\Controllers\Api\PaymentController::generateRandomId(5);
//     //           Create new order object and update order_id
//           $order = Order::find($id_comanda_salvata);
//           $order->id_order = $order_id_generated;
//           $order->save();
//            \App\Http\Controllers\Api\PaymentController::generate_apple_pay($order, $user->hash_receipt, true);
//         }
//       }
    }
}
