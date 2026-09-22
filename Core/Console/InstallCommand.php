<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\InstallCommand
 *      ตั้งค่า DB แล้ว migrate (โฟกัสหลัง create-project)
 *      php deawx install [--seed] [-y]
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Console;

use Core\DbSetup;
use Core\EnvWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

final class InstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('install')
            ->setDescription('ตั้งค่าฐานข้อมูล ทดสอบการเชื่อมต่อ สร้าง DB แล้ว migrate')
            ->addOption('seed', null, InputOption::VALUE_NONE, 'รัน db:seed หลัง migrate')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'ไม่ถาม — อ่าน .env แล้ว migrate + seed (ไม่ลบตาราง)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $root = dirname(__DIR__, 2);
        $env = $root . DIRECTORY_SEPARATOR . '.env';
        $example = $root . DIRECTORY_SEPARATOR . '.env.example';

        if (!is_file($env)) {
            if (!is_file($example)) {
                $io->error('ไม่พบ .env และ .env.example');

                return Command::FAILURE;
            }
            if (!copy($example, $env)) {
                $io->error('คัดลอก .env.example เป็น .env ไม่ได้');

                return Command::FAILURE;
            }
            $io->success('สร้าง .env จาก .env.example');
        }

        $yes = (bool) $input->getOption('yes');
        if ($yes) {
            $input->setInteractive(false);
        }
        $ask = $input->isInteractive();

        $defaults = DbSetup::mergeConfig([
            'host' => EnvWriter::read($env, 'DB_HOST') ?? ($_ENV['DB_HOST'] ?? '127.0.0.1'),
            'port' => EnvWriter::read($env, 'DB_PORT') ?? ($_ENV['DB_PORT'] ?? '3306'),
            'name' => EnvWriter::read($env, 'DB_NAME') ?? ($_ENV['DB_NAME'] ?? 'cyberfastapi'),
            'user' => EnvWriter::read($env, 'DB_USER') ?? ($_ENV['DB_USER'] ?? 'root'),
            'password' => EnvWriter::read($env, 'DB_PASSWORD') ?? ($_ENV['DB_PASSWORD'] ?? ''),
            'charset' => EnvWriter::read($env, 'DB_CHARSET') ?? ($_ENV['DB_CHARSET'] ?? 'utf8mb4'),
            'collation' => EnvWriter::read($env, 'DB_COLLATION') ?? ($_ENV['DB_COLLATION'] ?? 'utf8mb4_general_ci'),
            'type' => EnvWriter::read($env, 'DB_TYPE') ?? ($_ENV['DB_TYPE'] ?? 'mysql'),
        ]);

        if ($ask) {
            $io->title('ติดตั้งฐานข้อมูล CyberRestAPI');
            $config = $this->askConfig($io, $input, $output, $defaults);
            try {
                $this->writeEnv($env, $config);
            } catch (\Throwable $e) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }
            $io->writeln('<info>บันทึก DB_* ลง .env แล้ว</info>');
        } else {
            $config = $defaults;
            $mode = $yes ? '-y' : '--no-interaction';
            $io->writeln("<comment>โหมด {$mode}:</comment> อ่าน DB_* จาก .env");
        }

        DbSetup::applyEnv($config);

        $reachable = null;
        while (true) {
            $reachable = DbSetup::ensureReachable($config, createMissing: true);
            if ($reachable['ok']) {
                break;
            }

            $io->error('เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $reachable['message']);
            if (!$ask) {
                return Command::FAILURE;
            }

            if (!$io->confirm('แก้ไขค่า DB แล้วลองใหม่?', true)) {
                $io->warning('ยกเลิก install — ยังไม่ได้ migrate');

                return Command::FAILURE;
            }

            $config = $this->askConfig($io, $input, $output, $config);
            try {
                $this->writeEnv($env, $config);
            } catch (\Throwable $e) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }
            DbSetup::applyEnv($config);
        }

        if ($reachable['created']) {
            $io->success("สร้างฐานข้อมูล {$config['name']} แล้ว");
        } else {
            $io->writeln("<info>เชื่อมต่อได้:</info> {$config['host']}/{$config['name']}");
        }

        $tables = DbSetup::tables($config);
        $fresh = false;
        if ($tables !== []) {
            $io->writeln('<comment>พบตารางในฐานแล้ว:</comment> ' . implode(', ', $tables));
            if ($ask) {
                $fresh = $io->confirm(
                    'พบตารางในฐานนี้ จะ migrate:fresh (ลบข้อมูลทั้งหมด) ไหม?',
                    false,
                );
                if (!$fresh) {
                    $io->warning('ยกเลิก install — ไม่ได้ migrate / seed');

                    return Command::SUCCESS;
                }
            } else {
                $io->writeln('<comment>โหมดอัตโนมัติ:</comment> ไม่ลบตาราง — รัน migrate ปกติ');
            }
        }

        $app = $this->getApplication();
        if ($app === null) {
            return Command::FAILURE;
        }

        if ($fresh) {
            $code = $app->find('migrate:fresh')->run(
                new ArrayInput(['command' => 'migrate:fresh']),
                $output,
            );
        } else {
            $code = $app->find('migrate')->run(
                new ArrayInput(['command' => 'migrate']),
                $output,
            );
        }

        if ($code !== Command::SUCCESS) {
            return $code;
        }

        $wantSeed = (bool) $input->getOption('seed') || $yes;
        if (!$wantSeed && $ask) {
            $wantSeed = $io->confirm('รัน db:seed (user ทดลอง admin/user) ด้วยหรือไม่?', true);
        }

        if ($wantSeed) {
            return $app->find('db:seed')->run(
                new ArrayInput(['command' => 'db:seed']),
                $output,
            );
        }

        $io->success('ติดตั้งฐานข้อมูลเสร็จแล้ว');

        return Command::SUCCESS;
    }

    /**
     * @param array{
     *     type: string,
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     password: string,
     *     charset: string,
     *     collation: string
     * } $defaults
     * @return array{
     *     type: string,
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     password: string,
     *     charset: string,
     *     collation: string
     * }
     */
    private function askConfig(
        SymfonyStyle $io,
        InputInterface $input,
        OutputInterface $output,
        array $defaults,
    ): array {
        $helper = $this->getHelper('question');

        $type = $this->ask($helper, $input, $output, 'DB_TYPE', $defaults['type'] !== '' ? $defaults['type'] : 'mysql');
        $hostDefault = $defaults['host'] !== '' ? $defaults['host'] : '127.0.0.1';
        $host = $this->ask($helper, $input, $output, 'DB_HOST', $hostDefault);
        $port = $this->ask($helper, $input, $output, 'DB_PORT', (string) ($defaults['port'] ?: 3306));
        $name = $this->ask($helper, $input, $output, 'DB_NAME', $defaults['name'] !== '' ? $defaults['name'] : 'cyberfastapi');
        $user = $this->ask($helper, $input, $output, 'DB_USER', $defaults['user'] !== '' ? $defaults['user'] : 'root');
        $password = $this->askPassword($helper, $input, $output, $defaults['password']);
        $charset = $this->ask($helper, $input, $output, 'DB_CHARSET', $defaults['charset'] !== '' ? $defaults['charset'] : 'utf8mb4');
        $collationDefault = DbSetup::collationForCharset(
            $charset,
            $defaults['collation'] !== '' ? $defaults['collation'] : null,
        );
        $collation = $this->ask($helper, $input, $output, 'DB_COLLATION', $collationDefault);

        return DbSetup::mergeConfig([
            'type' => $type,
            'host' => $host,
            'port' => $port,
            'name' => $name,
            'user' => $user,
            'password' => $password,
            'charset' => $charset,
            'collation' => $collation,
        ]);
    }

    private function ask(
        mixed $helper,
        InputInterface $input,
        OutputInterface $output,
        string $label,
        string $default,
    ): string {
        $question = new Question("{$label} [{$default}]: ", $default);
        $answer = $helper->ask($input, $output, $question);

        return is_string($answer) ? $answer : $default;
    }

    /**
     * ถามรหัสผ่านแบบซ่อน — ไม่โชว์ค่าใน .env เป็น default บนหน้าจอ
     */
    private function askPassword(
        mixed $helper,
        InputInterface $input,
        OutputInterface $output,
        string $existing,
    ): string {
        $keep = $existing !== '';
        $prompt = $keep
            ? 'DB_PASSWORD [Enter = ใช้ค่าเดิมใน .env / พิมพ์ใหม่เพื่อเปลี่ยน]: '
            : 'DB_PASSWORD: ';

        $question = new Question($prompt, $keep ? '__KEEP__' : '');
        $question->setHidden(true);
        $question->setHiddenFallback(true);

        $answer = $helper->ask($input, $output, $question);
        if (!is_string($answer) || $answer === '__KEEP__') {
            return $existing;
        }

        return $answer;
    }

    /**
     * @param array{
     *     type: string,
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     password: string,
     *     charset: string,
     *     collation: string
     * } $config
     */
    private function writeEnv(string $path, array $config): void
    {
        $pairs = [
            'DB_TYPE' => $config['type'],
            'DB_HOST' => $config['host'],
            'DB_PORT' => (string) $config['port'],
            'DB_NAME' => $config['name'],
            'DB_USER' => $config['user'],
            'DB_PASSWORD' => $config['password'],
            'DB_CHARSET' => $config['charset'],
            'DB_COLLATION' => $config['collation'],
        ];

        foreach ($pairs as $key => $value) {
            EnvWriter::set($path, $key, DbSetup::formatEnvValue($value));
        }
    }
}
