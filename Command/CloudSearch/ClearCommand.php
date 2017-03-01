<?php


namespace SkyDiablo\AWSBundle\Command\CloudSearch;

use Aws\CloudSearchDomain\CloudSearchDomainClient;
use Aws\Result;
use SkyDiablo\AWSBundle\DependencyInjection\SkyDiabloAWSExtension;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Volker von Hoesslin <volker@oksnap.me>
 * Class ClearCommand
 */
class ClearCommand extends ContainerAwareCommand
{

    const NAME = 'skydiablo.aws.cloudsearch.clear';
    const ARGUMENT_INDEX = 'index';
    const OPTION_BATCH_SIZE = 'batch-size';
    const OPTION_SLEEP = 'sleep';

    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->addArgument(self::ARGUMENT_INDEX, InputArgument::REQUIRED)
            ->addOption(self::OPTION_BATCH_SIZE, null, InputOption::VALUE_OPTIONAL, 1000)
            ->addOption(self::OPTION_SLEEP, null, InputOption::VALUE_OPTIONAL, 'Sleep time in microseconds between batches', 5000);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('CloudSearch Clear');

        $index = $input->getArgument(self::ARGUMENT_INDEX);

        /** @var CloudSearchDomainClient $csSearchClient */
        $csSearchClient = $this->getContainer()->get(
            sprintf('%s.%s', SkyDiabloAWSExtension::SERVICE_ID_COMMAND_CLOUD_SEARCH_CLIENT_SEARCH, $index),
            ContainerInterface::NULL_ON_INVALID_REFERENCE
        );

        /** @var CloudSearchDomainClient $csSearchClient */
        $csDocClient = $this->getContainer()->get(
            sprintf('%s.%s', SkyDiabloAWSExtension::SERVICE_ID_COMMAND_CLOUD_SEARCH_CLIENT_DOC, $index),
            ContainerInterface::NULL_ON_INVALID_REFERENCE
        );

        if (!$csDocClient || !$csSearchClient) {
            $io->error('CloudSearch client missing!');
            return 100;
        }

        $init = false;
        $batchSize = abs((int)$input->getOption(self::OPTION_BATCH_SIZE) ?: 1000);
        $sleep = abs((int)$input->getOption(self::OPTION_SLEEP) ?: 5000);

        $io->text('running...');

        do {
            $result = $csSearchClient->search([
                'query' => 'matchall',
                'queryParser' => 'structured',
                'size' => $batchSize,
                'return' => '_no_fields', // only IDs
            ]);

            if (!$init) {
                $init = true;
                $io->text(sprintf('Found %d elements', $result->search('hits.found')));
                $io->progressStart();
            }

            $parts = array_map(function ($element) {
                return sprintf('<delete id="%s" />', $element['id']);
            }, $result->search('hits.hit') ?? []);

            if ($parts) {
                $documents = sprintf('<batch>%s</batch>', implode('', $parts));
                $result = $csDocClient->uploadDocuments([
                    'documents' => $documents,
                    'contentType' => 'application/xml'
                ]);
                if ($result instanceof Result) {
                    $io->progressAdvance($result->search('deletes'));
                    usleep($sleep);
                }
            }

        } while ($parts);

        $io->progressFinish();

        $io->text('done');
    }


}