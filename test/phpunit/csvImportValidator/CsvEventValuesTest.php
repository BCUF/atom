<?php

use org\bovigo\vfs\vfsStream;

/**
 * @internal
 * @covers \csvEventValuesTest
 */
class CsvEventValuesTest extends \PHPUnit\Framework\TestCase
{
    protected $vdbcon;
    protected $context;

    public function setUp(): void
    {
        $this->context = sfContext::getInstance();
        $this->vdbcon = $this->createMock(DebugPDO::class);

        $this->csvHeader = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture';
        $this->csvHeaderWithEventType = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,eventTypes,eventDates,eventStartDates,eventEndDates,repository,culture';
        $this->csvHeaderWithAllEventCols = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,eventTypes,eventDates,eventStartDates,eventEndDates,eventActors,eventActorHistories,eventPlaces,repository,culture';

        $this->csvData = [
            // Note: leading and trailing whitespace in first row is intentional
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataWithEventType = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","creation","1922-1925","1922","1925","",""',
            '"","","","Chemise","","","creation","2010","01-01-2010","12-12-2010","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "creation","2020-2021","Jan 1, 2020","Dec 31 2021", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","","creation", "1900-1999",1900,1999, "", "en"',
        ];

        $this->csvDataWithEventTypeMismatches = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","creation","1922-1925",,"1925","",""',
            '"","","","Chemise","","","creation|donation","2010","01-01-2010","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", ,"2020-2021","Jan 1, 2020","Dec 31 2021", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","","creation", "1900-1999",1900,1999, "", "en"',
        ];

        $this->csvDataWithAllEventCols = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","creation","1922-1925","1922","1925",S. Smith,Smith history., Chilliwack, BC,"",""',
            '"","","","Chemise","","","creation","2010","01-01-2010","12-12-2010",,,,"","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "creation","2020-2021","Jan 1, 2020","Dec 31 2021",,,, "", ""',
            '"", "DJ003", "ID4", "Title Four", "","","creation|donation", "1900|1999",1900|1901,1999|2000,,,, "", "en"',
        ];

        // define virtual file system
        $directory = [
            'unix_csv_without_utf8_bom.csv' => $this->csvHeader."\n".implode("\n", $this->csvData),
            'unix_csv_with_event_type.csv' => $this->csvHeaderWithEventType."\n".implode("\n", $this->csvDataWithEventType),
            'unix_csv_with_event_type_mismatches.csv' => $this->csvHeaderWithEventType."\n".implode("\n", $this->csvDataWithEventTypeMismatches),
            'unix_csv_with_event_type_all_cols.csv' => $this->csvHeaderWithAllEventCols."\n".implode("\n", $this->csvDataWithAllEventCols),
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
             * Test CsvEventValuesValidator.class.php
             *
             * Tests:
             * - event columns missing
             * - subset of event columns present w. each field populated with same # of values
             * - subset of event columns present w. each field populated with different # of values
             * - all event columns present w. each field populated with same # (> 1) of values
             */
            [
                'CsvEventValuesValidator-EventColsMissing' => [
                    'csvValidatorClasses' => ['CsvEventValuesValidator' => CsvEventValuesValidator::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvEventValuesValidator',
                    CsvBaseValidator::TEST_TITLE => CsvEventValuesValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvEventValuesValidator::RESULT_INFO,
                    CsvBaseValidator::TEST_RESULTS => [
                        'No event columns to check.',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvEventValuesValidator-WithEventTypeAndDates' => [
                    'csvValidatorClasses' => ['CsvEventValuesValidator' => CsvEventValuesValidator::class],
                    'filename' => '/unix_csv_with_event_type.csv',
                    'testname' => 'CsvEventValuesValidator',
                    CsvBaseValidator::TEST_TITLE => CsvEventValuesValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvEventValuesValidator::RESULT_INFO,
                    CsvBaseValidator::TEST_RESULTS => [
                        'Checking columns: eventTypes,eventDates,eventStartDates,eventEndDates',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvEventValuesValidator-WithEventTypeAndDateMismatches' => [
                    'csvValidatorClasses' => ['CsvEventValuesValidator' => CsvEventValuesValidator::class],
                    'filename' => '/unix_csv_with_event_type_mismatches.csv',
                    'testname' => 'CsvEventValuesValidator',
                    CsvBaseValidator::TEST_TITLE => CsvEventValuesValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvEventValuesValidator::RESULT_WARN,
                    CsvBaseValidator::TEST_RESULTS => [
                        'Checking columns: eventTypes,eventDates,eventStartDates,eventEndDates',
                        'Event value mismatches found: 1',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                        ',,,Chemise,,,creation|donation,2010,01-01-2010,,,fr',
                    ],
                ],
            ],

            [
                'CsvEventValuesValidator-WithEventTypeAllColsMatching' => [
                    'csvValidatorClasses' => ['CsvEventValuesValidator' => CsvEventValuesValidator::class],
                    'filename' => '/unix_csv_with_event_type_all_cols.csv',
                    'testname' => 'CsvEventValuesValidator',
                    CsvBaseValidator::TEST_TITLE => CsvEventValuesValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvEventValuesValidator::RESULT_INFO,
                    CsvBaseValidator::TEST_RESULTS => [
                        'Checking columns: eventTypes,eventDates,eventStartDates,eventEndDates,eventActors,eventActorHistories,eventPlaces',
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
