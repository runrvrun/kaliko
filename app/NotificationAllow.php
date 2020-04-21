<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class NotificationAllow extends Model
{
    protected $fillable = [
        'user_id', 'notification_name', 'allow'
    ];
    
}
