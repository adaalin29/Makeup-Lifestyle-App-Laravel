<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Mail\SendMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Validator;

class ContactController extends Controller
{
   public function send_message(Request $request){
      $contact_email = setting('site.contact');
      $form_data = $request->only(['name','email','phone','domain','terms']);
      $validationRules = [
          'name'    => ['required','min:3'],
          'email'   => ['required','email'],         
          'phone'    => ['required','min:3'],
          'domain'    => ['required','min:3'],
          'terms'    => ['required', 'accepted'],
          
      ];
      
//       if(!$request->input['terms']){
//       return ['success' => false,'mesaj'=> 'Te rugam sa accepti termenii si conditiile'];
//      }
      $validationMessages = [
          'name.required'=>'Numele este obligatoriu!',
          'email.required'=>'Te rog sa introduci o adresa de email!',
          'email.email'=>'Te rog sa introduci o adresa de email valida!',
          'phone.required'=>'Te rog sa introduci numarul de telefon!',
          'domain.required'=>'Te rog sa introduci domeniul de activitate!',
          'terms.required'=>'Te rog sa accepti termenii si conditiile!',
          'terms.accepted'=>'Te rog sa accepti termenii si conditiile!',

      ];
     

      $validator = Validator::make($form_data, $validationRules,$validationMessages);
      if ($validator->fails())
          return ['success' => false, 'error' => $validator->errors()->all()];  
      else{ 
          Mail::to(strip_tags($contact_email))->send(new SendMessage($request->all()));

          return ['success' => true,'successMessage'=> 'Mesajul a fost trimis cu succes!'];
      }      
  }
}