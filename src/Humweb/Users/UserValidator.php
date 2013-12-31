<?php namespace Humweb\Users;

use Humweb\Validation\Validation;

class UserValidator extends Validation {

	protected $rules = [
	    // 'default' => [
	    // 	'username' => 'required',
	    //     'password' => 'required',
	    //     'email' => 'required|email'
	    // ],
	    'login' => [
	    	'email'    => 'required|min:4|max:32|email',
			'password' => 'required|min:6'
		],
	    'register' => [
			'email'                 => 'required|min:4|max:32|email|unique:users,email',
			'password'              => 'required|min:6|confirmed',
			'password_confirmation' => 'required'
	    ],
	    'password_change' => [
			'oldPassword'              => 'required|min:6',
			'newPassword'              => 'required|min:6|confirmed',
			'newPassword_confirmation' => 'required'
		],
	    'password_reset' => [
	    	'email' => 'required|min:4|max:32|email'
	    ]
	];
}