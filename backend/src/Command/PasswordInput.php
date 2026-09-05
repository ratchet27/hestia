<?php

declare(strict_types = 1);

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A password never travels as a CLI option: options land in shell history,
 * `ps` output and container exec audit logs. Interactive use gets the hidden
 * prompt; scripts pipe the secret in with --password-stdin.
 */
final class PasswordInput
{
    public static function read(InputInterface $input, SymfonyStyle $io, string $prompt): string
    {
        if ($input->getOption('password-stdin') === true) {
            $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
            $line = fgets($stream ?? STDIN);

            return $line === false ? '' : rtrim($line, "\r\n");
        }

        // @mago-ignore analysis:mixed-assignment -- askHidden() returns mixed; narrowed below
        $answer = $io->askHidden($prompt);

        return is_string($answer) ? $answer : '';
    }
}
