<?php

declare(strict_types=1);

namespace Plan2net\FakeFal\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ListStorages
 *
 * @author  Ioulia Kondratovitch <ik@plan2.net>
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class ListStorages extends Command
{
    protected static $defaultName = 'fake-fal:list';

    protected function configure(): void
    {
        $this->setDescription('List storages with fake fal mode status');
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_storage');

        $storages = $queryBuilder->select('uid', 'name', 'driver', 'tx_fakefal_enable')
            ->from('sys_file_storage')
            ->execute()->fetchAll();

        foreach ($storages as &$storage) {
            $storage['tx_fakefal_enable'] = (bool) $storage['tx_fakefal_enable'] ? 'enabled' : 'disabled';
        }
        unset($storage);

        $table = new Table($output);
        $table
            ->setHeaders(['ID', 'Name', 'Driver', 'Fake mode'])
            ->setRows(
                $storages
            );
        $table->render();

        return 0;
    }
}
