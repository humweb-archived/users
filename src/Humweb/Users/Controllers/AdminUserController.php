<?php namespace Humweb\Users\Controllers;

use AdminController,
	Input,
	Validator,
	View,
	Sentry,
	Session,
	Redirect,
	Mail;

class AdminUserController extends AdminController {

	protected $sendgrid;
	protected $layout = 'layouts.admin';
	
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
	 * Display a listing of users
	 *
	 * @return Response
	 */
	public function getIndex()
	{

	    // Find the user using the user id
	    $data['user'] = $this->current_user;

	    if ($this->current_user->hasAccess('admin'))
	    {
			$data['userStatus'] = array();
			$data['allUsers']   = Sentry::getUserProvider()->findAll();
	    	foreach ($data['allUsers'] as $user)
	    	{

	    		if ($user->isActivated()) 
	    		{
	    			$data['userStatus'][$user->id] = "Active";
	    		} 
	    		else 
	    		{
	    			$data['userStatus'][$user->id] = "Not Active";
	    		}

	    		//Pull Suspension & Ban info for this user
	    		$throttle = Sentry::getThrottleProvider()->findByUserId($user->id);

	    		//Check for suspension
	    		if($throttle->isSuspended())
			    {
			        // User is Suspended
			        $data['userStatus'][$user->id] = "Suspended";
			    }

	    		//Check for ban
			    if($throttle->isBanned())
			    {
			        // User is Banned
			        $data['userStatus'][$user->id] = "Banned";
			    }

	    	}
	    }

	    $this->setContent('users::admin.users.index', $data);
	}

	/**
	 *  Display this user's details.
	 */
	
	public function getShow($id)
	{
		try
		{
		    //Get the current user's id.
			Sentry::check();
			$currentUser = Sentry::getUser();
		}
		catch (CartalystSentry\UserNotFoundException $e)
		{
		    Session::flash('error', 'There was a problem accessing your account.');
			return Redirect::to('/admin');
		}
		   	//Do they have admin access?
			if ( $currentUser->hasAccess('admin') || $currentUser->getId() == $id)
			{
					$data['user']     = Sentry::getUserProvider()->findById($id);
					$data['myGroups'] = $data['user']->getGroups();
					$this->setContent('users::admin.users.show', $data);
			} else {
				Session::flash('error', 'You don\'t have access to that user.');
				return Redirect::to('/admin');
			}


	}


	/**
	 * Register a new user. 
	 *
	 * @return Response
	 */
	public function getCreate()
	{
		// Show the register form
		$this->setContent('users::admin.users.register');
	}

	public function postCreate() 
	{
	
		// Gather Sanitized Input
		$input = array(
			'email'                 => \Input::get('email'),
			'password'              => \Input::get('password'),
			'password_confirmation' => \Input::get('password_confirmation')
			);

		// Set Validation Rules
		$rules = array (
			'email'                 => 'required|min:4|max:32|email',
			'password'              => 'required|min:6|confirmed',
			'password_confirmation' => 'required'
			);

		//Run input validation
		$v = Validator::make($input, $rules);

		if ($v->fails())
		{
			// Validation has failed
			return Redirect::to('users/register')->withErrors($v)->withInput();
		}
		else 
		{

			try {
				//Attempt to register the user. 
				$user = Sentry::register(array(
					'email' => $input['email'],
					'password' => $input['password']
				));

				//Get the activation code & prep data for email
				$data['activationCode'] = $user->GetActivationCode();
				$data['email']          = $input['email'];
				$data['userId']         = $user->getId();

				//send email with link to activate.
				Mail::send('emails.auth.welcome', $data, function($m) use($data)
				{
				    $m->to($data['email'])->subject('Welcome to Laravel4 With Sentry');
				});

				//success!
		    	Session::flash('success', 'Your account has been created. Check your email for the confirmation link.');
		    	return Redirect::to('/');

			}
			catch (CartalystSentry\Users\LoginRequiredException $e)
			{
			    Session::flash('error', 'Login field required.');
			    return Redirect::to('/admin/users/register')->withErrors($v)->withInput();
			}
			catch (CartalystSentry\Users\UserExistsException $e)
			{
			    Session::flash('error', 'User already exists.');
			    return Redirect::to('/admin/users/register')->withErrors($v)->withInput();
			}

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
		catch (CartalystSentry\Users\UserNotFoundException $e)
		{
		    Session::flash('error', 'User does not exist.');
			return Redirect::to('/');
		}
		catch (CartalystSentry\Users\UserAlreadyActivatedException $e)
		{
		    Session::flash('error', 'You have already activated this account.');
			return Redirect::to('/');
		}


	}


	

	/**
	 * Forgot Password / Reset
	 */
	public function getResetpassword() {
		// Show the change password
		return View::make('users::admin.users.reset');
	}

	public function postResetpassword () {
		// Gather Sanitized Input
		$input = array(
			'email' => Input::get('email')
			);

		// Set Validation Rules
		$rules = array (
			'email' => 'required|min:4|max:32|email'
			);

		//Run input validation
		$v = Validator::make($input, $rules);

		if ($v->fails())
		{
			// Validation has failed
			return Redirect::to('users/resetpassword')->withErrors($v)->withInput();
		}
		else 
		{
			try
			{
				$user              = Sentry::getUserProvider()->findByLogin($input['email']);
				$data['resetCode'] = $user->getResetPasswordCode();
				$data['userId']    = $user->getId();
				$data['email']     = $input['email'];

			    // Email the reset code to the user
				Mail::send('emails.auth.reset', $data, function($m) use($data)
				{
				    $m->to($data['email'])->subject('Password Reset Confirmation | Laravel4 With Sentry');
				});

				Session::flash('success', 'Check your email for password reset information.');
			    return Redirect::to('/');

			}
			catch (CartalystSentry\Users\UserNotFoundException $e)
			{
			    echo 'User does not exist';
			}
		}

	}


	/**
	 * Reset User's password
	 */
	public function getReset($userId = null, $resetCode = null) {
		try
		{
		    // Find the user
		    $user = Sentry::getUserProvider()->findById($userId);
		    $newPassword = $this->_generatePassword(8,8);

		    // Attempt to reset the user password
		    if ($user->attemptResetPassword($resetCode, $newPassword))
		    {
		        // Password rescoet passed
		        // 
		        // Email the reset code to the user

			    //Prepare New Password body
				$data['newPassword'] = $newPassword;
				$data['email']       = $user->getLogin();

			    Mail::send('emails.auth.newpassword', $data, function($m) use($data)
				{
				    $m->to($data['email'])->subject('New Password Information | Laravel4 With Sentry');
				});

				Session::flash('success', 'Your password has been changed. Check your email for the new password.');
			    return Redirect::to('/');
		        
		    }
		    else
		    {
		        // Password reset failed
		    	Session::flash('error', 'There was a problem.  Please contact the system administrator.');
			    return Redirect::to('users/resetpassword');
		    }
		}
		catch (CartalystSentry\Users\UserNotFoundException $e)
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
		catch (CartalystSentry\Users\UserNotFoundException $e)
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
			$currentUser = Sentry::getUser();

		   	//Do they have admin access?
			if ( $currentUser->hasAccess('admin'))
			{
				$data['user']       = Sentry::getUserProvider()->findById($id);
				$data['userGroups'] = $data['user']->getGroups();
				$data['allGroups']  = Sentry::getGroupProvider()->findAll();

				$this->setContent(View::make('users::admin.users.edit')->with($data));
			} 
			elseif ($currentUser->getId() == $id)
			{
				//They are not an admin, but they are viewing their own profile.
				$data['user']       = Sentry::getUserProvider()->findById($id);
				$data['userGroups'] = $data['user']->getGroups();

				return View::make('users::admin.users.edit')->with($data);
			} else {
				Session::flash('error', 'You don\'t have access to that user.');
				return Redirect::to('/');
			}

		}
		catch (CartalystSentry\UserNotFoundException $e)
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
			return Redirect::to('admin/users/edit/' . $id)->withErrors($v)->withInput();
		}
		else 
		{
			try
			{
				//Get the current user's id.
				Sentry::check();
				$currentUser = Sentry::getUser();

			   	//Do they have admin access?
				if ( $currentUser->hasAccess('admin')  || $currentUser->getId() == $id)
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
				        Session::flash('success', 'Your user info has been changed.');
						return Redirect::to('admin/users/show/'. $id);
				    }
				    else
				    {
				        // User information was not updated
				        Session::flash('error', 'Your user info could not be changed.');
						return Redirect::to('admin/users/edit/' . $id);
				    }

				} else {
					Session::flash('error', 'You don\'t have access to that user.');
					return Redirect::to('/');
				}			   			    
			}
			catch (CartalystSentry\Users\UserExistsException $e)
			{
			    Session::flash('error', 'User already exists.');
				return Redirect::to('admin/users/edit/' . $id);
			}
			catch (CartalystSentry\Users\UserNotFoundException $e)
			{
			    Session::flash('error', 'User was not found.');
				return Redirect::to('admin/users/edit/' . $id);
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
			'newPassword'              => Input::get('newPassword'),
			'newPassword_confirmation' => Input::get('newPassword_confirmation')
		];

		// Set Validation Rules
		$rules = [
			'newPassword'              => 'required|min:6|confirmed',
			'newPassword_confirmation' => 'required'
			];

		// Admin doesn't need old password
		if ( ! $this->current_user->hasAccess('admin'))
		{
			$input['oldPassword'] = Input::get('oldPassword');
			$rules['oldPassword'] = 'required|min:6';
		}

		//Run input validation
		$v = Validator::make($input, $rules);

		if ($v->fails())
		{
			// Validation has failed
			return Redirect::to('admin/users/edit/' . $id)->withErrors($v)->withInput();
		}
		else 
		{
			try
			{
			    
				//Get the current user's id.
				Sentry::check();
				$currentUser = $this->current_user;

			   	//Do they have admin access?
				if ( $currentUser->hasAccess('admin') or $currentUser->getId() == $id)
				{
					// Either they are an admin, or they are changing their own password. 
					$user = Sentry::getUserProvider()->findById($id);

					if ($currentUser->hasAccess('admin') or $user->checkHash($input['oldPassword'], $user->getPassword())) 
			    	{
				    	//The oldPassword matches the current password in the DB. Proceed.
				    	$user->password = $input['newPassword'];

				    	if ($user->save())
					    {
					        // User saved
					        Session::flash('success', 'Your password has been changed.');
							return Redirect::to('/admin/users'. $id);
					    }
					    else
					    {
					        // User not saved
					        Session::flash('error', 'Your password could not be changed.');
							return Redirect::to('/admin/users/edit/' . $id);
					    }
					} else {
						// The oldPassword did not match the password in the database. Abort. 
						Session::flash('error', 'You did not provide the correct password.');
						return Redirect::to('/admin/users/edit/' . $id);
					}
				} else {
					Session::flash('error', 'You don\'t have access to that user.');
					return Redirect::to('/admin/users');
				}			   			    
			}
			catch (CartalystSentry\Users\LoginRequiredException $e)
			{
			    Session::flash('error', 'Login field required.');
				return Redirect::to('/admin/users/edit/' . $id);
			}
			catch (CartalystSentry\Users\UserExistsException $e)
			{
			    Session::flash('error', 'User already exists.');
				return Redirect::to('/admin/users/edit/' . $id);
			}
			catch (CartalystSentry\Users\UserNotFoundException $e)
			{
			    Session::flash('error', 'User was not found.');
				return Redirect::to('/admin/users/edit/' . $id);
			}
		}
	}

	/**
	 * Process changes to user's group memberships
	 * @param  int 		$id The affected user's id
	 * @return [type]     [description]
	 */
	public function postUpdatememberships($id)
	{
		try 
		{
			//Get the current user's id.
			Sentry::check();
			$currentUser = Sentry::getUser();

		   	//Do they have admin access?
			if ( $currentUser->hasAccess('admin'))
			{
				$user        = Sentry::getUserProvider()->findById($id);
				$allGroups   = Sentry::getGroupProvider()->findAll();
				$permissions = Input::get('permissions');
				
				$statusMessage = '';
				foreach ($allGroups as $group) {
					
					if (isset($permissions[$group->id])) 
					{
						//The user should be added to this group
						if ($user->addGroup($group))
					    {
					        $statusMessage .= "Added to " . $group->name . "<br />";
					    }
					    else
					    {
					        $statusMessage .= "Could not be added to " . $group->name . "<br />";
					    }
					} else {
						// The user should be removed from this group
						if ($user->removeGroup($group))
					    {
					        $statusMessage .= "Removed from " . $group->name . "<br />";
					    }
					    else
					    {
					        $statusMessage .= "Could not be removed from " . $group->name . "<br />";
					    }
					}

				}
				Session::flash('info', $statusMessage);
				return Redirect::to('admin/users/edit/'. $id);
			} 
			else 
			{
				Session::flash('error', 'You don\'t have access to that user.');
				return Redirect::to('/');
			}
	
		}
		catch (CartalystSentry\Users\UserNotFoundException $e)
		{
		    Session::flash('error', 'User was not found.');
			return Redirect::to('/admin/users/edit/' . $id);
		}
		catch (CartalystSentry\Groups\GroupNotFoundException $e)
		{
		    Session::flash('error', 'Trying to access unidentified Groups.');
			return Redirect::to('/admin/users/edit/' . $id);
		}
	}


	/**
	 * Prepare the "Ban User" form
	 * @param  int $id The user id
	 * @return View     The "Ban Form" view
	 */
	public function getSuspend($id)
	{
		try
		{
		    //Get the current user's id.
			Sentry::check();
			$currentUser = Sentry::getUser();

		   	//Do they have admin access?
			if ( $currentUser->hasAccess('admin'))
			{
				$data['user'] = Sentry::getUserProvider()->findById($id);

				$this->setContent('users::admin.users.suspend', $data);
			} else {
				Session::flash('error', 'You are not allowed to do that.');
				return Redirect::to('/');
			}

		}
		catch (CartalystSentry\UserNotFoundException $e)
		{
		    Session::flash('error', 'There was a problem accessing that user\s account.');
			return Redirect::to('/admin/users');
		}
	}

	public function postSuspend($id)
	{
		// Gather Sanitized Input
		$input = array(
			'suspendTime' => Input::get('suspendTime')
			);

		// Set Validation Rules
		$rules = array (
			'suspendTime' => 'required|numeric'
			);

		//Run input validation
		$v = Validator::make($input, $rules);

		if ($v->fails())
		{
			// Validation has failed
			return Redirect::to('/admin/users/suspend/' . $id)->withErrors($v)->withInput();
		}
		else 
		{
			try
			{
				//Prep for suspension
				$throttle = Sentry::getThrottleProvider()->findByUserId($id);

				//Set suspension time
				$throttle->setSuspensionTime($input['suspendTime']);

				// Suspend the user
    			$throttle->suspend();

    			//Done.  Return to users page.
    			Session::flash('success', "User has been suspended for " . $input['suspendTime'] . " minutes.");
				return Redirect::to('/admin/users');

			}
			catch (CartalystSentry\UserNotFoundException $e)
			{
			    Session::flash('error', 'There was a problem accessing that user\s account.');
				return Redirect::to('/admin/users');
			}
		}
	}


	public function getUnsuspend($id)
	{

		try
		{
			//Prep for suspension
			$throttle = Sentry::getThrottleProvider()->findByUserId($id);

			// Unsuspend the user
			$throttle->unsuspend();

			//Done.  Return to users page.
			Session::flash('success', "User has been removed from suspention.");
			return Redirect::to('/admin/users');

		}
		catch (CartalystSentry\UserNotFoundException $e)
		{
		    Session::flash('error', 'There was a problem accessing that user\s account.');
			return Redirect::to('/admin/users');
		}
	}


	public function postDelete($id)
	{
		try
		{
		    // Find the user using the user id
		    $user = Sentry::getUserProvider()->findById($id);

		    // Delete the user
		    if ($user->delete())
		    {
		        // User was successfully deleted
		        Session::flash('success', 'That user has been deleted.');
				return Redirect::to('/admin/users');
		    }
		    else
		    {
		        // There was a problem deleting the user
		        Session::flash('error', 'There was a problem deleting that user.');
				return Redirect::to('/admin/users');
		    }
		}
		catch (CartalystSentry\Users\UserNotFoundException $e)
		{
		    Session::flash('error', 'There was a problem accessing that user\s account.');
			return Redirect::to('/admin/users');
		}
	}

	/**
	 * Generate password - helper function
	 * From http://www.phpscribble.com/i4xzZu/Generate-random-passwords-of-given-length-and-strength
	 * 
	 */
	
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

	public function getListusers()
	{
		return \DB::table('users')->select('id', 'email')->where('email','LIKE', \Input::get('email').'%')->get();

	}
}
