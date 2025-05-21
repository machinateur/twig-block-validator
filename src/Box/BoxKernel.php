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

namespace Machinateur\TwigBlockValidator\Box;

use Machinateur\TwigBlockValidator\TwigBlockValidatorKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\KernelInterface;

class BoxKernel extends TwigBlockValidatorKernel
{
    protected ?KernelInterface $kernel = null;

    public function __construct(string $environment = 'prod', bool $debug = false)
    {
        parent::__construct($environment, $debug);
    }
    public function getProjectDir(): string
    {
        return __DIR__.'/../..';
    }

    /**
     * @see \Symfony\Component\HttpKernel\Kernel::getContainerClass()
     */
    protected function getContainerClass(): string
    {
        // Make sure the parent class is used here, since otherwise the cache cannot be warmed properly before `phar` compilation.
        $class = 'Isolated\\'.parent::class;
        $class = \str_contains($class, "@anonymous\0") ? \get_parent_class($class).\str_replace('.', '_', ContainerBuilder::hash($class)) : $class;
        $class = \str_replace('\\', '_', $class).\ucfirst($this->environment).($this->debug ? 'Debug' : '').'Container';

        if (!\preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $class)) {
            throw new \InvalidArgumentException(\sprintf('The environment "%s" contains invalid characters, it can only contain characters allowed in PHP class names.', $this->environment));
        }

        return $class;
    }

    public function getKernel(): ?KernelInterface
    {
        if ($this->kernel) {
            return $this->kernel;
        }

        $kernel      = null;
        $projectRoot = \getcwd();

        if ($projectRoot && \is_file($projectRoot.'/bin/console')) {
            $application = include $projectRoot.'/bin/console';

            // The `Application` class from `symfony/framework-bundle` cannot be used directly, as it will be scoped inside the `phar` archive,
            //  so the check would always fail if used for comparison of outside sources.
            if (\is_object($application) && \method_exists($application, 'getContainer')) {
                /** @var \Symfony\Bundle\FrameworkBundle\Console\Application $application */
                $kernel = $application->getKernel();
            }
        }

        if (null === $kernel) {
            \trigger_error(\sprintf('Failed to load application kernel from "%s" root directory!', $projectRoot), \E_USER_WARNING);
        }

        return $this->kernel = $kernel ?? null;
    }

    public function boot(): void
    {
        parent::boot();

        // Resolve and boot the application kernel.
        $this->kernel = $this->getKernel();
        $this->kernel?->boot();
    }

    public function shutdown(): void
    {
        parent::shutdown();

        // Shut down the application kernel.
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    protected function prepareContainer(ContainerBuilder $container): void
    {
        parent::prepareContainer($container);

        // Copy over twig service from application kernel.
        $container->set('twig',
            $this->kernel?->getContainer()
                ->get('twig')
        );
    }
}
