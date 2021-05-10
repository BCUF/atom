<?php decorate_with('layout_1col.php') ?>

<?php slot('title') ?>
  <h1><?php echo __('Mettre à jour les utilisateurs LDAP?') ?></h1>
<?php end_slot() ?>

<?php slot('content') ?>
    <div id="content">
        <div>
            <?php echo __('/!\ Les noms d\'utilisateur trouvés dans LDAP qui ne se trouvent pas dans les groupes précisés seront supprimés!') ?>
        </div>
        <div>
            <?php echo __('/!\ Si vous avez fait des modifications dans les paramètres, les avez-vous sauvegardées avant?') ?>
        </div>
    </div>

    <?php echo $form->renderFormTag(url_for(array('module' => 'settings', 'action' => 'updateAdUser'))) ?>
    <section class="actions">
      <ul>
        <li><?php echo link_to(__('Cancel'), array('module' => 'settings', 'action' => 'ad'), array('class' => 'c-btn')) ?></li>
        <li><input class="c-btn c-btn-delete" type="submit" value="<?php echo __('Update') ?>"/></li>
      </ul>
    </section>

  </form>


<?php end_slot() ?>

