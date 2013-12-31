<?php namespace Humweb\Users\Controllers;

use AdminController,
	View,
	Sentry,
	Input,
	Validator,
	Session,
	Redirect,
	Permission;

class GroupController extends AdminController {

	/**
	 * Constructor
	 */
	
	public function __construct() 
	{
		$this->beforeFilter('admin_auth');

	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function getIndex()
	{

	    $user = Sentry::getUser();

	    // Get the user groups
	    $data['myGroups']  = $user->getGroups();

	    //Get all the available groups.
	    $data['allGroups'] = Sentry::getGroupProvider()->findAll();
		return View::make('users::admin.groups.index', $data);
		
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	
	public function create()
	{
		//Form for creating a new Group
		return View::make('users::admin.groups.create', array('availablePerms' => Permission::getWithNs()));
	}



	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		//Store the new group in the db. 
		//Start with Data Validation
		// Gather Sanitized Input
		$input = array(
			'newGroup' => Input::get('newGroup')
			);

		// Set Validation Rules
		$rules = array (
			'newGroup' => 'required|min:4'
			);

		//Run input validation
		$v = Validator::make($input, $rules);

		if ($v->fails())
		{
			// Validation has failed
			return Redirect::to('/admin/groups/create')->withErrors($v)->withInput();
		}
		else 
		{
			try
			{
			    // Create the group
			    $group = Sentry::getGroupProvider()->create(array(

					'name'        => $input['newGroup'],
				        'permissions' => array(
				            'admin' => Input::get('adminPermissions', 0),
				            'users' => Input::get('userPermissions', 0),
				        ),
				    ));

				
				if ($group) {
					Session::flash('success', 'New Group Created');
				    return Redirect::to('/admin/groups');
				} else {
					Session::flash('error', 'New Group was not created');
				    return Redirect::to('/admin/groups');
				}
		
			}
			catch (Cartalyst\Sentry\Groups\NameRequiredException $e)
			{
			    Session::flash('error', 'Name field is required');
			    return Redirect::to('/admin/groups/create')->withErrors($v)->withInput();
			}
			catch (Cartalyst\Sentry\Groups\GroupExistsException $e)
			{
			    Session::flash('error', 'Group already exists');
			    return Redirect::to('/admin/groups/create')->withErrors($v)->withInput();
			}
		}
	}

	/**
	 * Display the specified resource.
	 *
	 * @return Response
	 */
	public function show($id)
	{
		//Show a group and its permissions. 
		try
		{
		    // Find the group using the group id
		    $data['group'] = Sentry::getGroupProvider()->findById($id);

		    // Get the group permissions
		    $data['groupPermissions'] = $data['group']->getPermissions();
		}
		catch (Cartalyst\Sentry\Groups\GroupNotFoundException $e)
		{
		    Session::flash('error', 'Group does not exist.');
			return Redirect::to('/admin/groups');
		}


		return View::make('users::admin.groups.show', $data);
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @return Response
	 */
	public function getEdit($id)
	{
		//Pull the selected group
		try
		{
		    // Find the group using the group id
		    $data['group'] = Sentry::getGroupProvider()->findById($id);
		    
		    // Get available permissions
		    $data['availablePerms'] = Permission::getWithNs();

		    // Get the group permissions
    		$data['groupPerms'] = $data['group']->getPermissions();
    		// dd($data['groupPerms']);

		}
		catch (Cartalyst\Sentry\Groups\GroupNotFoundException $e)
		{
		    Session::flash('error', 'Group does not exist.');
			return Redirect::to('/admin/groups');
		}

		return View::make('users::admin.groups.edit', $data);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @return Response
	 */
	public function postUpdate($id)
	{
		// Update the Group.
		// Start with Data Validation
		// Gather Sanitized Input
		
		$input = array(
			'name' => Input::get('name')
			);

		// Set Validation Rules
		$rules = array (
			'name' => 'required|min:4'
			);

		//Run input validation
		$v = Validator::make($input, $rules);

		if ($v->fails())
		{
			// Validation has failed
			return Redirect::to('/admin/groups/'. $id . '/edit')->withErrors($v)->withInput();
		}
		else 
		{

			try
			{
			    // Find the group using the group id
			    $group = Sentry::getGroupProvider()->findById($id);

			    // Update the group details
			    $group->name = $input['name'];
			    
			    $perms = Input::get('permissions', array());
			    
			    foreach ($perms as $key => $perm)
			    {
			    	$permissions[$key] = $perm;
			    }

			    $group->permissions = $permissions;
			    // $group->permissions = array(
			    //    'admin' => Input::get('permissions', 0),
				   // 'users' => Input::get('userPermissions', 0),
			    // );

			    // Update the group
			    if ($group->save())
			    {
			        // Group information was updated
			        Session::flash('success', 'Group has been updated.');
					return Redirect::to('/admin/groups');
			    }
			    else
			    {
			        // Group information was not updated
			        Session::flash('error', 'There was a problem updating the group.');
					return Redirect::to('/admin/groups/'. $id . '/edit')->withErrors($v)->withInput();
			    }
			}
			catch (Cartalyst\Sentry\Groups\GroupExistsException $e)
			{
			    Session::flash('error', 'Group already exists.');
				return Redirect::to('/admin/groups/'. $id . '/edit')->withErrors($v)->withInput();
			}
			catch (Cartalyst\Sentry\Groups\GroupNotFoundException $e)
			{
			    Session::flash('error', 'Group was not found.');
				return Redirect::to('/admin/groups/'. $id . '/edit')->withErrors($v)->withInput();
			}
		}
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @return Response
	 */
	public function destroy($id)
	{
		
		try
		{
		    // Find the group using the group id
		    $group = Sentry::getGroupProvider()->findById($id);

		    // Delete the group
		    if ($group->delete())
		    {
		        // Group was successfully deleted
		        Session::flash('success', 'Group has been deleted.');
				return Redirect::to('/admin/groups/');
		    }
		    else
		    {
		        // There was a problem deleting the group
		        Session::flash('error', 'There was a problem deleting that group.');
				return Redirect::to('/admin/groups/');
		    }
		}
		catch (Cartalyst\Sentry\Groups\GroupNotFoundException $e)
		{
		    Session::flash('error', 'Group was not found.');
			return Redirect::to('/admin/groups/');
		}
	}

}