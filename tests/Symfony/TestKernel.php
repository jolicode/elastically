<?php

declare(strict_types=1);

namespace JoliCode\Elastically\Tests\Symfony;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles()
    {
        $bundles = [
            new FrameworkBundle(),
        ];

        return $bundles;
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/config.yaml');

        $def = $c->register(TestController::class);
        $def->setAutowired(true);
        $def->setAutoconfigured(true);
    }

    protected function configureRoutes(RouteCollectionBuilder $routes): void
    {
        $routes->add('/with_exception', TestController::class.'::withException', 'with_exception');
        $routes->add('/with_response', TestController::class.'::withResponse', 'with_response');
    }

    public function getCacheDir()
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'elastically';
    }

    public function getLogDir()
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'elastically_logs';
    }
}
