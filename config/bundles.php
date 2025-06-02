<?php

declare(strict_types=1);

// This file is used for the shopware CLI, which integrates with shopware.

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class          => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class              => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class                    => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class                  => ['dev' => true, 'test' => true],

    Machinateur\TwigBlockValidator\TwigBlockValidatorBundle::class => ['dev' => true, 'test' => true],
];
