<?php

use org\bovigo\vfs\vfsStream;

/**
 * @internal
 * @covers \csvSampleValuesTest
 */
class CsvSampleValuesTest extends \PHPUnit\Framework\TestCase
{
    protected $vdbcon;
    protected $context;

    public function setUp(): void
    {
        $this->context = sfContext::getInstance();
        $this->vdbcon = $this->createMock(DebugPDO::class);

        $this->csvHeader = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture';

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
             * Test CsvSampleValuesValidator.class.php
             *
             * CSV Sample Values test. Outputs column names and a sample value from first
             * populated row found. Only populated columns are included.
             */
            [
                'CsvSampleValuesValidator-testSampleValues' => [
                    'csvValidatorClasses' => ['CsvSampleValuesValidator' => CsvSampleValuesValidator::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvSampleValuesValidator',
                    CsvBaseValidator::TEST_TITLE => CsvSampleValuesValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvSampleValuesValidator::RESULT_INFO,
                    CsvBaseValidator::TEST_RESULTS => [
                        'legacyId:  B10101',
                        'parentId:  DJ001',
                        'identifier:  ID1',
                        'title:  Some Photographs',
                        'extentAndMedium:  Extent and medium 1',
                        'culture:  fr',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [],
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
