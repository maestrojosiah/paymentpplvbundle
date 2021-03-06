<?php

namespace Maestrojosiah\Payment\PaypalBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/*
 * Copyright 2010 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class MaestrojosiahPaymentPaypalExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->process($configuration->getConfigTree(), $configs);

        $xmlLoader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $xmlLoader->load('services.xml');

        $container->setParameter('payment.paypal.username', $config['username']);
        $container->setParameter('payment.paypal.password', $config['password']);
        $container->setParameter('payment.paypal.signature', $config['signature']);
        $container->setParameter('payment.paypal.express_checkout.return_url', $config['return_url']);
        $container->setParameter('payment.paypal.express_checkout.cancel_url', $config['cancel_url']);
        $container->setParameter('payment.paypal.express_checkout.notify_url', $config['notify_url']);
        $container->setParameter('payment.paypal.debug', $config['debug']);
        if ($config['recover_from_funding_failure']) {
            $plugin = $container->getDefinition('payment.plugin.paypal_express_checkout');
            $plugin->addMethodCall('setRedirectDueToFundingError', [$config['recover_from_funding_failure']]);
        }

    }
}
