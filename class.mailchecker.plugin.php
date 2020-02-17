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
        $sender->addSideMenu('dashboard/settings/plugins');
        $sender->setData('Title', t('Mailchecker Settings'));
        $sender->setData('Description', t('You can update the list from time to time but it is not needed at all since the plugin comes with an initial list.'));

        // Fetch new list and give feedback abut the number of providers.
        $sender->Form = new Gdn_Form();
        if ($sender->Form->authenticatedPostBack()) {
            $count = $this->updateList();
            if ($count) {
                saveToConfig(
                    'mailchecker.LastUpdate',
                    date(time())
                );
                $sender->informMessage(
                    sprintf(
                        t('There are currently %1s spam providers in the list'),
                        $count
                    )
                );
            }
        }

        $sender->render($this->getView('settings.php'));
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
        // List of disposable mail hosts (provided by Francois-Guillaume Ribreau).
        // Try to get most recent list which is held in cache folder.
        include(PATH_CACHE.'/mailchecker/providers.php');
        if (!isset($providers)) {
            // "Fall back" to contained list.
            require(__DIR__.'/providers.php');
        }
        // Get mail provider from form.
        if (isset($args['RegisteringUser'])) {
            $email = $args['RegisteringUser']['Email'];
        } else {
            $email = $args['User']['Email'];
        }
        $mail = explode('@', $email);
        // If no valid mail, we do not have to look further.
        if (count($mail) !== 2) {
            return;
        }

        // Return if host is in the list.
        if (in_array(strtolower($mail[1]), $providers)) {
            $sender->Validation->addValidationResult(
                'Email',
                'Disposable mail addresses are not allowed.'
            );
            $args['Valid'] = false;
        }
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
        $url = 'https://raw.githubusercontent.com/FGRibreau/mailchecker/master/list.json';
        $bareboneList = proxyRequest($url);
        // Save as backup.
        file_put_contents(PATH_CACHE.'/mailchecker/list.cjson', $bareboneList);

        // Convert to array.
        $list = '<?php $providerList = '.$bareboneList.';';
        file_put_contents(PATH_CACHE.'/mailchecker/list.php', $list);
        // Get array from file.
        require_once(PATH_CACHE.'/mailchecker/list.php');

        // "Flatten" array.
        $providers = [];
        array_walk_recursive(
            $providerList,
            function ($item, $key) use (&$providers) {
                $providers[] = $item;
            }
        );
        // Write flattened array to final file.
        file_put_contents(
            PATH_CACHE.'/mailchecker/providers.php',
            '<?php $providers = '.var_export($providers, true).';'
        );
        $providers = '';
        require_once(PATH_CACHE.'/mailchecker/providers.php');
        // Return count of spam providers in the list.
        return(count($providers));
    }
}
