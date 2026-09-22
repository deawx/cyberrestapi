<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\DbSeedCommand
 *      รัน seeder
 *      php deawx db:seed [--class=]
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Console;

use Core\Seeder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DbSeedCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('db:seed')
            ->setDescription('รัน seeder')
            ->addOption('class', null, InputOption::VALUE_REQUIRED, 'ชื่อคลาส seeder', 'DatabaseSeeder');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getOption('class');
        $class = str_contains($name, '\\') ? $name : 'Database\\Seeders\\' . $name;

        if (!class_exists($class)) {
            $output->writeln("<error>ไม่พบ seeder: {$class}</error>");

            return Command::FAILURE;
        }

        $seeder = new $class();
        if (!$seeder instanceof Seeder) {
            $output->writeln('<error>คลาสนี้ไม่ใช่ Core\\Seeder</error>');

            return Command::FAILURE;
        }

        try {
            $seeder->run();
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln("<info>seeded:</info> {$class}");

        return Command::SUCCESS;
    }
}
