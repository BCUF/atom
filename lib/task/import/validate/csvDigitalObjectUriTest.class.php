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
 * Check digitalObjectURI and verify URI is properly constructed:
 *  - Check if URIs are duplicated across multiple rows (the same way as paths are checked).
 *  - Check if protocol is http/https.
 *  - Use filter_var($url, FILTER_VALIDATE_URL) to verify that it is a properly formatted URI.
 *
 * @author     Steve Breker <sbreker@artefactual.com>
 */

/**
 * @internal
 * @coversNothing
 */
class CsvDigitalObjectUriTest extends CsvBaseTest
{
    const TITLE = 'Digital Object URI Test';

    protected $digitalObjectUriColumnPresent;
    protected $digitalObjectUses = [];

    public function __construct(array $options = null)
    {
        parent::__construct($options);

        $this->setTitle(self::TITLE);
        $this->reset();
    }

    public function reset()
    {
        $this->digitalObjectUses = [];
        $this->digitalObjectUriColumnPresent = null;

        parent::reset();
    }

    public function testRow(array $header, array $row)
    {
        parent::testRow($header, $row);
        $row = $this->combineRow($header, $row);

        if (!isset($this->digitalObjectUriColumnPresent)) {
            $this->digitalObjectUriColumnPresent = array_key_exists('digitalObjectUri', $row);
        }

        if ($this->digitalObjectUriColumnPresent) {
            if (!empty($row['digitalObjectUri'])) {
                $this->addToUsageSummary($row['digitalObjectUri']);
            }
        }
    }

    public function getTestResult()
    {
        if (false == $this->digitalObjectUriColumnPresent) {
            $this->addTestResult(self::TEST_STATUS, self::RESULT_INFO);
            $this->addTestResult(self::TEST_RESULTS, sprintf("Column 'digitalObjectUri' not present in CSV. Nothing to verify."));
        } else {
            $this->addTestResult(self::TEST_RESULTS, sprintf("Column 'digitalObjectUri' found."));

            if (empty($this->digitalObjectUses)) {
                $this->addTestResult(self::TEST_STATUS, self::RESULT_INFO);
                $this->addTestResult(self::TEST_RESULTS, sprintf("Column 'digitalObjectUri' is empty."));
            } else {
                $digitalObjectUrisUsedMoreThanOnce = $this->getUsedMoreThanOnce();

                if (!empty($digitalObjectUrisUsedMoreThanOnce)) {
                    $this->addTestResult(self::TEST_STATUS, self::RESULT_WARN);
                    $this->addTestResult(self::TEST_RESULTS, sprintf('Repeating Digital object URIs found in CSV.'));

                    foreach ($digitalObjectUrisUsedMoreThanOnce as $uri) {
                        $this->addTestResult(self::TEST_DETAIL, sprintf("Number of duplicates for URI '%s': %s", $uri, $this->digitalObjectUses[$uri]));
                    }
                }

                $invalidUris = $this->getInvalidUris();

                if (!empty($invalidUris)) {
                    $this->addTestResult(self::TEST_STATUS, self::RESULT_ERROR);
                    $this->addTestResult(self::TEST_RESULTS, sprintf('Invalid digitalObjectUri values detected: %s', count($invalidUris)));

                    foreach ($invalidUris as $file) {
                        $this->addTestResult(self::TEST_DETAIL, sprintf('Invalid URI: %s', $file));
                    }
                }
            }
        }

        return parent::getTestResult();
    }

    protected function getUsedMoreThanOnce()
    {
        $usedMoreThanOnce = [];

        foreach ($this->digitalObjectUses as $digitalObjectUri => $uses) {
            if ($uses > 1) {
                array_push($usedMoreThanOnce, $digitalObjectUri);
            }
        }

        return $usedMoreThanOnce;
    }

    protected function addToUsageSummary($value)
    {
        $this->digitalObjectUses[$value] = (!isset($this->digitalObjectUses[$value])) ? 1 : $this->digitalObjectUses[$value] + 1;
    }

    private function getInvalidUris()
    {
        $invalidUris = [];

        foreach ($this->digitalObjectUses as $uri => $uses) {
            if (false === filter_var($uri, FILTER_VALIDATE_URL)) {
                array_push($invalidUris, $uri);
            } else {
                $parsedUri = parse_url($uri);

                if (!('https' == $parsedUri['scheme'] || 'http' == $parsedUri['scheme'])) {
                    array_push($invalidUris, $uri);
                }
            }
        }

        return $invalidUris;
    }
}
