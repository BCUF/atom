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

class SettingsADAction extends DefaultEditAction
{
  // Arrays not allowed in class constants
  public static
    $NAMES = array(
      'ldapProtocol',
      'ldapHost',
      'ldapPort',
      'ldapBaseDn',
      'ldapGroupRO',
      'atomGroupRO',
      'ldapGroupRW',
      'atomGroupRW',
      'atomGroupArchived');

  protected function addField($name)
  {
    switch ($name)
    {
      case 'ldapProtocol':
      case 'ldapHost':
      case 'ldapPort':
      case 'ldapBaseDn':
      case 'ldapGroupRO':
      case 'ldapGroupRW':
        // Determine and set field default value
        if (null !== $this->{$name} = QubitSetting::getByName($name))
        {
          $default = $this->{$name}->getValue(array('sourceCulture' => true));
        }
        else
        {
          $defaults = array(
            'ldapProtocol' => 'ldap',
            'ldapPort' => '389'
          );

          $default = (isset($defaults[$name])) ? $defaults[$name] : '';
        }
        $this->form->setDefault($name, $default);
        // Set validator and widget
        $validator = new sfValidatorPass;
        $this->form->setValidator($name, $validator);
        $this->form->setWidget($name, new sfWidgetFormInput);
        break;
        
      case 'atomGroupRO':
      case 'atomGroupRW':
      case 'atomGroupArchived':
        if (null !== $this->{$name} = QubitSetting::getByName($name))
        {
          $default = $this->{$name}->getValue(array('sourceCulture' => true));
        }
        else
        {
          $default = 0;
        }

        $choices = array();
        $choices[0] = '-';
        $criteria = new Criteria;
        $criteria->add(QubitAclGroup::ID, 99, Criteria::GREATER_THAN);
        foreach (QubitAclGroup::get($criteria) as $item)
        {
          $choices[$item->id] = $item->getName(array('cultureFallback' => true));
        }

        $validator = new sfValidatorPass;
        $this->form->setDefault($name, $default);
        $this->form->setValidator($name, $validator);
        $this->form->setWidget($name, new sfWidgetFormSelect(array('choices' => $choices)));
        break;
    }
  }

  protected function processField($field)
  {
    switch ($name = $field->getName())
    {
      case 'ldapProtocol':
      case 'ldapHost':
      case 'ldapPort':
      case 'ldapBaseDn':
      case 'ldapGroupRW':
      case 'ldapGroupRO':
      case 'atomGroupRO':
      case 'atomGroupRW':
      case 'atomGroupArchived':
        if (null === $this->{$name})
        {
          $this->{$name} = new QubitSetting;
          $this->{$name}->name = $name;
          $this->{$name}->scope = 'ad';
        }
        $this->{$name}->setValue($field->getValue(), array('sourceCulture' => true));
        $this->{$name}->save();
        break;
    }
  }

  public function execute($request)
  {
    parent::execute($request);

    if ($request->isMethod('post'))
    {
      $this->form->bind($request->getPostParameters());
      if ($this->form->isValid())
      {
        $this->processForm();
        QubitCache::getInstance()->removePattern('settings:i18n:*');
        $this->redirect(array('module' => 'settings', 'action' => 'ad'));
      }
    }
  }
}