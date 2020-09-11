<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSessions extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'started_at', 'expired_at', 'last_request', 'request_ip', 'user_agent','device','platform','browser','robot', 'status','sesion_end_type','end_sesion_description'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */

}
