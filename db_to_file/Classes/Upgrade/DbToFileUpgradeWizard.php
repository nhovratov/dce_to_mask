<?php

declare(strict_types=1);

namespace Hov\DbToFile\Upgrade;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

class DbToFileUpgradeWizard implements UpgradeWizardInterface
{
    public function getIdentifier(): string
    {
        return 'dbToFile';
    }

    public function getTitle(): string
    {
        return 'DB sys_file to file reference';
    }

    public function getDescription(): string
    {
        return 'This migrates the old DCE type group with allow "sys_file" to FAL';
    }

    public function executeUpdate(): bool
    {
        $migrations = [
            [
                'table' => 'tx_mask_my_table', // The Mask table. Either tt_content or the Inline table name.
                'sourceField' => 'tx_mask_dce_image', // DCE field identifier
                'targetField' => 'tx_mask_mask_image', // Mask field identifier
            ],
            // ...
        ];

        foreach ($migrations as $migration) {
            $table = $migration['table'];
            $sourceField = $migration['sourceField'];
            $targetField = $migration['targetField'];
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable($table);
            $result = $queryBuilder
                ->select('uid', 'pid', 'sys_language_uid', $sourceField)
                ->from($table)
                ->where($queryBuilder->expr()->neq($sourceField, $queryBuilder->createNamedParameter('')))
                ->executeQuery();
            while ($row = $result->fetchAssociative()) {
                $sysFileUids = GeneralUtility::intExplode(',', $row[$sourceField]);
                $count = count($sysFileUids);
                foreach ($sysFileUids as $sorting => $sysFileUid) {
                    // Insert sys_file_reference and add count in targetField.
                    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getQueryBuilderForTable('sys_file_reference');
                    $queryBuilder
                        ->insert('sys_file_reference')
                        ->values(
                            [
                                'uid_local' => $sysFileUid,
                                'uid_foreign' => $row['uid'],
                                'fieldname' => $targetField,
                                'tablenames' => $table,
                                'sorting_foreign' => $sorting,
                                'pid' => $row['pid'],
                                'tstamp' => time(),
                                'crdate' => time(),
                                'sys_language_uid' => $row['sys_language_uid'],
                            ]
                        )
                        ->executeStatement();
                }
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable($table);
                $queryBuilder
                    ->update($table)
                    ->set($targetField, $count)
                    ->executeStatement();
            }
        }
        return true;
    }

    public function updateNecessary(): bool
    {
        return true;
    }

    public function getPrerequisites(): array
    {
        return [];
    }
}
