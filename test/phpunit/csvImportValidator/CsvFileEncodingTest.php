<?php

use org\bovigo\vfs\vfsStream;

/**
 * @internal
 * @covers \csvFileEncodingTest
 */
class CsvFileEncodingTest extends \PHPUnit\Framework\TestCase
{
    protected $vdbcon;
    protected $context;

    public function setUp(): void
    {
        $this->context = sfContext::getInstance();
        $this->vdbcon = $this->createMock(DebugPDO::class);

        $this->csvHeader = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture';
        $this->csvHeaderWithUtf8Bom = CsvImportValidator::UTF8_BOM.$this->csvHeader;
        $this->csvHeaderWithUtf16LEBom = CsvImportValidator::UTF16_LITTLE_ENDIAN_BOM.$this->csvHeader;
        $this->csvHeaderWithUtf16BEBom = CsvImportValidator::UTF16_BIG_ENDIAN_BOM.$this->csvHeader;
        $this->csvHeaderWithUtf32LEBom = CsvImportValidator::UTF32_LITTLE_ENDIAN_BOM.$this->csvHeader;
        $this->csvHeaderWithUtf32BEBom = CsvImportValidator::UTF32_BIG_ENDIAN_BOM.$this->csvHeader;

        $this->csvData = [
            // Note: leading and trailing whitespace in first row is intentional
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        // define virtual file system
        $directory = [
            'unix_csv_with_utf8_bom.csv' => $this->csvHeaderWithUtf8Bom."\n".implode("\n", $this->csvData),
            'unix_csv_without_utf8_bom.csv' => $this->csvHeader."\n".implode("\n", $this->csvData),
            'windows_csv_with_utf8_bom.csv' => $this->csvHeaderWithUtf8Bom."\r\n".implode("\r\n", $this->csvData),
            'windows_csv_without_utf8_bom.csv' => $this->csvHeader."\r\n".implode("\r\n", $this->csvData),
            'unix_csv-windows_1252.csv' => mb_convert_encoding($this->csvHeader."\n".implode("\n", $this->csvData), 'Windows-1252', 'UTF-8'),
            'windows_csv-windows_1252.csv' => mb_convert_encoding($this->csvHeader."\r\n".implode("\r\n", $this->csvData), 'Windows-1252', 'UTF-8'),
            'unix_csv_with_utf16LE_bom.csv' => $this->csvHeaderWithUtf16LEBom."\n".implode("\n", $this->csvData),
            'unix_csv_with_utf16BE_bom.csv' => $this->csvHeaderWithUtf16BEBom."\n".implode("\n", $this->csvData),
            'unix_csv_with_utf32LE_bom.csv' => $this->csvHeaderWithUtf32LEBom."\n".implode("\n", $this->csvData),
            'unix_csv_with_utf32BE_bom.csv' => $this->csvHeaderWithUtf32BEBom."\n".implode("\n", $this->csvData),
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
            [
                'CsvFileEncodingValidator-Utf8ValidatorUnixWithBOM' => [
                    'csvValidatorClasses' => ['CsvFileEncodingValidator' => CsvFileEncodingValidator::class],
                    'filename' => '/unix_csv_with_utf8_bom.csv',
                    'testname' => 'CsvFileEncodingValidator',
                    CsvValidatorResult::TEST_TITLE => CsvFileEncodingValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_INFO,
                    CsvValidatorResult::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                        'This file includes a UTF-8 BOM.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvFileEncodingValidator-testUtf8ValidatorUnix' => [
                    'csvValidatorClasses' => ['CsvFileEncodingValidator' => CsvFileEncodingValidator::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvFileEncodingValidator',
                    CsvValidatorResult::TEST_TITLE => CsvFileEncodingValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_INFO,
                    CsvValidatorResult::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvFileEncodingValidator-testUtf8ValidatorWindowsWithBOM' => [
                    'csvValidatorClasses' => ['CsvFileEncodingValidator' => CsvFileEncodingValidator::class],
                    'filename' => '/windows_csv_with_utf8_bom.csv',
                    'testname' => 'CsvFileEncodingValidator',
                    CsvValidatorResult::TEST_TITLE => CsvFileEncodingValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_INFO,
                    CsvValidatorResult::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                        'This file includes a UTF-8 BOM.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvFileEncodingValidator-testUtf8ValidatorWindows' => [
                    'csvValidatorClasses' => ['CsvFileEncodingValidator' => CsvFileEncodingValidator::class],
                    'filename' => '/windows_csv_without_utf8_bom.csv',
                    'testname' => 'CsvFileEncodingValidator',
                    CsvValidatorResult::TEST_TITLE => CsvFileEncodingValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_INFO,
                    CsvValidatorResult::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvFileEncodingValidator-testUtf8IncompatibleUnix' => [
                    'csvValidatorClasses' => ['CsvFileEncodingValidator' => CsvFileEncodingValidator::class],
                    'filename' => '/unix_csv-windows_1252.csv',
                    'testname' => 'CsvFileEncodingValidator',
                    CsvValidatorResult::TEST_TITLE => CsvFileEncodingValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_ERROR,
                    CsvValidatorResult::TEST_RESULTS => [
                        'File encoding does not appear to be UTF-8 compatible.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [implode(',', str_getcsv(mb_convert_encoding('"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""', 'Windows-1252', 'UTF-8')))],
                ],
            ],

            [
                'CsvFileEncodingValidator-testUtf8IncompatibleWindows' => [
                    'csvValidatorClasses' => ['CsvFileEncodingValidator' => CsvFileEncodingValidator::class],
                    'filename' => '/windows_csv-windows_1252.csv',
                    'testname' => 'CsvFileEncodingValidator',
                    CsvValidatorResult::TEST_TITLE => CsvFileEncodingValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_ERROR,
                    CsvValidatorResult::TEST_RESULTS => [
                        'File encoding does not appear to be UTF-8 compatible.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [implode(',', str_getcsv(mb_convert_encoding('"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""', 'Windows-1252', 'UTF-8')))],
                ],
            ],

            [
                'CsvFileEncodingValidator-testDetectUtf16LEBomUnix' => [
                    'csvValidatorClasses' => ['CsvFileEncodingValidator' => CsvFileEncodingValidator::class],
                    'filename' => '/unix_csv_with_utf16LE_bom.csv',
                    'testname' => 'CsvFileEncodingValidator',
                    CsvValidatorResult::TEST_TITLE => CsvFileEncodingValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_ERROR,
                    CsvValidatorResult::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                        'This file includes a unicode BOM, but it is not UTF-8.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvFileEncodingValidator-testDetectUtf16BEBomUnix' => [
                    'csvValidatorClasses' => ['CsvFileEncodingValidator' => CsvFileEncodingValidator::class],
                    'filename' => '/unix_csv_with_utf16BE_bom.csv',
                    'testname' => 'CsvFileEncodingValidator',
                    CsvValidatorResult::TEST_TITLE => CsvFileEncodingValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_ERROR,
                    CsvValidatorResult::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                        'This file includes a unicode BOM, but it is not UTF-8.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvFileEncodingValidator-testDetectUtf32LEBomUnix' => [
                    'csvValidatorClasses' => ['CsvFileEncodingValidator' => CsvFileEncodingValidator::class],
                    'filename' => '/unix_csv_with_utf32LE_bom.csv',
                    'testname' => 'CsvFileEncodingValidator',
                    CsvValidatorResult::TEST_TITLE => CsvFileEncodingValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_ERROR,
                    CsvValidatorResult::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                        'This file includes a unicode BOM, but it is not UTF-8.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvFileEncodingValidator-testDetectUtf32BEBomUnix' => [
                    'csvValidatorClasses' => ['CsvFileEncodingValidator' => CsvFileEncodingValidator::class],
                    'filename' => '/unix_csv_with_utf32BE_bom.csv',
                    'testname' => 'CsvFileEncodingValidator',
                    CsvValidatorResult::TEST_TITLE => CsvFileEncodingValidator::TITLE,
                    CsvValidatorResult::TEST_STATUS => CsvValidatorResult::RESULT_ERROR,
                    CsvValidatorResult::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                        'This file includes a unicode BOM, but it is not UTF-8.',
                    ],
                    CsvValidatorResult::TEST_DETAIL => [],
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
