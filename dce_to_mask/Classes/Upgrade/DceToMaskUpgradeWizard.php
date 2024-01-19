<?php

declare(strict_types=1);

namespace Hov\DceToMask\Upgrade;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

class DceToMaskUpgradeWizard implements UpgradeWizardInterface
{
    public function getIdentifier(): string
    {
        return 'dceToMask';
    }

    public function getTitle(): string
    {
        return 'DCE to Mask';
    }

    public function getDescription(): string
    {
        return 'Migration for DCE FlexForm to Mask fields';
    }

    public function executeUpdate(): bool
    {
        $flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
        $dceElementsToMigrate = [
            // Example normal fields
            [
                'dceType' => 'dce_element1', // DCE type identifier
                'maskType' => 'mask_element1', // Mask CType
                'columns' => [
                    'header' => 'header', // DCE field identifier => Mask field identifier
                    'text' => 'bodytext',
                    'buttonText' => 'tx_mask_button_text',
                    'buttonLink' => 'tx_mask_button_link',
                    'phoneText' => 'tx_mask_phone_text',
                    'phone' => 'tx_mask_phone',
                ],
            ],
            // Example FlexForm Sections to Mask Inline
            [
                'dceType' => 'dce_repeating', // DCE type identifier
                'maskType' => 'mask_repeating', // Mask CType
                'columns' => [
                    'location' => [ // DCE Section identifier
                        'table' => 'tx_mask_locations', // New Mask inline table name
                        'dceContainerName' => 'container_name', // DCE container name
                        'fields' => [
                            'name' => 'tx_mask_location', // DCE field identifier => Mask field identifier
                            'address' => 'tx_mask_address',
                            'text' => 'tx_mask_text',
                            'detailMap' => 'tx_mask_detail_map',
                            'page' => 'tx_mask_page',
                            'map' => 'tx_mask_map',
                            'x' => 'tx_mask_x',
                            'y' => 'tx_mask_y',
                        ],
                    ],
                    // other normal fields like in example 1.
                    'size' => 'tx_mask_size',
                ],
            ],
        ];

        foreach ($dceElementsToMigrate as $element) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('tt_content');
            $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
            $result = $queryBuilder
                ->select('*')
                ->from('tt_content')
                ->where($queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter($element['dceType'])))
                ->executeQuery();

            // Migrate fields
            while ($row = $result->fetchAssociative()) {
                $flexFormSettings = $flexFormService->convertFlexFormContentToArray($row['pi_flexform'])['settings'];
                foreach ($element['columns'] as $dceFieldName => $maskFieldName) {
                    // FlexForm Sections will be migrated to real inline tables.
                    if (is_array($maskFieldName)) {
                        $table = $maskFieldName['table'];
                        $inlineFieldsMapping = $maskFieldName['fields'];
                        $containerName = $maskFieldName['dceContainerName'];
                        $counter = 0;
                        foreach ($flexFormSettings[$dceFieldName] as $container) {
                            $insertValues = [
                                'pid' => $row['pid'],
                                'crdate' => time(),
                                'tstamp' => time(),
                                'parentid' => $row['uid'],
                                'parenttable' => 'tt_content',
                                'sorting' => $counter,
                                'sys_language_uid' => $row['sys_language_uid'],
                            ];
                            foreach ($container[$containerName] as $dceInlineFieldName => $dceInlineFieldValue) {
                                $insertValues[$inlineFieldsMapping[$dceInlineFieldName]] = $dceInlineFieldValue;
                            }
                            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                                ->getQueryBuilderForTable($table);
                            $queryBuilder
                                ->insert($table)
                                ->values($insertValues)
                                ->executeStatement();

                            $counter++;
                        }
                    } else {
                        if (!isset($flexFormSettings[$dceFieldName])) {
                            continue;
                        }
                        $this->migrateField($maskFieldName, $flexFormSettings[$dceFieldName], $row);
                    }
                }
            }

            // Update CType, clear pi_flexform
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('tt_content');
            $queryBuilder
                ->update('tt_content')
                ->set('CType', $element['maskType'])
                ->set('pi_flexform', '')
                ->where($queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter($element['dceType'])))
                ->executeStatement();
        }
        return true;
    }

    protected function migrateField(string $maskFieldName, string $value, array $row): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $queryBuilder
            ->update('tt_content')
            ->set($maskFieldName, $value)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int)$row['uid'], \PDO::PARAM_INT)))
            ->executeStatement();
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
