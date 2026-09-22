<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\DbColumnsCommand
 *      แสดงคอลัมน์ของตารางที่ระบุ
 *      php deawx db:columns users
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Console;

use Core\Schema;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DbColumnsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('db:columns')
            ->setDescription('แสดงชื่อฟิลด์ในตาราง')
            ->addArgument('table', InputArgument::REQUIRED, 'ชื่อตาราง เช่น users');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = (string) $input->getArgument('table');

        try {
            $columns = Schema::columns($table);
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $view = new Table($output);
        $view->setHeaders(['Field', 'Type', 'Null', 'Key', 'Default', 'Extra']);
        foreach ($columns as $column) {
            $view->addRow([
                $column['name'],
                $column['type'],
                $column['nullable'] ? 'YES' : 'NO',
                $column['key'] !== '' ? $column['key'] : '',
                $column['default'] ?? 'NULL',
                $column['extra'],
            ]);
        }
        $view->render();

        return Command::SUCCESS;
    }
}
