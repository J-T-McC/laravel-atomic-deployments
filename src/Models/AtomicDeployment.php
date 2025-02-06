<?php

namespace JTMcC\AtomicDeployments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\File;
use JTMcC\AtomicDeployments\Exceptions\AreYouInsaneException;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;
use JTMcC\AtomicDeployments\Services\Exec;

class AtomicDeployment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'commit_hash',
        'deployment_status',
        'build_path',
        'deployment_path',
        'deployment_link',
    ];

    protected static function boot()
    {
        parent::boot();
        static::deleting(function ($model) {
            if ($model->isCurrentlyDeployed) {
                throw new AreYouInsaneException('Cannot delete live deployment');
            }
            $model->deleteDeployment();
        });
    }

    public function scopeSuccessful($query)
    {
        return $query->where('deployment_status', DeploymentStatus::SUCCESS);
    }

    public function getHasDeploymentAttribute()
    {
        return File::isDirectory($this->deployment_path);
    }

    /**
     * @return bool
     *
     * @throws \JTMcC\AtomicDeployments\Exceptions\ExecuteFailedException
     */
    public function getIsCurrentlyDeployedAttribute()
    {
        if (! $this->hasDeployment) {
            return false;
        }

        return Exec::readlink($this->deployment_link) === $this->deployment_path;
    }

    public function deleteDeployment()
    {
        if ($this->hasDeployment) {
            File::deleteDirectory($this->deployment_path);
        }

        return $this;
    }
}
