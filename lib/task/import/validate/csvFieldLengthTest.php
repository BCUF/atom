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
 * CSV field length test. Define fields to be checked and their max lengths.
 * Allow to trigger either warning or error by column type.
 *
 * @author     Steve Breker <sbreker@artefactual.com>
 *
 * @internal
 * @coversNothing
 */
class CsvFieldLengthTest extends CsvBaseTest
{
    const TITLE = 'Field Length Check';
    // Add fields to check legth of here with max len. Anything larger than this value will
    // trigger the default action.
    protected $fieldMaxSizes = [
        'culture' => 6,
        'language' => 6,
        'script' => 6,
    ];

    // Associate action with field.
    protected $fieldAction = [
        'culture' => self::RESULT_WARN,
        'language' => self::RESULT_WARN,
        'script' => self::RESULT_WARN,
    ];

    protected $columnsFound = [];
    protected $columnsChecked = false;

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

        parent::reset();
    }

    public function testRow(array $header, array $row)
    {
        parent::testRow($header, $row);
        $row = $this->combineRow($header, $row);

        // Check which columns are present on first row read.
        if (!$this->columnsChecked) {
            // Loop over each configured column check and see if they are present in the import CSV.
            foreach ($this->fieldMaxSizes as $columnName => $fieldSize) {
                if (array_key_exists($columnName, $row)) {
                    $this->columnsFound[$columnName] = 0;
                }
            }

            $this->columnsChecked = true;
        }

        // Check each field present.
        foreach ($this->columnsFound as $columnName => $errorCount) {
            // Check if value length is greater than configured max field length.
            if (strlen($row[$columnName]) > $this->fieldMaxSizes[$columnName]) {
                ++$this->columnsFound[$columnName];

                $this->addTestResult(self::TEST_DETAIL, sprintf('%s column value: %s', $columnName, $row[$columnName]));
            }
        }
    }

    public function getTestResult()
    {
        $this->addTestResult(self::TEST_STATUS, self::RESULT_INFO);

        if (empty($this->columnsFound)) {
            $this->addTestResult(self::TEST_RESULTS, 'No columns to check.');
        } else {
            $this->addTestResult(self::TEST_RESULTS, sprintf('Checking columns: %s', implode(',', array_keys($this->columnsFound))));
        }

        foreach ($this->columnsFound as $columnName => $errorCount) {
            if (0 < $errorCount) {
                $this->addTestResult(self::TEST_STATUS, self::RESULT_WARN);
                $this->addTestResult(self::TEST_RESULTS, sprintf("'%s' column may have invalid values.", $columnName));
            }
            $this->addTestResult(self::TEST_RESULTS, sprintf("'%s' values that exceed %s characters: %s", $columnName, $this->fieldMaxSizes[$columnName], $errorCount));
        }

        return parent::getTestResult();
    }
}
