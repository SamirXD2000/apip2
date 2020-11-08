<?php
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    
});


$router->get('1.0/foodimages',  'loginController@foodImages'); //User registraion

$router->group( ['middleware' => 'auth'], function () use ($router) {

    //$router->get('1.0/foodimages',  'loginController@foodImages'); //User registraion

    //VALIDATE API TOKEN ON HEADERS
	$router->get('user', function () use ($router) {
        return auth()->user();
    });
});