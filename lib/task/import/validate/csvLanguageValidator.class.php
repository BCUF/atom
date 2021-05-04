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
 * CSV language column test. Check if present, check values against master list.
 *
 * @author     Steve Breker <sbreker@artefactual.com>
 *
 * @internal
 * @coversNothing
 */
class CsvLanguageValidator extends CsvBaseValidator
{
    const TITLE = 'Language Check';
    protected $languages = [];

    protected $languageColumnPresent;
    protected $rowsWithInvalidLanguage = 0;
    protected $invalidLanguages = [];

    public function __construct(array $options = null)
    {
        $this->setTitle(self::TITLE);
        parent::__construct($options);

        $this->languages = array_keys(sfCultureInfo::getInstance()->getLanguages());
    }

    public function reset()
    {
        $this->languageColumnPresent = null;
        $this->rowsWithPipeFoundInLanguage = 0;
        $this->rowsWithInvalidLanguage = 0;
        $this->invalidLanguages = [];

        parent::reset();
    }

    public function testRow(array $header, array $row)
    {
        parent::testRow($header, $row);
        $row = $this->combineRow($header, $row);

        // Set if language column is present.
        if (!isset($this->languageColumnPresent)) {
            $this->languageColumnPresent = array_key_exists('language', $row);
        }

        // If present check contents.
        if ($this->languageColumnPresent) {
            if (!empty($row['language'])) {
                // Validate language value against AtoM.
                $errorDetailAdded = false;
                foreach (explode('|', $row['language']) as $value) {
                    if (!$this->isLanguageValid(trim($value))) {
                        if (!$errorDetailAdded) {
                            ++$this->rowsWithInvalidLanguage;
                            $this->testData->addDetail(implode(',', $row));
                            $errorDetailAdded = true;
                        }

                        // Keep a list of invalid language values.
                        if (!in_array($row['language'], $this->invalidLanguages)) {
                            $this->invalidLanguages[] = trim($value);
                        }
                    }
                }
            }
        }
    }

    public function getTestResult()
    {
        $this->testData->setStatusInfo();

        if (false == $this->languageColumnPresent) {
            // language column not present in file.
            $this->testData->setStatusInfo();
            $this->testData->addResult(sprintf("'language' column not present in file."));
        } else {
            // Rows exist with invalid language.
            if (0 < $this->rowsWithInvalidLanguage) {
                $this->testData->setStatusError();
                $this->testData->addResult(sprintf('Rows with invalid language values: %s', $this->rowsWithInvalidLanguage));
            }

            if (0 < $this->rowsWithInvalidLanguage) {
                $this->testData->addResult(sprintf('Invalid language values: %s', implode(', ', $this->invalidLanguages)));
            }

            if (0 == $this->rowsWithInvalidLanguage) {
                $this->testData->addResult(sprintf("'language' column values are all valid."));
            }
        }

        return parent::getTestResult();
    }

    // TODO: Remove these DB accesses to a wrapper class so it's not performed in the
    // test class itself.
    protected function isLanguageValid(string $language)
    {
        if (!empty($language)) {
            return in_array($language, $this->languages);
        }
    }
}
