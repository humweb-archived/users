<?php namespace Humweb\Users;

use Humweb\Event\Subscriber;

class EventSubscriber extends Subscriber {

    protected $listeners = [
        'user.registered' => 'onUserSignup',
        'user.logged.out' => 'onUserLogout'
    ];

    public function onUserSignup($user, $data)
    {
        Mail::send('emails.auth.welcome', $data, function($m) use($data)
        {
            $m->to($user->email)->subject('Signup confirmation email.');
        });
    }

    public function onUserConfirmSignup($value='')
    {
        # code...
    }
}