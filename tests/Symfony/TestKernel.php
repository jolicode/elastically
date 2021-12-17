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

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
        ];
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'elastically';
    }

    public function getLogDir(): string
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

    protected function configureRoutes($routes): void
    {
        if ($routes instanceof RouteCollectionBuilder) {
            $routes->add('/with_exception', TestController::class . '::withException', 'with_exception');
            $routes->add('/with_response', TestController::class . '::withResponse', 'with_response');
        } else {
            $routeConfigurator = $routes->add('with_exception', '/with_exception');
            $routeConfigurator->controller(sprintf('%s::withException', TestController::class));

            $routeConfigurator = $routes->add('with_response', '/with_response');
            $routeConfigurator->controller(sprintf('%s::withResponse', TestController::class));
        }
    }
}
