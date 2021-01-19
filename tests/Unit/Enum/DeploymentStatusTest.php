<?php

namespace Tests\Unit\Enum;

use Tests\TestCase;

use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;

class DeploymentStatusTest extends TestCase
{

    use EnumTestTrait;

    const expected = [
        'FAILED' => 0,
        'RUNNING' => 1,
        'SUCCESS' => 2,
    ];

    const model = DeploymentStatus::class;

}
