<?php

return array(

	'default' => 'mysql',

	/*
	|--------------------------------------------------------------------------
	| Database Connections
	|--------------------------------------------------------------------------
	|
	| Here are each of the database connections setup for your application.
	| Of course, examples of configuring each database platform that is
	| supported by Laravel is shown below to make development simple.
	|
	|
	| All database work in Laravel is done through the PHP PDO facilities
	| so make sure you have the driver for your particular database of
	| choice installed on your machine before you begin development.
	|
	*/

	'connections' => array(

		'sqlite' => array(
			'driver'   => 'sqlite',
			'database' => __DIR__.'/../database/production.sqlite',
			'prefix'   => '',
		),

		'mysql' => array(
			'driver'    => 'mysql',
			'host'      => 'localhost',
			'database'  => 'database',
			'username'  => 'root',
			'password'  => '',
			'charset'   => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix'    => '',
		),
	    'mysql_tenant' => array(
	        'driver'    => 'mysql',
	        'host'      => 'localhost',
	        'database'  => subdomain(),  // Note the subdomain method here 
	        'username'  => 'root',
	        'password'  => '',
	        'charset'   => 'utf8',
	        'collation' => 'utf8_unicode_ci',
	        'prefix'    => '',
	    )

	)

);



// routes.php
Route::group(array('before' => 'verifyTenant'), function()
{
	Route::get('/', array('as' => 'signin', 'uses' => 'AuthController@getSignin'));
…
});

// filter.php
Route::filter('verifyTenant', function($route, $request) 
{
    $host = $request->getHost();
    $parts = explode('.', $host);
    $subdomain = $parts[0];

    # Ping DB for tenant match. Note that my Tenant model directs laravel to ping the tenant table in the master db to verify tenant
    $tenant = Tenant::where('subdomain', '=', $subdomain)->first();

    # If tenant database exists but tenant not in master db, redirect to homepage
    if ($tenant == null) return Redirect::to('http://www.'.Config::get('app.domain'));
});


	/**
	 * Register a connection with the manager.
	 *
	 * @param  array   $config
	 * @param  string  $name
	 * @return void
	 */
	public function addConnection(array $config, $name = 'default')
	{
		$connections = $this->container['config']['database.connections'];

		$connections[$name] = $config;

		$this->container['config']['database.connections'] = $connections;
	}

if ( ! function_exists('subdomain'))
{
	/**
	 * Generate a subdomain for the tenant. Used in the database config file.
	 *
	 * @param  string  $path
	 * @param  mixed   $parameters
	 * @param  bool    $secure
	 * @return string
	 */
	function subdomain($subdomain = '')
	{
		if(isset($_SERVER['HTTP_HOST']))
		{	
			$domain_parts = explode('.', $_SERVER['HTTP_HOST']);
			
			if (count($domain_parts) == 3)
			{
			    $subdomain = $domain_parts[0];
			}

			return $subdomain;
		}
	}
}
