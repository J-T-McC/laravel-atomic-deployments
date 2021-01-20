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
    public AtomicDeployment $deployment;

    /**
     * DeploymentSuccessful constructor.
     * @param AtomicDeployments $deploymentService
     * @param AtomicDeployment $deployment
     */
    public function __construct(AtomicDeployments $deploymentService, AtomicDeployment $deployment)
    {
        $this->deploymentService = $deploymentService;
        $this->deployment = $deployment;
    }

}
