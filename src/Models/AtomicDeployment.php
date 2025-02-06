<?php

namespace JTMcC\AtomicDeployments\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\File;
use JTMcC\AtomicDeployments\Exceptions\AreYouInsaneException;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;
use JTMcC\AtomicDeployments\Services\Exec;

/**
 * @mixin Builder
 *
 * @method static Builder successful()
 *
 * @property-read bool $has_deployment
 * @property-read bool $is_currently_deployed
 * @property string $commit_hash
 * @property int $deployment_status
 * @property string $build_path
 * @property string $deployment_path
 * @property string $deployment_link
 */
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

    protected $casts = [
        'deployment_status' => DeploymentStatus::class,
    ];

    protected static function boot()
    {
        parent::boot();
        static::deleting(function ($model) {
            if ($model->is_currently_deployed) {
                throw new AreYouInsaneException('Cannot delete live deployment');
            }
            $model->deleteDeployment();
        });
    }

    public function scopeSuccessful($query)
    {
        return $query->where('deployment_status', DeploymentStatus::SUCCESS);
    }

    public function hasDeployment(): Attribute
    {
        return Attribute::make(
            get: fn () => File::isDirectory($this->deployment_path),
        );
    }

    public function isCurrentlyDeployed(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->has_deployment && Exec::readlink($this->deployment_link) === $this->deployment_path,
        );
    }

    public function deleteDeployment()
    {
        if ($this->has_deployment) {
            File::deleteDirectory($this->deployment_path);
        }

        return $this;
    }
}
