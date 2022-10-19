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

class ADUser extends myUser implements Zend_Acl_Role_Interface
{
  protected $ldapConnection;
  protected $ldapBound;

  public function initialize(sfEventDispatcher $dispatcher, sfStorage $storage, $options = array())
  {
    // initialize parent
    parent::initialize($dispatcher, $storage, $options);

    if (!extension_loaded('ldap'))
    {
      throw new sfConfigurationException('ADUser class needs the "ldap" extension to be loaded.');
    }
  }

  public function authenticate($username, $password)
  {
    // Anonymous is not a real user
    if ($username == 'anonymous' || $password == "")
    {
      return false;
    }

    $host = (null !== $setting = QubitSetting::getByName('ldapHost')) ? $setting->getValue(['sourceCulture' => true]) : null;
    $authenticated = $this->ldapBind($username. '@' .$host, $password);

    if ($authenticated)
    {
      // Load user using username or, if one doesn't exist, create it
      $criteria = new Criteria;
      // Use the AD Username or else you will get the UPN as username.
      $criteria->add(QubitUser::USERNAME, $username);
      if (null === $user = QubitUser::getOne($criteria))
      {
        $user = $this->createUserFromLdapInfo($username);
      }
      $authenticated = $this->checkGroupsLdap($user);
    }

    // Unbind if necessary to be easy on the LDAP server
    if ($this->ldapBound)
    {
      ldap_unbind($this->ldapConnection);
    }

    // Fallback to non-LDAP authentication if need be and load/create user data
    if (!$authenticated)
    {
      $authenticated = parent::authenticate($username, $password);
      // Load user
      $criteria = new Criteria;
      $criteria->add(QubitUser::EMAIL, $username);
      $user = QubitUser::getOne($criteria);
    }

    // Sign in user if authentication was successful
    if ($authenticated)
    {
      $this->signIn($user);
    }

    return $authenticated;
  }

  protected function ldapBind($username, $password)
  {
    if ($conn = $this->getLdapConnection())
    {
      $this->ldapBound = ldap_bind($conn, $username, $password);
      return $this->ldapBound;
    }
  }

  protected function createUserFromLdapInfo($username)
  {
    $user = new QubitUser();
    $user->username = $username;

    $conn = $this->getLdapConnection();
    // Do AD search for user's email address
    $base_dn = (null !== $setting = QubitSetting::getByName('ldapBaseDn')) ? $setting->getValue(['sourceCulture' => true]) : null;
    $host = (null !== $setting = QubitSetting::getByName('ldapHost')) ? $setting->getValue(['sourceCulture' => true]) : null;
    $filter='(&(objectCategory=person)(objectClass=user)(userPrincipalName='. $username .'@'. $host .'))';
    $result = ldap_search($conn, $base_dn, $filter);
    $entries = ldap_get_entries($conn, $result);

    // If user is found and email exists, store it
    if ($entries['count'] && !empty($entries[0]['mail']))
    {
      $user->email = strtolower($entries[0]['mail'][0]);
    }

    $user->save();
    return $user;
  }

  protected function checkGroupsLdap($user)
  {
    $conn = $this->getLdapConnection();
    $host = (null !== $setting = QubitSetting::getByName('ldapHost')) ? $setting->getValue(['sourceCulture' => true]) : null;
    $base_dn = (null !== $setting = QubitSetting::getByName('ldapBaseDn')) ? $setting->getValue(['sourceCulture' => true]) : null;
    $groupRO = (null !== $setting = QubitSetting::getByName('ldapGroupRO')) ? $setting->getValue(['sourceCulture' => true]) : null;
    $atomGroupRO = (int)(null !== $setting = QubitSetting::getByName('atomGroupRO')) ? $setting->getValue(['sourceCulture' => true]) : null;
    $isRO = $this->editGroupLdap($user, $groupRO, $atomGroupRO, $conn, $host, $base_dn);

    $groupRW = (int)(null !== $setting = QubitSetting::getByName('ldapGroupRW')) ? $setting->getValue(['sourceCulture' => true]) : null;
    $atomGroupRW = (int)(null !== $setting = QubitSetting::getByName('atomGroupRW')) ? $setting->getValue(['sourceCulture' => true]) : null;
    $isRW = $this->editGroupLdap($user, $groupRW, $atomGroupRW, $conn, $host, $base_dn);

    if (!$isRO && !$isRW)
    {
      return false;
    }
    
    $user->save();
    return true;
  }

  protected function editGroupLdap($user, $group, $atomGroupId, $conn, $host, $base_dn)
  {
    if ($atomGroupId != 0)
    {
      foreach ($user->aclUserGroups as $item)
      {
        if ($item->groupId == $atomGroupId)
        {
          $item->delete();
          break;
        }
      }
      $filter='(&(objectCategory=person)(objectClass=user)(userPrincipalName='. $user->username .'@'. $host .')(memberOf='. $group .'))';
      $result = ldap_search($conn, $base_dn, $filter);
      $entriesCount = ldap_count_entries($conn, $result);
      if ($entriesCount > 0)
      {
        $userGroup = new QubitAclUserGroup;
        $userGroup->groupId = $atomGroupId;
        $user->aclUserGroups[] = $userGroup;
        return true;
      }
    }
    return false;
  }

  protected function getLdapConnection()
  {
    if (isset($this->ldapConnection))
    {
      return $this->ldapConnection;
    }

    $protocol = (null !== $setting = QubitSetting::getByName('ldapProtocol')) ? $setting->getValue(['sourceCulture' => true]) : null;
    $host = (null !== $setting = QubitSetting::getByName('ldapHost')) ? $setting->getValue(['sourceCulture' => true]) : null;
    $port = (null !== $setting = QubitSetting::getByName('ldapPort')) ? $setting->getValue(['sourceCulture' => true]) : null;

    if (null !== $protocol && null !== $host && null !== $port)
    {
      // If using an URI you only need to send the host URI, so the $port will be null
      $connection = ldap_connect($protocol. "://" .$host. ":" .$port);    
      ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
      ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
      $this->ldapConnection = $connection;
      return $connection;
    }
  }
}