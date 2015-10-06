<?php

namespace Geneanet\I18nRoutingBundle;

use Geneanet\I18nRoutingBundle\DependencyInjection\Compiler\OverrideRoutingCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class GeneanetI18nRoutingBundle extends Bundle
{
    const VERSION = '2.4.0-DEV';

    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new OverrideRoutingCompilerPass());

        parent::build($container);
    }
}
