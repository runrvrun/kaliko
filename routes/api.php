<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
no need authorization token
*/
Route::get('login', 'Api\UserController@login');
Route::post('login', 'Api\UserController@login');
Route::post('social-login', 'Api\UserController@socialLogin');
Route::post('register', 'Api\UserController@register');
Route::post('forget-password', 'Api\UserController@forgetPassword');
Route::post('check-otp', 'Api\UserController@checkOtp');
/*
through API guard, must send authorization token
*/
Route::group(['middleware' => 'auth:api'], function() {
    Route::post('refresh-token', 'Api\UserController@refreshToken');
    Route::post('logout', 'Api\UserController@logout');
    Route::post('user/edit', 'Api\UserController@edit');
    Route::post('user/edit-avatar', 'Api\UserController@editAvatar');
});
