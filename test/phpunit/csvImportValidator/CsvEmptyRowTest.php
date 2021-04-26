<?php

use org\bovigo\vfs\vfsStream;

/**
 * @internal
 * @covers \csvDuplicateEmptyRowTest
 */
class CsvEmptyRowTest extends \PHPUnit\Framework\TestCase
{
    protected $vdbcon;
    protected $context;

    public function setUp(): void
    {
        $this->context = sfContext::getInstance();
        $this->vdbcon = $this->createMock(DebugPDO::class);

        $this->csvHeader = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture';
        $this->csvHeaderBlank = '';
        $this->csvHeaderBlankWithCommas = ',,,';

        $this->csvData = [
            // Note: leading and trailing whitespace in first row is intentional
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataEmptyRows = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
            '  , ',
            ' ',
            '',
        ];

        $this->csvDataEmptyRowsWithCommas = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            ',,,',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '   , , ',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        // define virtual file system
        $directory = [
            'unix_csv_without_utf8_bom.csv' => $this->csvHeader."\n".implode("\n", $this->csvData),
            'unix_csv_with_empty_rows.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataEmptyRows),
            'unix_csv_with_empty_rows_with_commas.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataEmptyRowsWithCommas),
            'unix_csv_with_empty_rows_header.csv' => $this->csvHeaderBlank."\n".implode("\n", $this->csvDataEmptyRows),
            'unix_csv_with_empty_rows_header_with_commas.csv' => $this->csvHeaderBlankWithCommas."\n".implode("\n", $this->csvDataEmptyRowsWithCommas),
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
            [
                'CsvEmptyRowValidator-testNoEmptyRows' => [
                    'csvValidatorClasses' => ['CsvEmptyRowValidator' => CsvEmptyRowValidator::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvEmptyRowValidator',
                    CsvBaseValidator::TEST_TITLE => CsvEmptyRowValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvEmptyRowValidator::RESULT_INFO,
                    CsvBaseValidator::TEST_RESULTS => [
                        'CSV does not have any blank rows.',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvEmptyRowValidator-testEmptyRows' => [
                    'csvValidatorClasses' => ['CsvEmptyRowValidator' => CsvEmptyRowValidator::class],
                    'filename' => '/unix_csv_with_empty_rows.csv',
                    'testname' => 'CsvEmptyRowValidator',
                    CsvBaseValidator::TEST_TITLE => CsvEmptyRowValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvEmptyRowValidator::RESULT_ERROR,
                    CsvBaseValidator::TEST_RESULTS => [
                        'CSV blank row count: 2',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                        'Blank row numbers: 3, 6',
                    ],
                ],
            ],

            [
                'CsvEmptyRowValidator-testEmptyRowsWithCommas' => [
                    'csvValidatorClasses' => ['CsvEmptyRowValidator' => CsvEmptyRowValidator::class],
                    'filename' => '/unix_csv_with_empty_rows_with_commas.csv',
                    'testname' => 'CsvEmptyRowValidator',
                    CsvBaseValidator::TEST_TITLE => CsvEmptyRowValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvEmptyRowValidator::RESULT_ERROR,
                    CsvBaseValidator::TEST_RESULTS => [
                        'CSV blank row count: 2',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                        'Blank row numbers: 3, 5',
                    ],
                ],
            ],

            [
                'CsvEmptyRowValidator-testEmptyHeader' => [
                    'csvValidatorClasses' => ['CsvEmptyRowValidator' => CsvEmptyRowValidator::class],
                    'filename' => '/unix_csv_with_empty_rows_header.csv',
                    'testname' => 'CsvEmptyRowValidator',
                    CsvBaseValidator::TEST_TITLE => CsvEmptyRowValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvEmptyRowValidator::RESULT_ERROR,
                    CsvBaseValidator::TEST_RESULTS => [
                        'CSV Header is blank.',
                        'CSV blank row count: 2',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                        'Blank row numbers: 3, 6',
                    ],
                ],
            ],

            [
                'CsvEmptyRowValidator-EmptyRowsAndHeader' => [
                    'csvValidatorClasses' => ['CsvEmptyRowValidator' => CsvEmptyRowValidator::class],
                    'filename' => '/unix_csv_with_empty_rows_header_with_commas.csv',
                    'testname' => 'CsvEmptyRowValidator',
                    CsvBaseValidator::TEST_TITLE => CsvEmptyRowValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvEmptyRowValidator::RESULT_ERROR,
                    CsvBaseValidator::TEST_RESULTS => [
                        'CSV Header is blank.',
                        'CSV blank row count: 2',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                        'Blank row numbers: 3, 5',
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
