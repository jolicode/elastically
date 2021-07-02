<?php

declare(strict_types=1);

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

    public function getCacheDir()
    {
        return sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'elastically';
    }

    public function getLogDir()
    {
        return sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'elastically_logs';
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        $loader->load(__DIR__ . '/config.yaml');

        $def = $c->register(TestController::class);
        $def->setAutowired(true);
        $def->setAutoconfigured(true);
    }

    protected function configureRoutes(RouteCollectionBuilder $routes): void
    {
        $routes->add('/with_exception', TestController::class . '::withException', 'with_exception');
        $routes->add('/with_response', TestController::class . '::withResponse', 'with_response');
    }
}
