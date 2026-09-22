<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\MakeSeederCommand
 *      สร้างไฟล์ Database/Seeders
 *      php deawx make:seeder
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeSeederCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:seeder')
            ->setDescription('สร้าง seeder ใน Database/Seeders')
            ->addArgument('name', InputArgument::REQUIRED, 'ชื่อเช่น UserSeeder')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'ทับไฟล์เดิมถ้ามีอยู่แล้ว');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $class = ClassName::pascal((string) $input->getArgument('name'), 'Seeder');
        if ($class === '') {
            $output->writeln('<error>ระบุชื่อ seeder ไม่ถูกต้อง</error>');

            return Command::FAILURE;
        }

        $className = str_ends_with($class, 'Seeder') ? $class : $class . 'Seeder';
        $dir = dirname(__DIR__, 2) . '/Database/Seeders';
        $path = $dir . '/' . $className . '.php';

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $output->writeln('<error>สร้างโฟลเดอร์ seeders ไม่ได้</error>');

            return Command::FAILURE;
        }

        if (is_file($path) && !$input->getOption('force')) {
            $output->writeln("<error>มีไฟล์อยู่แล้ว: {$path} (ใช้ --force ถ้าต้องการทับ)</error>");

            return Command::FAILURE;
        }

        $stub = <<<PHP
<?php

declare(strict_types=1);

namespace Database\\Seeders;

use Core\\Seeder;

final class {$className} extends Seeder
{
    public function run(): void
    {
        \$this->db()->insert('users', [
            'email' => 'admin@example.com',
            'name' => 'Admin',
        ]);
    }
}

PHP;

        if (file_put_contents($path, $stub) === false) {
            $output->writeln('<error>เขียนไฟล์ไม่สำเร็จ</error>');

            return Command::FAILURE;
        }

        $output->writeln("<info>สร้างแล้ว:</info> Database/Seeders/{$className}.php");

        return Command::SUCCESS;
    }
}
