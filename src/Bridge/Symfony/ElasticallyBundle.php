<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Bridge\Symfony;

use JoliCode\Elastically\Bridge\Symfony\DependencyInjection\Compiler\DataCollectorPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ElasticallyBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Must run before the ProfilerPass of the FrameworkBundle
        $container->addCompilerPass(new DataCollectorPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
    }
}
