<?php

namespace JTMcC\AtomicDeployments\Interfaces;

interface DeploymentInterface
{
    public function getBuildPath();

    public function setDeploymentDirectory(string $name = '');

    public function setDeploymentPath();

    public function getDeploymentPath();

    public function getCurrentDeploymentPath();

    public function copyContents();

    public function linkDeployment();

    public function getDeploymentLink();

    public function getModel();

    public function updateDeploymentStatus(int $status);
}
