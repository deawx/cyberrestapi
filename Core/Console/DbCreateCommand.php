<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\DbCreateCommand
 *      สร้างฐานข้อมูลจาก DB_* ใน .env
 *      php deawx db:create
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Console;

use Core\DbSetup;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DbCreateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('db:create')
            ->setDescription('สร้างฐานข้อมูลตาม DB_NAME ใน .env (ถ้ายังไม่มี)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = DbSetup::configFromEnv();

        try {
            DbSetup::assertMysqlFamily($config['type']);
            DbSetup::assertDatabaseName($config['name']);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        try {
            if (DbSetup::databaseExists($config)) {
                $output->writeln("<comment>มีฐานข้อมูลอยู่แล้ว:</comment> {$config['name']}");

                return Command::SUCCESS;
            }

            DbSetup::createDatabase($config);
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln("<info>สร้างฐานข้อมูลแล้ว:</info> {$config['name']}");

        return Command::SUCCESS;
    }
}
