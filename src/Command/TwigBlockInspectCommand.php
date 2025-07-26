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

namespace Machinateur\TwigBlockValidator\Command;

use Machinateur\TwigBlockValidator\Inspector\TwigBlockInspector;
use Machinateur\TwigBlockValidator\TwigBlockValidatorOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

class TwigBlockInspectCommand extends AbstractConsoleCommand
{
    final public const DEFAULT_NAME = 'twig:block:inspect';

    /**
     * @param string|null $name     The override command name. This is useful for adding it as composer script.
     */
    public function __construct(
        private readonly TwigBlockInspector $inspector,
        TwigBlockValidatorOutput            $output,
        Environment                         $platformTwig,
        ?string                             $name = null,
    ) {
        parent::__construct($output, $platformTwig, $name);
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Inspect block comments in twig templates')
            //->setHelp()
            ->addOption('check-all', 'a', InputOption::VALUE_NONE, 'Check all available template paths')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output->init($input, $output);

        $templatePaths = (array)$input->getOption('template');
        $inspectPaths = (array)$input->getOption('validate');
        $version       = $input->getOption('use-version');

        $templatePaths = $this->resolveNamespaces($templatePaths);
        $inspectPaths = $this->resolveNamespaces($inspectPaths);

        // Fallback to platform twig paths, if none are given.
        if (0 === \count($templatePaths)) {
            $templatePaths = $this->getPlatformTemplatePaths();
        }
        // Fallback to version injected from shopware.
        if (null === $version) {
            $version = $this->getVersion();
        } elseif (false === $version) {
            $version = null;
        }

        // Check all available paths, when `-a` option is given.
        if ((bool)$input->getOption('check-all')) {
            if (0 === \count($inspectPaths)) {
                // Replace paths.
                $inspectPaths = $templatePaths;
            } else {
                // Merge paths.
                $inspectPaths = \array_merge_recursive($templatePaths, $inspectPaths);
            }
        }

        $this->inspector->inspect($inspectPaths, $templatePaths, $version);

        $this->output->reset();

        return Command::SUCCESS;
    }
}
