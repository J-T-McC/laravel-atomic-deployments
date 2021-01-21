<?php

namespace Tests\Unit\Enum;

use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;
use Tests\TestCase;

class DeploymentStatusTest extends TestCase
{
    use EnumTestTrait;

    const expected = [
        'FAILED'  => 0,
        'RUNNING' => 1,
        'SUCCESS' => 2,
    ];

    const model = DeploymentStatus::class;
}
