<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\MakeModelCommand
 *      สร้างไฟล์ Apps/Models
 *      php deawx make:model รองรับ --controller
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeModelCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:model')
            ->setDescription('สร้าง model ใน Apps/Models')
            ->addArgument('name', InputArgument::REQUIRED, 'ชื่อคลาส เช่น user, order_item หรือ User')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'ทับไฟล์เดิมถ้ามีอยู่แล้ว')
            ->addOption('controller', 'c', InputOption::VALUE_NONE, 'สร้าง controller คู่กันด้วย');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $className = ClassName::pascal((string) $input->getArgument('name'), 'Model');

        if ($className === '') {
            $output->writeln('<error>ระบุชื่อ model ไม่ถูกต้อง</error>');
            return Command::FAILURE;
        }

        $table = ClassName::snake($className);
        $dir = dirname(__DIR__, 2) . '/Apps/Models';
        $path = $dir . '/' . $className . '.php';

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $output->writeln('<error>สร้างโฟลเดอร์ Models ไม่ได้</error>');
            return Command::FAILURE;
        }

        if (is_file($path) && !$input->getOption('force')) {
            $output->writeln("<error>มีไฟล์อยู่แล้ว: {$path} (ใช้ --force ถ้าต้องการทับ)</error>");
            return Command::FAILURE;
        }

        $stub = <<<PHP
<?php

declare(strict_types=1);
namespace App\\Models;
use Core\\Model;

final class {$className} extends Model
{
    protected static string \$table = '{$table}';
    protected static array \$fillable = [];
    protected static bool \$timestamps = true;

}

PHP;

        if (file_put_contents($path, $stub) === false) {
            $output->writeln('<error>เขียนไฟล์ไม่สำเร็จ</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>สร้างแล้ว:</info> Apps/Models/{$className}.php");

        if ($input->getOption('controller')) {
            $app = $this->getApplication();
            if ($app === null) {
                return Command::FAILURE;
            }

            $params = ['command' => 'make:controller', 'name' => $className];
            if ($input->getOption('force')) {
                $params['--force'] = true;
            }

            return $app->find('make:controller')->run(new ArrayInput($params), $output);
        }

        return Command::SUCCESS;
    }
}
