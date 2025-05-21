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

use Composer\InstalledVersions;
use Machinateur\TwigBlockValidator\Command\TwigBlockAnnotateCommand;
use Machinateur\TwigBlockValidator\Command\TwigBlockValidateCommand;
use Machinateur\TwigBlockValidator\Twig\Extension\BlockValidatorExtension;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class TwigBlockValidatorKernel extends Kernel
{
    public static function getShopwareVersion(): ?string
    {
        try {
            return InstalledVersions::getVersion('shopware/storefront');
        } catch (\OutOfBoundsException) {
            return null;
        }
    }

    /**
     * @return \Generator<\Symfony\Component\HttpKernel\Bundle\Bundle>
     */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        // Needed for prettier console output.
        yield new MonologBundle();

        if (\in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            yield new DebugBundle();
        }

        yield new TwigBlockValidatorBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {}

    public function boot(): void
    {
        parent::boot();

        if (null !== static::getShopwareVersion()) {
            BlockValidatorExtension::$preferredLabel = 'shopware';
        }
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        if (null !== $version = static::getShopwareVersion()) {
            $commands = [
                TwigBlockValidateCommand::class,
                TwigBlockAnnotateCommand::class,
            ];

            foreach ($commands as $command) {
                $container->getDefinition($command)
                    ->addMethodCall('setVersion', [$version]);
            }
        }
    }
}
