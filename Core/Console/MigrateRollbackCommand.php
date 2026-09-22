<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\MigrateRollbackCommand
 *      ย้อน migration แบบ Laravel
 *      php deawx migrate:rollback [--step=N] [--batch=N]
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

final class MigrateRollbackCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:rollback')
            ->setDescription('ย้อน migration ตาม batch, --step, --batch หรือ --path')
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'จำนวนไฟล์ที่จะย้อน นับจากล่าสุด')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'ย้อนเฉพาะ batch ที่ระบุ')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'ย้อนเฉพาะไฟล์ เช่น create_users_table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $path = $input->getOption('path');
            $rolled = Migrator::rollback(
                self::intOption($input, 'step'),
                self::intOption($input, 'batch'),
                is_string($path) && $path !== '' ? $path : null,
            );
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

    private static function intOption(InputInterface $input, string $name): ?int
    {
        $value = $input->getOption($name);
        if ($value === null || $value === false || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
