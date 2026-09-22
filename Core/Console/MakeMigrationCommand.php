<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\MakeMigrationCommand
 *      สร้างไฟล์ Database/migrations
 *      php deawx make:migration
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
use Symfony\Component\Console\Output\OutputInterface;

final class MakeMigrationCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:migration')
            ->setDescription('สร้างไฟล์ migration ใน Database/migrations')
            ->addArgument('name', InputArgument::REQUIRED, 'ชื่อเช่น create_users_table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $raw = (string) $input->getArgument('name');
        $snake = ClassName::snake(ClassName::pascal($raw));
        if ($snake === '') {
            $output->writeln('<error>ระบุชื่อ migration ไม่ถูกต้อง</error>');

            return Command::FAILURE;
        }

        $dir = dirname(__DIR__, 2) . '/Database/migrations';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $output->writeln('<error>สร้างโฟลเดอร์ migrations ไม่ได้</error>');

            return Command::FAILURE;
        }

        $file = date('YmdHis') . '_' . $snake . '.php';
        $path = $dir . '/' . $file;
        $table = self::guessTable($snake);

        $stub = <<<PHP
<?php

declare(strict_types=1);

use Core\\Blueprint;
use Core\\Migration;
use Core\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', static function (Blueprint \$table): void {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('{$table}');
    }
};

PHP;

        if (file_put_contents($path, $stub) === false) {
            $output->writeln('<error>เขียนไฟล์ไม่สำเร็จ</error>');

            return Command::FAILURE;
        }

        $output->writeln("<info>สร้างแล้ว:</info> Database/migrations/{$file}");

        return Command::SUCCESS;
    }

    private static function guessTable(string $snake): string
    {
        if (preg_match('/^create_(.+)_table$/', $snake, $matches) === 1) {
            return $matches[1];
        }

        return $snake;
    }
}
