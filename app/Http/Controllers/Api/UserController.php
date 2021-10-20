<?php

namespace App\Http\Controllers\Api;

use Auth;
use Socialite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use App\Mail\SendMessageCod;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client as GuzzleClient;
use Validator;
use App\Abonament;
use App\Category;
use App\Course;
use App\Account;
use Carbon\Carbon;
use App\Order;




class UserController extends Controller
{

    public function checkVersion(Request $request){
        if($request->version && $request->version>=5){
            return ['success' => true];

        }else{
            return ['success' => false,'message'=>'Pentru a facilita de toate optiunile aplicatiei, va rugam sa ii faceti update!'];
        }
    }
  
    public function register(Request $request)
    {
        // if($request->email != 'test@test.com'){
        //     return[
        //         'success'=>'false',
        //         'error' => "Ne pare rau, momentan suntem in mentenanta, multumim!",
        //         ];
        // }
        $form_data = $request->only(['name','email', 'password','rePassword','terms', 'platform']);
        $validationRules = [
            'name'      => ['required','min:3'],
            'email'     => ['required', 'email', 'unique:accounts'],
            'password'  => ['required', 'min:6'],
            'rePassword'  => ['required', 'min:6'],
            'terms'    => ['required', 'accepted'],
        ];
        $validationMessages = [
            'name.required'   => "Campul nume este obligatoriu!",
            'name.min'   => "Campul nume trebuie sa contina minim 3 caractere!",
            'email.email'      => "Trebuie sa introduci o adresa de :attribute valida!",
            'email.unique'     => "Exista un cont asociat acestei adrese de email!",
            'email.required'   => "Campul email este obligatoriu!",
            'password.min'      => "Dimensiunea parolei prea mica!",
            'rePassword.min'      => "Dimensiunea parolei prea mica!",
            'password.required' => "Campul parola trebuie completat!",
            'rePassword.required' => "Parola trebuie confirmata!",
            'terms.required' => "Trebuie sa accepti termenii si conditiile!",
            'terms.accepted' => "Trebuie sa accepti termenii si conditiile!",
        ];
        $validator = Validator::make($form_data, $validationRules, $validationMessages);
        if ($validator->fails())
            return ['success' => false, 'error' => $validator->errors()->all()];
        else{
            if($request->input('password') != $request->input('rePassword')){
                return ['success' => false, 'error' => 'Parolele nu se potrivesc!'];  
              }
        }
        // register new user
        $user = new Account;
        // return ['succes'=>false,'error'=>json_encode($user)]
        $user->name    = $form_data['name'];
        $user->platform    = $form_data['platform'] != null ? $form_data['platform'] : 'android';
        $user->email    = $form_data['email'];
        $user->password = Hash::make($form_data['password']);
        
        if (!$user->save())
            return ['success' => false, 'error' =>'unknown'];
        
        $token = $this->generateToken($user->email, $form_data['password']);
        if (!$token)
            return ['success' => false, 'error' => 'unknown'];
        
        return [
            'success' => true,
            'msg'=>'Contul a fost creat',
            'user' => $user,
            'token' => $token,
        ];
    }
  
    public function login(Request $request)
    {

        // if($_SERVER['REMOTE_ADDR'] != '89.35.129.44'){
            
        // }
//         if($request->email != 'test@test.com'){
//             return[
//                 'success'=>'false',
//                 'error' => "Ne pare rau, momentan suntem in mentenanta, multumim!",
//                 ];
//         }
        if (!$request->email)
            return ['success' => false, 'error' => 'Nu ati introdus email-ul!'];
        
        if (!$request->password)
            return ['success' => false, 'error' => 'Nu ati introdus parola!'];
        
        $user = Account::where('email', $request->email)->first();
        if (!$user)
            return ['success' => false, 'error' => 'Nu exista utilizator cu aceasta adresa de email!'];
        
        if ($user->password == 'oauth')
            return ['success' => false, 'error' => 'Aceasta adresa de email s-a inregistrat cu Facebook sau Apple'];
        
        if (!Hash::check($request->password, $user->password))
            return ['success' => false, 'error' => 'Parola nu este corecta!'];
        
        $token = $this->generateToken($user->email, $request->password);
        if (!$token)
            return ['success' => false, 'error' => 'unknown'];
        
        $user->api_address;
        return [
            'success' => true,
            'user' => $user,
            'token' => $token,
            'msg'=>'Logarea a avut loc cu succes!',
        ];
    }
  
   public function facebook(Request $request)
    {
        return $this->socialiteProvider('facebook', $request);
    }
    public function appleLogin(Request $request)
    {
        return $this->socialiteProvider('sign-in-with-apple', $request);
    }
    public function socialiteProvider($provider, $request)
    {
        // return[
        //     'success'=>'false',
        //     'error' => "Ne pare rau, momentan suntem in mentenanta, multumim!",
        //     ];
        $token = $request->access_token;
        if (!$token)
            return ['success' => false, 'error' => 'unknown'];
        
        // if ($request->header('app-version') == '1.0.0') {
        //     $user = Account::find(6);
        // } else {
        try {
            $oauthUser = Socialite::driver($provider)->userFromToken($token);
        }
        catch (\Exception $e) {
            return ['success' => false, 'error' => 'wrong-token','debug'=>$e->getMessage()];
        }
        Logger()->error('apple login user',(array)$oauthUser);
        $user_email = $oauthUser->getEmail();
        if(!$user_email){
            $user_email = $oauthUser->email;
            if(!$user_email){
                return ['success' => false, 'error' => 'Este o problema cu autentificarea folosind acest cont de apple! Reincercati!'];
            }
        }
        
        $user = Account::where('email', $user_email)->first();
        // if($_SERVER['REMOTE_ADDR'] =='89.35.129.44'){
        //     return ['success' => false, 'error' => 'Unknown error','email'=>$user_email];
        // }
        // }
        if (!$user) {
            $user = new Account;
            $user->email     = $user_email;
            $user->password    = 'oauth';
            $user->name      = $oauthUser->getName();
            if (!$user->save())
                return ['success' => false, 'error' => 'Unknown error'];
        }
        
        $token = $this->generateToken($user->email, encrypt('oauth'));
        if (!$token)
            return ['success' => false, 'error' => 'unknown'];
        return [
            'success' => true,
            'user' => $user,
            'token' => $token,
        ];
    }
  
      public static function generateRandomString($length = 90) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    
    public function forgotPassword(Request $request)
    {
        // return[
        //     'success'=>'false',
        //     'error' => "Ne pare rau, momentan suntem in mentenanta, multumim!",
        //     ];
        if (!$request->email)
            return ['success' => false, 'error' => 'Va rugam sa introduceti email-ul!'];
        
        $user = Account::where('email', $request->email)->first();
        if (!$user)
            return ['success' => false, 'error' => 'Nu exista utilizator cu aceasta adresa de email!'];
        
        $codDeTrimis = strtoupper(\App\Http\Controllers\Api\UserController::generateRandomString(3)).rand(100, 999);
        try {
            $user->cod_email_schimbare_parola = $codDeTrimis;
            $user->save();
            Mail::to($user->email)->send(new SendMessageCod($codDeTrimis));
            return [
                'success' => true,
                'msg' => 'Introdu codul primit pe email!',
                'actiune' => 'verifica-cod',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Nu s-a putut modifica parola. Reincercati!',
            ];
        }
    }
    
    public function forgotPasswordVerify(Request $request)
    {
        // return[
        //     'success'=>'false',
        //     'error' => "Ne pare rau, momentan suntem in mentenanta, multumim!",
        //     ];
        if (!$request->email)
              return ['success' => false, 'error' => 'Emailul este obligatoriu!'];
        if (!$request->cod)
            return ['success' => false, 'error' => 'Codul este obligatoriu!'];
        if (!$request->password)
            return ['success' => false, 'error' => 'Ambele parole trebuie completate!'];
        if (!$request->rePassword)
            return ['success' => false, 'error' => 'Ambele parole trebuie completate!'];
        if ($request->password != $request->rePassword)
            return ['success' => false, 'error' => 'Parolele nu se potrivesc!'];
        
        $user = Account::where('email', $request->email)->first();
        if (!$user)
            return ['success' => false, 'error' => 'Nu s-a gasit niciun cont cu aceasta adresa de email!'];
        if ($user->cod_email_schimbare_parola !== $request->cod)
            return ['success' => false, 'error' => 'Codul de verificare este gresit!'];
        
        try {
            $user->cod_email_schimbare_parola = null;
            $user->password = Hash::make($request->password);
            $user->save();
            return [
                'success' => true,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => 'Nu s-a putut modifica parola. Reincercati!'];
        }
    }
    
    public function edit(Request $request)
    {
        $user = Auth::guard('api')->user();
        
        $form_data = $request->only(['name','email']);
        $validationRules = [
            'email'      => ['required', 'email'],
            'name'    => ['required', 'min:6'],
        ];
        $validationMessages = [
            'name.required'    => "Campul nume este obligatoriu!",
            'name.min'    => "Campul nume trebuie sa contina :min caractere!",
            'email.email'    => "Trebuie sa introduci o adresa de :attribute valida!",
            'email.required'    => "Campul email este obligatoriu!",
           
            
        ];
        $validator = Validator::make($form_data, $validationRules, $validationMessages);
        if(!filter_var($request->input('email'), FILTER_VALIDATE_EMAIL)){
           return ['success' => false, 'error' => 'Adresa de email nu respecta formatul standard! Ex: email@email.com'];
        }
        if ($validator->fails())
            return ['success' => false, 'error' => $validator->errors()->all()];
        
        if ($request->email && $user->email != $request->email){
            if (Account::where('id', '!=', $user->id)->where('email', $request->email)->count() > 0)
                return ['success' => false, 'error' => 'Acest email deja se afla in baza noastra de date'];
            $user->email = $request->email;
        }
        if($request->input('password') != $request->input('rePassword')){
            return ['success' => false, 'error' => 'Parolele nu se potrivesc!'];  
        }
        
        if ($request->name && $user->name != $request->name)
            $user->name = $request->name;
        
        if ($request->password && !Hash::check($user->password, $request->password))
            $user->password = Hash::make($request->password);
        
        if ($request->nume && $user->nume != $request->nume)
            $user->nume = $request->nume;
        
        if ($user->isDirty()) {
            if (!$user->save())
                return ['success' => false, 'error' => 'Datele nu au putut fi salvate!'];
        }
        return [
            'success' => true,
            'user' => $user,
        ];
    }
  
//     public static function checkDate(){
//       $user = Auth::guard('api')->user();
//       $currDate = Carbon::now();
//       $currDate = Carbon::parse($currDate)->format('Y-m-d');
//       if($user['exp_date']){
//           $user_exp_date = Carbon::parse($user['exp_date'] )->format('Y-m-d');
//           $message_expired_week =  Carbon::parse($user['exp_date'] )->subDays(7)->format('Y-m-d');
//           $message_expired_day =  Carbon::parse($user['exp_date'] )->subDays(3)->format('Y-m-d');
//           if($user_exp_date < $currDate && $user['id_abonament'] != null){
//             return ['valabil'=>false, 'msg'=>'Pentru a avea acces la următoarele tutoriale trebuie sa va abonați.'];
//             }
//             if($message_expired_day==$currDate && $user['id_abonament'] != null){
//                 return [
//                     'valabil'=>true,
//                     'day'=>true,
//                     'currDate'=>$currDate,
//                 ];
//               }
//               if($message_expired_week==$currDate && $user['id_abonament'] != null){
//                 return [
//                     'valabil'=>true,
//                     'week'=>true,
//                     'currDate'=>$currDate,
//                 ];
//               }
//               else{
//                 return ['valabil'=>true];
//               }

//       }else{
//         $userOrder = Order::where('id_user',$user->id)->orderBy('created_at','DESC')->first();
//         if($userOrder && $userOrder->status=='asteptare'){
//           return['pending'=>true,'valabil'=>true];
//         }
//         return ['valabil'=>false,'$userOrder'=>$userOrder];
//       }        

//     }
  
  
    public static function checkDate(){
      $user = Auth::guard('api')->user();
      $currDate = Carbon::now();
      $currDate = Carbon::parse($currDate)->format('Y-m-d');
      logger()->debug('exp_date',[
        $user['exp_date'],
      ]);
      if($user['exp_date']){
          $user_exp_date = Carbon::parse($user['exp_date'] )->format('Y-m-d');
          $message_expired_week =  Carbon::parse($user['exp_date'] )->subDays(7)->format('Y-m-d');
          $message_expired_day =  Carbon::parse($user['exp_date'] )->subDays(3)->format('Y-m-d');
          if(Carbon::parse($user_exp_date)->timestamp < Carbon::parse($currDate)->timestamp && $user['id_abonament'] != null){
            return ['valabil'=>false, 'msg'=>'Pentru a avea acces la următoarele tutoriale trebuie sa va abonați.'];
            }
            if($message_expired_day==$currDate && $user['id_abonament'] != null){
                return [
                    'valabil'=>true,
                    'day'=>true,
                    'currDate'=>$currDate,
                ];
              }
              if($message_expired_week==$currDate && $user['id_abonament'] != null){
                return [
                    'valabil'=>true,
                    'week'=>true,
                    'currDate'=>$currDate,
                ];
              }
              else{
                return ['valabil'=>true];
              }

      }else{
        $userOrder = Order::where('id_user',$user->id)->orderBy('created_at','DESC')->first();
        if($userOrder && $userOrder->status=='asteptare'){
          return['pending'=>true,'valabil'=>true];
        }
        return ['valabil'=>false,'userOrder'=>$userOrder];
      }        

    }
  
  public function getRecurency(){
    $user = Auth::guard('api')->user();
    return['is_recurency'=>$user->is_recurency];
    
  }
  
   public function setRecurency(Request $request){
    $user = Auth::guard('api')->user();
     $user->is_recurency = $request->input('is_recurency') ? 1 : 0;
     $user->save();
     $message = 'Plata recurenta in aplicatie a fost dezactivata';
     if($user->is_recurency == 1){
       $message = 'Plata recurenta in aplicatie a fost activata';
     }
    return['success'=>true,'message'=>$message];
    
  }
    
  
  
    public function profileImage(Request $request)
    {
        $user = Auth::guard('api')->user();
//         $fileName = date('d-m-Y-H-i-s') . '-' . $request->photo->getClientOriginalName();
//         $request->photo->storeAs('/public/account', $fileName);
      
        $image = \Image::make($request->photo)->encode('jpg', 90);
        $fileName = date('d-m-Y-H-i-s') . '-' . uniqid() . '.jpg';
        \Storage::disk('public')->put('account/'.$fileName, $image->stream());
        $user->profile_image = 'account/' . $fileName;
        $user->save();
        return ['success' => true];
    }
  
  public function abonamente(){
    $abonamente = Abonament::where('id', '1')->get();
    if($_SERVER['REMOTE_ADDR'] == '89.35.129.44'){
      $abonamente = Abonament::get();
    }
    return[
      'abonamente'=>$abonamente,
//       'success'=>true, //nu pot sa vad abonamentele 
      'success'=>false,
      
    ];
  }
    private function generateToken($username, $password)
    {
        $http = new GuzzleClient;
        $response = $http->request('POST', url('/oauth/token'), [
            'allow_redirects' => true,
            'http_errors' => false,
            'form_params' => [
                'grant_type'    => 'password',
                'client_id'     => env('OAUTH_PASSWORD_CLIENT_ID'),
                'client_secret' => env('OAUTH_PASSWORD_CLIENT_SECRET'),
                'username'      => $username,
                'password'      => $password,
                'scope'         => '*',
            ],
        ]);
        return json_decode((string) $response->getBody(), true);
    }
    
    public function checkToken(Request $request)
    {
        // return['logged'=>false,
        // 'user'=>false,];



        $guard = Auth::guard('api');
        $logged = $guard->check();
        $user = false;
        if ($logged) {
            $user = $guard->user();
            $user->api_address;
        }
        return [
            'logged'  => $logged,
            'user'    => $user,
        ];
    }
    
    public function refreshToken(Request $request)
    {
        if (!$request->has('refresh_token'))
            return ['success' => false, 'error' => 'no-token'];
        
        $refresh_token = $request->refresh_token;
        $http = new GuzzleClient;
        $responseObj = $http->request('POST', url('/oauth/token'), [
            'allow_redirects' => true,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'form_params' => [
                'grant_type'    => 'refresh_token',
                'client_id'     => env('OAUTH_PASSWORD_CLIENT_ID'),
                'client_secret' => env('OAUTH_PASSWORD_CLIENT_SECRET'),
                'refresh_token' => $refresh_token,
                'scope'         => '*',
            ],
        ]);
        $response = json_decode((string) $responseObj->getBody(), true);
        if (!$response) return ['success' => false, 'error' => 'unknown'];
        
        if (isset($response['error'])) {
            $return = ['success' => false, 'error' => 'unknown'];
            
            if ($response['error'] === 'invalid_request')
                $return['error'] = 'expired-token';
            
            if ($response['error'] === 'invalid_client') {
                // Sentry::captureException(new Exception('Internal oauth2 server, invalid client error.'), [
                //     'extra' => ['Response' => $response],
                // ]);
            }
            
            return $return;
        }
        
        // Note: action() will return the latest url with this action assigned
        $checkResponse = $http->request('GET', action('Api\UserController@checkToken'), [
            'allow_redirects' => true,
            'http_errors' => false,
            'headers' => [
                'Content-Type'   => 'application/json',
                'Accept'         => 'application/json',
                'Authorization'  => $response['token_type'].' '.$response['access_token'],
            ],
        ]);
        $check = json_decode((string) $checkResponse->getBody(), true);
        
        return [
            'success' => true,
            'token'   => $response,
            'user'    => $check['user'],
        ];
    }
    
}
