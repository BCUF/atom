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
 * CSV culture column test. Check if present, check values against master list,
 * and check if piped value.
 * 
 * @package    symfony
 * @subpackage task
 * @author     Steve Breker <sbreker@artefactual.com>
 */

class CsvCultureTest extends CsvBaseTest
{
  protected $cultureColumnPresent = null;
  protected $rowsWithBlankCulture = 0;
  protected $rowsWithPipeFoundInCulture = 0;
  protected $rowsWithInvalidCulture = 0;
  protected $invalidCultures = [];

  const TITLE = 'Culture Column Test';

  public function __construct()
  {
    parent::__construct();

    $this->setTitle(self::TITLE);
    $this->reset();
  }

  public function reset()
  {
    $this->cultureColumnPresent = null;
    $this->rowsWithBlankCulture = 0;
    $this->rowsWithPipeFoundInCulture = 0;
    $this->rowsWithInvalidCulture = 0;
    $this->invalidCultures = [];

    parent::reset();
  }

  // TODO: Remove these DB accesses to a wrapper class so it's not performed in the
  // test class itself.
  protected function isCultureValid(string $culture)
  {
    if (!empty($culture))
    {
      return sfCultureInfo::validCulture($culture);
    }
  }

  public function testRow(array $header, array $row)
  {
    parent::testRow($header, $row);
    $row = $this->combineRow($header, $row);

    // Set if culture column is present.
    if (!isset($this->cultureColumnPresent))
    {
      $this->cultureColumnPresent = array_key_exists('culture', $row);
    }

    // If present check contents.
    if ($this->cultureColumnPresent)
    {
      if (empty($row['culture']))
      {
        $this->rowsWithBlankCulture++;
      }
      else
      {
        // Check if contains pipe.
        if (0 < strpos($row['culture'], "|"))
        {
          $this->rowsWithPipeFoundInCulture++;
          $this->addTestResult(self::TEST_DETAIL, implode(',', $row));

          // Keep a list of invalid culture values.
          if (!in_array($row['culture'], $this->invalidCultures))
          {
            $this->invalidCultures[] = $row['culture'];
          }
        }
        // Validate culture value against AtoM.
        else if (!$this->isCultureValid($row['culture']))
        {
          $this->rowsWithInvalidCulture++;
          $this->addTestResult(self::TEST_DETAIL, implode(',', $row));

          // Keep a list of invalid culture values.
          if (!in_array($row['culture'], $this->invalidCultures))
          {
            $this->invalidCultures[] = $row['culture'];
          }
        }
      }
    }
  }

  public function getTestResult()
  {
    $this->addTestResult(self::TEST_STATUS, self::RESULT_INFO);

    if (false == $this->cultureColumnPresent)
    {
      // culture column not present in file.
      $this->addTestResult(self::TEST_STATUS, self::RESULT_WARN);
      $this->addTestResult(self::TEST_RESULTS, sprintf("'culture' column not present in file."));
      $this->addTestResult(self::TEST_RESULTS, sprintf("Rows without a valid culture value will be imported using AtoM's default source culture."));
    }
    else
    {
      // Rows exist without culture populated.
      if (0 < $this->rowsWithBlankCulture)
      {
        $this->addTestResult(self::TEST_STATUS, self::RESULT_WARN);
        $this->addTestResult(self::TEST_RESULTS, sprintf("Rows with blank culture value: %s", $this->rowsWithBlankCulture));
      }

      // Rows exist with invalid culture.
      if (0 < $this->rowsWithInvalidCulture)
      {
        $this->addTestResult(self::TEST_STATUS, self::RESULT_ERROR);
        $this->addTestResult(self::TEST_RESULTS, sprintf("Rows with invalid culture values: %s", $this->rowsWithInvalidCulture));
      }

      // Rows exist with culture containing pipe '|'
      if (0 < $this->rowsWithPipeFoundInCulture)
      {
        $this->addTestResult(self::TEST_STATUS, self::RESULT_ERROR);
        $this->addTestResult(self::TEST_RESULTS, sprintf("Rows with pipe character in culture values: %s", $this->rowsWithPipeFoundInCulture));
        $this->addTestResult(self::TEST_RESULTS, sprintf("'culture' column does not allow for multiple values separated with a pipe '|' character."));
      }

      if (0 < $this->rowsWithInvalidCulture || 0 < $this->rowsWithPipeFoundInCulture)
      {
        $this->addTestResult(self::TEST_RESULTS, sprintf("Invalid culture values: %s", implode(", ", $this->invalidCultures)));
      }

      if (0 < $this->rowsWithBlankCulture || 0 < $this->rowsWithInvalidCulture)
      {
        $this->addTestResult(self::TEST_RESULTS, sprintf("Rows with a blank culture value will be imported using AtoM's default source culture."));
      }

      if (0 == $this->rowsWithBlankCulture && 0 == $this->rowsWithInvalidCulture && 0 == $this->rowsWithPipeFoundInCulture)
      {
        $this->addTestResult(self::TEST_RESULTS, sprintf("'culture' column values are all valid."));
      }
    }

    return parent::getTestResult();
  }
}
