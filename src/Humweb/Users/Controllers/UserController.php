<?php namespace Humweb\Users\Controllers;

use Event, View, Input, Session, Redirect, Validator, Sentry;

use Humweb\Core\Controllers\FrontController;
use Humweb\Users\UserValidator;

use Cartalyst\Sentry\UserNotFoundException;
use Cartalyst\Sentry\Users\LoginRequiredException;
use Cartalyst\Sentry\Users\UserExistsException;
use Cartalyst\Sentry\Users\UserAlreadyActivatedException;
use Cartalyst\Sentry\Users\PasswordRequiredException;
use Cartalyst\Sentry\Users\WrongPasswordException;
use Cartalyst\Sentry\Users\UserNotActivatedException;
use Cartalyst\Sentry\Throttling\UserSuspendedException;
use Cartalyst\Sentry\Throttling\UserBannedException;

class UserController extends FrontController {

	protected $user;

	/**
	 * Instantiate a new UserController
	 */
	public function __construct()
	{
		parent::__construct();

		//Check CSRF token on POST
		$this->beforeFilter('csrf', array('on' => 'post'));
		$this->beforeFilter('auth', array('except' => array('getLogin', 'postLogin', 'getRegister', 'postRegister')));

		
	}

	/**
	 * Display a user profile
	 *
	 * @return Response
	 */
	public function getIndex()
	{
		return $this->getShow();
	}

	/**
	 *  Display this user's details.
	 */
	
	public function getShow($id = null)
	{
	    //Get the current user's id.
		$id = $id ?: $this->user->getId();

	   	//Do they have admin access?
		if ($this->user->hasAccess('admin') or $this->user->getId() == $id)
		{
			//Either they are an admin, or:
			//They are not an admin, but they are viewing their own profile.
			$data['user'] = ($this->user->getId() == $id)
				? $this->user
				: Sentry::getUserProvider()->findById($id);

			$data['myGroups'] = $data['user']->getGroups();

			$this->setContent(View::make('users::users.show')->with($data));
		}
		else {
			Session::flash('error', 'You don\'t have access to that user\'s profile.');
			return Redirect::to('/');
		}
	}


	/**
	 * Register a new user. 
	 *
	 * @return Response
	 */
	public function getRegister()
	{
		// Show the register form
		return View::make('users::users.register');
	}

	public function postRegister() 
	{
	
		// Gather Sanitized Input
		$input = array(
			'email'                 => Input::get('email'),
			'password'              => Input::get('password'),
			'password_confirmation' => Input::get('password_confirmation')
			);

		$v = UserValidator::make($input, 'register');

		if ( ! $v->passes())
		{
			// Validation has failed
			return Redirect::route('get.signup')->withErrors($v)->exceptInput('password');
		}
		else 
		{
			unset($input['password_confirmation']);

			//Attempt to register the user. 
			$user = Sentry::register($input);

			//Get the activation code & prep data for email
			$data['activationCode'] = $user->GetActivationCode();
			$data['email']          = $input['email'];
			$data['userId']         = $user->getId();
				
			Event::fire('user.registered', compact('user', 'data'));

			//success!
			Session::flash('success', 'Your account has been created. Check your email for the confirmation link.');
			return Redirect::to('/');
		}
	}

	/**
	 * Activate a new User
	 */
	public function getActivate($userId = null, $activationCode = null) {
		try 
		{
		    // Find the user
		    $user = Sentry::getUserProvider()->findById($userId);

		    // Attempt user activation
		    if ($user->attemptActivation($activationCode))
		    {
		        // User activation passed
		        
		    	//Add this person to the user group. 
		    	$userGroup = Sentry::getGroupProvider()->findById(1);
		    	$user->addGroup($userGroup);

		    	Event::fire('user.activated', $data);
		        
		        Session::flash('success', 'Your account has been activated. <a href="/users/login">Click here</a> to log in.');
				return Redirect::to('/');
		    }
		    else
		    {
		        // User activation failed
		        Session::flash('error', 'There was a problem activating this account. Please contact the system administrator.');
				return Redirect::to('/');
		    }
		}
		catch (UserNotFoundException $e)
		{
		    Session::flash('error', 'User does not exist.');
			return Redirect::to('/');
		}
		catch (UserAlreadyActivatedException $e)
		{
		    Session::flash('info', 'You have already activated this account.');
			return Redirect::to('/');
		}
	}

	/**
	 * Login
	 *
	 * @return Response
	 */
	public function getLogin()
	{

		// Show the register form
		return View::make('users::users.login');
	}

	public function postLogin() 
	{
		/**
		 * Throttle logic
		 */
		// Gather Sanitized Input
		$input = array(
			'email'      => Input::get('email'),
			'password'   => Input::get('password'),
		);

		// Set Validation Rules
		$rules = array (
			'email'    => 'required|min:4|max:32|email',
			'password' => 'required|min:6'
		);

		//Run input validation
		$v = UserValidator::make($input, 'login');
		if ( ! $v->passes())
		{
			Event::fire('user.login.failed', $data);

			// Validation has failed
			return Redirect::to('users/login')->withErrors($v)->exceptInput('password');
		}
		else
		{
		    //Get User
			try
			{
			    $user = Sentry::authenticate($input, Input::get('rememberMe'));
			}

			catch (\Cartalyst\Sentry\Users\UserNotActivatedException $e)
			{
				Session::flash('error', 'You have not yet activated this account.');
				return Redirect::back()->withErrors($v)->exceptInput('password');
			}
			catch (\Cartalyst\Sentry\Throttling\UserSuspendedException $e)
			{
				$time = $UserThrottle->getSuspensionTime();
				Session::flash('error', "Your account has been suspended for $time minutes.");
				return Redirect::back()->withErrors($v)->exceptInput('password');
			}
			catch (\Cartalyst\Sentry\Throttling\UserBannedException $e)
			{
				Session::flash('error', 'You have been banned.');
				return Redirect::back()->withErrors($v)->exceptInput('password');
			}

			catch (\Exception $e)
			{
				if ($e instanceof \Cartalyst\Sentry\Users\UserNotFoundException or 
					$e instanceof \Cartalyst\Sentry\Users\WrongPasswordException)
				{
					$v->errors()->add('username-password', 'Could not login with the username or password given.');
					return Redirect::back()->withErrors($v)->exceptInput('password');
				}
				else {
					Session::flash('error', 'Internal error logging in.');
					return Redirect::back()->withErrors($v)->exceptInput('password');
				}
			}

			Event::fire('user.logged.in', $user);
			return Redirect::to('/');
		}
	}

	/**
	 * Logout
	 */
	
	public function getLogout() 
	{
		$user = Sentry::getUser();
		Sentry::logout();
		Event::fire('user.logged.out', $user);
		return Redirect::to('/');
	}


	

	/**
	 * Forgot Password / Reset
	 */
	public function getResetpassword()
	{
		// Show the change password
		return View::make('users::users.reset');
	}

	public function postResetpassword () {
		// Gather Sanitized Input
		$input = ['email' => Input::get('email')];


		//Run input validation
		$v = UserValidator::make($input, 'password_reset');

		if ( ! $v->passes())
		{
			Event::fire('user.reset.failed', $data);
			// Validation has failed
			return Redirect::route('get.reset_password')->withErrors($v)->withInput();
		}
		else 
		{
			try
			{
				$user              = Sentry::getUserProvider()->findByLogin($input['email']);
				$data['resetCode'] = $user->getResetPasswordCode();
				$data['userId']    = $user->getId();
				$data['email']     = $input['email'];

				Event::fire('user.reset.success', $data);

			    // Email the reset code to the user
				Mail::send('emails.auth.reset', $data, function($m) use($data)
				{
				    $m->to($data['email'])->subject('Password Reset Confirmation');
				});

				Session::flash('success', 'Check your email for password reset information.');
			    return Redirect::to('/');

			}
			catch (UserNotFoundException $e)
			{
			    echo 'User does not exist';
			}
		}

	}


	/**
	 * Reset User's password
	 */
	public function getPasswordreset($userId = null, $resetCode = null) {
		try
		{
		    // Find the user
		    $user = Sentry::getUserProvider()->findById($userId);
		    $newPassword = $this->_generatePassword(8,8);

		    // Attempt to reset the user password
		    if ($user->attemptResetPassword($resetCode, $newPassword))
		    {

		        // Email the reset code to the user

			    // Prepare email data
				$data['newPassword'] = $newPassword;
				$data['email']       = $user->getLogin();

			    Mail::send('emails.auth.newpassword', $data, function($m) use($data)
				{
				    $m->to($data['email'])->subject('New Password Information | Laravel4 With Sentry');
				});

				Session::flash('success', 'Your password has been changed. Check your email for the new password.');
			    return Redirect::to('/');
		    }
		    else {
		        // Password reset failed
		    	Session::flash('error', 'There was a problem.  Please contact the system administrator.');
			    return Redirect::to('users/resetpassword');
		    }
		}
		catch (UserNotFoundException $e)
		{
		    echo 'User does not exist.';
		}
	}


	public function getClearreset($userId = null) {
		try
		{
		    // Find the user
		    $user = Sentry::getUserProvider()->findById($userId);

		    // Clear the password reset code
		    $user->clearResetPassword();

		    echo "clear.";
		}
		catch (UserNotFoundException $e)
		{
		    echo 'User does not exist';
		}
	}


	/**
	 *  Edit / Update User Profile
	 */
	
	public function getEdit($id) 
	{
		try
		{
		    //Get the current user's id.
			Sentry::check();
			

		   	//Do they have admin access?
			if ( $this->user->hasAccess('admin'))
			{
				$data['user']       = Sentry::getUserProvider()->findById($id);
				$data['userGroups'] = $data['user']->getGroups();
				$data['allGroups']  = Sentry::getGroupProvider()->findAll();

				$this->setContent(View::make('users::users.edit')->with($data));
			} 
			elseif ($this->user->getId() == $id)
			{
				//They are not an admin, but they are viewing their own profile.
				$data['user']       = Sentry::getUserProvider()->findById($id);
				$data['userGroups'] = $data['user']->getGroups();

				return View::make('users::users.edit')->with($data);
			} else {
				Session::flash('error', 'You don\'t have access to that user.');
				return Redirect::to('/');
			}

		}
		catch (UserNotFoundException $e)
		{
		    Session::flash('error', 'There was a problem accessing your account.');
			return Redirect::to('/');
		}
	}


	public function postEdit($id) {
		// Gather Sanitized Input
		$input = array(
			'firstName' => Input::get('firstName'),
			'lastName'  => Input::get('lastName')
			);

		// Set Validation Rules
		$rules = array (
			'firstName' => 'alpha',
			'lastName'  => 'alpha',
			);

		//Run input validation
		$v = Validator::make($input, $rules);

		if ($v->fails())
		{
			// Validation has failed
			return Redirect::to('users/edit/' . $id)->withErrors($v)->withInput();
		}
		else 
		{
			try
			{
				//Get the current user's id.
				Sentry::check();
				

			   	//Do they have admin access?
				if ( $this->user->hasAccess('admin')  || $this->user->getId() == $id)
				{
					// Either they are an admin, or they are changing their own password. 
					// Find the user using the user id
					$user = Sentry::getUserProvider()->findById($id);	
					
				    // Update the user details
					$user->first_name = $input['firstName'];
					$user->last_name  = $input['lastName'];

				    // Update the user
				    if ($user->save())
				    {
				        // User information was updated
				        Session::flash('success', 'Your password has been changed.');
						return Redirect::to('users/show/'. $id);
				    }
				    else
				    {
				        // User information was not updated
				        Session::flash('error', 'Your password could not be changed.');
						return Redirect::to('users/edit/' . $id);
				    }

				} else {
					Session::flash('error', 'You don\'t have access to that user.');
					return Redirect::to('/');
				}			   			    
			}
			catch (UserExistsException $e)
			{
			    Session::flash('error', 'User already exists.');
				return Redirect::to('users/edit/' . $id);
			}
			catch (UserNotFoundException $e)
			{
			    Session::flash('error', 'User was not found.');
				return Redirect::to('users/edit/' . $id);
			}
		}
	}

	/**
	 * Process changepassword form. 
	 * @param  [type] $id [description]
	 * @return [type]     [description]
	 */
	public function postChangepassword($id) 
	{
		// Gather Sanitized Input
		$input = [
			'oldPassword'              => Input::get('oldPassword'),
			'newPassword'              => Input::get('newPassword'),
			'newPassword_confirmation' => Input::get('newPassword_confirmation')
		];

		//Run input validation
		$v = UserValidator::make($input, 'password_change');

		if ( ! $v->passes())
		{
			// Validation has failed
			return Redirect::to('users/edit/' . $id)->withErrors($v)->withInput();
		}
		else 
		{
			try
			{
			    
				//Get the current user's id.
				Sentry::check();
				

			   	//Do they have admin access?
				if ( $this->user->hasAccess('admin')  || $this->user->getId() == $id)
				{
					// Either they are an admin, or they are changing their own password. 
					$user = Sentry::getUserProvider()->findById($id);	
					if ($user->checkHash($input['oldPassword'], $user->getPassword())) 
			    	{
				    	//The oldPassword matches the current password in the DB. Proceed.
				    	$user->password = $input['newPassword'];

				    	if ($user->save())
					    {
					        // User saved
					        Session::flash('success', 'Your password has been changed.');
							return Redirect::to('users/show/'. $id);
					    }
					    else
					    {
					        // User not saved
					        Session::flash('error', 'Your password could not be changed.');
							return Redirect::to('users/edit/' . $id);
					    }
					} else {
						// The oldPassword did not match the password in the database. Abort. 
						Session::flash('error', 'You did not provide the correct password.');
						return Redirect::to('users/edit/' . $id);
					}
				} else {
					Session::flash('error', 'You don\'t have access to that user.');
					return Redirect::to('/');
				}			   			    
			}
			catch (LoginRequiredException $e)
			{
			    Session::flash('error', 'Login field required.');
				return Redirect::to('users/edit/' . $id);
			}
			catch (UserExistsException $e)
			{
			    Session::flash('error', 'User already exists.');
				return Redirect::to('users/edit/' . $id);
			}
			catch (UserNotFoundException $e)
			{
			    Session::flash('error', 'User was not found.');
				return Redirect::to('users/edit/' . $id);
			}
		}
	}

	private function _generatePassword($length=9, $strength=4) {
		$vowels = 'aeiouy';
		$consonants = 'bcdfghjklmnpqrstvwxz';
		if ($strength & 1) {
			$consonants .= 'BCDFGHJKLMNPQRSTVWXZ';
		}
		if ($strength & 2) {
			$vowels .= "AEIOUY";
		}
		if ($strength & 4) {
			$consonants .= '23456789';
		}
		if ($strength & 8) {
			$consonants .= '@#$%';
		}
	 
		$password = '';
		$alt = time() % 2;
		for ($i = 0; $i < $length; $i++) {
			if ($alt == 1) {
				$password .= $consonants[(rand() % strlen($consonants))];
				$alt = 0;
			} else {
				$password .= $vowels[(rand() % strlen($vowels))];
				$alt = 1;
			}
		}
		return $password;
	}

}
