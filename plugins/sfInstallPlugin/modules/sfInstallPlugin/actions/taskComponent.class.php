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

class sfInstallPluginTaskComponent extends sfAction
{
    public function execute($request)
    {
        $this->checkSystemStatus;
        $this->configureDatabaseStatus;
        $this->configureSearchStatus;
        $this->loadDataStatus;
        $this->configureSiteStatus;

        switch ($request->action) {
            case 'checkSystem':
                $this->checkSystemStatus = 'active';

                break;

            case 'configureDatabase':
                $this->checkSystemStatus = 'done';
                $this->configureDatabaseStatus = 'active';

                break;

            case 'configureSearch':
                $this->checkSystemStatus = 'done';
                $this->configureDatabaseStatus = 'done';
                $this->configureSearchStatus = 'active';

                break;

            case 'loadData':
                $this->checkSystemStatus = 'done';
                $this->configureDatabaseStatus = 'done';
                $this->configureSearchStatus = 'done';
                $this->loadDataStatus = 'active';

                break;

            case 'configureSite':
                $this->checkSystemStatus = 'done';
                $this->configureDatabaseStatus = 'done';
                $this->configureSearchStatus = 'done';
                $this->loadDataStatus = 'done';
                $this->configureSiteStatus = 'active';

                break;

            case 'finishInstall':
                $this->checkSystemStatus = 'done';
                $this->configureDatabaseStatus = 'done';
                $this->configureSearchStatus = 'done';
                $this->loadDataStatus = 'done';
                $this->configureSiteStatus = 'done';

                break;
        }
    }
}
