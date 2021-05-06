<?php

use org\bovigo\vfs\vfsStream;

/**
 * @internal
 * @covers \CsvColumnNameValidator
 */
class CsvColumnNameTest extends \PHPUnit\Framework\TestCase
{
    protected $vdbcon;
    protected $context;

    public function setUp(): void
    {
        $this->context = sfContext::getInstance();
        $this->vdbcon = $this->createMock(DebugPDO::class);

        $this->csvHeader = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture';
        $this->csvHeaderUnknownColumnName = 'legacyId,parentId,identifier,title,levilOfDescrooption,extentAndMedium,repository,culture';
        $this->csvHeaderBadCaseColumnName = 'legacyId,parentId, identifier,Title,levelOfDescription,extentAndMedium,repository,culture';

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
            'unix_csv_unknown_column_name.csv' => $this->csvHeaderUnknownColumnName."\n".implode("\n", $this->csvData),
            'unix_csv_bad_case_column_name.csv' => $this->csvHeaderBadCaseColumnName."\n".implode("\n", $this->csvData),
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

        $this->assertSame($options[CsvValidatorResult::TEST_TITLE], $result[CsvValidatorResult::TEST_TITLE]);
        $this->assertSame($options[CsvValidatorResult::TEST_STATUS], $result[CsvValidatorResult::TEST_STATUS]);
        $this->assertSame($options[CsvValidatorResult::TEST_RESULTS], $result[CsvValidatorResult::TEST_RESULTS]);
        $this->assertSame($options[CsvValidatorResult::TEST_DETAIL], $result[CsvValidatorResult::TEST_DETAIL]);
    }

    public function csvValidatorTestProvider()
    {
        $vfsUrl = 'vfs://root';

        return [
            /*
             * Test CsvColumnNameValidator.class.php
             *
             * Tests:
             * - class-name not set
             * - all columns validate against config file
             * - some columns fail to validate without matching by lower case
             * - some columns fail to validate but match by lower case
             */
            [
                'CsvColumnNameValidator-ClassNameNotSet' => [
                    'csvValidatorClasses' => ['CsvColumnNameValidator' => CsvColumnNameValidator::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvColumnNameValidator',
                    'validatorOptions' => [
                        'source' => 'testsourcefile.csv',
                        'verbose' => true,
                    ],
                    CsvValidatorResult::TEST_TITLE => CsvColumnNameValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_INFO,
                    CsvValidatorResult::TEST_RESULTS => [
                        'Number of unrecognized column names found in CSV: 0',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvColumnNameValidator-AllColumnNamesMatch' => [
                    'csvValidatorClasses' => ['CsvColumnNameValidator' => CsvColumnNameValidator::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvColumnNameValidator',
                    'validatorOptions' => [
                        'source' => 'testsourcefile.csv',
                        'className' => 'QubitInformationObject',
                        'verbose' => true,
                    ],
                    CsvValidatorResult::TEST_TITLE => CsvColumnNameValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_INFO,
                    CsvValidatorResult::TEST_RESULTS => [
                        'Number of unrecognized column names found in CSV: 0',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvColumnNameValidator-SomeUnmatched' => [
                    'csvValidatorClasses' => ['CsvColumnNameValidator' => CsvColumnNameValidator::class],
                    'filename' => '/unix_csv_unknown_column_name.csv',
                    'testname' => 'CsvColumnNameValidator',
                    'validatorOptions' => [
                        'source' => 'testsourcefile.csv',
                        'className' => 'QubitInformationObject',
                        'verbose' => true,
                    ],
                    CsvValidatorResult::TEST_TITLE => CsvColumnNameValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_WARN,
                    CsvValidatorResult::TEST_RESULTS => [
                        'Number of unrecognized column names found in CSV: 1',
                        'Unrecognized columns will be ignored by AtoM when the CSV is imported.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [
                        'Unrecognized column: levilOfDescrooption',
                    ],
                ],
            ],

            [
                'CsvColumnNameValidator-BadCaseColumnName' => [
                    'csvValidatorClasses' => ['CsvColumnNameValidator' => CsvColumnNameValidator::class],
                    'filename' => '/unix_csv_bad_case_column_name.csv',
                    'testname' => 'CsvColumnNameValidator',
                    'validatorOptions' => [
                        'source' => 'testsourcefile.csv',
                        'className' => 'QubitInformationObject',
                        'verbose' => true,
                    ],
                    CsvValidatorResult::TEST_TITLE => CsvColumnNameValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_WARN,
                    CsvValidatorResult::TEST_RESULTS => [
                        'Number of unrecognized column names found in CSV: 2',
                        'Unrecognized columns will be ignored by AtoM when the CSV is imported.',
                        'Number of column names with leading or trailing whitespace characters: 1',
                        'Number of unrecognized columns that may be case related: 1',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [
                        'Unrecognized column:  identifier',
                        'Unrecognized column: Title',
                        'Column names with leading or trailing whitespace: identifier',
                        'Possible match for Title: title',
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
