<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\DbTablesCommand
 *      แสดงรายชื่อตารางและจำนวนแถว
 *      php deawx db:tables
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Console;

use Core\Blueprint;
use Core\Schema;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DbTablesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('db:tables')
            ->setDescription('แสดงตารางทั้งหมดในฐานข้อมูล');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $tables = Schema::tables();
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        if ($tables === []) {
            $output->writeln('<comment>ยังไม่มีตาราง</comment>');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($tables as $table) {
            $stmt = Schema::pdo()->query('SELECT COUNT(*) FROM "' . Blueprint::ident($table) . '"');
            $rows[] = [$table, (int) ($stmt ? $stmt->fetchColumn() : 0)];
        }

        $view = new Table($output);
        $view->setHeaders(['Table', 'Rows']);
        $view->setRows($rows);
        $view->render();

        return Command::SUCCESS;
    }
}
