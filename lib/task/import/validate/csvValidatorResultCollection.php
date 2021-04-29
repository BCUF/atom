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

    public function appendResult(CsvValidatorResult $result, string $filename, string $testname)
    {
        $this->results[$filename][$testname] = $result;
    }

    public function toArray()
    {
        $resultArray = [];

        foreach ($this->results as $filename => $fileTests) {
            foreach ($fileTests as $testname => $testResult) {
                $resultArray[$filename][$testname] = $testResult->toArray();
            }
        }

        return $resultArray;
    }

    public function toJson()
    {
        return json_encode($this->toArray());
    }

    public function getResultByFilenameTestname(string $filename, string $testname)
    {
        return $this->results[$filename][$testname]->toArray();
    }

    public function renderAsText()
    {
        foreach ($this->results as $filename => $fileGroup) {
            $fileStr = sprintf("\nFilename: %s", $filename);
            printf("%s\n", $fileStr);
            printf("%s\n", str_repeat('=', strlen($fileStr)));

            foreach ($fileGroup as $testResult) {
                printf("\n%s - %s\n", $testResult->getTitle(), $this->formatStatus($testResult->getStatus()));
                printf("%s\n", str_repeat('-', strlen($testResult->getTitle())));

                foreach ($testResult->getResults() as $line) {
                    printf("%s\n", $line);
                }

                if ($this->verbose && 0 < count($testResult->getDetails())) {
                    printf("\nDetails:\n");

                    foreach ($testResult->getDetails() as $line) {
                        printf("%s\n", $line);
                    }
                }
            }
        }
    }

    protected function formatStatus(int $status)
    {
        switch ($status) {
            case CsvValidatorResult::RESULT_INFO:
                return 'info';

            case CsvValidatorResult::RESULT_WARN:
                return 'Warning';

            case CsvValidatorResult::RESULT_ERROR:
                return 'ERROR';
        }
    }
}
