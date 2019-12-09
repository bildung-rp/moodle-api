<?php

use Illuminate\Http\Request;

Route::group([
    'prefix' => 'v1',
    'namespace' => 'Api\V1'
        ], function () {
    Route::get('about', 'AboutApiController@index');
});
/**
 * Auth Routes
 */
Route::group([
    'prefix' => 'v1',
    'as' => 'auth.',
    'namespace' => 'Api\V1\Auth'
        ], function () {

    Route::post('login', 'AuthController@login');
    Route::post('signup', 'AuthController@signup');

    Route::group([
        'middleware' => 'api'
            ], function() {
        Route::get('logout', 'AuthController@logout');
        Route::get('user', 'AuthController@user');
    });
});


Route::group([
    'prefix' => 'v1',
    'as' => 'admin.',
    'namespace' => 'Api\V1\Admin',
    'middleware' => 'api'
        ], function () {

    Route::delete('instances/expel', 'InstancesApiController@expel');
    Route::apiResource('instances', 'InstancesApiController');
});
