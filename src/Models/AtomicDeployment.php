<?php

namespace JTMcC\AtomicDeployments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AtomicDeployment extends Model
{
    use HasFactory;

    protected $fillable = [
        'commit_hash',
        'deployment_status',
        'build_path',
        'deployment_path',
        'web_root',
    ];

}
