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

namespace Machinateur\TwigBlockValidator\Validator;

use Machinateur\TwigBlockValidator\Event\Validator\TwigLoadFilesEvent;
use Machinateur\TwigBlockValidator\Event\Validator\TwigLoadPathsErrorEvent;
use Machinateur\TwigBlockValidator\Event\Validator\TwigLoadPathsEvent;
use Machinateur\TwigBlockValidator\Event\Validator\TwigRegisterPathsErrorEvent;
use Machinateur\TwigBlockValidator\Event\Validator\TwigRegisterPathsEvent;
use Machinateur\TwigBlockValidator\Event\Validator\TwigCollectBlocksEvent;
use Machinateur\TwigBlockValidator\Event\Validator\TwigValidateCommentsErrorEvent;
use Machinateur\TwigBlockValidator\Event\Validator\TwigValidateCommentsEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Error\Error as TwigError;

class TwigBlockValidatorOutput implements EventSubscriberInterface, ResetInterface
{
    /**
     * @return array<class-string, string|array{0: string, 1?: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TwigRegisterPathsEvent::class      => 'onTwigRegisterPaths',
            TwigRegisterPathsErrorEvent::class => 'onTwigRegisterPathsError',

            TwigLoadPathsEvent::class          => 'onTwigLoadPaths',
            TwigLoadPathsErrorEvent::class     => 'onTwigLoadPathsError',

            TwigLoadFilesEvent::class          => 'onTwigLoadFiles',

            TwigCollectBlocksEvent::class      => 'onTwigCollectBlocks',

            TwigValidateCommentsEvent::class      => 'onTwigValidateComments',
            TwigValidateCommentsErrorEvent::class => 'onTwigValidateCommentsError',
        ];
    }

    protected ?InputInterface  $input;
    protected ?OutputInterface $output;

    protected ?SymfonyStyle    $console;

    public function __construct()
    {}

    /**
     * Set up the output (and input) and `ConsoleStyle`.
     */
    public function init(InputInterface $input, OutputInterface $output): void
    {
        $this->input   = $input;
        $this->output  = $output;
        $this->console = new SymfonyStyle($input, $output);
    }

    /**
     * Reset IO and console to `null`.
     */
    public function reset(): void
    {
        $this->input   = null;
        $this->output  = null;
        $this->console = null;
    }

    /**
     * Print the *register paths* header to the console output.
     *
     * @see TwigBlockValidator::registerPaths()
     */
    public function onTwigRegisterPaths(TwigRegisterPathsEvent $event): void
    {
        if ($this->console?->isVeryVerbose()) {
            $this->console?->note(\sprintf('Adding namespace "%s" with paths:', $event->namespace));
            $this->console?->listing($event->paths);
        } else {
            $this->console?->note(\sprintf('Adding namespace "%s"...', $event->namespace));
        }
    }

    /**
     * Print loader errors to the console output.
     *
     * @see TwigBlockValidator::registerPaths()
     */
    public function onTwigRegisterPathsError(TwigRegisterPathsErrorEvent $event): void
    {
        $this->console?->warning('Twig loader errors!');
        $this->listingTwigErrors($event->errors);
    }

    /**
     * Print the *load paths* header to the console output.
     *
     * @see TwigBlockValidator::loadPaths()
     */
    public function onTwigLoadPaths(TwigLoadPathsEvent $event): void
    {
        if ($this->console?->isVeryVerbose()) {
            $this->console?->note(\sprintf('Loading namespace "%s" with paths:', $event->namespace));
            $this->console?->listing($event->paths);
        } else {
            $this->console?->note(\sprintf('Loading namespace "%s".', $event->namespace));
        }
    }

    /**
     * Print loader errors to the console output.
     *
     * @see TwigBlockValidator::loadPaths()
     */
    public function onTwigLoadPathsError(TwigLoadPathsErrorEvent $event): void
    {
        $this->console?->warning('Twig loader errors!');
        $this->listingTwigErrors($event->errors);
    }

    /**
     * Handle loading files progress bar.
     */
    public function onTwigLoadFiles(TwigLoadFilesEvent $event): void
    {
        $console = $this->console;

        if ($console->isVeryVerbose()) {
            $event->callback(TwigLoadFilesEvent::CALL_BEGIN,
                static fn () => $console?->note('Loading files:')
            );
            $event->callback(TwigLoadFilesEvent::CALL_STEP,
                static fn (SplFileInfo $file) => $console?->text(\sprintf('  * %s', $file->getRelativePathname()))
            );
            $event->callback(TwigLoadFilesEvent::CALL_END,
                static fn () => $console?->comment('Count: ' . $event->finder->count())
            );
        } else {
            $event->callback(TwigLoadFilesEvent::CALL_BEGIN,
                static fn () => $console?->progressStart($event->finder->count())
            );
            $event->callback(TwigLoadFilesEvent::CALL_STEP,
                static fn (SplFileInfo $file) => $console?->progressAdvance()
            );
            $event->callback(TwigLoadFilesEvent::CALL_END,
                static fn () => $console?->progressFinish()
            );
        }
    }

    public function onTwigCollectBlocks(TwigCollectBlocksEvent $event): void
    {
        $this->console?->note(\sprintf('Collected %d blocks across %d templates.', \count($event->blocks), \count($event->paths)));
    }

    public function onTwigValidateComments(TwigValidateCommentsEvent $event): void
    {
        $defaultVersion = $event->version;
        $console        = $this->console;
        $table          = $this->console?->createTable();

        $event->callback(TwigValidateCommentsEvent::CALL_BEGIN,
            static fn () => $table?->setHeaders(['template', 'parent template', 'block', 'hash', 'version', 'mismatch'])
        );
        $event->callback(TwigValidateCommentsEvent::CALL_STEP,
            static function (array $comment) use ($console, $table, $defaultVersion) {
                if (null === $table) {
                    return;
                }

                $row = [
                    ...$comment,
                    'block_lines' => \sprintf('%d-%d', ...$comment['block_lines']),
                    'hash' => !$comment['match']['hash']
                        ? \sprintf("<fg=white;bg=red>%s</>\n<fg=black;bg=green>%s</>", $comment['hash'], $comment['source_hash'])
                        : $comment['hash'],
                    'version' => !$comment['match']['version']
                        ? \sprintf("<fg=white;bg=red>%s</>\n<fg=black;bg=green>%s</>", $comment['version'], $comment['source_version'])
                        : $comment['version'],
                    'valid' => \sprintf('[%s]', $comment['valid'] ? ' ' : 'x'),
                ];
                unset($row['block_lines'], $row['source_hash'], $row['source_version'], $row['match']);

                $table->addRow($row);

                if ($console?->isDebug() && ! $comment['match']['hash']) {
                    $console?->warning(\sprintf('Mismatch from block hash to source hash for "%s"!', $comment['template']));
                    $console?->writeln(\sprintf("< <fg=white;bg=red>%s</>", $comment['hash']));
                    $console?->writeln(\sprintf("> <fg=black;bg=green>%s</>", $comment['source_hash']));
                    $console?->newLine();
                }
                if ($console?->isDebug() &&  ! $comment['match']['version']) {
                    $console?->warning(\sprintf('Mismatch from block version to source version for "%s"!', $comment['template']));
                    $console?->writeln(\sprintf("< <fg=white;bg=red>%s</>", $comment['version']));
                    $console?->writeln(\sprintf("> <fg=black;bg=green>%s</>", $defaultVersion));
                    $console?->newLine();
                }
            }
        );
        $event->callback(TwigValidateCommentsEvent::CALL_END,
            static fn () => $table?->render()
        );
    }

    public function onTwigValidateCommentsError(TwigValidateCommentsErrorEvent $event): void
    {
        $this->console?->warning([
            'Twig errors!',
        ]);

        $this->listingTwigErrors($event->errors);
    }

    /**
     * @param list<TwigError> $errors
     */
    protected function listingTwigErrors(array $errors): void
    {
        $this->console?->listing(
            \array_map(static fn (TwigError $error) => $error->getMessage()
                . (($prev = $error->getPrevious()) instanceof \Throwable ? "\n  " . $prev->getMessage() : ''), $errors)
        );
    }

    /**
     * @internal
     */
    public function getConsole(): ?SymfonyStyle
    {
        return $this->console;
    }
}
