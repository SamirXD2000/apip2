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
    return $router->app->version();
});

//FOR PUBLIC
$router->group(['prefix' => '1.0/'], function () use ($router) {

	$router->post('prelogin',  'loginController@prelogin'); //Check mail if exists

	$router->post('login',  'loginController@login'); //Login

	$router->post('registeruser',  'loginController@registerUser'); //User registraion

	$router->post('confirmemail',  'loginController@confirmEmail'); //User registraion

	$router->get('foodimages',  'loginController@foodImages'); //User registraion

	$router->get('projects',  'loginController@projects'); //Project List
  
  	$router->get('headers',  'loginController@headers'); //Project List

});

//FOR PRIVATE
$router->group( ['middleware' => 'auth'], function () use ($router) {
    
    $router->post('1.0/user/profile', 'loginController@profile');
	
    $router->post('1.0/user/sendemailconfirmation', 'loginController@sendEmailConfirmation');

    $router->post('1.0/user/confirmphone', 'loginController@confirmPhone');

    $router->post('1.0/user/createnip', 'loginController@createNip');

    $router->post('1.0/user/validatenip', 'loginController@validateNip');

	$router->get('user', function () use ($router) {
        return auth()->user();
    });
});