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
 * CSV validation result collection class.
 *
 * @author     Steve Breker <sbreker@artefactual.com>
 */
class CsvValidatorResultCollection
{
    protected $results = [];

    public function __construct()
    {
    }

    public function appendResult(CsvValidatorResult $result)
    {
        $this->results[] = $result;

        $this->sortByFilenameStatusDescending();
    }

    public function toArray()
    {
        $resultArray = [];

        foreach ($this->results as $testResult) {
            $resultArray[$testResult->getFilename()][$testResult->getClassname()] = $testResult->toArray();
        }

        return $resultArray;
    }

    public function toJson()
    {
        return json_encode($this->toArray());
    }

    public function getResultByFilenameTestname(string $filename, string $testname)
    {
        foreach ($this->results as $result) {
            if ($filename == $result->getFilename() && $testname == $result->getClassname()) {
                return $result->toArray();
            }
        }

        return $this->results[$filename][$testname]->toArray();
    }

    public function getErrorCount()
    {
        $errorCount = 0;

        foreach ($this->results as $testResult) {
            if (CsvValidatorResult::RESULT_ERROR === $testResult->getStatus()) {
                ++$errorCount;
            }
        }

        return $errorCount;
    }

    public function getWarnCount()
    {
        $warnCount = 0;

        foreach ($this->results as $testResult) {
            if (CsvValidatorResult::RESULT_WARN === $testResult->getStatus()) {
                ++$warnCount;
            }
        }

        return $warnCount;
    }

    public function sortByFilenameStatusDescending()
    {
        uasort($this->results, ['CsvValidatorResultCollection', 'compare']);
    }

    protected function compare($a, $b)
    {
        if ($a->getFilename() == $b->getFilename()) {
            if ($a->getStatus() == $b->getStatus()) {
                return 0;
            }

            return ($a->getStatus() > $b->getStatus()) ? -1 : 1;
        }

        return ($a->getFilename() < $b->getFilename()) ? -1 : 1;
    }
}
