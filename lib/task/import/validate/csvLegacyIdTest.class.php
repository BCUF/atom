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
 * CSV legacyId test. Check if legacyId is blank.
 * Output error status and any rows where legacyId is not found.
 * 
 * @package    symfony
 * @subpackage task
 * @author     Steve Breker <sbreker@artefactual.com>
 */

class CsvLegacyIdTest extends CsvBaseTest
{
  // Persist across multiple CSVs.
  protected $legacyIdValues = [];
  
  // Reset after every CSV.
  protected $legacyIdColumnPresent = null;
  protected $rowsWithoutLegacyId = 0;
  protected $nonUniqueLegacyIdValues = [];

  const TITLE = 'LegacyId check';

  public function __construct(array $options = null)
  {
    parent::__construct($options);

    $this->setTitle(self::TITLE);
    $this->reset();
  }

  public function reset()
  {
    $this->legacyIdColumnPresent = null;
    $this->rowsWithoutLegacyId = 0;
    $this->nonUniqueLegacyIdValues = [];

    parent::reset();
  }

  public function testRow(array $header, array $row)
  {
    parent::testRow($header, $row);
    $row = $this->combineRow($header, $row);

    // Is legacyId column present?
    if (!isset($this->legacyIdColumnPresent))
    {
      $this->legacyIdColumnPresent = array_key_exists('legacyId', $row);
    }

    if ($this->legacyIdColumnPresent)
    {
      if (empty($row['legacyId']))
      {
        $this->rowsWithoutLegacyId++;
        $this->addTestResult(self::TEST_DETAIL, implode(',', $row));
      }
      else
      {
        if (in_array($row['legacyId'],$this->legacyIdValues))
        {
          // This is a duplicate legacyId. Add to list of non-unique legacyIds.
          $this->nonUniqueLegacyIdValues[] = $row['legacyId'];
        }
        else
        {
          $this->legacyIdValues[] = $row['legacyId'];
        }
      }      
    }

  }

  public function getTestResult()
  {
    if (false == $this->legacyIdColumnPresent)
    {
      $this->addTestResult(self::TEST_STATUS, self::RESULT_WARN);
      $this->addTestResult(self::TEST_RESULTS, sprintf("'legacyId' column not present. Future CSV updates may not match these records."));
    }
    else
    {
      // Rows exist with non unique legacyId. Warn that this will complicate/break future CSV update matching.
      if (0 < count($this->nonUniqueLegacyIdValues))
      {
        $this->addTestResult(self::TEST_STATUS, self::RESULT_ERROR);
        $this->addTestResult(self::TEST_RESULTS, sprintf("Rows with non-unique 'legacyId' values: %s", count($this->nonUniqueLegacyIdValues)));
        $this->addTestResult(self::TEST_DETAIL, sprintf("Non-unique 'legacyId' values: %s", implode(', ', $this->nonUniqueLegacyIdValues)));
      }
      else
      {
        $this->addTestResult(self::TEST_STATUS, self::RESULT_INFO);
        $this->addTestResult(self::TEST_RESULTS, sprintf("'legacyId' values are all unique."));
      }

      // Rows exist with both parentId and qubitParentSlug populated. Warn that qubitParentSlug will override.
      if (0 < $this->rowsWithoutLegacyId)
      {
        $this->addTestResult(self::TEST_STATUS, self::RESULT_WARN);
        $this->addTestResult(self::TEST_RESULTS, sprintf("Rows with empty 'legacyId' column: %s", $this->rowsWithoutLegacyId));
      }

      if (0 < $this->rowsWithoutLegacyId || 0 < count($this->nonUniqueLegacyIdValues))
      {
        $this->addTestResult(self::TEST_RESULTS, sprintf("Future CSV updates may not match these records."));
      }
    }

    return parent::getTestResult();
  }
}
