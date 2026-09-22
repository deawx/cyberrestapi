<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\MigrateResetCommand
 *      ย้อนทุก migration ที่รันแล้ว
 *      php deawx migrate:reset
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Console;

use Core\Migrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MigrateResetCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:reset')
            ->setDescription('ย้อนทุก migration ที่รันแล้ว');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $rolled = Migrator::reset();
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        if ($rolled === []) {
            $output->writeln('<comment>ไม่มี migration ให้ย้อน</comment>');

            return Command::SUCCESS;
        }

        foreach ($rolled as $name) {
            $output->writeln("<info>rolled back:</info> {$name}");
        }

        return Command::SUCCESS;
    }
}
