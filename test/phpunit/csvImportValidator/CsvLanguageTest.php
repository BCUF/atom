<?php

use org\bovigo\vfs\vfsStream;

/**
 * @internal
 * @covers \csvLanguageTest
 */
class CsvLanguageTest extends \PHPUnit\Framework\TestCase
{
    protected $vdbcon;
    protected $context;

    public function setUp(): void
    {
        $this->context = sfContext::getInstance();
        $this->vdbcon = $this->createMock(DebugPDO::class);

        $this->csvHeader = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture';
        $this->csvHeaderWithLanguage = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture,language';

        $this->csvData = [
            // Note: leading and trailing whitespace in first row is intentional
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataValidLanguages = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","","es ", "es"',
            '"","","","Chemise","","","","fr","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", "de","en "',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"," en"',
        ];

        $this->csvDataLanguagesSomeInvalid = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","","es ", "Spanish"',
            '"","","","Chemise","","","","fr","fr|en"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", "de","en_GB"',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"," en_gb"',
        ];

        // define virtual file system
        $directory = [
            'unix_csv_without_utf8_bom.csv' => $this->csvHeader."\n".implode("\n", $this->csvData),
            'unix_csv_valid_languages.csv' => $this->csvHeaderWithLanguage."\n".implode("\n", $this->csvDataValidLanguages),
            'unix_csv_languages_some_invalid.csv' => $this->csvHeaderWithLanguage."\n".implode("\n", $this->csvDataLanguagesSomeInvalid),
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
             * Test CsvLanguageValidator.class.php
             *
             * Tests:
             * - language column missing
             * - language column present with valid data
             * - language column present with mix of valid and invalid data
             */
            [
                'CsvLanguageValidator-LanguageColMissing' => [
                    'csvValidatorClasses' => ['CsvLanguageValidator' => CsvLanguageValidator::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvLanguageValidator',
                    CsvValidatorResult::TEST_TITLE => CsvLanguageValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_INFO,
                    CsvValidatorResult::TEST_RESULTS => [
                        '\'language\' column not present in file.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvLanguageValidator-LanguageValid' => [
                    'csvValidatorClasses' => ['CsvLanguageValidator' => CsvLanguageValidator::class],
                    'filename' => '/unix_csv_valid_languages.csv',
                    'testname' => 'CsvLanguageValidator',
                    CsvValidatorResult::TEST_TITLE => CsvLanguageValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_INFO,
                    CsvValidatorResult::TEST_RESULTS => [
                        '\'language\' column values are all valid.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvLanguageValidator-LanguagesSomeInvalid' => [
                    'csvValidatorClasses' => ['CsvLanguageValidator' => CsvLanguageValidator::class],
                    'filename' => '/unix_csv_languages_some_invalid.csv',
                    'testname' => 'CsvLanguageValidator',
                    CsvValidatorResult::TEST_TITLE => CsvLanguageValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_ERROR,
                    CsvValidatorResult::TEST_RESULTS => [
                        'Rows with invalid language values: 2',
                        'Invalid language values: Spanish, en_gb',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [
                        'B10101,DJ001,ID1,Some Photographs,,Extent and medium 1,,es,Spanish',
                        ',DJ003,ID4,Title Four,,,,en,en_gb',
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
