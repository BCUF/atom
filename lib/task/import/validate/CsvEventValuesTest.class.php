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
 * CSV Multi Value test. Test if specified mulitvalue columns are all of the same length.
 * e.g.: eventType, eventActor, etc...
 * 
 * Issue warning if eventTypes has nonzero count of x eventTypes, and any another column 
 * has a different number of event values than eventTypes. Do not warn for empty fields.
 * 
 * @package    symfony
 * @subpackage task
 * @author     Steve Breker <sbreker@artefactual.com>
 */

class CsvEventValuesTest extends CsvBaseTest
{
  protected $matchList = [
    'eventTypes',
    'eventDates',
    'eventStartDates',
    'eventEndDates',
    'eventActors',
    'eventActorHistories',
    'eventPlaces',
  ];

  protected $columnsFound = [];
  protected $columnsChecked = false;
  protected $countMismatchedRows = 0;

  const TITLE = 'Event Value Count Test';

  public function __construct(array $options = null)
  {
    parent::__construct($options);

    $this->setTitle(self::TITLE);
    $this->reset();
  }

  public function reset()
  {
    $this->columnsFound = [];
    $this->columnsChecked = false;
    $this->countMismatchedRows = 0;
    
    parent::reset();
  }

  public function testRow(array $header, array $row)
  {
    $fieldCounts = [];

    parent::testRow($header, $row);
    $row = $this->combineRow($header, $row);

    // Check which columns are present on first row read.
    if (!$this->columnsChecked)
    {
      // Loop over each configured column check and see if they are present in the import CSV.
      foreach ($this->matchList as $columnName)
      {
        if (array_key_exists($columnName, $row))
        {
          $this->columnsFound[] = $columnName;
        } 
      }
      $this->columnsChecked = true;
    }

    // Check each field present.
    foreach ($this->columnsFound as $columnName)
    {
      // Split the row field by pipe "|" and count them. Trim all piped values.
      $values = array_map('trim', explode("|", $row[$columnName]));

      if (0 < $count = count(array_filter($values)))
      {
        $fieldCounts[$columnName] = $count;
      }
    }

    // If the number of fields differ within a row's event fields, count it.
    if (!empty($fieldCounts) && 1 != count(array_unique($fieldCounts)))
    {
      $this->countMismatchedRows++;

      $this->addTestResult(self::TEST_DETAIL, implode(',', $row));
    }
  }

  public function getTestResult()
  {
    if (empty($this->columnsFound))
    {
      $this->addTestResult(self::TEST_RESULTS, "No event columns to check.");
    }
    else
    {
      $this->addTestResult(self::TEST_RESULTS, sprintf("Checking columns: %s", implode(",", $this->columnsFound)));
    }

    if (0 < $this->countMismatchedRows)
    {
      $this->addTestResult(self::TEST_STATUS, self::RESULT_WARN);

      $this->addTestResult(self::TEST_RESULTS, sprintf("Event value mismatches found: %s", $this->countMismatchedRows));
    }

    return parent::getTestResult();
  }
}
