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

const ADMINISTRATOR_ID = 100;
class SettingsUpdateAdUserAction extends sfAction
{
  protected $bcuConfig;

  public function execute($request)
  {
    $this->form = new sfForm;

    if ($request->isMethod('post'))
    {
      $this->bcuConfig = parse_ini_file($_SERVER['DOCUMENT_ROOT'] . "/config/bcu_config.ini");

      $usersRO = [];
      $usersRW = [];
      $ldapUsersRO = [];
      $ldapUsersRW = [];

      $ldapconn = $this->ldapConnect();
      if ($ldapconn)
      {
        $atomGroupRO = QubitSetting::getByName('atomGroupRO');
        $ldapGroupRO = (string)QubitSetting::getByName('ldapGroupRO');
        if ($atomGroupRO != null && $atomGroupRO->value != 0 && $ldapGroupRO != null && $ldapGroupRO != "")
        {
          $usersRO = $this->getUserFromAtomGroup((int)$atomGroupRO->value);
          $ldapUsersRO = $this->getUserFromLdapGroup($ldapconn, $ldapGroupRO);
        }
  
        $atomGroupRW = QubitSetting::getByName('atomGroupRW');
        $ldapGroupRW = (string)QubitSetting::getByName('ldapGroupRW');
        if ($atomGroupRW != null && $atomGroupRW->value != 0 && $ldapGroupRW != null && $ldapGroupRW != "")
        {
          $usersRW = $this->getUserFromAtomGroup((int)$atomGroupRW->value);
          $ldapUsersRW = $this->getUserFromLdapGroup($ldapconn, $ldapGroupRW);
        }
      }

      $this->checkUsersGroup($usersRO, $usersRW, $ldapUsersRO, $ldapUsersRW, $ldapconn);

      $this->ldapDisconnect($ldapconn);
      $this->redirect(array('module' => 'settings', 'action' => 'ad'));
    }
  }
  
  protected function getUserFromAtomGroup($id)
  {
    $usersId = [];
    $criteria = new Criteria;
    $criteria->add(QubitAclUserGroup::GROUP_ID, $id, Criteria::EQUAL);
    foreach (QubitAclUserGroup::get($criteria) as $item)
    {
      $usersId[] = (int)$item->userId;
    }

    $criteria = new Criteria;
    $criteria->add(QubitUser::ID, $usersId, Criteria::IN);
    $user = QubitUser::get($criteria);
    return $user;
  }

  protected function getUserFromLdapGroup($ldapconn, $path)
  {
    $filter='(objectClass=*)';
    $result = ldap_read($ldapconn, $path, $filter);
    $entries = ldap_get_entries($ldapconn, $result);
    $attributes = array("member");
    $sr=ldap_read($ds, $dn, $filter, $attributes);
    $entry = ldap_get_entries($ds, $sr);

    // If user is found and email exists, store it
    $usersDn = [];
    if ($entries['count'] && !empty($entries[0]['member']))
    {
      $usersDn = $entries[0]['member'];
    }

    $users = [];
    foreach ($usersDn as $userDn)
    {
      $result = ldap_read($ldapconn, $userDn, $filter, array("userprincipalname", "mail"));
      $entries = ldap_get_entries($ldapconn, $result);
      $attributes = array("userprincipalname", "mail");
  
      $sr=ldap_read($ds, $dn, $filter, $attributes);
      $entry = ldap_get_entries($ds, $sr);
      if ($entries['count'] && !empty($entries[0]["mail"]) && !empty($entries[0]["userprincipalname"]))
      {
        $users[] = array("userprincipalname" => strtolower($entries[0]["userprincipalname"][0]), "mail" => strtolower($entries[0]["mail"][0]));
      }
    }

    return $users;
  }

  protected function checkUsersGroup($usersRO, $usersRW, $ldapUsersRO, $ldapUsersRW, $ldapconn)
  {
    $host = (string)QubitSetting::getByName('ldapHost');
    foreach($usersRO as $userRO)
    {
      if(array_search($userRO["username"], array_column($usersRW, 'username')) == false) {
        $usersRW[] = $userRO;
      }
    }
    $users = $usersRW;

    foreach ($users as $user)
    {
      $ldapUsername = strtolower($user["username"]. '@' .$host);
      if (array_search($ldapUsername, array_column($ldapUsersRO, 'userprincipalname')) === false
         && array_search($ldapUsername, array_column($ldapUsersRW, 'userprincipalname')) === false) {

        if ($this->context->user->user === $user)
        {
          continue;
        }
        elseif (0 < $user->getNotes()->count())
        {
          $this->archiveUser(true, $user);
        }
        else
        {
          $to_delete = true;
          foreach ($user->aclUserGroups as $item)
          {
            if ($item->groupId == ADMINISTRATOR_ID)
            {
              $to_delete = false;
              break;
            }
          }
          if ($to_delete) {
            $user->delete();
          }
        }
      } else {
        $this->archiveUser(false, $user);
      }
    }
    
    $atomGroupRO = (int)QubitSetting::getByName('atomGroupRO')->value;
    $this->generateAtomGroupUsers($atomGroupRO, $ldapUsersRO);

    $atomGroupRW = (int)QubitSetting::getByName('atomGroupRW')->value;
    $this->generateAtomGroupUsers($atomGroupRW, $ldapUsersRW);
  }

  protected function generateAtomGroupUsers($groupId, $ldapUsers)
  {
    $criteria = new Criteria;
    $criteria->add(QubitAclUserGroup::GROUP_ID, $groupId, Criteria::EQUAL);
    foreach (QubitAclUserGroup::get($criteria) as $item)
    {
      $item->delete();
    }
    foreach ($ldapUsers as $ldapUser)
    {
      $username = explode("@", $ldapUser["userprincipalname"])[0];
      $criteria = new Criteria;
      $criteria->add(QubitUser::USERNAME, $username);
      if (null === $user = QubitUser::getOne($criteria))
      {
        $user = new QubitUser();
        $user->username = $username;
        $user->email = $ldapUser["mail"];
      }
      
      $userGroup = new QubitAclUserGroup;
      $userGroup->groupId = $groupId;
      $user->aclUserGroups[] = $userGroup;

      $user->save();
    }
  }
  
  protected function ldapConnect()
  {
    $protocol = QubitSetting::getByName('ldapProtocol');
    $host = QubitSetting::getByName('ldapHost');
    $port = QubitSetting::getByName('ldapPort');
    $base_dn = QubitSetting::getByName('ldapBaseDn');

    if (null === $protocol || null === $base_dn || null === $host || null === $port)
    {
      $this->redirect(array('module' => 'settings', 'action' => 'add'));
      return;
    }
    // If using an URI you only need to send the host URI, so the $port will be null
    $ldapconn = ldap_connect($protocol->getValue(array('sourceCulture' => true)). "://" .$host->getValue(array('sourceCulture' => true)). ":" .$port->getValue(array('sourceCulture' => true)));
    if ($ldapconn) {
      ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);

      $ldapbind = ldap_bind($ldapconn, $this->bcuConfig["username"], $this->bcuConfig["password"]);
  
      // verify binding
      if ($ldapbind) {
        return $ldapconn;
      } else {
        $this->ldapDisonnect($ldapconn);
      }
    }
  }

  protected function ldapDisconnect($ldapconn)
  {
    if ($ldapconn)
    {
      ldap_unbind($ldapconn);
    }
  }

  protected function archiveUser($mustArchive, $user)
  {
    $user->active = !$mustArchive;
    $user->save();

    $groupId = QubitSetting::getByName('atomGroupArchived');

    if ($groupId == null || $groupId->value == 0)
    {
      return;
    }

    $groupId = (int)$groupId->value;
    $criteria = new Criteria;
    $criteria->add(QubitAclUserGroup::GROUP_ID, $groupId, Criteria::EQUAL);
    $criteria->add(QubitAclUserGroup::USER_ID , $user->id, Criteria::EQUAL);

    if ($mustArchive)
    {
      if (null === $userGroup = QubitAclUserGroup::getOne($criteria))
      {
        $userGroup = new QubitAclUserGroup;
        $userGroup->groupId = $groupId;
        $user->aclUserGroups[] = $userGroup;
        $user->save();
      }
    }
    else
    {
      if (null !== $userGroup = QubitAclUserGroup::getOne($criteria))
      {
        $userGroup->delete();
      }
    }
  }
}
