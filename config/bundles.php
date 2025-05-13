<?php

declare(strict_types=1);

// This file is used for the shopware CLI, which integrates with shopware.

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class          => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class              => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class                    => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class                  => ['dev' => true, 'test' => true],

    Shopware\Core\Profiling\Profiling::class                       => ['dev' => true],
    Shopware\Core\Framework\Framework::class                       => ['dev' => true],
    Shopware\Core\System\System::class                             => ['dev' => true],
    Shopware\Core\Content\Content::class                           => ['dev' => true],
    Shopware\Core\Checkout\Checkout::class                         => ['dev' => true],
    Shopware\Core\DevOps\DevOps::class                             => ['dev' => true],
    Shopware\Core\Maintenance\Maintenance::class                   => ['dev' => true],
    Shopware\Administration\Administration::class                  => ['dev' => true],
    Shopware\Storefront\Storefront::class                          => ['dev' => true],
    //Shopware\Elasticsearch\Elasticsearch::class                    => ['dev' => true],
    //Shopware\Core\Service\Service::class                           => ['dev' => true],

    Machinateur\TwigBlockValidator\TwigBlockValidatorBundle::class => ['dev' => true, 'test' => true],
];
