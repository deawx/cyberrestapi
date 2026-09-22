<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\MakeControllerCommand
 *      สร้างไฟล์ Apps/Controllers
 *      php deawx make:controller รองรับ --model
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

final class MakeControllerCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:controller')
            ->setDescription('สร้าง controller ใน Apps/Controllers')
            ->addArgument('name', InputArgument::REQUIRED, 'ชื่อคลาส เช่น user, order_item หรือ UserController')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'ทับไฟล์เดิมถ้ามีอยู่แล้ว')
            ->addOption('model', 'm', InputOption::VALUE_NONE, 'สร้าง model คู่กันด้วย');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $class = ClassName::pascal((string) $input->getArgument('name'), 'Controller');

        if ($class === '') {
            $output->writeln('<error>ระบุชื่อ controller ไม่ถูกต้อง</error>');
            return Command::FAILURE;
        }

        $className = $class . 'Controller';
        $dir = dirname(__DIR__, 2) . '/Apps/Controllers';
        $path = $dir . '/' . $className . '.php';

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $output->writeln('<error>สร้างโฟลเดอร์ Controllers ไม่ได้</error>');
            return Command::FAILURE;
        }

        if (is_file($path) && !$input->getOption('force')) {
            $output->writeln("<error>มีไฟล์อยู่แล้ว: {$path} (ใช้ --force ถ้าต้องการทับ)</error>");
            return Command::FAILURE;
        }

        $stub = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Controllers;

use Core\\Controller;
use Core\\Request;

final class {$className} extends Controller
{
    public function index(Request \$request): never
    {
        \$this->json(['message' => '{$className}']);
    }
}

PHP;

        if (file_put_contents($path, $stub) === false) {
            $output->writeln('<error>เขียนไฟล์ไม่สำเร็จ</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>สร้างแล้ว:</info> Apps/Controllers/{$className}.php");

        if ($input->getOption('model')) {
            return $this->makePair('make:model', $class, (bool) $input->getOption('force'), $output);
        }

        return Command::SUCCESS;
    }

    private function makePair(string $command, string $name, bool $force, OutputInterface $output): int
    {
        $app = $this->getApplication();
        if ($app === null) {
            return Command::FAILURE;
        }

        $params = ['command' => $command, 'name' => $name];
        if ($force) {
            $params['--force'] = true;
        }

        return $app->find($command)->run(new ArrayInput($params), $output);
    }
}
