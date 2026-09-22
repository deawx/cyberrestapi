<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\MigrateRefreshCommand
 *      ย้อนแล้ว migrate ใหม่ (ไม่ดรอปตารางอื่น)
 *      php deawx migrate:refresh [--step=N] [--seed]
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

final class MigrateRefreshCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:refresh')
            ->setDescription('ย้อน migration แล้วรันใหม่')
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'ย้อนเฉพาะ N ไฟล์ล่าสุด แล้ว migrate ใหม่')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'ย้อนและรันใหม่เฉพาะไฟล์ที่ระบุ')
            ->addOption('seed', null, InputOption::VALUE_NONE, 'รัน db:seed ต่อท้าย');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stepValue = $input->getOption('step');
        $step = ($stepValue === null || $stepValue === false || $stepValue === '')
            ? null
            : (int) $stepValue;

        try {
            $path = $input->getOption('path');
            $result = Migrator::refresh(
                $step,
                is_string($path) && $path !== '' ? $path : null,
            );
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        if ($result['rolled'] === []) {
            $output->writeln('<comment>ไม่มี migration ให้ย้อน</comment>');
        } else {
            foreach ($result['rolled'] as $name) {
                $output->writeln("<info>rolled back:</info> {$name}");
            }
        }

        if ($result['migrated'] === []) {
            $output->writeln('<comment>ไม่มี migration ใหม่</comment>');
        } else {
            foreach ($result['migrated'] as $name) {
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
