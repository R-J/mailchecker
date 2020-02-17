<?php

/**
 * Disallows users to register with a disposable mail address.
 *
 * Checks mail address during registration process and will return a validation
 * error if mail provider is on the given list of disposable mail providers.
 *
 * @package mailchecker
 * @author Robin Jurinka
 * @license MIT
 */
class MailcheckerPlugin extends Gdn_Plugin {
    /**
     * Allow updating the list of spam providers manually.
     *
     * @param settingsController $sender Instance of the calling class.
     * @package mailchecker
     * @since 0.2
     * @return void.
     */
    public function settingsController_mailchecker_create($sender) {
        $sender->permission('Garden.Settings.Manage');
        $sender->setHighlightRoute('dashboard/settings/plugins');
        $sender->setData('Title', Gdn::translate('Mailchecker Settings'));
        $sender->setData(
            'Description',
            Gdn::translate('You can update the list from time to time but it is no needed to get started.')
        );

        // Fetch new list and give feedback abut the number of providers.
        $sender->Form = new Gdn_Form();
        if ($sender->Form->authenticatedPostBack()) {
            $count = $this->updateList();
            if ($count) {
                Gdn::config()->saveToConfig(
                    'mailchecker.LastUpdate',
                    date(time())
                );
                $sender->informMessage(
                    sprintf(
                        Gdn::translate('There are currently %1s spam providers in the list'),
                        $count
                    )
                );
            }
        }

        $sender->render('settings', '', 'plugins/mailchecker');
    }

    /**
     * Disallow users to register with disposable mails.
     *
     * Uses routine provided by Francois-Guillaume Ribreau to check if the email
     * provider is in a list of disposable mail providers. Adds a validation
     * result if there is a match.
     *
     * @param object $sender UserModel.
     * @param mixed $args EventArguments of BeforeRegister.
     * @package mailchecker
     * @since 0.1
     * @return void.
     */
    public function userModel_beforeRegister_handler($sender, $args) {
        // Get mail provider from form.
        if (isset($args['RegisteringUser'])) {
            $email = $args['RegisteringUser']['Email'];
        } else {
            $email = $args['User']['Email'];
        }
        // Return if no vaild mail.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // List of disposable mail hosts (provided by Francois-Guillaume Ribreau).
        // Try to get most recent list which is held in cache folder.
        if (file_exists(PATH_CACHE.'/mailchecker/providers.php')) {
            include(PATH_CACHE.'/mailchecker/providers.php');
        } else {
            // "Fall back" to contained list.
            require(__DIR__.'/providers.php');
        }

        // Get lowercase domain from email.
        $domainStart = strrpos($email, '@') + 1;
        $domain = strtolower(substr($email, $domainStart));

        // Return if domain is not blacklisted.
        if (!in_array($domain, $providers)) {
            return;
        }

        // Set error message.
        $sender->Validation->addValidationResult(
            'Email',
            'Disposable mail addresses are not allowed.'
        );
        $args['Valid'] = false;
    }

    /**
     * Updates provider list which is held in cache.
     *
     * Plugin comes with a fallback if providers can not be loaded from cache.
     * TODO: error checking!
     *
     * @package mailchecker
     * @since 0.2
     * @return integer Number of providers in list.
     */
    private function updateList() {
        // Create path for spam providers list file.
        mkdir(PATH_CACHE.'/mailchecker');

        // Get main list from GitHub.
        $url = Gdn::config(
            'Mailchecker.UpdateFileUrl',
            'https://raw.githubusercontent.com/FGRibreau/mailchecker/master/list.txt'
        );
        $bareboneList = proxyRequest($url);

        // Save as backup.
        file_put_contents(PATH_CACHE.'/mailchecker/list.txt', $bareboneList);

        $providersList = str_replace("\n", "','", $bareboneList);
        file_put_contents(
            PATH_CACHE.'/mailchecker/providers.php',
            '<?php $providers = [\''.$providersList."'];"
        );

        // Return count of spam providers in the list.
        include(PATH_CACHE.'/mailchecker/providers.php');
        return(count($providers));
    }
}
