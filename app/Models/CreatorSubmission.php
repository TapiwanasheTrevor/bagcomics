<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorSubmission extends Model
{
    protected $fillable = [
        'name',
        'email',
        'portfolio_url',
        'comic_title',
        'genre',
        'synopsis',
        'sample_pages_url',
        'status',
        'admin_notes',
    ];
}
