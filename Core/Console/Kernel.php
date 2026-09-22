<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Console\Kernel
 *      จุดเข้า CLI ของ php deawx
 *      ลงทะเบียนคำสั่ง make, migrate และ db:*
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Kernel extends Application
{
    public function __construct(string $version)
    {
        parent::__construct('CyberRestAPI', $version);
        $this->addCommand(new KeyGenerateCommand());
        $this->addCommand(new MakeControllerCommand());
        $this->addCommand(new MakeModelCommand());
        $this->addCommand(new MakeMigrationCommand());
        $this->addCommand(new MakeSeederCommand());
        $this->addCommand(new MigrateCommand());
        $this->addCommand(new MigrateRollbackCommand());
        $this->addCommand(new MigrateResetCommand());
        $this->addCommand(new MigrateRefreshCommand());
        $this->addCommand(new MigrateStatusCommand());
        $this->addCommand(new MigrateFreshCommand());
        $this->addCommand(new DbSeedCommand());
        $this->addCommand(new DbCreateCommand());
        $this->addCommand(new DbTablesCommand());
        $this->addCommand(new DbColumnsCommand());
        $this->addCommand(new InstallCommand());
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        if ($this->shouldShowLogo($output)) {
            if ($this->shouldClearScreen($input)) {
                $this->clearScreen();
            }
            $this->writeLogo($output);
        }

        return parent::doRun($input, $output);
    }

    private function shouldShowLogo(OutputInterface $output): bool
    {
        return !$output->isQuiet();
    }

    /**
     * เคลียร์จอเฉพาะตอนเปิด list / help / version — คำสั่งอื่นไม่ลบประวัติเทอร์มินัล
     */
    private function shouldClearScreen(InputInterface $input): bool
    {
        if ($input->hasParameterOption(['-V', '--version'], true)) {
            return true;
        }

        $command = $input->getFirstArgument();

        return $command === null || $command === 'list' || $command === 'help';
    }

    private function clearScreen(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            popen('cls', 'w');
            return;
        }

        passthru('clear');
    }

    private function writeLogo(OutputInterface $output): void
    {
        $logo = <<<'LOGO'
┏━╸╻ ╻┏┓ ┏━╸┏━┓╺┳╸╻ ╻┏━┓╻   ┏━┓┏━┓╻
┃  ┗┳┛┣┻┓┣╸ ┣┳┛ ┃ ┣━┫┣━┫┃   ┣━┫┣━┛┃
┗━╸ ╹ ┗━┛┗━╸╹┗╸ ╹ ╹ ╹╹ ╹╹   ╹ ╹╹  ╹
LOGO;

        foreach (explode("\n", $logo) as $line) {
            $output->writeln("<fg=cyan>{$line}</>");
        }

        $output->writeln(sprintf(
            '  <info>%s</info>  <comment>v%s</comment>  ·  deawx',
            $this->getName(),
            $this->getVersion(),
        ));
        $output->writeln('');
    }
}
