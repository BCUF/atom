<?php

use org\bovigo\vfs\vfsStream;

/**
 * @internal
 * @covers \CsvCultureValidator
 */
class CsvCultureTest extends \PHPUnit\Framework\TestCase
{
    protected $vdbcon;
    protected $context;

    public function setUp(): void
    {
        $this->context = sfContext::getInstance();
        $this->vdbcon = $this->createMock(DebugPDO::class);

        $this->csvHeader = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture';
        $this->csvHeaderMissingCulture = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository';

        $this->csvData = [
            // Note: leading and trailing whitespace in first row is intentional
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataMissingCulture = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1",""',
            '"","","","Chemise","","",""',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", ""',
        ];

        $this->csvDataValidCultures = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","","es "',
            '"","","","Chemise","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", "de"',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataCulturesSomeInvalid = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","","es "',
            '"","","","Chemise","","","","fr|en"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", "gg"',
            '"E20202", "DJ003", "ID4", "Title Four", "","", "", "en"',
            '"F20202", "DJ004", "DD8989", "pdf documents", "","", "", ""',
        ];

        // define virtual file system
        $directory = [
            'unix_csv_without_utf8_bom.csv' => $this->csvHeader."\n".implode("\n", $this->csvData),

            'unix_csv_missing_culture.csv' => $this->csvHeaderMissingCulture."\n".implode("\n", $this->csvDataMissingCulture),
            'unix_csv_valid_cultures.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataValidCultures),
            'unix_csv_cultures_some_invalid.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataCulturesSomeInvalid),
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
             * Test CsvCultureValidator.class.php
             *
             * Tests:
             * - culture column missing
             * - culture column present with valid data
             * - culture column present with mix of valid and invalid data
             */
            [
                'CsvCultureValidator-CultureColMissing' => [
                    'csvValidatorClasses' => ['CsvCultureValidator' => CsvCultureValidator::class],
                    'filename' => '/unix_csv_missing_culture.csv',
                    'testname' => 'CsvCultureValidator',
                    'validatorOptions' => [
                        'verbose' => true,
                    ],
                    CsvValidatorResult::TEST_TITLE => CsvCultureValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_WARN,
                    CsvValidatorResult::TEST_RESULTS => [
                        '\'culture\' column not present in file.',
                        'Rows without a valid culture value will be imported using AtoM\'s default source culture.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvCultureValidator-CulturesValid' => [
                    'csvValidatorClasses' => ['CsvCultureValidator' => CsvCultureValidator::class],
                    'filename' => '/unix_csv_valid_cultures.csv',
                    'testname' => 'CsvCultureValidator',
                    'validatorOptions' => [
                        'verbose' => true,
                    ],
                    CsvValidatorResult::TEST_TITLE => CsvCultureValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_INFO,
                    CsvValidatorResult::TEST_RESULTS => [
                        '\'culture\' column values are all valid.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvCultureValidator-CulturesSomeInvalid' => [
                    'csvValidatorClasses' => ['CsvCultureValidator' => CsvCultureValidator::class],
                    'filename' => '/unix_csv_cultures_some_invalid.csv',
                    'testname' => 'CsvCultureValidator',
                    'validatorOptions' => [
                        'verbose' => true,
                    ],
                    CsvValidatorResult::TEST_TITLE => CsvCultureValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_ERROR,
                    CsvValidatorResult::TEST_RESULTS => [
                        'Rows with blank culture value: 1',
                        'Rows with invalid culture values: 1',
                        'Rows with pipe character in culture values: 1',
                        '\'culture\' column does not allow for multiple values separated with a pipe \'|\' character.',
                        'Invalid culture values: fr|en, gg',
                        'Rows with a blank culture value will be imported using AtoM\'s default source culture.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [
                        ',,,Chemise,,,,fr|en',
                        'D20202,DJ002,,Voûte, étagère 0074,,,,gg',
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
