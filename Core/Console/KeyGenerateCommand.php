<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\KeyGenerateCommand
 *      สร้าง ENCRYPTION_KEY และ JWT_SECRET ใน .env
 *      php deawx key:generate
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Console;

use Core\Cipher;
use Core\EnvWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class KeyGenerateCommand extends Command
{
    /**
     * @return array{ENCRYPTION_KEY: string, JWT_SECRET: string}
     */
    public static function makeKeys(): array
    {
        return [
            'ENCRYPTION_KEY' => bin2hex(random_bytes(32)),
            'JWT_SECRET' => bin2hex(random_bytes(32)),
        ];
    }

    protected function configure(): void
    {
        $this
            ->setName('key:generate')
            ->setDescription('สร้าง ENCRYPTION_KEY และ JWT_SECRET ลงไฟล์ .env')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'ทับคีย์ที่มีค่าอยู่แล้ว')
            ->addOption('show', null, InputOption::VALUE_NONE, 'แสดงคีย์ที่สุ่ม โดยไม่เขียนไฟล์');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $keys = self::makeKeys();

        if ($input->getOption('show')) {
            foreach ($keys as $name => $value) {
                $output->writeln("<info>{$name}=</info>{$value}");
            }

            return Command::SUCCESS;
        }

        $root = dirname(__DIR__, 2);
        $env = $root . DIRECTORY_SEPARATOR . '.env';
        $example = $root . DIRECTORY_SEPARATOR . '.env.example';

        if (!is_file($env)) {
            if (!is_file($example)) {
                $output->writeln('<error>ไม่พบ .env และ .env.example</error>');

                return Command::FAILURE;
            }

            if (!copy($example, $env)) {
                $output->writeln('<error>คัดลอก .env.example เป็น .env ไม่ได้</error>');

                return Command::FAILURE;
            }

            $output->writeln('<info>สร้าง .env จาก .env.example</info>');
        }

        $force = (bool) $input->getOption('force');

        try {
            foreach ($keys as $name => $value) {
                if (!$force && EnvWriter::isFilled($env, $name)) {
                    $output->writeln("<comment>ข้าม {$name} เพราะมีค่าแล้ว (ใช้ --force ถ้าต้องการสุ่มใหม่)</comment>");
                    continue;
                }

                EnvWriter::set($env, $name, $value);
                $output->writeln("<info>เขียนแล้ว:</info> {$name}");
            }
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $encryption = EnvWriter::read($env, 'ENCRYPTION_KEY') ?? '';
        try {
            new Cipher($encryption);
        } catch (\Throwable $e) {
            $output->writeln('<error>ENCRYPTION_KEY ใช้กับ Cipher ไม่ได้: ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
