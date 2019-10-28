<?php
Route::redirect('/home', '/');

Route::get('/impressum', 'OpenController@impressum')->name('Impressum');

Route::get('/terms', 'OpenController@terms')->name('Terms');

Auth::routes(['register' => false]);

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

Route::group(['middleware' => 'auth'], function () {
    Route::get('/', 'HomeController@index')->name('home');
});
