<?php

namespace JTMcC\AtomicDeployments\Interfaces;

use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;

interface DeploymentInterface
{
    public function getBuildPath(): string;

    public function setDirectory(string $name = ''): void;

    public function setPath(): void;

    public function getPath(): string;

    public function getCurrentPath(): string;

    public function copyContents(): void;

    public function link(): void;

    public function getLink(): string;

    public function getModel(): AtomicDeployment;

    public function updateStatus(DeploymentStatus $status): void;

    public function isDeployed(): bool;

    public function getDirectoryName(): string;

    public function createDirectory(): void;
}
