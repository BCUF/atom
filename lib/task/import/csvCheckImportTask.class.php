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
 * Check csv data.
 *
 * @author     Mike Cantelon <mike@artefactual.com>
 * @author     Steve Breker <sbreker@artefactual.com>
 */
class csvCheckImportTask extends arBaseTask
{
    protected $verbose;

    /**
     * @see sfTask
     *
     * @param mixed $arguments
     * @param mixed $options
     */
    public function execute($arguments = [], $options = [])
    {
        parent::execute($arguments, $options);

        $validatorOptions = $this->setOptions($options);

        if (isset($options['verbose']) && $options['verbose']) {
            $this->verbose = true;
        }

        $filenames = $this->setCsvValidatorFilenames($arguments['filename']);

        $validator = new CsvImportValidator(
            $this->context, $this->getDbConnection(), $validatorOptions);

        $validator->setShowDisplayProgress(true);
        $validator->setFilenames($filenames);
        $results = $validator->validate();
        $this->printResults($results);

        unset($validator);
    }

    protected function configure()
    {
        $this->addArguments([
            new sfCommandArgument('filename', sfCommandArgument::REQUIRED,
              'The input file name (csv format).'),
        ]);

        $this->addOptions([
            new sfCommandOption('application', null,
                sfCommandOption::PARAMETER_OPTIONAL, 'The application name', 'qubit'
            ),
            new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED,
              'The environment', 'cli'
            ),
            new sfCommandOption('connection', null,
                sfCommandOption::PARAMETER_REQUIRED, 'The connection name', 'propel'
            ),
            new sfCommandOption('verbose', 'i',
                sfCommandOption::PARAMETER_NONE,
                'Provide detailed information regarding each test'
            ),
            new sfCommandOption('source', null,
                sfCommandOption::PARAMETER_REQUIRED,
                'Source name for validating parentId matching against previous imports. If not set, parentId validation against AtoM\'s database will be skipped.'
            ),
            new sfCommandOption('class-name', null,
                sfCommandOption::PARAMETER_REQUIRED,
                'Qubit object type contained in CSV.',
                'QubitInformationObject'
            ),
            new sfCommandOption('specific-tests', null,
                sfCommandOption::PARAMETER_REQUIRED,
                'Specific test classes to run.'
            ),
            new sfCommandOption('separator', null,
                sfCommandOption::PARAMETER_REQUIRED,
                'Optional separator parameter sets CSV field separator (1 character).',
                ','
            ),
            new sfCommandOption('enclosure', null,
                sfCommandOption::PARAMETER_REQUIRED,
                'Optional enclosure parameter sets CSV field enclosure character (1 character).',
                '"'
            ),
        ]);

        $this->namespace = 'csv';
        $this->name = 'check-import';
        $this->briefDescription = 'Check CSV data, providing diagnostic info.';
        $this->detailedDescription = <<<'EOF'
    Check CSV data, providing information about it.
EOF;
    }

    protected function getDbConnection()
    {
        $databaseManager = new sfDatabaseManager($this->configuration);

        return $databaseManager->getDatabase('propel')->getConnection();
    }

    protected function setCsvValidatorFilenames($filenameString)
    {
        // Could be a comma separated list of filenames or just one.
        $filenames = explode(',', $filenameString);

        foreach ($filenames as $filename) {
            CsvImportValidator::validateFileName($filename);
        }

        return $filenames;
    }

    protected function setOptions($options = [])
    {
        $this->validateOptions($options);

        $opts = [];

        $keymap = [
            'verbose' => 'verbose',
            'source' => 'source',
            'class-name' => 'className',
            'separator' => 'separator',
            'enclosure' => 'enclosure',
            'escape' => 'escape',
            'specific-tests' => 'specificTests',
        ];

        foreach ($keymap as $oldkey => $newkey) {
            if (empty($options[$oldkey])) {
                continue;
            }

            $opts[$newkey] = $options[$oldkey];
        }

        return $opts;
    }

    protected function validateOptions($options = [])
    {
        // Throw exception here if set option is invalid.

        // TODO: Add validation of class-name
    }

    protected function formatStatus(int $status)
    {
        switch ($status) {
            case csvBaseTest::RESULT_INFO:
                return 'info';

              case csvBaseTest::RESULT_WARN:
                return 'Warning';

            case csvBaseTest::RESULT_ERROR:
                return 'ERROR';
        }
    }

    protected function printResults(array $results)
    {
        foreach ($results as $filename => $fileGroup) {
            $fileStr = sprintf("\nFilename: %s", $filename);
            printf("%s\n", $fileStr);
            printf("%s\n", str_repeat('=', strlen($fileStr)));

            foreach ($fileGroup as $testResult) {
                printf("\n%s - %s\n", $testResult['title'], $this->formatStatus($testResult['status']));
                printf("%s\n", str_repeat('-', strlen($testResult['title'])));

                foreach ($testResult['results'] as $line) {
                    printf("%s\n", $line);
                }

                if ($this->verbose && 0 < count($testResult['details'])) {
                    printf("\nDetails:\n");

                    foreach ($testResult['details'] as $line) {
                        printf("%s\n", $line);
                    }
                }
            }
        }
    }
}
