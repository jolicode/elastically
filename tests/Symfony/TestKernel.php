<?php

declare(strict_types=1);

namespace JoliCode\Elastically\Tests\Symfony;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            new FrameworkBundle(),
        ];

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/config.yaml');
    }

    public function getCacheDir()
    {
        return sys_get_temp_dir();
    }

    public function getLogDir()
    {
        return sys_get_temp_dir();
    }
}
