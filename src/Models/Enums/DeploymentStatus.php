<?php
namespace JTMcC\AtomicDeployments\Models\Enums;

class DeploymentStatus extends Enum {

    const FAILED = 0;
    const RUNNING = 1;
    const SUCCESS = 2;

}