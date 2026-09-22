<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\MigrateStatusCommand
 *      แสดงสถานะไฟล์ migration
 *      php deawx migrate:status
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Console;

use Core\Migrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MigrateStatusCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:status')
            ->setDescription('แสดงสถานะไฟล์ migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $rows = Migrator::status();
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        if ($rows === []) {
            $output->writeln('<comment>ยังไม่มีไฟล์ migration</comment>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Ran', 'Migration', 'Batch']);
        foreach ($rows as $row) {
            $table->addRow([
                $row['ran'] ? 'Yes' : 'No',
                $row['migration'],
                $row['batch'] ?? '-',
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
