<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Storage;

class StaticeController extends Controller
{
  public function termeni(){
    
    return setting('site.termeni');
  }
  public function backgroundImage(){
    
    return [
      'background'=>Storage::disk('public')->url(setting('site.background')),
      'dashboard'=>Storage::disk('public')->url(setting('site.dashboard')),
      'login'=>Storage::disk('public')->url(setting('site.login')),
      'forgot'=>Storage::disk('public')->url(setting('site.forgot')),
      'register'=>Storage::disk('public')->url(setting('site.register')),
    ];
//     return setting('site.background');
  }
}