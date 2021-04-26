<?php

use org\bovigo\vfs\vfsStream;

/**
 * @internal
 * @covers \csvDuplicateColumnNameTest
 */
class CsvDuplicateColumnNameTest extends \PHPUnit\Framework\TestCase
{
    protected $vdbcon;
    protected $context;

    public function setUp(): void
    {
        $this->context = sfContext::getInstance();
        $this->vdbcon = $this->createMock(DebugPDO::class);

        $this->csvHeader = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture';
        $this->csvHeaderDuplicatedRepository = 'legacyId,parentId,identifier,title,repository,extentAndMedium,repository,culture';
        $this->csvHeaderDuplicatedRepositoryCulture = 'legacyId,parentId,culture,title,repository,culture,repository,culture';

        $this->csvData = [
            // Note: leading and trailing whitespace in first row is intentional
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        // define virtual file system
        $directory = [
            'unix_csv_without_utf8_bom.csv' => $this->csvHeader."\n".implode("\n", $this->csvData),

            'unix_csv_one_duplicated_header.csv' => $this->csvHeaderDuplicatedRepository."\n".implode("\n", $this->csvData),
            'unix_csv_duplicated_headers.csv' => $this->csvHeaderDuplicatedRepositoryCulture."\n".implode("\n", $this->csvData),
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
             * Test CsvDuplicateColumnNameValidator.class.php
             *
             * Tests:
             * - no duplicated column headers
             * - one duplicated column header
             * - mulitple different duplicated column headers
             */
            [
                'CsvDuplicateColumnNameValidator-NoDuplicatedColumnHeader' => [
                    'csvValidatorClasses' => ['CsvDuplicateColumnNameValidator' => CsvDuplicateColumnNameValidator::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvDuplicateColumnNameValidator',
                    CsvBaseValidator::TEST_TITLE => CsvDuplicateColumnNameValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvDuplicateColumnNameValidator::RESULT_INFO,
                    CsvBaseValidator::TEST_RESULTS => [
                        'No duplicate column names found.',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvDuplicateColumnNameValidator-OneDuplicatedColumnHeader' => [
                    'csvValidatorClasses' => ['CsvDuplicateColumnNameValidator' => CsvDuplicateColumnNameValidator::class],
                    'filename' => '/unix_csv_one_duplicated_header.csv',
                    'testname' => 'CsvDuplicateColumnNameValidator',
                    CsvBaseValidator::TEST_TITLE => CsvDuplicateColumnNameValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvDuplicateColumnNameValidator::RESULT_ERROR,
                    CsvBaseValidator::TEST_RESULTS => [
                        'Columns with name \'repository\': 2',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvDuplicateColumnNameValidator-DuplicatedColumnHeaders' => [
                    'csvValidatorClasses' => ['CsvDuplicateColumnNameValidator' => CsvDuplicateColumnNameValidator::class],
                    'filename' => '/unix_csv_duplicated_headers.csv',
                    'testname' => 'CsvDuplicateColumnNameValidator',
                    CsvBaseValidator::TEST_TITLE => CsvDuplicateColumnNameValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvDuplicateColumnNameValidator::RESULT_ERROR,
                    CsvBaseValidator::TEST_RESULTS => [
                        'Columns with name \'culture\': 3',
                        'Columns with name \'repository\': 2',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
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
