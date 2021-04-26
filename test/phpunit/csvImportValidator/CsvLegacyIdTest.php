<?php

use org\bovigo\vfs\vfsStream;

/**
 * @internal
 * @covers \csvLegacyIdTest
 */
class CsvLegacyIdTest extends \PHPUnit\Framework\TestCase
{
    protected $vdbcon;
    protected $context;

    public function setUp(): void
    {
        $this->context = sfContext::getInstance();
        $this->vdbcon = $this->createMock(DebugPDO::class);

        $this->csvHeader = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture';
        $this->csvHeaderMissingLegacyId = 'parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture';

        $this->csvData = [
            // Note: leading and trailing whitespace in first row is intentional
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataDuplicatedLegacyId = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"B10101", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataMissingLegacyId = [
            '" DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","Chemise","","","","fr"',
            '"DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        // define virtual file system
        $directory = [
            'unix_csv_without_utf8_bom.csv' => $this->csvHeader."\n".implode("\n", $this->csvData),

            'unix_csv_with_duplicated_legacy_id.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataDuplicatedLegacyId),
            'unix_csv_missing_legacy_id.csv' => $this->csvHeaderMissingLegacyId."\n".implode("\n", $this->csvDataMissingLegacyId),
        ];

        $this->vfs = vfsStream::setup('root', null, $directory);

        $this->ormClasses = [
            'QubitFlatfileImport' => \AccessToMemory\test\mock\QubitFlatfileImport::class,
            'QubitObject' => \AccessToMemory\test\mock\QubitObject::class,
        ];
    }

    /**
     * @dataProvider csvValidatorTestProvider
     *
     * Generic test - options and expected results from csvValidatorTestProvider()
     *
     * @param mixed $options
     */
    public function testCsvValidator($options)
    {
        $filename = $this->vfs->url().$options['filename'];
        $validatorOptions = isset($options['validatorOptions']) ? $options['validatorOptions'] : null;

        $csvValidator = new CsvImportValidator($this->context, null, $validatorOptions);
        $this->runValidator($csvValidator, $filename, $options['csvValidatorClasses']);
        $result = $csvValidator->getResultsByFilenameTestname($filename, $options['testname']);

        $this->assertSame($options[CsvBaseValidator::TEST_TITLE], $result[CsvBaseValidator::TEST_TITLE]);
        $this->assertSame($options[CsvBaseValidator::TEST_STATUS], $result[CsvBaseValidator::TEST_STATUS]);
        $this->assertSame($options[CsvBaseValidator::TEST_RESULTS], $result[CsvBaseValidator::TEST_RESULTS]);
        $this->assertSame($options[CsvBaseValidator::TEST_DETAIL], $result[CsvBaseValidator::TEST_DETAIL]);
    }

    public function csvValidatorTestProvider()
    {
        $vfsUrl = 'vfs://root';

        return [
            /*
             * Test CsvLegacyIdValidator.class.php
             *
             * Tests:
             * - legacyId col missing
             * - legacyId not populated
             * - legacyId populated
             * - duplicate legacyId
             */
            [
                'CsvLegacyTest-LegacyIdColumnMissing' => [
                    'csvValidatorClasses' => ['CsvLegacyIdValidator' => CsvLegacyIdValidator::class],
                    'filename' => '/unix_csv_missing_legacy_id.csv',
                    'testname' => 'CsvLegacyIdValidator',
                    CsvBaseValidator::TEST_TITLE => CsvLegacyIdValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvLegacyIdValidator::RESULT_WARN,
                    CsvBaseValidator::TEST_RESULTS => [
                        '\'legacyId\' column not present. Future CSV updates may not match these records.',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvLegacyTest-LegacyIdColumnPresent' => [
                    'csvValidatorClasses' => ['CsvLegacyIdValidator' => CsvLegacyIdValidator::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvLegacyIdValidator',
                    CsvBaseValidator::TEST_TITLE => CsvLegacyIdValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvLegacyIdValidator::RESULT_WARN,
                    CsvBaseValidator::TEST_RESULTS => [
                        '\'legacyId\' values are all unique.',
                        'Rows with empty \'legacyId\' column: 2',
                        'Future CSV updates may not match these records.',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                        ',,,Chemise,,,,fr',
                        ',DJ003,ID4,Title Four,,,,en',
                    ],
                ],
            ],

            [
                'CsvLegacyTest-DuplicatedLegacyId' => [
                    'csvValidatorClasses' => ['CsvLegacyIdValidator' => CsvLegacyIdValidator::class],
                    'filename' => '/unix_csv_with_duplicated_legacy_id.csv',
                    'testname' => 'CsvLegacyIdValidator',
                    CsvBaseValidator::TEST_TITLE => CsvLegacyIdValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvLegacyIdValidator::RESULT_ERROR,
                    CsvBaseValidator::TEST_RESULTS => [
                        'Rows with non-unique \'legacyId\' values: 1',
                        'Rows with empty \'legacyId\' column: 1',
                        'Future CSV updates may not match these records.',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                        ',,,Chemise,,,,fr',
                        'Non-unique \'legacyId\' values: B10101',
                    ],
                ],
            ],
        ];
    }

    // Generic Validation
    protected function runValidator($csvValidator, $filenames, $tests, $verbose = true)
    {
        $csvValidator->setCsvTests($tests);
        $csvValidator->setFilenames(explode(',', $filenames));
        $csvValidator->setVerbose($verbose);
        $csvValidator->setOrmClasses($this->ormClasses);

        return $csvValidator->validate();
    }
}
