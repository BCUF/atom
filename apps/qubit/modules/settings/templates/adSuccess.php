<?php decorate_with('layout_2col.php') ?>

<?php slot('sidebar') ?>

  <?php echo get_component('settings', 'menu') ?>

<?php end_slot() ?>

<?php slot('title') ?>

  <h1><?php echo __('Active Directory authentication') ?></h1>

<?php end_slot() ?>

<?php slot('content') ?>

  <?php echo $form->renderFormTag(url_for(array('module' => 'settings', 'action' => 'ad'))) ?>

    <div id="content">

      <fieldset class="collapsible">

        <legend><?php echo __('Active Directory authentication settings') ?></legend>

        <?php echo $form->ldapProtocol
          ->label(__('Protocol'))
          ->renderRow() ?>

        <?php echo $form->ldapHost
          ->label(__('Hotname (URI)'))
          ->renderRow() ?>

        <?php echo $form->ldapPort
          ->label(__('Port'))
          ->renderRow() ?>

        <?php echo $form->ldapBaseDn
          ->label(__('Base DN'))
          ->renderRow() ?>

        <?php echo $form->atomGroupRO
          ->label(__('Atom read only group'))
          ->renderRow() ?>

        <?php echo $form->ldapGroupRO
          ->label(__('AD read only group'))
          ->renderRow() ?>

        <?php echo $form->atomGroupRW
          ->label(__('Atom read/write group'))
          ->renderRow() ?>

        <?php echo $form->ldapGroupRW
          ->label(__('Read/write group'))
          ->renderRow() ?>

        <?php echo $form->atomGroupArchived
          ->label(__('Archived group for users with notice'))
          ->renderRow() ?>

      </fieldset>

    </div>

    <section class="actions">
      <ul>
        <li><?php echo link_to (__('Update LDAP User'), array('module' => 'settings', 'action' => 'updateAdUser'), array('class' => 'c-btn')) ?></li>

        <li><input class="c-btn c-btn-submit" type="submit" value="<?php echo __('Save') ?>"/></li>
      </ul>
    </section>

  </form>

<?php end_slot() ?>