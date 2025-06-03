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

namespace Machinateur\TwigBlockValidator\Box\Twig;

use Machinateur\TwigBlockValidator\Box\ProxyManager\ProxyManager;
use Machinateur\TwigBlockValidator\Box\Twig\Proxy\Extension\IsolatedExtensionProxy;
use Machinateur\TwigBlockValidator\Twig\BlockValidatorEnvironment;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Extension\ExtensionInterface;

/**
 * Special implementation to allow external interoperability of extensions when running inside the executable phar archive.
 */
class IsolatedTwigValidatorEnvironment extends BlockValidatorEnvironment
{
    public function __construct(
        private readonly ProxyManager $proxyManager,
        object                        $platformTwig,
        CacheInterface                $cache,
        EventDispatcherInterface      $dispatcher,
        ?string                       $version = null,
    ) {
        parent::__construct($platformTwig, $cache, $dispatcher, $version);
    }

    /**
     * @param Environment $platformTwig
     */
    protected function initExtensions(object $platformTwig): void
    {
        $this->proxyManager->initClassLoader();

        // https://github.com/shopware/shopware/blob/6.6.x/src/Core/Framework/Adapter/Twig/StringTemplateRenderer.php
        foreach ($platformTwig->getExtensions() as $extension) {
            // Check if there is the same extension already installed.
            if ($this->hasExtension($extension::class)) {
                continue;
            }

            // Check if there is the isolated version of the extension installed.
            $extensionClass = 'Isolated\\'.$extension::class;
            if ($this->hasExtension($extensionClass)) {
                continue;
            }

            // Here we check for the prefixed extension base class, as well as the unprefixed interface.
            if ( ! $extension instanceof ExtensionInterface && \is_subclass_of($extension, 'Twig\\Extension\\'.'ExtensionInterface')) {

                //  We need different classnames, because twig internally uses the class name to detect duplicated extensions.
                $extension = $this->proxyManager->createProxy(IsolatedExtensionProxy::class, $extension);
                // Then decorate the extension class.
            }

            $this->addExtension($extension);
        }

        // Concatenate to avoid being picked up by the isolation, if inside the phar (i.e. in isolation prefix mode).
        /** @var class-string<CoreExtension> $class */
        $extensionClass = '\\Twig'.'\\CoreExtension';

        if ($this->hasExtension(CoreExtension::class) && $platformTwig->hasExtension($extensionClass)) {
            /** @var CoreExtension $coreExtensionInternal */
            $coreExtensionInternal = $this->getExtension(CoreExtension::class);
            /** @var CoreExtension $coreExtensionGlobal */
            $coreExtensionGlobal = $platformTwig->getExtension($extensionClass);

            $coreExtensionInternal->setTimezone($coreExtensionGlobal->getTimezone());
            $coreExtensionInternal->setDateFormat(...$coreExtensionGlobal->getDateFormat());
            $coreExtensionInternal->setNumberFormat(...$coreExtensionGlobal->getNumberFormat());
        }
    }
}
