<?php

class Haystack_OptionsManager {
    
    public function check_reindex() {
        $nonce = isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : false;
        return wp_verify_nonce($nonce,'reindex');
    }

    public function getOptionNamePrefix() {
        return get_class($this) . '_';
    }


    public function getOptionMetaData() {
        return array();
    }

    public function getOptionNames() {
        return array_keys($this->getOptionMetaData());
    }

    protected function initOptions() {
    }

    protected function deleteSavedOptions() {
        $optionMetaData = $this->getOptionMetaData();
        if (is_array($optionMetaData)) {
            foreach ($optionMetaData as $key => $val) {
                $prefixedOptionName = $this->prefix($key); // how it is stored in DB
                delete_option($prefixedOptionName);
            }
        }
    }

    public function getPluginDisplayName() {
        return get_class($this);
    }

    public function prefix($name) {
        $optionNamePrefix = $this->getOptionNamePrefix();
        if (strpos($name, $optionNamePrefix) === 0) { // 0 but not false
            return $name; // already prefixed
        }
        return $optionNamePrefix . $name;
    }

    public function &unPrefix($name) {
        $optionNamePrefix = $this->getOptionNamePrefix();
        if (strpos($name, $optionNamePrefix) === 0) {
            return substr($name, strlen($optionNamePrefix));
        }
        return $name;
    }

    public function getOption($optionName, $default = null) {
        $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB
        $retVal = get_option($prefixedOptionName);
        if (!$retVal && $default) {
            $retVal = $default;
        }
        return $retVal;
    }

    public function deleteOption($optionName) {
        $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB
        return delete_option($prefixedOptionName);
    }

    public function addOption($optionName, $value) {
        $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB
        return add_option($prefixedOptionName, $value);
    }

    public function updateOption($optionName, $value) {
        $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB
        return update_option($prefixedOptionName, $value);
    }

    public function getRoleOption($optionName) {
        $roleAllowed = $this->getOption($optionName);
        if (!$roleAllowed || $roleAllowed == '') {
            $roleAllowed = 'Administrator';
        }
        return $roleAllowed;
    }

    protected function roleToCapability($roleName) {
        switch ($roleName) {
            case 'Super Admin':
                return 'manage_options';
            case 'Administrator':
                return 'manage_options';
            case 'Editor':
                return 'publish_pages';
            case 'Author':
                return 'publish_posts';
            case 'Contributor':
                return 'edit_posts';
            case 'Subscriber':
                return 'read';
            case 'Anyone':
                return 'read';
        }
        return '';
    }

    public function isUserRoleEqualOrBetterThan($roleName) {
        if ('Anyone' == $roleName) {
            return true;
        }
        $capability = $this->roleToCapability($roleName);
        return current_user_can($capability);
    }

    public function canUserDoRoleOption($optionName) {
        $roleAllowed = $this->getRoleOption($optionName);
        if ('Anyone' == $roleAllowed) {
            return true;
        }
        return $this->isUserRoleEqualOrBetterThan($roleAllowed);
    }

    public function createSettingsMenu() {
        $pluginName = $this->getPluginDisplayName();
        //create new top-level menu
        add_menu_page($pluginName . ' Plugin Settings', $pluginName, 'administrator', get_class($this), array(&$this, 'settingsPage'));

        //call register settings function
        add_action('admin_init', array(&$this, 'registerSettings'));
    }

    public function registerSettings() {
        $settingsGroup = get_class($this) . '-settings-group';
        $optionMetaData = $this->getOptionMetaData();
        foreach ($optionMetaData as $key => $val) {
            register_setting($settingsGroup, $val);
        }
    }

    public function save_data($passed = false) {
        $optionMetaData = $this->getOptionMetaData();
        foreach ($optionMetaData as $key => $val) {
            $tmp_arr = $val;
            $val = false;
            if (isset($passed[$key])) {
                $val = $passed[$key];
            }
            else if (isset($_POST[$key])) {
                $val = $_POST[$key];
            }

            if ($key == 'suggest_menu' && $val == 'default') {
                if (isset($_POST['quick_links_text'])) {
                    $quick = $_POST['quick_links_text'];
                }
                else if (isset($passed['quick_links_text'])) {
                    $quick = $passed['quick_links_text'];
                }
                else {
                    $quick = '';
                }

                $this->updateOption('quick_links_text',$quick);
            }

            if ($val) { //Saving changes
                $this->updateOption($key,$val);
                $set = true;
            }
            else {
                if (!isset($tmp_arr['hide'])) {
                    $this->updateOption($key,''); 
                }
            }
        }
    }

    public function settingsPage() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'haystack'));
        }

        //Check if call to Re-index process
        if ($this->check_reindex()) {
            $this->reindex();
        }

        //CSS
        wp_enqueue_style('haystack_admin',plugin_dir_url(__FILE__).'css/haystack_admin.css');
        wp_register_script('haystack_admin_js',plugin_dir_url(__FILE__).'js/haystack_admin.js');
        $ajax_data = array(
            'status_url' => HAYSTACK_AJAX_ADMIN,
            'ajax_url' => HAYSTACK_AJAX,
        );
        wp_localize_script('haystack_admin_js','ajax_data',$ajax_data);
        wp_enqueue_script('haystack_admin_js');       

        $reindex_url = $this->reindex_url();

        $health = $this->health_check();
        $optionMetaData = $this->getOptionMetaData(); //We edit this to show errors

        //Subit process
        if (isset($_POST['haystack_submit'])) {
            $data = array();
            $ori_key = $this->getOption('api_key',false);
            $api = $_POST['api_key'];
            
            //API Key Change
            if ($api != $ori_key) {
                $cred = $this->get_credentials($api);
                if ($cred) {
                    $this->show_notice('reindex');
                    $data['api_key'] = $api;
                    $this->deleteOption('haystack_first_index');
                }
                else {
                    $this->deleteOption('client_hash');
                    $this->deleteOption('api_key');
                    $this->deleteOption('haystack_first_index');
                    $optionMetaData['api_key']['error'] = 'Invalid API Key.';
                }

            }

            if ($health > 1) { 
                $type_change = $this->check_post_types_change();
                if (!empty($type_change['add'])) {
                    $this->add_type($type_change['add']);
                }
                if (!empty($type_change['sub'])) {
                    $this->sub_type($type_change['sub']);
                }
            }

            $this->save_data($data);

            //First Index has not been run or needs to be rerun
            $post_types = $this->getOption('post_types',false);
            if ($health == 1 && $post_types) {
                $this->reindex();
            }

            $optionMetaData = $this->getOptionMetaData(); //Refresh for form
            $health = $this->health_check(); //Refresh for form
        }

        $status = $this->process_queue->status();
        include_once plugin_dir_path(__FILE__).'inc/form.php';
        $this->get_form();
    }

    //Check if content types have changed...
    public function check_post_types_change() {        
        $old_types = $this->getOption('post_types',false);
        $old_types = $old_types ? array_keys($old_types) : array();
        $new_types = isset($_POST['post_types']) ? array_keys($_POST['post_types']) : array();


        $diff_add = array();
        $diff_sub = array();

        foreach ($old_types as $key => $val) {
            if (!in_array($val,$new_types)) {
                $diff_sub[] = $val;
            }
        }
        foreach ($new_types as $key => $val) {
            if (!in_array($val,$old_types)) {
                $diff_add[] = $val;
            }
        }

        return array(
            'add' => $diff_add,
            'sub' => $diff_sub,
        );
    }

    public function get_settings_fields() {
        $settingsGroup = get_class($this) . '-settings-group';
        return settings_fields($settingsGroup);
    }

    public function get_form() {
        include_once plugin_dir_path(__FILE__).'inc/form.php';
    }

    public function show_notice($type = 'need') {
        $settings_page = $this->settings_url();
        if ($type == 'need') {
            include_once plugin_dir_path(__FILE__).'inc/banner/init_needed.php';
        }
        else if ($type == 'reindex') {
            include_once plugin_dir_path(__FILE__).'inc/banner/init_reindex.php';
        }
    }

    protected function createFormControl($key, $val, $savedOptionValue,$error = false) {
        global $wp_post_types;
       
        if ($key == 'post_types') {
            if (!$savedOptionValue) {
                $savedOptionValue = array();
            }
            // $val = explode('|',$savedOptionValue);
             echo '<div class="check-row">';
            foreach ($wp_post_types as $k => $v) {
                $tmp_str = $k;
                $tmp_name = $v->labels->name;

                if (!in_array($k,$this->ignorePostTypes)) {
                    echo '
                    <div class="check-item">
                        <input type="checkbox" name="'.$key.'['.$k.']" '.(array_key_exists($k,$savedOptionValue) ? 'checked' : '').' />
                        <label for="'.$k.'">'.$tmp_name.'</label>
                    </div>';
                }
            }
            echo '</div>';
        }
        else if ($key == 'suggest_menu') {
            if (!$savedOptionValue) {
                $savedOptionValue = array();
            }
            // $val = explode('|',$savedOptionValue);
            echo '<div class="radio-row">';

            $menus = get_terms('nav_menu');
            $quick_links = '<p id="quick_links_text_row"><textarea name="quick_links_text" id="quick_links_text" rows="4" cols="50">'.stripslashes(esc_textarea($this->getOption('quick_links_text',false))).'</textarea></p>';

            //Check default
            if (!$savedOptionValue) {
                $savedOptionValue = 'default';
            }
            //Add default
            array_unshift($menus,(object) array(
                'name' => 'User input menu',
                'term_id' => 'default',
            ));
            foreach ($menus as $k => $v) {
                echo '
                <div class="radio-item">
                    <input type="radio" name="'.$key.'" value="'.$v->term_id.'" '.($v->term_id == $savedOptionValue ? 'checked="checked"' : '').' />
                    <label for="'.$key.'['.$v->term_id.']">'.$v->name.'</label>
                    '.($v->term_id == 'default' ? $quick_links : '').'
                </div>';
            }
            echo '</div>';
        }
        else { // Simple input field
            ?>
            <p>
                <input type="text" name="<?php echo $key ?>" id="<?php echo $key ?>" value="<?php echo esc_attr($savedOptionValue) ?>" size="50"/>
                <?php echo ($error ? '<span class="error">'.$error.'</span>' : ''); ?>
            </p>
            <?php

        }
    }

   protected function getOptionValueI18nString($optionValue) {
        switch ($optionValue) {
            case 'true':
                return __('true', 'haystack');
            case 'false':
                return __('false', 'haystack');

            case 'Administrator':
                return __('Administrator', 'haystack');
            case 'Editor':
                return __('Editor', 'haystack');
            case 'Author':
                return __('Author', 'haystack');
            case 'Contributor':
                return __('Contributor', 'haystack');
            case 'Subscriber':
                return __('Subscriber', 'haystack');
            case 'Anyone':
                return __('Anyone', 'haystack');
        }
        return $optionValue;
    }

    protected function getMySqlVersion() {
        global $wpdb;
        $rows = $wpdb->get_results('select version() as mysqlversion');
        if (!empty($rows)) {
             return $rows[0]->mysqlversion;
        }
        return false;
    }

    public function getEmailDomain() {
        // Get the site domain and get rid of www.
        $sitename = strtolower($_SERVER['SERVER_NAME']);
        if (substr($sitename, 0, 4) == 'www.') {
            $sitename = substr($sitename, 4);
        }
        return $sitename;
    }
}