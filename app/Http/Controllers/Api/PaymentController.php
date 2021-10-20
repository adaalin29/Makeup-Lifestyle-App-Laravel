<?php

 namespace App\Http\Controllers\Api;
 use App\Http\Controllers\Controller;
 use Validator;
 use Session;
 use Illuminate\Http\Request;
 use App\Order;
 use App\Abonament;
 use Auth;
 use App\Account;
 use App\OrderPackage;
 use App\Package;
 use App\smartbill\SmartBillCloudRestClient;
 use Carbon\Carbon;
 use Illuminate\Support\Facades\Storage;
 use Illuminate\Support\Facades\Mail;
 use Illuminate\Support\Facades\DB;

 use App\Mail\TrimiteFactura;
 use SoapClient;
 use \stdClass;
 use ReceiptValidator\iTunes\Validator as iTunesValidator;

 class PaymentController extends Controller{
  
  // In cazul in care exista utilizatori ce si-au facut abonament pe IOS si nu a mers, cu functia asta trec prin ei in functie de hash si adaug data de expirare
   public function sendInvoice(){
     // $users = Account::whereNotNull('hash_receipt')->whereNull('exp_date')->get();
     $users = Account::whereNotNull('hash_receipt')->where('exp_date','2020-12-27')->get();
     // dd($users);
     foreach($users as $user){
       $order = Order::where('id_user',$user->id)->first();
       $user->exp_date = Carbon::now()->addDays(60)->format('Y-m-d');
//     $user->exp_date = $order->created_at->addMonths(2)->format('Y-m-d'); in cazul in care adaug in functie de data de comanda creata
       $user->save();
     }
//      dd($users->toArray());
   }

   public function setUsers($receipt_hash, $user){
     $validator = new iTunesValidator(iTunesValidator::ENDPOINT_PRODUCTION); // Or iTunesValidator::ENDPOINT_live if live testing
//     put the receipt_hash
       $receiptBase64Data = $receipt_hash;
       try {
//       $response = $validator->setReceiptData($receiptBase64Data)->validate();
         $sharedSecret = '5d51eb5bc3b44fde88077f11fc9df9b9'; // Generated in iTunes Connect's In-App Purchase menu
         $response = $validator->setSharedSecret($sharedSecret)->setReceiptData($receiptBase64Data)->validate(); // use setSharedSecret() if for recurring subscriptions
       } catch (Exception $e) {
         return ['success' => 'false', 'msg' => 'Din pacate s-a produs o eroare. Reincercati!'];
       }

       if ($response->isValid()) {

         $order_server = Order::where('id_user',$user->id)->first();
         $order_server->status = 'platita';
         $order_server->save();
        
         $user->exp_date = $order->created_at->addDays(60);
         $user->id_abonament =1;
         $user->platform = 'ios';
         $user->save();
        
         return ['success' => 'true', 'msg' => 'Abonarea a fost facuta cu succes! Va multumim!'];
       
       } else {
         return ['success' => 'false', 'msg' => 'Din pacate s-a produs o eroare la autentificarea in contul Apple. Reincercati!'];
       }
   }
  
   public function comandaCardRaspuns(Request $request){
    
     $order = Order::where('id_order',$request->order_id)->first();
     return ['success'=>true, 'order'=>$order];
   }
   public function sendOrder(Request $request){
      $user = Auth::guard('api')->user();
      $form_data = $request->only(['platform','packages']);
      $validationRules = [
          'packages'    => ['required'],
      ];
      $validationMessages = [
          'packages.required'    => "Campul pachet este obligatoriu!",   
      ];
      $validator = Validator::make($form_data, $validationRules, $validationMessages);
      if ($validator->fails()){
          return ['success' => false, 'error' => $validator->errors()->all()];
      }else{
          $expDate = Carbon::now()->addDays(60)->format('Y-m-d');
          $order=new Order;
          $order->name = $user->name;
          $order->id_user = $user->id;
          $order->email = $user->email;
          $order->status = 'asteptare';
          $order->id_order = null;
          $id_comanda_salvata = $order->id;
          $order_id_generated = $id_comanda_salvata.(new self())->generateRandomId(5);
          $order->id_order = $order_id_generated;
          $order->total = 0;
          $order->exp_date = $expDate;
          if($request->platform =="ios"){
            $order->status ="platita"; //de modificat
          }
          foreach($request->packages as $package){
              $order->total+=Package::where('id',$package)->select('price')->first()->price;
          }
          $order->platform = $request->platform;
          $order->total = number_format($order->total,2,'.',',');
          $order->save();
          foreach($request->packages as $package){
              $orderPackage = new OrderPackage();
              $orderPackage->order_id = $order->id;
              $orderPackage->package_id = $package;
              $orderPackage->created_at =  Carbon::now();
              $orderPackage->updated_at =  Carbon::now();
              $orderPackage->save();
          }
          if($request->platform == 'ios'){
            // (new self())->generare_factura($order);
            // $payment_response = $this->generate_apple_pay($order, $user->hash_receipt,$is_recurent);

            return ['success'=>true];
          }else{
            $form = $this->procesare_plata_card($order);
          }
          if($form!=null){
            return ['form' => $form,'success'=>true];
          }else{
            return ['success'=>false,'error'=>'S-a produs o eroare, va rugam sa reincercati!'];
          }
      }
   }
   public static function generateRandomId($length = 5) {
     $characters = '0123456789';
     $charactersLength = strlen($characters);
     $randomString = '0';
     for ($i = 0; $i < $length; $i++) {
       $randomString .= $characters[rand(0, $charactersLength - 1)];
     }
     if($randomString[0]== 0){
       $randomString[0] = "1";
     }
     return $randomString;
   }
  
   public function genereaza_formular() {
     $html = request()->get('html');
 //     dd($html);exit;
     return view('appform', ['html' => $html]);
   }
  
   public static function procesare_plata_card_recurent($order){
     $user = Account::find($order->id_user);
     $soap = new SoapClient('http://secure.mobilpay.ro/api/payment2/?wsdl', Array('cache_wsdl' => WSDL_CACHE_NONE));
     $sacId = 'ANC9-5NRG-LSW5-1XHR-F1X7';
     $req = new stdClass();

     $account = new stdClass();
     $account->id = $sacId;
     $account->user_name = "ZRZA.api"; //please ask mobilPay to upgrade the necessary access required for token payments
     //$account->customer_ip = $_SERVER['REMOTE_ADDR']; //the IP address of the buyer. 
//     $account->customer_ip = $user->mobilpay_server_addr; //the IP address of the buyer. 
     $account->confirm_url = str_replace('http','https',\URL::to('/').'/confirm-order');  //this is where mobilPay will send the payment result. This has priority over the SOAP call response


     $transaction = new stdClass();
     $transaction->paymentToken = $user->token; //you will receive this token together with its expiration date following a standard payment. Please store and use this token with maximum care
    
     $facturare_user = explode(' ', $user->mobilpay_firstName);
     $first_name = 'Guest';
     $last_name = 'Guest';
     if(isset($facturare_user[0])){
       $first_name = $facturare_user[0];
     }
     if(isset($facturare_user[1])){
       $last_name = $facturare_user[1];
     }
    
    
     $billing = new stdClass();
     $billing->country = 'Romania';
     $billing->county = 'Bucharest';
     $billing->city = 'Bucharest';
     $billing->address = $user->mobilpay_address ? $user->mobilpay_address : "Bucharest";
     $billing->postal_code = '';
     $billing->first_name = $first_name;
     $billing->last_name = $last_name;
     $billing->phone = $user->mobilpay_mobilePhone ? $user->mobilpay_mobilePhone : "1231231234";
     $billing->email = $user->mobilpay_email ? $user->mobilpay_email : $user->email;
    
     $order_obj = new stdClass();
     $order_obj->id = $order->id_order; //your orderId. As with all mobilPay payments, it needs to be unique at seller account level
     $order_obj->description = 'Plata recurenta abonament'; //payment descriptor
    
     //$order_obj->amount = $order->total; // order amount; decimals present only when necessary, i.e. 15 not 15.00
     $order_obj->amount = $order->total; // order amount; decimals present only when necessary, i.e. 15 not 15.00
     $order_obj->currency = 'RON'; //currency
     $order_obj->billing = $billing;
     //$order->shipping = $shipping;

     $params = new stdClass();
     $params->item = new stdClass();
 	  $params->item->name = 'param1name';
 	  $params->item->value = 'param1value';
    
     $account->hash = strtoupper(sha1(strtoupper(md5('fqSNG2zA')) . "{$order->id_order}{$order_obj->amount}{$order_obj->currency}{$account->id}"));

     $req->account = $account;
     $req->order = $order_obj;
 	  $req->params = $params;
     $req->transaction = $transaction;
    
     try
     {
       $response = $soap->doPayT(Array('request' => $req));

         if (isset($response->errors) && $response->errors->code != ERR_CODE_OK)
         {
             throw new Exception($response->code, $response->message);
         }
     }
     catch(SoapFault $e)
     {
         throw new Exception($e->faultstring);//, $e->faultcode, $e);
     }
    
   }
  
   public function save_hash_apple(Request $request){
     $user = Auth::guard('api')->user();
     $form_data = $request->only(['hash_receipt']);
     $validationRules = [
         'hash_receipt'    => ['required'],
     ];
     $validationMessages = [
         'hash_receipt.required'    => "S-a produs o eroare!",


     ];
     $validator = Validator::make($form_data, $validationRules, $validationMessages);
     if ($validator->fails()){
         return ['success' => false, 'error' => $validator->errors()->all()];
     } else{
       $user->hash_receipt = $form_data['hash_receipt'];
       $user->save();
       return ['success' => true];
     }
   }
  
   public static function generate_apple_pay($order, $receipt_hash,$is_recurent){
       $validator = new iTunesValidator(iTunesValidator::ENDPOINT_live); // Or iTunesValidator::ENDPOINT_live if live testing
       // put the receipt_hash
       $receiptBase64Data = $receipt_hash;
       try {
         //         $response = $validator->setReceiptData($receiptBase64Data)->validate();
         $sharedSecret = '5d51eb5bc3b44fde88077f11fc9df9b9'; // Generated in iTunes Connect's In-App Purchase menu
         $response = $validator->setSharedSecret($sharedSecret)->setReceiptData($receiptBase64Data)->validate(); // use setSharedSecret() if for recurring subscriptions
       } catch (Exception $e) {
         return ['success' => 'false', 'msg' => 'Din pacate s-a produs o eroare. Reincercati!'];
       }
       if ($response->isValid()) {
         $user = Account::find($order->id_user);
         $order_server = Order::find($order->id);
         $order_server->status = 'platita';
         $order_server->save();
        //  (new self())->generare_factura($order_server);
         // nu stiu exact daca trebuie sa generam factura sau nu
        
         $user->exp_date = Carbon::now()->addDays(60);
         $user->is_recurency = $is_recurent ? 1 : 0;
         $user->id_abonament =$order->id_subscription;
         $user->platform = 'ios';

         $user->save();
        
         return ['success' => 'true', 'msg' => 'Va multumim pentru abonare! Începând de astăzi veți avea acces la vizionarea următoarelor tutorialele care vor fi încărcare in aplicatie pe toată perioada activa a abonamentului. Tutorialele care au fost încărcate anterior abonarii nu se pot viziona.'];
       
       } else {
         return ['success' => 'false', 'msg' => 'Din pacate s-a produs o eroare la autentificarea in contul Apple. Reincercati!'. $response->getResultCode() . PHP_EOL];
       }
   }
   public function check_validate_ios(Request $request){
    $user = Auth::guard('api')->user();
    $form_data = $request->only(['expires_date']);
    $validationRules = [
        'expires_date'    => ['required'],
    ];
    $validationMessages = [
        'expires_date.required'    => "S-a produs o eroare!",
    ];
    $validator = Validator::make($form_data, $validationRules, $validationMessages);
    if ($validator->fails()){
        return ['success' => false, 'error' => $validator->errors()->all()];
    } else{
        // $orders = Order::where('id_user',$user->id)->where('status','asteptare')->whereDate('exp_date',Carbon::parse($form_data['expires_date'])->format('Y-m-d'))->get();
        $orders = Order::where('id_user',$user->id)->where('status','asteptare')->whereDate('exp_date',Carbon::parse($form_data['expires_date'])->addDays(60)->format('Y-m-d'))->where('receipt',$form_data['receipt'])->get();
        if(count($orders)>0 && $orders){
          foreach($orders as &$order){
            $order->status = "platita";
            $order->save();
          }
          return['success'=>true];
        }else{
          return['success'=>false];
        }
    }
  }
  //  public function check_validate_ios(Request $request){
  //    $user = Auth::guard('api')->user();
  //    $form_data = $request->only(['expires_date']);
  //    $validationRules = [
  //        'expires_date'    => ['required'],
  //    ];
  //    $validationMessages = [
  //        'expires_date.required'    => "S-a produs o eroare!",
  //    ];
  //    $validator = Validator::make($form_data, $validationRules, $validationMessages);
  //    if ($validator->fails()){
  //        return ['success' => false, 'error' => $validator->errors()->all()];
  //    } else{
  //        $current_date = Carbon::parse(date("Y-m-d"))->format('Y-m-d');               // 2021-03-22
  //        $expired_date = Carbon::parse($form_data['expires_date'])->format('Y-m-d'); // 2021-04-22
  //        $user_exp_date = $user->exp_date; 
  //        $user_exp_date = Carbon::parse($user->exp_date)->format('Y-m-d');
  //        return ['success' => false, 'dates' => $current_date.' | '.$expired_date.' | '.$user_exp_date.' | '. $expired_date];
  //        if($current_date < $expired_date && $user_exp_date != null && $user_exp_date < $expired_date){
  //          $created_at_check = Carbon::parse($form_data['expires_date'])->subMonth()->format('Y-m-d');
  //          $orders = Order::where('id_user',$user->id)->whereRaw('DATE(created_at) = ?', [$created_at_check])->orderBy('created_at','desc')->get();
  //          $abonament = Abonament::first();
  //          if(count($orders) > 0){
  //            $order = $orders[0];
  //            if($order){
  //              $order->status = 'platita';
  //              $order->save();
  //            } else{
  //              $order_new = new Order;
  //              $order_new->created_at = $created_at_check;
  //              $order_new->updated_at = $created_at_check;
  //              $order_new->name = $user->name;
  //              $order_new->email = $user->email;
  //              $order_new->id_subscription = 1;
  //              $order_new->total = $abonament->pret_abonament;
  //              $order_new->save();
  //              $id_comanda_salvata = $order_new->id;
  //              $order_id_generated = $id_comanda_salvata.(new self())->generateRandomId(5);
  //              $order_new->status = 'platita';
  //              $order_new->subscription_name = $abonament->name;
  //              $order_new->tip = $abonament->tip;
  //              $order_new->id_user= $user->id;
  //              $user->id_abonament = 1;
  //              $order_new->save();
  //            }
  //          } else{
  //              $order_new = new Order;
  //              $order_new->created_at = $created_at_check;
  //              $order_new->updated_at = $created_at_check;
  //              $order_new->name = $user->name;
  //              $order_new->email = $user->email;
  //              $order_new->id_subscription = 1;
  //              $order_new->total = $abonament->pret_abonament;
  //              $order_new->save();
  //              $id_comanda_salvata = $order_new->id;
  //              $order_id_generated = $id_comanda_salvata.(new self())->generateRandomId(5);
  //              $order_new->status = 'platita';
  //              $order_new->subscription_name = $abonament->name;
  //              $order_new->tip = $abonament->tip;
  //              $order_new->id_user= $user->id;
  //              $user->id_abonament = 1;
  //              $order_new->save();
  //          }
  //          $user->id_abonament = 1;
  //          $user->exp_date = $expired_date;
  //          $user->save();
  //          return ['success' => true, 'loc' => 'Inside If'];
  //        }
  //        return ['success' => true, 'loc' => 'Outside If'];
  //    }
  //  }
  
   function procesare_plata_card ($order) {
     require_once base_path('app/Mobilpay/Payment/Request/Abstract.php');
     require_once base_path('app/Mobilpay/Payment/Request/Card.php');
     require_once base_path('app/Mobilpay/Payment/Invoice.php');
     require_once base_path('app/Mobilpay/Payment/Address.php');
    
     #for testing purposes, all payment requests will be sent to the live server. Once your account will be active you must switch back to the live server https://secure.mobilpay.ro
     #in order to display the payment form in a different language, simply add the language identifier to the end of the paymentUrl, i.e https://secure.mobilpay.ro/en for English
     $paymentUrl = 'https://secure.mobilpay.ro'; //dev
//     $paymentUrl = 'https://secure.mobilpay.ro';
     // this is the path on your server to the public certificate. You may download this from Admin -> Conturi de comerciant -> Detalii -> Setari securitate
     $x509FilePath 	= base_path('app/Mobilpay/certificates/live.ANC9-5NRG-LSW5-1XHR-F1X7.public.cer');
     try
     {
       srand((double) microtime() * 1000000);
       $objPmReqCard 						= new \Mobilpay_Payment_Request_Card();
       #merchant account signature - generated by mobilpay.ro for every merchant account
       #semnatura contului de comerciant - mergi pe www.mobilpay.ro Admin -> Conturi de comerciant -> Detalii -> Setari securitate
       $objPmReqCard->signature 			= 'ANC9-5NRG-LSW5-1XHR-F1X7';
       #you should assign here the transaction ID registered by your application for this commercial operation
       #order_id should be unique for a merchant account
       $objPmReqCard->orderId 				= $order->id_order;
       #below is where mobilPay will send the payment result. This URL will always be called first; mandatory
       $objPmReqCard->confirmUrl 			= \URL::to('/').'/confirm-order'; 
       #below is where mobilPay redirects the client once the payment process is finished. Not to be mistaken for a "successURL" nor "cancelURL"; mandatory
       $objPmReqCard->returnUrl 			= \URL::to('/').'/return-order'; 
       #detalii cu privire la plata: moneda, suma, descrierea
       #payment details: currency, amount, description
       $objPmReqCard->invoice = new \Mobilpay_Payment_Invoice();
       #payment currency in ISO Code format; permitted values are RON, EUR, USD, MDL; please note that unless you have mobilPay permission to 
       #process a currency different from RON, a currency exchange will occur from your currency to RON, using the official BNR exchange rate from that moment
       #and the customer will be presented with the payment amount in a dual currency in the payment page, i.e N.NN RON (e.ee EUR)
       $objPmReqCard->invoice->currency	= 'RON';
       $objPmReqCard->invoice->amount		= $order->total;
       #available installments number; if this parameter is present, only its value(s) will be available
       //$objPmReqCard->invoice->installments= '2,3';
       #selected installments number; its value should be within the available installments defined above
       //$objPmReqCard->invoice->selectedInstallments= '3';
       //platile ulterioare vor contine in request si informatiile despre token. Prima plata nu va contine linia de mai jos.
       $objPmReqCard->invoice->details		= 'Plata online cu cardul';
       #detalii cu privire la adresa posesorului cardului
       #details on the cardholder address (optional)
       $facturare_user = explode(' ', $order->name);
       $first_name = 'Guest';
       $last_name = 'Guest';
       if(isset($facturare_user[0])){
         $first_name = $facturare_user[0];
       }
       if(isset($facturare_user[1])){
         $last_name = $facturare_user[1];
       }
       $billingAddress 				= new \Mobilpay_Payment_Address();
 //       $billingAddress->type			= $_POST['billing_type']; //should be "person"
       $billingAddress->firstName		= $first_name;
       $billingAddress->lastName		= $last_name;
       $billingAddress->address		= "no Address";
       $billingAddress->email			= $order->email;
       $billingAddress->mobilePhone		= '123';
       $objPmReqCard->invoice->setBillingAddress($billingAddress);
       #details on the shipping address
       $shippingAddress 				= new \Mobilpay_Payment_Address();
 //       $shippingAddress->type			= $_POST['shipping_type'];
       $shippingAddress->firstName		= $first_name;
       $shippingAddress->lastName		= $last_name;
       $shippingAddress->address		= "No Address";
       $shippingAddress->email			= $order->email;
       $shippingAddress->mobilePhone		= '1234';
       $objPmReqCard->invoice->setShippingAddress($shippingAddress);

       #uncomment the line below in order to see the content of the request
       //echo "<pre>";print_r($objPmReqCard);echo "</pre>";
       $objPmReqCard->encrypt($x509FilePath);
       $mobilpayFormData = new \stdClass();
 			$mobilpayFormData->postUrl = $paymentUrl;
 			$mobilpayFormData->env_key = $objPmReqCard->getEnvKey();
 			$mobilpayFormData->data = $objPmReqCard->getEncData();
//       return $objPmReqCard;
       return json_encode( $mobilpayFormData );
     }
     catch(Exception $e)
     {
       return null;
     }
   }
  
  
   public function confirm_order() {
     require_once base_path('app/Mobilpay/Payment/Request/Abstract.php');
     require_once base_path('app/Mobilpay/Payment/Request/Card.php');
     require_once base_path('app/Mobilpay/Payment/Request/Notify.php');
     require_once base_path('app/Mobilpay/Payment/Invoice.php');
     require_once base_path('app/Mobilpay/Payment/Address.php');

     $errorCode 		= 0;
     $errorType		= \Mobilpay_Payment_Request_Abstract::CONFIRM_ERROR_TYPE_NONE;
     $errorMessage	= '';

     if (strcasecmp($_SERVER['REQUEST_METHOD'], 'post') == 0)
     {
       if(isset($_POST['env_key']) && isset($_POST['data']))
       {
         #calea catre cheia privata
         #cheia privata este generata de mobilpay, accesibil in Admin -> Conturi de comerciant -> Detalii -> Setari securitate
         $privateKeyFilePath = base_path('app/Mobilpay/certificates/live.ANC9-5NRG-LSW5-1XHR-F1X7private.key');

         try
         {
         $objPmReq = \Mobilpay_Payment_Request_Abstract::factoryFromEncrypted($_POST['env_key'], $_POST['data'], $privateKeyFilePath);
         #uncomment the line below in order to see the content of the request
 //         dd($objPmReq);
         $rrn = $objPmReq->objPmNotify->rrn;
         // action = status only if the associated error code is zero
          
           $id_comanda = $objPmReq->orderId;      
           $order = Order::with('packages')->where('id_order', $id_comanda)->first();
         if ($objPmReq->objPmNotify->errorCode == 0) {
            
               switch($objPmReq->objPmNotify->action)
               {
             #orice action este insotit de un cod de eroare si de un mesaj de eroare. Acestea pot fi citite folosind $cod_eroare = $objPmReq->objPmNotify->errorCode; respectiv $mesaj_eroare = $objPmReq->objPmNotify->errorMessage;
             #pentru a identifica ID-ul comenzii pentru care primim rezultatul platii folosim $id_comanda = $objPmReq->orderId;
            
             case 'confirmed':
               #cand action este confirmed avem certitudinea ca banii au plecat din contul posesorului de card si facem update al starii comenzii si livrarea produsului
             //update DB, SET status = "confirmed/captured"
             $errorMessage = $objPmReq->objPmNotify->errorMessage;
             // return['incercare'=>$objPmReq];
             if($order->status != "platita"){
                 $order->status = "platita";
                 $order->save();
                 (new self())->generare_factura($order);
               if($user){
                 $user->platform = "android";
                 $user->save();
               }
             }
                  
             break;
             case 'confirmed_pending':
               #cand action este confirmed_pending inseamna ca tranzactia este in curs de verificare antifrauda. Nu facem livrare/expediere. In urma trecerii de aceasta verificare se va primi o noua notificare pentru o actiune de confirmare sau anulare.
             //update DB, SET status = "pending"
               $errorMessage = $objPmReq->objPmNotify->errorMessage;
               $order->status = "asteptare";
               $order->save();
               break;
             case 'paid_pending':
               #cand action este paid_pending inseamna ca tranzactia este in curs de verificare. Nu facem livrare/expediere. In urma trecerii de aceasta verificare se va primi o noua notificare pentru o actiune de confirmare sau anulare.
             //update DB, SET status = "pending"
               $errorMessage = $objPmReq->objPmNotify->errorMessage;
               $order->status = "asteptare";
               $order->save();
                  
               break;
             case 'paid':
               #cand action este paid inseamna ca tranzactia este in curs de procesare. Nu facem livrare/expediere. In urma trecerii de aceasta procesare se va primi o noua notificare pentru o actiune de confirmare sau anulare.
             //update DB, SET status = "open/preauthorized"
               $errorMessage = $objPmReq->objPmNotify->errorMessage;
               $order->status = "asteptare";
               $order->save();
               break;
             case 'canceled':
               #cand action este canceled inseamna ca tranzactia este anulata. Nu facem livrare/expediere.
             //update DB, SET status = "canceled"
             $errorMessage = $objPmReq->objPmNotify->errorMessage;
                  
             $order->status = "canceled";
             $order->save();
               break;
             case 'credit':
               #cand action este credit inseamna ca banii sunt returnati posesorului de card. Daca s-a facut deja livrare, aceasta trebuie oprita sau facut un reverse. 
             //update DB, SET status = "refunded"
             $errorMessage = $objPmReq->objPmNotify->errorMessage;
               break;
           default:
             $errorType		= \Mobilpay_Payment_Request_Abstract::CONFIRM_ERROR_TYPE_PERMANENT;
               $errorCode 		= \Mobilpay_Payment_Request_Abstract::ERROR_CONFIRM_INVALID_ACTION;
               $errorMessage 	= 'mobilpay_refference_action paramaters is invalid';
               break;
               }
         }
         else {
           //update DB, SET status = "rejected"
           $errorMessage = $objPmReq->objPmNotify->errorMessage;
           $order->status = "canceled";
             $order->save();
             }
         }
         catch(Exception $e)
         {
           $errorType 		= \Mobilpay_Payment_Request_Abstract::CONFIRM_ERROR_TYPE_TEMPORARY;
           $errorCode		= $e->getCode();
           $errorMessage 	= $e->getMessage();
         }
         }
         else
         {
           $errorType 		= \Mobilpay_Payment_Request_Abstract::CONFIRM_ERROR_TYPE_PERMANENT;
           $errorCode		= \Mobilpay_Payment_Request_Abstract::ERROR_CONFIRM_INVALID_POST_PARAMETERS;
           $errorMessage 	= 'mobilpay.ro posted invalid parameters';
         }
         }
         else 
         {
           $errorType 		= \Mobilpay_Payment_Request_Abstract::CONFIRM_ERROR_TYPE_PERMANENT;
           $errorCode		= \Mobilpay_Payment_Request_Abstract::ERROR_CONFIRM_INVALID_POST_METHOD;
           $errorMessage 	= 'invalid request metod for payment confirmation';
         }

         header('Content-type: application/xml');
         echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
         if($errorCode == 0)
         {
           echo "<crc>{$errorMessage}</crc>";
         }
         else
         {
           echo "<crc error_type=\"{$errorType}\" error_code=\"{$errorCode}\">{$errorMessage}</crc>";
         }
       }
  
     public static function generare_factura($order) {
     require_once(base_path("app/Http/Controllers/smartbill/SmartBillCloudRestClient.php"));
      
     $user = Account::where('email',$order->email)->first();
     $username = 'narcis.caius@yahoo.com';
     $token = '43b84ed9e8dedad40cf0cf6d1b1496d3';
     $sbcClient = new \SmartBillCloudRestClient($username, $token);
     $companyVatCode = "33927675";
     $products = [];
       foreach($order->packages as $package){
        array_push($products,[
          'name' 				=> $package->name,
          'code' 				=> "ccd1",
          'isDiscount' 		=> false,
          'currency' 			=> "RON",
          'price' 			=> $package->price,
            'measuringUnitName'   => "buc",
          'isTaxIncluded' 	=> true,
          'taxName' 			=> "Normala",
          'taxPercentage' 	=> 19,
          'isService' 		=> true,
        ]);
       };

       $invoice = array(
         'companyVatCode'  => "33927675",
         'client'          => array(
             'name'      => $user['name'],
             'vatCode'   => $order->cui != null ? $order->cui : '',
             'code'      => "",
             'address'   => $order->address != null ? $order->address : "",
             'regCom'    => $order->reg_com != null ? $order->reg_com : '',
             'isTaxPayer'=> true,
             'contact'   => "",
             'phone'     => "",
             'city'      => "",
             'county'    => "",
             'country'   => "Romania",
             'email'     => $user['email'],
             'bank'      => "",
             'iban'      => "",
             'saveToDb'  => false,
         ),
         'products'        => $products,
         'seriesName' 	=> "APP",
         'isDraft' 		=> false,
         'dueDate' 		=> date('Y-m-d', time() + 14*3600),
         'mentions' 		=> "",
         'observations' 	=> "",
         'deliveryDate' 	=> date('Y-m-d', time() + 1*3600),
     );
     try {
         $output = $sbcClient->createInvoice($invoice); //invoice.Number will be  generated by the server
               $invoiceNumber = $output['number'];
               $invoiceSeries = $output['series'];
     } catch (Exception $ex) {
         echo $ex->getMessage();
     }
     try {
        $invoice = $sbcClient->PDFInvoice($companyVatCode, $invoiceSeries, $invoiceNumber); // file is saved in the specified file 
        if ( !empty($invoice) ) {
              $fullFilename = null;
              $id_client = $order->id_user;
              $path = 'invoices/'.$id_client.'/';
              $filename = 'factura'.date('Y-m-d H:i:s');
              $filesPath = [];
              $filename_counter = 1;
              $disk = Storage::disk('local');
          
               // Make sure the filename does not exist, if it does make sure to add a number to the end 1, 2, 3, etc...
               while ($disk->exists($path.$filename.'.pdf')) {
                 $filename = $filename.'('.(string) ($filename_counter++).')';
               }
               $filename = $filename.'.pdf';
               $disk->put($path.$filename, $invoice);
            
               array_push($filesPath, [
                 'download_link' => $path.$filename,
                 'original_name' => $filename,
               ]);
               $atasament_de_salvat_in_bd_orders = json_encode($filesPath);
              Order::where('id', $order->id)->update(['invoice' => $atasament_de_salvat_in_bd_orders]);
              $order = Order::find($order->id);
              Mail::to($order->email)->send(new TrimiteFactura($order));
          } 
      } catch (Exception $ex) {
 //           dd($ex->getMessage());
       }
   }
  
}