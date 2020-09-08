<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Users extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'lastname', 'lastname2', 'email', 'phone', 'food_image','api_token','email_confirmation','email_confirmed'
    ];

    protected $hidden = [
       'password'
    ];
    
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */

}
