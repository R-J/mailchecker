<?php defined('APPLICATION') or die; ?>

<h1><?= $this->data('Title') ?></h1>
<div class="Info"><?= $this->data('Description') ?></div>
<?= $this->Form->open() ?>
<?= $this->Form->close('Refresh Spam Provider List') ?>

<?php
if (c('mailchecker.LastUpdate', false) != false) {
    echo '<div class="Info">';
    echo sprintf(
        t('List has been last updated on: %1s'),
        Gdn_format::date(c('mailchecker.LastUpdate'))
    );
    echo '</div>';
}
