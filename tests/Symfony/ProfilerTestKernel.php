<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Tests\Symfony;

use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ProfilerTestKernel extends TestKernel
{
    public function registerBundles(): iterable
    {
        yield from parent::registerBundles();
        yield new TwigBundle();
        yield new WebProfilerBundle();
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir() . '_profiler';
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader): void
    {
        parent::configureContainer($c, $loader);

        $c->loadFromExtension('framework', [
            'profiler' => ['enabled' => true, 'collect' => true],
        ]);
        $c->loadFromExtension('web_profiler', [
            'toolbar' => false,
        ]);
    }

    protected function configureRoutes($routes): void
    {
        parent::configureRoutes($routes);

        $routeConfigurator = $routes->add('with_search', '/with_search');
        $routeConfigurator->controller(\sprintf('%s::withSearch', TestController::class));

        $resource = \dirname((new \ReflectionClass(WebProfilerBundle::class))->getFileName()) . '/Resources/config/routing/profiler';
        $routes->import(is_file($resource . '.php') ? $resource . '.php' : $resource . '.xml')->prefix('/_profiler');
    }
}
