<?php

declare(strict_types = 1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:set-password', description: 'Reset a user password')]
final class SetUserPasswordCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'Login username')->addOption(
            'password',
            null,
            InputOption::VALUE_REQUIRED,
            'New password (prompted if omitted)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = (string) $input->getArgument('username');
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);

        if (!$user instanceof User) {
            $io->error(sprintf('User "%s" not found.', $username));

            return Command::FAILURE;
        }

        $password = $input->getOption('password') ?? $io->askHidden('New password');
        if (!is_string($password) || $password === '') {
            $io->error('Password is required.');

            return Command::FAILURE;
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->flush();

        $io->success(sprintf('Password updated for "%s".', $username));

        return Command::SUCCESS;
    }
}
