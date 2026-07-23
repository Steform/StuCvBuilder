<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\Employment\CompanyCvProfileCloneException;
use App\Repository\TrackedCompanyRepository;
use App\Service\Employment\CompanyCvProfileCloneService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @brief Re-clone a custom company CvProfile from the current global CV (assets included).
 */
#[AsCommand(
    name: 'app:employment:reclone-company-cv',
    description: 'Re-clone a company custom CV profile from the global CV',
)]
final class RecloneCompanyCvCommand extends Command
{
    /**
     * @brief Wire reclone command dependencies.
     *
     * @param TrackedCompanyRepository $trackedCompanyRepository Company repository.
     * @param CompanyCvProfileCloneService $companyCvProfileCloneService Clone service.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function __construct(
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
        private readonly CompanyCvProfileCloneService $companyCvProfileCloneService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('code', InputArgument::REQUIRED, 'Tracked company code (12 characters)');
    }

    /**
     * @brief Execute reclone for one company code.
     *
     * @param InputInterface $input CLI input.
     * @param OutputInterface $output CLI output.
     * @return int Exit code.
     * @date 2026-07-23
     * @author Stephane H.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = trim((string) $input->getArgument('code'));
        $company = $this->trackedCompanyRepository->findOneBy(['code' => $code]);
        if ($company === null) {
            $output->writeln(sprintf('<error>Company not found for code "%s".</error>', $code));

            return Command::FAILURE;
        }

        try {
            $this->companyCvProfileCloneService->switchToSynced($company);
            $profile = $this->companyCvProfileCloneService->switchToCustom($company);
        } catch (CompanyCvProfileCloneException $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>Recloned company %s (%s) to CvProfile #%d.</info>',
            $code,
            $company->getName(),
            (int) $profile->getId(),
        ));

        return Command::SUCCESS;
    }
}
