<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\MigrateCommand
 *      รัน migration ที่ยังไม่เคยรัน
 *      php deawx migrate
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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MigrateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate')
            ->setDescription('รัน migration ที่ยังไม่ถูกใช้')
            ->addOption('step', null, InputOption::VALUE_NONE, 'รันทีละไฟล์ คนละ batch')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'รันเฉพาะไฟล์หรือโฟลเดอร์ที่ระบุ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $path = $input->getOption('path');
            $applied = Migrator::migrate(
                (bool) $input->getOption('step'),
                is_string($path) && $path !== '' ? $path : null,
            );
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        if ($applied === []) {
            $output->writeln('<comment>ไม่มี migration ใหม่</comment>');

            return Command::SUCCESS;
        }

        foreach ($applied as $name) {
            $output->writeln("<info>migrated:</info> {$name}");
        }

        return Command::SUCCESS;
    }
}
