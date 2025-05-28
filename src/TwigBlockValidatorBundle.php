<?php
/*
 * MIT License
 *
 * Copyright (c) 2025 machinateur
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace Machinateur\TwigBlockValidator;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class TwigBlockValidatorBundle extends AbstractBundle
{
    final public    const VERSION     = '0.1.0-beta';

    /**
     * @see https://symfony.com/doc/7.2/bundles.html#bundle-directory-structure
     * @see https://symfony.com/doc/4.x/bundles.html#bundle-directory-structure
     */
    public function getPath(): string
    {
        return __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->registerContainerFile($container);

        $this->buildDefaultConfig($container);
    }

    /**
     * Looks for service definition files inside the `Resources/config`
     *  directory and loads `services.yaml` files.
     */
    protected function registerContainerFile(ContainerBuilder $container): void
    {
        $locator    = new FileLocator('Resources/config');
        $resolver   = new LoaderResolver([
            // Somehow glob won't work here. TODO: Use load(..., type=glob) instead.
            new YamlFileLoader($container, $locator),
        ]);
        $configDir    = $this->getPath() . '/Resources/config';
        $configLoader = new DelegatingLoader($resolver);

        $configLoader->load($configDir.'/services.yaml');

        if ('test' === $container->getParameter('kernel.environment')) {
            $configLoader->load($configDir.'/services_test.yaml');
        }
    }

    protected function buildDefaultConfig(ContainerBuilder $container): void
    {
        $locator  = new FileLocator('Resources/config');
        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
        ]);

        $configDir    = $this->getPath() . '/Resources/config';
        $configLoader = new DelegatingLoader($resolver);

        $configLoader->load($configDir . '/{packages}/*.yaml', 'glob');

        $env = $container->getParameter('kernel.environment');
        \assert(\is_string($env));

        $configLoader->load($configDir.'/{packages}/'.$env.'/*.yaml', 'glob');
    }
}
