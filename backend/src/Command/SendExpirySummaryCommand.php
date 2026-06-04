<?php

declare(strict_types = 1);

namespace App\Command;

use App\Message\SendDailyExpirySummary;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:telegram:send-summary',
    description: 'Dispatch the daily expiry summary now (manual trigger for the scheduled job).'
)]
final class SendExpirySummaryCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $bus
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bus->dispatch(new SendDailyExpirySummary());

        new SymfonyStyle($input, $output)->success(
            'Сводка поставлена в очередь — воркер отправит её в Telegram (или промолчит, если ничего не истекает).'
        );

        return Command::SUCCESS;
    }
}
