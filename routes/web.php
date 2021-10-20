<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/return-order', function () {
  echo ' <meta name="viewport" content="width=device-width, initial-scale=1">';
    echo "<body style = 'display:flex;height:100vh;justify-content:center;align-items:center;padding:40px'><h1 style = 'text-align:center;justify-content:center;'>Please wait<h1>";
});

 Route::get('/privacy', 'PrivacyController@index');

 Route::post('/confirm-order', 'Api\PaymentController@confirm_order');
 Route::get('/confirm-order', 'Api\PaymentController@confirm_order');
 Route::post('/return-order', 'Api\PaymentController@confirm_order');
 Route::get('/check', 'Controller@checkSmartBill');
 Route::get('/trimite', 'Api\PaymentController@sendInvoice');

  Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();
    Route::get('view_invoice/{order_id}', ['uses' => 'VoyagerOrderController@view_invoice'])->middleware('admin.user');
  });
