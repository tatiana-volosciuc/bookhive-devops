<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\OrphanPhotoCleaner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-orphan-photos',
    description: 'Deletes author photos in S3 that are not referenced by any author.',
)]
final class CleanupOrphanPhotosCommand extends Command
{
    public function __construct(
        private readonly OrphanPhotoCleaner $cleaner,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List orphaned photos without deleting them.')
            ->addOption('min-age-hours', null, InputOption::VALUE_REQUIRED, 'Only touch files older than this many hours.', '24');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $minAgeHours = filter_var($input->getOption('min-age-hours'), FILTER_VALIDATE_INT);
        if ($minAgeHours === false || $minAgeHours < 0) {
            $io->error('--min-age-hours must be a non-negative integer.');

            return Command::INVALID;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $olderThan = new \DateTimeImmutable(sprintf('-%d hours', $minAgeHours));

        $this->logger->info('Orphan photo cleanup started.', [
            'dry_run' => $dryRun,
            'min_age_hours' => $minAgeHours,
        ]);

        try {
            $keys = $this->cleaner->cleanup($olderThan, $dryRun);
        } catch (\Throwable $exception) {
            $this->logger->error('Orphan photo cleanup failed.', ['exception' => $exception]);
            $io->error('Cleanup failed: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        foreach ($keys as $key) {
            $io->writeln(($dryRun ? 'Would delete: ' : 'Deleted: ') . $key);
        }

        $this->logger->info('Orphan photo cleanup finished.', [
            'dry_run' => $dryRun,
            'count' => count($keys),
        ]);

        $io->success(sprintf('%s %d orphaned photo(s).', $dryRun ? 'Found' : 'Deleted', count($keys)));

        return Command::SUCCESS;
    }
}
