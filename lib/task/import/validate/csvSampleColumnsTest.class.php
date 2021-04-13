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
 * CSV Sample Values test. Output column names and a sample value from first
 * populated row found. Only populated columns are included.
 *
 * @author     Steve Breker <sbreker@artefactual.com>
 *
 * @internal
 * @coversNothing
 */
class CsvSampleValuesTest extends CsvBaseTest
{
    const TITLE = 'Sample Values';
    protected $values = [];

    public function __construct(array $options = null)
    {
        parent::__construct($options);

        $this->setTitle(self::TITLE);
        $this->reset();
    }

    public function reset()
    {
        $this->values = [];

        parent::reset();
    }

    public function testRow(array $header, array $row)
    {
        parent::testRow($header, $row);
        $row = $this->combineRow($header, $row);

        foreach ($row as $columnName => $value) {
            // Create sample values array.
            if (!array_key_exists($columnName, $this->values) && !empty($value)) {
                $this->values[$columnName] = $value;
            }
        }
    }

    public function getTestResult()
    {
        $this->addTestResult(self::TEST_STATUS, self::RESULT_INFO);

        foreach ($this->values as $columnName => $sampleValue) {
            $this->addTestResult(self::TEST_RESULTS, sprintf('%s:  %s', $columnName, $sampleValue));
        }

        return parent::getTestResult();
    }
}
