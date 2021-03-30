<?php decorate_with('layout_1col.php'); ?>

<?php slot('title'); ?>
  <?php if (isset($resource->parent)) { ?>
    <?php if (QubitTerm::CHAPTERS_ID == $resource->usageId || QubitTerm::SUBTITLES_ID == $resource->usageId) { ?>
      <h1><?php echo __('Are you sure you want to delete these captions/subtitles/chapters?'); ?></h1>
    <?php } else { ?>
      <h1><?php echo __('Are you sure you want to delete this reference/thumbnail representation?'); ?></h1>
    <?php } ?>
  <?php } else { ?>
    <h1><?php echo __('Are you sure you want to delete the %1% linked to %2%?', ['%1%' => mb_strtolower(sfConfig::get('app_ui_label_digitalobject')), '%2%' => render_title($object)]); ?></h1>
  <?php } ?>
<?php end_slot(); ?>

<?php slot('content'); ?>

  <?php echo $form->renderGlobalErrors(); ?>

  <?php echo $form->renderFormTag(url_for([$resource, 'module' => 'digitalobject', 'action' => 'delete']), ['method' => 'delete']); ?>

    <?php echo $form->renderHiddenFields(); ?>

    <section class="actions">
      <ul>
        <?php if (isset($resource->parent)) { ?>
          <li><?php echo link_to(__('Cancel'), [$resource->parent, 'module' => 'digitalobject', 'action' => 'edit'], ['class' => 'c-btn']); ?></li>
        <?php } else { ?>
          <li><?php echo link_to(__('Cancel'), [$resource, 'module' => 'digitalobject', 'action' => 'edit'], ['class' => 'c-btn']); ?></li>
        <?php } ?>
        <li><input class="c-btn c-btn-delete" type="submit" value="<?php echo __('Delete'); ?>"/></li>
      </ul>
    </section>

  </form>

<?php end_slot(); ?>
