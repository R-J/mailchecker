<?php defined('APPLICATION') or die; ?>

<h1><?= $this->data('Title') ?></h1>
<div class="padded alert alert-info"><?= $this->data('Description') ?></div>
<?= $this->Form->open() ?>
<?= $this->Form->close('Refresh Spam Provider List') ?>

<?php
if (Gdn::config('mailchecker.LastUpdate', false) != false) {
    echo '<div class="padded">';
    echo sprintf(
        Gdn::translate('List has been last updated on: %1s'),
        Gdn_format::date(Gdn::config('mailchecker.LastUpdate'))
    );
    echo '</div>';
}
