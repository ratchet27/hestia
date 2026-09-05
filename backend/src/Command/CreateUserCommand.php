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

#[AsCommand(name: 'app:user:create', description: 'Create a user account')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Login username')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Display name')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email (optional)')
            ->addOption(
                'password-stdin',
                null,
                InputOption::VALUE_NONE,
                'Read the password from standard input instead of the hidden prompt'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = (string) $input->getArgument('username');
        $name = (string) ( $input->getOption('name') ?? $username );
        // @mago-ignore analysis:mixed-assignment -- Symfony Console getOption() is mixed; narrowed by is_string() below
        $email = $input->getOption('email');
        $password = PasswordInput::read($input, $io, 'Password');

        if ($password === '') {
            $io->error('Password is required.');

            return Command::FAILURE;
        }

        if ($this->em->getRepository(User::class)->findOneBy(['username' => $username]) !== null) {
            $io->error(sprintf('User "%s" already exists.', $username));

            return Command::FAILURE;
        }

        $user = new User($username);
        $user->setName($name);
        $user->setEmail(is_string($email) ? $email : null);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('User "%s" created.', $username));

        return Command::SUCCESS;
    }
}
