<?php namespace Humweb\Users;

use Illuminate\Support\ServiceProvider;
use Humweb\Users\EventSubscriber;

class UsersServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	public function boot()
	{
		$this->package('humweb/users');
	}


	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		EventSubscriber::register();
	}


	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('users', 'permissions');
	}

}