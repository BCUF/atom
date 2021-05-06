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

class installTask extends sfBaseTask
{
    /**
     * @see sfTask
     *
     * @param mixed $arguments
     * @param mixed $options
     */
    public function execute($arguments = [], $options = [])
    {
        // TODO: check already configured instance

        $this->logSection('install', 'Configure database');

        $databaseOptions = [
            'databaseHost' => $this->getOptionValue(
                'database-host',
                $options,
                'Database host',
                'localhost'
            ),
            'databasePort' => $this->getOptionValue(
                'database-port',
                $options,
                'Database port',
                '3306'
            ),
            'databaseName' => $this->getOptionValue(
                'database-name',
                $options,
                'Database name',
                'atom'
            ),
            'databaseUsername' => $this->getOptionValue(
                'database-user',
                $options,
                'Database user',
                'atom'
            ),
            'databasePassword' => $this->getOptionValue(
                'database-password',
                $options,
                'Database password'
            ),
        ];

        $this->logSection('install', 'Configure search');

        $searchOptions = [
            'searchHost' => $this->getOptionValue(
                'search-host',
                $options,
                'Search host',
                'localhost'
            ),
            'searchPort' => $this->getOptionValue(
                'search-port',
                $options,
                'Search port',
                '9200'
            ),
            'searchIndex' => $this->getOptionValue(
                'search-index',
                $options,
                'Search index',
                'atom'
            ),
        ];

        if ($options['demo']) {
            $this->logSection('install', 'Setting demo options');

            $siteOptions = [
                'siteTitle' => 'Demo site',
                'siteDescription' => 'Demo site',
                'siteBaseUrl' => 'http://127.0.0.1',
            ];
            $adminOptions = [
                'email' => 'demo@example.com',
                'username' => 'demo',
                'password' => 'demo',
            ];
        } else {
            $this->logSection('install', 'Configure site');

            $siteOptions = [
                'siteTitle' => $this->getOptionValue(
                    'site-title',
                    $options,
                    'Site title',
                    'AtoM'
                ),
                'siteDescription' => $this->getOptionValue(
                    'site-description',
                    $options,
                    'Site description',
                    'Access to Memory'
                ),
                'siteBaseUrl' => $this->getOptionValue(
                    'site-base-url',
                    $options,
                    'Site base URL',
                    'http://127.0.0.1'
                ),
            ];

            $this->logSection('install', 'Configure admin user');

            $adminOptions = [
                'email' => $this->getOptionValue(
                    'admin-email',
                    $options,
                    'Admin email'
                ),
                'username' => $this->getOptionValue(
                    'admin-username',
                    $options,
                    'Admin username',
                ),
                'password' => $this->getOptionValue(
                    'admin-password',
                    $options,
                    'Admin password',
                ),
            ];
        }

        // TODO:
        // - Configure cache?
        // - Configure Gearman server?
        // - Other config settings:
        //   - csrf_secret
        //   - default_culture (maybe after data load)
        //   - default_timezone
        //   - others from app.yml

        // TODO: show final config and ask confirmation
        // $this->logSection('install', 'Confirm configuration');

        $this->logSection('install', 'Creating configuration files');

        // TODO: check need and CLI behavior of these functions
        sfInstall::checkDependencies();
        sfInstall::checkWritablePaths();
        sfInstall::checkDatabasesYml();
        sfInstall::checkPropelIni();
        sfInstall::checkMemoryLimit();
        sfInstall::checkSettingsYml(false);

        sfInstall::configureDatabase($databaseOptions);

        // TODO: properly report DB connection errors and stop
        $databaseManager = new sfDatabaseManager($this->configuration);
        $databaseManager->getDatabase('propel')->getConnection();

        // TODO: check DB existence and status and ask confirmation

        $errors = sfInstall::configureSearch($searchOptions);

        // TODO: properly report ES connection errors and stop
        foreach ($errors as $error) {
            echo $e;
        }

        $cacheClear = new sfCacheClearTask(
            $this->dispatcher,
            $this->formatter
        );
        $cacheClear->run();

        Propel::setDefaultDB('propel');

        $this->configuration = ProjectConfiguration::getApplicationConfiguration(
            'qubit',
            'cli',
            true
        );
        $this->context = sfContext::createInstance($this->configuration);

        arElasticSearchPluginConfiguration::reloadConfig(
            $this->context->getConfiguration()
        );

        $this->logSection('install', 'Initializing database');

        $insertSql = new sfPropelInsertSqlTask(
            $this->dispatcher,
            $this->formatter
        );
        $insertSql->run([], ['no-confirmation' => $options['no-confirmation']]);

        sfInstall::modifySql();

        // TODO: move this back to sfInstall::loadData()
        QubitSearch::disable();

        $object = new QubitInformationObject();
        $object->id = QubitInformationObject::ROOT_ID;
        $object->indexOnSave = false;
        $object->save();

        $object = new QubitActor();
        $object->id = QubitActor::ROOT_ID;
        $object->indexOnSave = false;
        $object->save();

        $object = new QubitRepository();
        $object->id = QubitRepository::ROOT_ID;
        $object->indexOnSave = false;
        $object->save();

        $object = new QubitSetting();
        $object->name = 'plugins';
        $object->value = serialize([
            'sfDcPlugin',
            'arDominionPlugin',
            'sfEacPlugin',
            'sfEadPlugin',
            'sfIsaarPlugin',
            'sfIsadPlugin',
            'arDacsPlugin',
            'sfIsdfPlugin',
            'sfIsdiahPlugin',
            'sfModsPlugin',
            'sfRadPlugin',
            'sfSkosPlugin',
        ]);
        $object->save();

        $loadData = new sfPropelDataLoadTask($this->dispatcher, $this->formatter);
        $loadData->run();

        $premisAccessRightValues = [];
        foreach (QubitTaxonomy::getTermsById(QubitTaxonomy::RIGHT_BASIS_ID) as $item) {
            $premisAccessRightValues[$item->slug] = [
                'allow_master' => 1,
                'allow_reference' => 1,
                'allow_thumb' => 1,
                'conditional_master' => 0,
                'conditional_reference' => 1,
                'conditional_thumb' => 1,
                'disallow_master' => 0,
                'disallow_reference' => 0,
                'disallow_thumb' => 0,
            ];
        }
        $setting = new QubitSetting();
        $setting->name = 'premisAccessRightValues';
        $setting->sourceCulture = sfConfig::get('sf_default_culture');
        $setting->setValue(serialize($premisAccessRightValues), ['sourceCulture' => true]);
        $setting->save();

        // TODO: restore translations
        $accessDisallowWarning = 'Access to this record is restricted because it contains personal or confidential information. Please contact the Reference Archivist for more information on accessing this record.';
        $accessConditionalWarning = 'This record has not yet been reviewed for personal or confidential information. Please contact the Reference Archivist to request access and initiate an access review.';
        foreach (QubitTaxonomy::getTermsById(QubitTaxonomy::RIGHT_BASIS_ID) as $item) {
            $setting = new QubitSetting();
            $setting->name = "{$item->slug}_disallow";
            $setting->scope = 'access_statement';
            $setting->setValue($accessDisallowWarning, ['culture' => 'en']);
            $setting->save();

            $setting = new QubitSetting();
            $setting->name = "{$item->slug}_conditional";
            $setting->scope = 'access_statement';
            $setting->setValue($accessConditionalWarning, ['culture' => 'en']);
            $setting->save();
        }

        $this->logSection('install', 'Creating search index');

        sfInstall::populateSearchIndex();

        $this->logSection('install', 'Adding site configuration');

        foreach ($siteOptions as $name => $value) {
            $setting = new QubitSetting();
            $setting->name = $name;
            $setting->value = $value;
            $setting->save();
        }

        $this->logSection('install', 'Creating admin user');

        addSuperuserTask::addSuperUser($adminOptions['username'], $adminOptions);

        // TODO: generate arDominionPlugin and arArchivesCanadaPlugin CSS?

        $this->logSection('install', 'Installation complete!');
    }

    /**
     * @see sfTask
     */
    protected function configure()
    {
        $this->addOptions([
            new sfCommandOption(
                'application',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'The application name',
                true
            ),
            new sfCommandOption(
                'env',
                null,
                sfCommandOption::PARAMETER_REQUIRED,
                'The environment',
                'cli'
            ),
            new sfCommandOption(
                'connection',
                null,
                sfCommandOption::PARAMETER_REQUIRED,
                'The connection name',
                'propel'
            ),
            new sfCommandOption(
                'database-host',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'Database host'
            ),
            new sfCommandOption(
                'database-port',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'Database port'
            ),
            new sfCommandOption(
                'database-name',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'Database name'
            ),
            new sfCommandOption(
                'database-user',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'Database user'
            ),
            new sfCommandOption(
                'database-password',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'Database password'
            ),
            new sfCommandOption(
                'search-host',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'Search host'
            ),
            new sfCommandOption(
                'search-port',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'Search port'
            ),
            new sfCommandOption(
                'search-index',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'Search index'
            ),
            new sfCommandOption(
                'site-title',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'Site title'
            ),
            new sfCommandOption(
                'site-description',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'Site description'
            ),
            new sfCommandOption(
                'site-base-url',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'Site base URL'
            ),
            new sfCommandOption(
                'admin-email',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'Admin email'
            ),
            new sfCommandOption(
                'admin-username',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'Admin username'
            ),
            new sfCommandOption(
                'admin-password',
                null,
                sfCommandOption::PARAMETER_OPTIONAL,
                'Admin password'
            ),
            new sfCommandOption(
                'demo',
                null,
                sfCommandOption::PARAMETER_NONE,
                'Use default demo values'
            ),
            new sfCommandOption(
                'no-confirmation',
                null,
                sfCommandOption::PARAMETER_NONE,
                'Do not ask for confirmation'
            ),
        ]);

        $this->namespace = 'tools';
        $this->name = 'install';
        $this->briefDescription = 'Install AtoM.';
        $this->detailedDescription = 'TODO';
    }

    private function getOptionValue($name, $options, $prompt, $default = null)
    {
        if ($options[$name]) {
            return $options[$name];
        }

        if ($default) {
            $prompt .= " [{$default}]";
        }

        $value = readline($prompt.': ');
        $value = $value ? trim($value) : $default;

        if (!$value) {
            throw new Exception("{$prompt} is required.");
        }

        return $value;
    }
}
