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
 * CSV language column test. Check if present, check values against master list,
 * and check if piped value.
 * 
 * @package    symfony
 * @subpackage task
 * @author     Steve Breker <sbreker@artefactual.com>
 */

class CsvLanguageTest extends CsvBaseTest
{
  protected $languages = [];

  protected $languageColumnPresent = null;
  protected $rowsWithPipeFoundInLanguage = 0;
  protected $rowsWithInvalidLanguage = 0;
  protected $invalidLanguages = [];

  const TITLE = 'Language Check';

  public function __construct(array $options = null)
  {
    parent::__construct($options);

    $this->languages = array_keys(sfCultureInfo::getInstance()->getLanguages());

    $this->setTitle(self::TITLE);
    $this->reset();
  }

  public function reset()
  {
    $this->languageColumnPresent = null;
    $this->rowsWithPipeFoundInLanguage = 0;
    $this->rowsWithInvalidLanguage = 0;
    $this->invalidLanguages = [];

    parent::reset();
  }

  // TODO: Remove these DB accesses to a wrapper class so it's not performed in the
  // test class itself.
  protected function isLanguageValid(string $language)
  {
    if (!empty($language))
    {
      return in_array($language, $this->languages);
    }
  }

  public function testRow(array $header, array $row)
  {
    parent::testRow($header, $row);
    $row = $this->combineRow($header, $row);

    // Set if language column is present.
    if (!isset($this->languageColumnPresent))
    {
      $this->languageColumnPresent = array_key_exists('language', $row);
    }

    // If present check contents.
    if ($this->languageColumnPresent)
    {
      if (!empty($row['language']))
      {
        // Check if contains pipe.
        if (0 < strpos($row['language'], "|"))
        {
          $this->rowsWithPipeFoundInLanguage++;
          $this->addTestResult(self::TEST_DETAIL, implode(',', $row));

          // Keep a list of invalid language values.
          if (!in_array($row['language'], $this->invalidLanguages))
          {
            $this->invalidLanguages[] = $row['language'];
          }
        }
        // Validate language value against AtoM.
        else if (!$this->isLanguageValid($row['language']))
        {
          $this->rowsWithInvalidLanguage++;
          $this->addTestResult(self::TEST_DETAIL, implode(',', $row));

          // Keep a list of invalid language values.
          if (!in_array($row['language'], $this->invalidLanguages))
          {
            $this->invalidLanguages[] = $row['language'];
          }
        }
      }
    }
  }

  public function getTestResult()
  {
    $this->addTestResult(self::TEST_STATUS, self::RESULT_INFO);

    if (false == $this->languageColumnPresent)
    {
      // language column not present in file.
      $this->addTestResult(self::TEST_STATUS, self::RESULT_INFO);
      $this->addTestResult(self::TEST_RESULTS, sprintf("'language' column not present in file."));
    }
    else
    {
      // Rows exist with invalid language.
      if (0 < $this->rowsWithInvalidLanguage)
      {
        $this->addTestResult(self::TEST_STATUS, self::RESULT_ERROR);
        $this->addTestResult(self::TEST_RESULTS, sprintf("Rows with invalid language values: %s", $this->rowsWithInvalidLanguage));
      }

      // Rows exist with language containing pipe '|'
      if (0 < $this->rowsWithPipeFoundInLanguage)
      {
        $this->addTestResult(self::TEST_STATUS, self::RESULT_ERROR);
        $this->addTestResult(self::TEST_RESULTS, sprintf("Rows with pipe character in language values: %s", $this->rowsWithPipeFoundInLanguage));
        $this->addTestResult(self::TEST_RESULTS, sprintf("'language' column does not allow for multiple values separated with a pipe '|' character."));
      }

      if (0 < $this->rowsWithInvalidLanguage || 0 < $this->rowsWithPipeFoundInLanguage)
      {
        $this->addTestResult(self::TEST_RESULTS, sprintf("Invalid language values: %s", implode(", ", $this->invalidLanguages)));
      }

      if (0 == $this->rowsWithInvalidLanguage && 0 == $this->rowsWithPipeFoundInLanguage)
      {
        $this->addTestResult(self::TEST_RESULTS, sprintf("'language' column values are all valid."));
      }
    }

    return parent::getTestResult();
  }
}
