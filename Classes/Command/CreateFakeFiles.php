<?php

declare(strict_types=1);

namespace Plan2net\FakeFal\Command;

use PDO;
use Plan2net\FakeFal\Resource\Core\ResourceFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CreateFakeFiles
 *
 * @author  Ioulia Kondratovitch <ik@plan2.net>
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class CreateFakeFiles extends Command
{
    protected static $defaultName = 'fake-fal:create';

    protected function configure(): void
    {
        $this->setDescription('Create fake files in given storage path');
        $this->addArgument(
            'storageId',
            InputArgument::REQUIRED,
            'Storage ID'
        );
        $this->addArgument(
            'storagePath',
            InputArgument::OPTIONAL,
            'Storage path',
            '/'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storageId = $input->getArgument('storageId');
        $storagePath = $input->getArgument('storagePath');
        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $storage = $resourceFactory->getStorageObject($storageId);

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');
        $fileIdentifiers = $queryBuilder
            ->select('identifier')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('storage', $storageId),
                $queryBuilder->expr()->like('identifier',
                    $queryBuilder->createNamedParameter($queryBuilder->escapeLikeWildcards($storagePath) . '%'))
            )
            ->orderBy('identifier')
            ->execute()->fetchAll(PDO::FETCH_COLUMN);

        foreach ($fileIdentifiers as $fileIdentifier) {
            // Require the storage to get the file,
            // this will create a fake file
            $file = $storage->getFile($fileIdentifier);
            $output->writeln(sprintf('Processing file "%s"', $fileIdentifier));
            unset($file);
        }

        return 0;
    }
}
