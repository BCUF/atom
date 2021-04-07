<?php

/*
 * This file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

require_once __DIR__.'/../vendor/composer/autoload.php';

/**
 * Check csv data
 *
 * @package    symfony
 * @subpackage task
 * @author     Steve Breker <sbreker@artefactual.com>
 */
class CsvImportValidator
{
  protected $context;
  protected $dbcon;
  protected $filenames = [];
  protected $csvTests = null;
  protected $header;
  protected $rows = [];
  protected $showDisplayProgress = false;
  protected $results = [];
  protected $ormClasses = [];

  const UTF8_BOM = "\xEF\xBB\xBF";
  const UTF16_LITTLE_ENDIAN_BOM = "\xFF\xFE";
  const UTF16_BIG_ENDIAN_BOM = "\xFE\xFF";
  const UTF32_LITTLE_ENDIAN_BOM = "\xFF\xFE\x00\x00";
  const UTF32_BIG_ENDIAN_BOM = "\x00\x00\xFE\xFF";

  static $bomTypeMap = [
    'utf8Bom' => self::UTF8_BOM,
    'utf16LittleEndianBom' => self::UTF16_LITTLE_ENDIAN_BOM,
    'utf16BigEndianBom' => self::UTF16_BIG_ENDIAN_BOM,
    'utf32LittleEndianBom' => self::UTF32_LITTLE_ENDIAN_BOM,
    'utf32BigEndianBom' => self::UTF32_BIG_ENDIAN_BOM,
  ];

  // Default options
  protected $validatorOptions = [
    'className' => 'QubitInformationObject',
    'verbose'   => false,
    'source'    => '',
    'separator' => ',',
    'enclosure' => '"',
  ];

  protected $defaultCsvClassNameList = [
    'QubitInformationObject',
  ];

  public function __construct(sfContext $context = null,
    $dbcon = null,
    $options = array())
  {
    if (null === $context)
    {
      $context = new sfContext(ProjectConfiguration::getActive());
    }

    $this->setContext($context);
    $this->dbcon = $dbcon;
    $this->setOptions($options);

    $this->setCsvTests(
      [
        'CsvFileEncodingTest'         => CsvFileEncodingTest::class,
        'CsvColumnNameTest'           => CsvColumnNameTest::class,
        'CsvColumnCountTest'          => CsvColumnCountTest::class,
        'CsvDuplicateColumnNameTest'  => CsvDuplicateColumnNameTest::class,
        'CsvEmptyRowTest'             => CsvEmptyRowTest::class,
        'CsvParentTest'               => CsvParentTest::class,
        'CsvLegacyIdTest'             => CsvLegacyIdTest::class,
        'CsvCultureTest'              => CsvCultureTest::class,
        'CsvLanguageTest'             => CsvLanguageTest::class,
        'CsvFieldLengthTest'          => CsvFieldLengthTest::class,
        'CsvSampleColumnsTest'        => CsvSampleColumnsTest::class,
      ]
    );

    $this->setOrmClasses(
      [
        'QubitFlatfileImport'    => QubitFlatfileImport::class,
        'QubitObject'            => QubitObject::class,
      ]
    );
  }

  private function handleByteOrderMark($fh)
  {
    foreach (self::$bomTypeMap as $key => $value)
    {
      if (false === $data = fread($fh, strlen($value)))
      {
        throw new sfException('Failed to read from CSV file in handleByteOrderMark.');
      }

      if (0 === strncmp($data, $value, strlen($value)))
      {
        return; // Just eat the BOM and move on from this file position
      }

      // No BOM, rewind the file handle position
      if (false === rewind($fh))
      {
        throw new sfException('Rewinding file position failed in handleByteOrderMark.');
      }
    }
  }

  public function loadCsvData($fh)
  {
    $this->handleByteOrderMark($fh);
    $this->header = fgetcsv($fh, 60000, $this->getOption('separator'), $this->getOption('enclosure'));

    if ($this->header === false)
    {
      throw new sfException('Could not read initial row. File could be empty.');
    }

    $this->rows = [];
    while ($item = fgetcsv($fh, 60000, $this->getOption('separator'), $this->getOption('enclosure')))
    {
      $this->rows[] = $item;
    }

    // Remove trailing blank lines. Iterate over array from bottom up, 
    // removing empty rows.
    for ($i = count($this->rows) - 1; $i >= 0; $i--)
    {
      if (!(1 == count($this->rows[$i]) && empty(trim(implode('', $this->rows[$i])))))
      {
        return;
      }
      else
      {
        unset($this->rows[$i]);
      }
    }
  }

  protected function getLongestRow() : int
  {
    $rowsMaxCount = count(max($this->rows));
    $headerCount = count($this->header);

    if ($rowsMaxCount > $headerCount)
    {
      return $rowsMaxCount;
    }
    else
    {
      return count($this->header);
    }
  }

  public function validate()
  {
    foreach ($this->filenames as $filename)
    {
      if (false === $fh = fopen($filename, 'rb'))
      {
        throw new sfException('You must specify a valid filename');
      }

      $this->loadCsvData($fh);

      // Set specifics for this csv file
      foreach ($this->csvTests as $test)
      {
        $test->setOrmClasses($this->ormClasses);
        $test->setFilename($filename);
        $test->setColumnCount($this->getLongestRow());
      }

      // Iterate csv rows, calling each test/row.
      foreach ($this->rows as $row)
      {
        if ($this->showDisplayProgress)
        {
          print $this->renderProgressDescription();
        }
        
        foreach ($this->csvTests as $test)
        {
          $test->testRow($this->header, $row);
        }
      }

      // Gather results for this CSV file. 
      // Call reset() on each test.
      foreach ($this->csvTests as $testkey => $test)
      {
        $this->results[$filename][$testkey] = $test->getTestResult();
        $test->reset();
      }
    }

    if ($this->showDisplayProgress)
    {
      print $this->renderProgressDescription(true);
    }

    return $this->results;
  }

  public function setShowDisplayProgress(bool $value)
  {
    $this->showDisplayProgress = $value;
  }

  public function getContext()
  {
    return $this->context;
  }

  public function setContext($context)
  {
    $this->context = $context;
  }  

  public function getResults()
  {
    return $this->results;
  }

  public function getResultsByFilenameTestname(string $filename, string $testname)
  {
    if (isset($filename) && isset($testname))
    {
      if (isset($this->results[$filename][$testname]))
      {
        return $this->results[$filename][$testname];
      }
    }
  }

  public function setOrmClasses(array $classes)
  {
    $this->ormClasses = $classes;
  }

  public function setCsvTests(array $classes)
  {
    unset($this->csvTests);

    foreach($classes as $key => $class)
    {
      $this->csvTests[$key] = new $class($this->getOptions());
    }
  }

  public function getCsvTests()
  {
    return $this->csvTests;
  }

  public function setOptions(array $options = null)
  {
    if (empty($options))
    {
      return;
    }

    foreach ($options as $name => $val)
    {
      $this->setOption($name, $val);
    }
  }

  public function setOption(string $name, $value)
  {
    switch ($name)
    {
      case 'className':
        $this->setClassName($value);

        break;

      case 'verbose':
        $this->setVerbose($value);

        break;

      case 'source':
        $this->setSource($value);

        break;

      case 'separator':
        $this->setSeparator($value);

        break;

      case 'enclosure':
        $this->setEnclosure($value);

        break;

      default:
        throw new UnexpectedValueException(sprintf('Invalid option "%s".', $name));
    }
  }

  public function setClassName(string $value)
  {
    if (in_array($value, $this->defaultCsvClassNameList))
    {
      $this->validatorOptions['className'] = $value;
    }
    else
    {
      throw new UnexpectedValueException(sprintf('Invalid option "%s".', $name));
    }
  }

  public function setVerbose(bool $value)
  {
    $this->validatorOptions['verbose'] = $value;
  }

  public function setSource(string $value)
  {
    $this->validatorOptions['source'] = $value;
  }

  public function setSeparator(string $value)
  {
    if (1 != strlen($value))
    {
      throw new UnexpectedValueException(sprintf('Invalid separator "%s".', $value));
    }

    $this->validatorOptions['separator'] = $value;
  }

  public function setEnclosure(string $value)
  {
    if (1 != strlen($value))
    {
      throw new UnexpectedValueException(sprintf('Invalid enclosure "%s".', $value));
    }

    $this->validatorOptions['enclosure'] = $value;
  }

  public function getOption(String $name)
  {
    if (array_key_exists($name, $this->validatorOptions))
    {
      return $this->validatorOptions[$name];
    }
    else
    {
      throw new UnexpectedValueException(sprintf('Invalid option "%s".', $name));
    }
  }

  public function getOptions()
  {
    return $this->validatorOptions;
  }

  public function getDbCon()
  {
    if (null === $this->dbcon)
    {
      $this->dbcon = Propel::getConnection();
    }

    return $this->dbcon;
  }

  public function setFilenames(array $filenames)
  {
    foreach ($filenames as $filename)
    {
      self::validateFileName($filename);
    }

    $this->filenames = $filenames;
  }

  public function getRowCount()
  {
    return (count($this->rows));
  }

  public static function validateFilename($filename)
  {
    if (empty($filename))
    {
      throw new sfException('Please specify a valid filename.');
    }

    if (!file_exists($filename))
    {
      throw new sfException(sprintf('Can not find file %s', $filename));
    }

    if (!is_readable($filename))
    {
      throw new sfException(sprintf('Can not read %s', $filename));
    }

    return $filename;
  }

  public function renderProgressDescription(bool $complete = false)
  {
    $output = '.';

    if ($complete)
    {
      return "\nAnalysis complete.\n";
    }

    return $output;
  }
}