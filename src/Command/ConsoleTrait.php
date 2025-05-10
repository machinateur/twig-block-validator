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

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * todo: replace with events, keep console inside command.
 */
trait ConsoleTrait
{
    protected ?OutputStyle     $console = null;
    protected ?InputInterface  $input   = null;
    protected ?OutputInterface $output  = null;

    public function setConsole(OutputStyle $console = null): void {
        if (null === $console) {
            $this->output  = new NullOutput();
            $this->input   = new ArrayInput([]);

            $this->console = new SymfonyStyle($this->input, $this->output);

            return;
        }

        if ( ! $console instanceof SymfonyStyle) {
            throw new \InvalidArgumentException('The $console must be an instance of SymfonyStyle!');
        }

        $this->console = $console;

        // Initialize IO.
        foreach (['input', 'output'] as $property) {
            \assert(\property_exists($this, $property));

            $ref = new \ReflectionProperty(SymfonyStyle::class, $property);
            //$ref->setAccessible(true);
            $val = $ref->getValue($this->console);

            $this->{$property} = $val;
        }
    }
}
