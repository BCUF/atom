<?php

use org\bovigo\vfs\vfsStream;

/**
 * @internal
 * @covers \csvDigitalObjectTest
 */
class CsvDigitalObjectTest extends \PHPUnit\Framework\TestCase
{
    protected $vdbcon;
    protected $context;

    public function setUp(): void
    {
        $this->context = sfContext::getInstance();
        $this->vdbcon = $this->createMock(DebugPDO::class);

        $this->csvHeader = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture';
        $this->csvHeaderWithDigitalObjectCols = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,digitalObjectPath,digitalObjectUri,culture';

        $this->csvData = [
            // Note: leading and trailing whitespace in first row is intentional
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataWithDigitalObjectCols = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","","","",""',
            '"","","","Chemise","","","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "","","", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "","","", "en"',
        ];

        $this->csvDataWithDigitalObjectColsPopulated = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","","a.png","",""',
            '"A10101","","","Chemise","","","","A.PNG","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "","b.png","https://www.artefactual.com/wp-content/uploads/2018/08/artefactual-logo-white.svg", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "","a.png","", "en"',
            '"E10101 "," DJ004","ID1 ","Some Photographs","","Extent and medium 1","","b.png","https://www.artefactual.com/wp-content/uploads/2018/08/artefactual-logo-white.svg",""',
            '"G30303","","","Sweater","","","","d.png","","fr"',
            '"F20202", "DJ005", "", "Voûte, étagère 0074", "", "", "","","www.google.com", ""',
            '"", "DJ003", "ID5", "Title Four", "","", "","","ftp://www.artefactual.com/wp-content/uploads/2018/08/artefactual-logo-white.svg", "en"',
        ];

        // define virtual file system
        $directory = [
            'unix_csv_without_utf8_bom.csv' => $this->csvHeader."\n".implode("\n", $this->csvData),
            'unix_csv_with_digital_object_cols.csv' => $this->csvHeaderWithDigitalObjectCols."\n".implode("\n", $this->csvDataWithDigitalObjectCols),
            'unix_csv_with_digital_object_cols_populated.csv' => $this->csvHeaderWithDigitalObjectCols."\n".implode("\n", $this->csvDataWithDigitalObjectColsPopulated),
            'digital_objects' => [
                'a.png' => random_bytes(100),
                'b.png' => random_bytes(100),
                'c.png' => random_bytes(100),
            ],
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
             * Test CsvDigitalObjectPathValidator.class.php
             *
             * Tests:
             * - digitalObjectPath column missing
             * - digitalObjectPath column present but empty
             * - digitalObjectPath column present and populated with:
             * -- valid file path
             * -- duplicated file path
             * -- invalid file path
             * -- empty value
             * -- digitalObjectUri column present and populated
             */
            [
                'CsvDigitalObjectPathValidator-digitalObjectPathMissing' => [
                    'csvValidatorClasses' => ['CsvDigitalObjectPathValidator' => CsvDigitalObjectPathValidator::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvDigitalObjectPathValidator',
                    CsvBaseValidator::TEST_TITLE => CsvDigitalObjectPathValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvDigitalObjectPathValidator::RESULT_INFO,
                    CsvBaseValidator::TEST_RESULTS => [
                        "Column 'digitalObjectPath' not present in CSV. Nothing to verify.",
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvDigitalObjectPathValidator-digitalObjectPathEmpty' => [
                    'csvValidatorClasses' => ['CsvDigitalObjectPathValidator' => CsvDigitalObjectPathValidator::class],
                    'filename' => '/unix_csv_with_digital_object_cols.csv',
                    'testname' => 'CsvDigitalObjectPathValidator',
                    CsvBaseValidator::TEST_TITLE => CsvDigitalObjectPathValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvDigitalObjectPathValidator::RESULT_INFO,
                    CsvBaseValidator::TEST_RESULTS => [
                        "Column 'digitalObjectPath' found.",
                        'Digital object folder location not specified.',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvDigitalObjectPathValidator-digitalObjectPathEmptyWithDOFolder' => [
                    'csvValidatorClasses' => ['CsvDigitalObjectPathValidator' => CsvDigitalObjectPathValidator::class],
                    'filename' => '/unix_csv_with_digital_object_cols.csv',
                    'testname' => 'CsvDigitalObjectPathValidator',
                    'validatorOptions' => [
                        'source' => 'testsourcefile.csv',
                        'className' => 'QubitInformationObject',
                        'className' => 'QubitInformationObject',
                        'pathToDigitalObjects' => 'vfs://root/digital_objects',
                    ],
                    CsvBaseValidator::TEST_TITLE => CsvDigitalObjectPathValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvDigitalObjectPathValidator::RESULT_INFO,
                    CsvBaseValidator::TEST_RESULTS => [
                        "Column 'digitalObjectPath' found.",
                        "Column 'digitalObjectPath' is empty.",
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvDigitalObjectPathValidator-digitalObjectPathPopulatedWithDOFolder' => [
                    'csvValidatorClasses' => ['CsvDigitalObjectPathValidator' => CsvDigitalObjectPathValidator::class],
                    'filename' => '/unix_csv_with_digital_object_cols_populated.csv',
                    'testname' => 'CsvDigitalObjectPathValidator',
                    'validatorOptions' => [
                        'source' => 'testsourcefile.csv',
                        'className' => 'QubitInformationObject',
                        'className' => 'QubitInformationObject',
                        'pathToDigitalObjects' => 'vfs://root/digital_objects',
                    ],
                    CsvBaseValidator::TEST_TITLE => CsvDigitalObjectPathValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvDigitalObjectPathValidator::RESULT_ERROR,
                    CsvBaseValidator::TEST_RESULTS => [
                        "Column 'digitalObjectPath' found.",
                        "'digitalObjectPath' will be overridden by 'digitalObjectUri' if both are populated.",
                        "'digitalObjectPath' values that will be overridden by digitalObjectUri: 2",
                        'Number of duplicated digital object paths found in CSV: 2',
                        'Digital objects in folder not referenced by CSV: 1',
                        'Digital object referenced by CSV not found in folder: 2',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                        "Number of duplicates for path 'a.png': 2",
                        "Number of duplicates for path 'b.png': 2",
                        'Unreferenced digital object: c.png',
                        'Unable to locate digital object: vfs://root/digital_objects/A.PNG',
                        'Unable to locate digital object: vfs://root/digital_objects/d.png',
                    ],
                ],
            ],

            /*
             * Test CsvDigitalObjectUriValidator.class.php
             *
             * Tests:
             * - digitalObjectUri column missing
             * - digitalObjectUri column present but empty
             * - digitalObjectUri column present and populated with:
             * -- valid URI
             * -- incorrect scheme URI (e.g. ftp://)
             * -- duplicated URI
             * -- invalid URI
             * -- empty value
             * -- digitalObjectUri column present and populated
             */
            [
                'CsvDigitalObjectUriValidator-digitalObjectUriMissing' => [
                    'csvValidatorClasses' => ['CsvDigitalObjectUriValidator' => CsvDigitalObjectUriValidator::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvDigitalObjectUriValidator',
                    CsvBaseValidator::TEST_TITLE => CsvDigitalObjectUriValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvDigitalObjectUriValidator::RESULT_INFO,
                    CsvBaseValidator::TEST_RESULTS => [
                        "Column 'digitalObjectUri' not present in CSV. Nothing to verify.",
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvDigitalObjectUriValidator-digitalObjectUriEmpty' => [
                    'csvValidatorClasses' => ['CsvDigitalObjectUriValidator' => CsvDigitalObjectUriValidator::class],
                    'filename' => '/unix_csv_with_digital_object_cols.csv',
                    'testname' => 'CsvDigitalObjectUriValidator',
                    CsvBaseValidator::TEST_TITLE => CsvDigitalObjectUriValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvDigitalObjectUriValidator::RESULT_INFO,
                    CsvBaseValidator::TEST_RESULTS => [
                        "Column 'digitalObjectUri' found.",
                        "Column 'digitalObjectUri' is empty.",
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvDigitalObjectUriValidator-digitalObjectUriPopulatedWithDOFolder' => [
                    'csvValidatorClasses' => ['CsvDigitalObjectUriValidator' => CsvDigitalObjectUriValidator::class],
                    'filename' => '/unix_csv_with_digital_object_cols_populated.csv',
                    'testname' => 'CsvDigitalObjectUriValidator',
                    'validatorOptions' => [
                        'source' => 'testsourcefile.csv',
                        'className' => 'QubitInformationObject',
                        'className' => 'QubitInformationObject',
                        'pathToDigitalObjects' => 'vfs://root/digital_objects',
                    ],
                    CsvBaseValidator::TEST_TITLE => CsvDigitalObjectUriValidator::TITLE,
                    CsvBaseValidator::TEST_STATUS => CsvDigitalObjectUriValidator::RESULT_ERROR,
                    CsvBaseValidator::TEST_RESULTS => [
                        "Column 'digitalObjectUri' found.",
                        'Repeating Digital object URIs found in CSV.',
                        'Invalid digitalObjectUri values detected: 2',
                    ],
                    CsvBaseValidator::TEST_DETAIL => [
                        "Number of duplicates for URI 'https://www.artefactual.com/wp-content/uploads/2018/08/artefactual-logo-white.svg': 2",
                        'Invalid URI: www.google.com',
                        'Invalid URI: ftp://www.artefactual.com/wp-content/uploads/2018/08/artefactual-logo-white.svg',
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
