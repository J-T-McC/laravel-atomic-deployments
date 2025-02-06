<?php

namespace JTMcC\AtomicDeployments\Interfaces;

interface DeploymentInterface
{
    public function getBuildPath();

    public function setDirectory(string $name = '');

    public function setPath();

    public function getPath();

    public function getCurrentPath();

    public function copyContents();

    public function link();

    public function getLink();

    public function getModel();

    public function updateStatus(int $status);

    public function isDeployed(): bool;

    public function getDirectoryName(): string;

    public function createDirectory();
}
