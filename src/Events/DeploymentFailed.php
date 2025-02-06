<?php

namespace JTMcC\AtomicDeployments\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Services\AtomicDeploymentService;

class DeploymentFailed implements ShouldQueue
{
    use Dispatchable;
    use SerializesModels;

    public AtomicDeploymentService $deploymentService;

    public ?AtomicDeployment $deployment = null;

    /**
     * DeploymentSuccessful constructor.
     */
    public function __construct(AtomicDeploymentService $deploymentService, ?AtomicDeployment $deployment = null)
    {
        $this->deploymentService = $deploymentService;
        $this->deployment = $deployment;
    }
}
