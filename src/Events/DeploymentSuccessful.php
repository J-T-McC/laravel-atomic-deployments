<?php

namespace JTMcC\AtomicDeployments\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Services\AtomicDeployments;

class DeploymentSuccessful implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public AtomicDeployments $deploymentService;
    public ?AtomicDeployment $deployment = null;

    /**
     * DeploymentSuccessful constructor.
     * @param AtomicDeployments $deploymentService
     * @param mixed $deployment
     */
    public function __construct(AtomicDeployments $deploymentService, ?AtomicDeployment $deployment = null)
    {
        $this->deploymentService = $deploymentService;
        $this->deployment = $deployment;
    }

}
