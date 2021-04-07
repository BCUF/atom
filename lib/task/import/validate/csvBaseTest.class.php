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

/**
 * CSV validation test base class.
 *
 * @package    symfony
 * @subpackage task
 * @author     Steve Breker <sbreker@artefactual.com>
 */

abstract class CsvBaseTest
{
  // Integer type to allow comparison of severity values.
  const RESULT_INFO = 0;
  const RESULT_WARN = 1;
  const RESULT_ERROR = 2;

  const TEST_TITLE = 'title';
  const TEST_STATUS = 'status';
  const TEST_RESULTS = 'results';
  const TEST_DETAIL = 'details';

  const HEADER_PLACEHOLDER = 'EXTRA_COLUMN';

  protected $testData = array();

  protected $filename = '';
  protected $columnCount = 0;
  protected $rowNumber = 1;
  protected $title = '';
  protected $options = [];
  protected $ormClasses = [];

  public function __construct(array $options = null)
  {
    if (isset($options))
    {
      $this->setOptions($options);
    }
  }
  
  public function testRow(array $header, array $row)
  {
    $this->rowNumber++;
  }

  public function reset()
  {
    $this->testData = [
      self::TEST_TITLE => $this->title,
      self::TEST_STATUS => self::RESULT_INFO,
      self::TEST_RESULTS => array(),
      self::TEST_DETAIL => array(),
    ];
  }

  protected function addTestResult(string $datatype, string $value)
  {
    switch ($datatype)
    {
      case self::TEST_STATUS:
        // Only update when severity increases.
        if ($value > $this->testData[$datatype])
        {
          $this->testData[$datatype] = intval($value);
        }
        break;

      case self::TEST_RESULTS:
      case self::TEST_DETAIL:
        $this->testData[$datatype][] = $value;
        break;

      default: 
        throw new sfException('Unknown test result datatype in csvBaseTest.');
    } 
  }

  protected function combineRow(array $header, array $row)
  {
    // Enforce header has $columnCount elements. Add elements if necessary.
    for ($i = count($header); $i < $this->columnCount; $i++) {
      $header[] = sprintf("%s-%d", self::HEADER_PLACEHOLDER, $i);
    }
    // Enforce row has $columnCount elements.
    for ($i = count($row); $i < $this->columnCount; $i++) {
      $row[] = '';
    }

    // return array_combined row, trim each element.
    return array_combine(array_map('trim', $header), array_map('trim', $row));
  }

  public function setOrmClasses(array $classes)
  {
    $this->ormClasses = $classes;
  }

  public function setOptions(array $options)
  {
    $this->options = $options;
  }

  public function setFilename(string $filename)
  {
    $this->filename = $filename;
  }

  public function getFilename()
  {
    return $this->filename;
  }

  public function setTitle(string $title)
  {
    $this->title = $title;

    if (isset($this->testData[self::TEST_TITLE]))
    {
      $this->testData[self::TEST_TITLE] = $title;
    }
  }

  public function getTitle(): string
  {
    return $this->title;
  }

  public function setColumnCount(int $count)
  {
    $this->columnCount = $count;
  }

  public function getColumnCount(): int
  {
    return $this->columnCount;
  }

  public function getTestResult()
  {
    $this->addTestResult(self::TEST_STATUS, self::RESULT_INFO);
    
    return $this->testData;
  }
}