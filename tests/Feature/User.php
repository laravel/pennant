<?php

namespace Tests\Feature;

use Illuminate\Foundation\Auth\User as AuthUser;

class User extends AuthUser
{
    protected $guarded = [];
}
