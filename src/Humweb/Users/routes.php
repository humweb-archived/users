<?php

Route::get('login', [
	'as'   => 'get.login',
	'uses' => 'UserController@getLogin'
]);

Route::post('login', [
	'as'   => 'post.login',
	'uses' => 'UserController@postLogin'
]);

Route::get('logout', [
	'as'   => 'logout',
	'uses' => 'UserController@getLogout'
]);

Route::get('signup', [
	'as'   => 'get.signup',
	'uses' => 'UserController@getSignup'
]);

Route::post('signup', [
	'as'   => 'post.signup',
	'uses' => 'UserController@postSignup'
]);

Route::get('activate', [
	'as'   => 'get.activate',
	'uses' => 'UserController@getActivate'
]);

Route::group(array('prefix' => 'resetpassword'), function()
{
	Route::get('{userId}/{resetCode}', [
		'as'   => 'get.password_reset',
		'uses' => 'UserController@postPasswordreset'
	]);

	Route::get('/', [
		'as'   => 'get.reset_password',
		'uses' => 'UserController@getResetpassword'
	]);

	Route::post('/', [
		'as'   => 'post.reset_password',
		'uses' => 'UserController@postResetpassword'
	]);
});


Route::get('edit', [
	'as'   => 'get.user.edit',
	'uses' => 'UserController@getEdit'
]);

Route::post('edit', [
	'as'   => 'post.user.edit',
	'uses' => 'UserController@postEdit'
]);