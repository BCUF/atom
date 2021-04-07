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
 * CSV column name test. Validate column names against list of valid AtoM import fields.
 * Output warning if unknown column names found. Output list of unknown column names.
 * Validate against files in 'lib/flatfile/config'.
 * 
 * @package    symfony
 * @subpackage task
 * @author     Steve Breker <sbreker@artefactual.com>
 */

class CsvColumnNameTest extends CsvBaseTest
{
  // Do not reset in between multiple CSVs.
  protected $validColumnNames = [];
  protected $validColumnNamesLowercase = [];

  protected $unknownColumnNames = [];
  protected $caseIssuesColumnNameMap = [];
  protected $trimIssuesColumnNames = [];
  protected $complete = false;

  const TITLE = 'Column Name Validation';

  public function __construct(array $options = null)
  {
    parent::__construct($options);

    $this->setTitle(self::TITLE);
    $this->reset();

    $this->loadObjectColumnNames($this->options['className']);
  }

  public function reset()
  {
    $this->complete = false;
    $this->unknownColumnNames = [];
    $this->caseIssuesColumnNameMap = [];
    $this->trimIssuesColumnNames = [];

    parent::reset();
  }

  // Load the column name config from lib/flatfile/config.
  protected function loadObjectColumnNames($resourceClass)
  {
    $resourceTypeBaseConfigFile = $resourceClass .'.yml';
    $config = QubitFlatfileExport::loadResourceConfigFile($resourceTypeBaseConfigFile, 'base');

    $this->validColumnNames = $config['columnNames'];
    $standardColumns   = isset($config['direct']) ? $config['direct'] : array();
    $columnMap         = isset($config['map']) ? $config['map'] : array();
    $propertyMap       = isset($config['property']) ? $config['property'] : array();

    // If column names/order aren't specified, derive them
    if ($this->validColumnNames === null)
    {
      // Add standard columns
      $this->validColumnNames = ($standardColumns !== null) ? $standardColumns : array();

      // Add from column map
      if ($columnMap !== null)
      {
        $this->validColumnNames = array_merge($this->validColumnNames, array_values($columnMap));
      }

      // Add from property map
      if ($propertyMap !== null)
      {
        $this->validColumnNames = array_merge($this->validColumnNames, array_values($propertyMap));
      }
    }

    $this->validColumnNamesLowercase = array_map('strtolower', $this->validColumnNames);
  }

  public function testRow(array $header, array $row)
  {
    parent::testRow($header, $row);

    // Only do this check once per file.
    if (!$this->complete)
    {
      foreach ($header as $columnName)
      {
        // If $columnName is not in validColumnNames.
        if (!in_array($columnName, $this->validColumnNames))
        {
          // Test for leading or trailing whitespace.
          if (in_array(trim($columnName), $this->validColumnNames))
          {
            $this->trimIssuesColumnNames[$columnName] = trim($columnName);
          }
          // Test if a match is found using lowercase trimmed matching.
          else if (false !== $key = array_search(strtolower(trim($columnName)), $this->validColumnNamesLowercase))
          {
            // Map unknown column name to possible match.
            $this->caseIssuesColumnNameMap[trim($columnName)] = $this->validColumnNames[$key];
          }

          // Add to unknown column name list.
          $this->unknownColumnNames[$columnName] = $columnName;
        }
      }

      $this->complete = true;
    }
  }

  public function getTestResult()
  {
    $this->addTestResult(self::TEST_RESULTS, sprintf("Number of unrecognized column names found in CSV: %s", count($this->unknownColumnNames)));

    if (0 < count($this->unknownColumnNames))
    {
      $this->addTestResult(self::TEST_STATUS, self::RESULT_WARN);
      $this->addTestResult(self::TEST_RESULTS, "Unrecognized columns will be ignored by AtoM when the CSV is imported.");
      foreach ($this->unknownColumnNames as $unknownColumnName)
      {
        $this->addTestResult(self::TEST_DETAIL, sprintf("Unrecognized column: %s", $unknownColumnName));
      }
    }

    if (0 < count($this->trimIssuesColumnNames))
    {
      $this->addTestResult(self::TEST_STATUS, self::RESULT_WARN);
      $this->addTestResult(self::TEST_RESULTS, sprintf("Number of column names with leading or trailing whitespace characters: %s", count($this->trimIssuesColumnNames)));
      $this->addTestResult(self::TEST_DETAIL, sprintf("Column names with leading or trailing whitespace: %s", implode(',', $this->trimIssuesColumnNames)));
    }

    if (0 < count($this->caseIssuesColumnNameMap))
    {
      $this->addTestResult(self::TEST_STATUS, self::RESULT_WARN);
      $this->addTestResult(self::TEST_RESULTS, sprintf("Number of unrecognized columns that may be case related: %s", count($this->caseIssuesColumnNameMap)));

      foreach ($this->caseIssuesColumnNameMap as $key => $value)
      {
        $this->addTestResult(self::TEST_DETAIL, sprintf("Possible match for %s: %s", $key, $value));
      }
    }

    return parent::getTestResult();
  }
}
