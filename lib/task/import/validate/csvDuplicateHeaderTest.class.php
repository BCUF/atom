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
 * CSV Duplicate column name test. Throw error if any column names are repeated in the file.
 * 
 * @package    symfony
 * @subpackage task
 * @author     Steve Breker <sbreker@artefactual.com>
 */

class CsvDuplicateColumnNameTest extends CsvBaseTest
{
  protected $columnFrequency = [];

  const TITLE = 'Duplicate Column Name Check';

  public function __construct(array $options = null)
  {
    parent::__construct($options);

    $this->setTitle(self::TITLE);
    $this->reset();
  }

  public function reset()
  {
    $this->columnFrequency = [];
    
    parent::reset();
  }

  public function testRow(array $header, array $row)
  {
    parent::testRow($header, $row);
    $header = array_map('trim', $header);

    // Only empty on first iteration.
    if (empty($this->columnFrequency))
    {
      foreach ($header as $columnName)
      {
        if (!array_key_exists($columnName, $this->columnFrequency))
        {
          $this->columnFrequency[$columnName] = 1;
        }
        else
        {
          $this->columnFrequency[$columnName]++;
        }
      }
    }
  }

  public function getTestResult()
  {
    foreach ($this->columnFrequency as $columnName => $count)
    {
      if (1 < $count)
      {
        $this->addTestResult(self::TEST_STATUS, self::RESULT_ERROR);
        $this->addTestResult(self::TEST_RESULTS, sprintf("Columns with name '%s': %s", $columnName, $count));
      }
    }
    // No duplicate header values when array_unique has only one element, and last element's value === 1.
    if (1 === count(array_unique($this->columnFrequency)) && 1 === end($this->columnFrequency))
    {
      $this->addTestResult(self::TEST_STATUS, self::RESULT_INFO);
      $this->addTestResult(self::TEST_RESULTS, "No duplicate column names found.");
    }

    return parent::getTestResult();
  }
}
