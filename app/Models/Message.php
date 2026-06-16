<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    public const SLUG_WELCOME_BASIC = 'welcome-basic';

    public const SLUG_WELCOME_EXTENDED = 'welcome-extended';

    protected $fillable = [
        'name',
        'slug',
        'text',
    ];
}
