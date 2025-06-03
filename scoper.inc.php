<?php

declare(strict_types=1);

$unfinalizedPaths = [
    'vendor/twig/twig/src/TwigFilter.ph',
    'vendor/twig/twig/src/TwigFunction.ph',
    'vendor/twig/twig/src/TwigTest.ph',
];

return [
    'prefix'          => 'Isolated',
    'exclude-classes' => [\Composer\InstalledVersions::class],
    'exclude-files'   => ['vendor/composer/InstalledVersions.php'],
    'patchers'        => [

        static function (string $filePath, string $prefix, string $content): string {
            //
            // PHP-Parser patch for yaml/yml files (redundant).
            //
            //if (\str_ends_with($filePath, '.yaml')
            //    || \str_ends_with($filePath, '.yml')
            //) {
            //    return \preg_replace(
            //        "%Machinateur\\\\TwigBlockValidator\\\\%",
            //        $prefix . '\\Machinateur\\TwigBlockValidator\\',
            //        $content,
            //    );
            //}

            return $content;
        },

        static function (string $filePath, string $prefix, string $content) use ($unfinalizedPaths): string {
            //
            // PHP-Parser patch to unfinalize certain files.
            //
            if (\in_array($filePath, $unfinalizedPaths)) {
                return \preg_replace(
                    "%^final class %",
                    'class',
                    $content,
                );
            }

            return $content;
        },

    ],
];
