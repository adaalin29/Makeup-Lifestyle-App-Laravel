<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::group(['namespace' => 'Api'], function() {
    Route::prefix('/user')->group(function() {
        Route::get('/check',                   'UserController@checkToken');
        Route::post('/refresh',                'UserController@refreshToken');
        Route::post('/forgot-password',        'UserController@forgotPassword');
        Route::post('/forgot-password-verify', 'UserController@forgotPasswordVerify');
        // Route::post('/facebook',               'UserController@facebook');
        Route::post('/apple',                  'UserController@appleLogin');
        Route::post('/register', 'UserController@register');
      
        Route::middleware('auth:api')->group(function () {
            Route::post('/edit', 'UserController@edit');
            Route::post('/profileImage', 'UserController@profileImage');
            Route::get('/abonamente', 'UserController@abonamente');
            Route::get('/checkdate', 'UserController@checkDate');
            Route::get('/getRecurency', 'UserController@getRecurency');
            Route::post('/setRecurency', 'UserController@setRecurency');
            Route::post('/save-receipt', 'PaymentController@save_hash_apple');
            Route::post('/check-validate-ios', 'PaymentController@check_validate_ios');
            
            
        });
        
    });
  Route::middleware('auth:api')->group(function () {
    Route::get('/categories', 'CourseController@categories');
    Route::get('/courses', 'CourseController@courses');
    Route::get('/freeCourse', 'CourseController@freeCourse');
    Route::get('/getSubcategories', 'CourseController@getSubcategories');
    Route::get('/listingCourses', 'CourseController@listingCourses');
    Route::get('/course/{id}', 'CourseController@courseDetail');
    Route::get('/freeCourseDetail', 'CourseController@freeCourseDetail');

    Route::any('/packages', 'CourseController@packages');
    Route::post('/user-packages', 'CourseController@userPackages');
  });
  
    Route::post('/check-version','UserController@checkVersion');
    Route::get('/termeni', 'StaticeController@termeni');
    Route::get('/background', 'StaticeController@backgroundImage');
    Route::post('/contact', 'ContactController@send_message');
    Route::post('/login',                  'UserController@login');
    Route::post('/forgot-password',        'UserController@forgotPassword');
    Route::post('/forgot-password-verify', 'UserController@forgotPasswordVerify');
  
    Route::post('/cumparaPachete', 'PaymentController@sendOrder');
    Route::post('/genereaza-formular', 'PaymentController@genereaza_formular');
    Route::post('/comanda-card-raspuns', 'PaymentController@comandaCardRaspuns');
});