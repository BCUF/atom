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
 * CSV column count test. Test all rows in CSV have the same number of columns.
 * 
 * @package    symfony
 * @subpackage task
 * @author     Steve Breker <sbreker@artefactual.com>
 */

class CsvColumnCountTest extends CsvBaseTest
{
  protected $headerCount = null;
  protected $rowCountSummary = [];

  const TITLE = 'CSV Column Count Check';

  public function __construct(array $options = null)
  {
    parent::__construct($options);

    $this->setTitle(self::TITLE);
    $this->reset();
  }

  public function reset()
  {
    $this->headerCount = null;
    $this->rowCountSummary = [];
    
    parent::reset();
  }

  public function testRow(array $header, array $row)
  {
    parent::testRow($header, $row);

    if (!isset($this->headerCount))
    {
      $this->headerCount = count($header);

      $this->updateRowCountSummary($this->headerCount);
    }
    
    $this->updateRowCountSummary(count($row));
  }

  public function getTestResult()
  {
    // When rows are all same length then rowCountSummary will have 1 row.
    if (1 == count($this->rowCountSummary))
    {
      $this->addTestResult(self::TEST_STATUS, self::RESULT_INFO);
      $this->addTestResult(self::TEST_RESULTS, sprintf("Number of columns in CSV: %s", $this->headerCount));

      // Set a warning if there's less than 2 columns. This is probably an issue with field separators.
      if (1 >= $this->headerCount)
      {
        $this->addTestResult(self::TEST_STATUS, self::RESULT_WARN);
        $this->addTestResult(self::TEST_RESULTS, "CSV appears to have only one column - check CSV separator option matches file.");
      }
    }
    else
    {
      $this->addTestResult(self::TEST_STATUS, self::RESULT_ERROR);

      foreach ($this->rowCountSummary as $columnCount => $numOccurrences)
      {
        $this->addTestResult(self::TEST_RESULTS, sprintf("Number of rows with %s columns: %s", $columnCount, $numOccurrences));
      }
      $this->addTestResult(self::TEST_RESULTS, "CSV rows with different lengths detected - check CSV enclosure option matches file.");
    }
    
    return parent::getTestResult();
  }

  protected function updateRowCountSummary(int $numColumns)
  {
    if (array_key_exists($numColumns, $this->rowCountSummary))
    {
      $this->rowCountSummary[$numColumns]++;
    }
    else
    {
      $this->rowCountSummary[$numColumns] = 1;
    }
  }
}
