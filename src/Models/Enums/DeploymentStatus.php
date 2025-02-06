<?php

namespace JTMcC\AtomicDeployments\Models\Enums;

enum DeploymentStatus: int
{
    case FAILED = 0;
    case RUNNING = 1;
    case SUCCESS = 2;
}
