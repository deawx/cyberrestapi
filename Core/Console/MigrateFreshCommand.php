<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\MigrateFreshCommand
 *      ลบตารางทั้งหมดแล้ว migrate ใหม่
 *      php deawx migrate:fresh [--seed]
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Console;

use Core\Migrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MigrateFreshCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:fresh')
            ->setDescription('ลบตารางทั้งหมด แล้วรัน migration ใหม่')
            ->addOption('seed', null, InputOption::VALUE_NONE, 'รัน db:seed ต่อท้าย');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $applied = Migrator::fresh();
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        if ($applied === []) {
            $output->writeln('<comment>ไม่มี migration</comment>');
        } else {
            foreach ($applied as $name) {
                $output->writeln("<info>migrated:</info> {$name}");
            }
        }

        if (!$input->getOption('seed')) {
            return Command::SUCCESS;
        }

        $app = $this->getApplication();
        if ($app === null) {
            return Command::FAILURE;
        }

        return $app->find('db:seed')->run(new ArrayInput(['command' => 'db:seed']), $output);
    }
}
