<?php

include_once('Haystack_InstallIndicator.php');

class Haystack_LifeCycle extends Haystack_InstallIndicator {

    public function install() {

        // Initialize Plugin Options
        $this->initOptions();

        // Initialize DB Tables used by the plugin
        $this->installDatabaseTables();

        // Other Plugin initialization - for the plugin writer to override as needed
        $this->otherInstall();

        // Record the installed version
        $this->saveInstalledVersion();

        // To avoid running install() more then once
        $this->markAsInstalled();
     }

    public function uninstall() {
        $this->otherUninstall();
        $this->unInstallDatabaseTables();
        $this->deleteSavedOptions();
        $this->markAsUnInstalled();
    }

    /**
     * May need to do work on this, adding cURL and version control for Haystack
     */
    public function upgrade() {
    }

    /**
     * Called when plugin receives from active status
     */
    public function activate() {
    }

    /**
     * Called when plugin removed from active status
     */
    public function deactivate() {
        // $this->uninstall();
    }

    protected function initOptions() {
    }

    public function addActionsAndFilters() {
    }

    protected function installDatabaseTables() {
    }

    protected function unInstallDatabaseTables() {
    }

    protected function otherInstall() {
    }

    protected function otherUninstall() {
    }

    public function addSettingsSubMenuPage() {
        $this->addSettingsSubMenuPageToPluginsMenu();
    }


    protected function requireExtraPluginFiles() {
        require_once(ABSPATH . 'wp-includes/pluggable.php');
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    public function settings_url() {
        return '?page='.$this->getSettingsSlug();
    }
    public function reindex_url() {
        return wp_nonce_url('?page='.$this->getSettingsSlug().'&reindex=1', 'reindex' );
    }

    protected function getSettingsSlug() {
        return 'haystack-wordpress-settings';
    }

    protected function addSettingsSubMenuPageToPluginsMenu() {
        $this->requireExtraPluginFiles();
        $displayName = $this->getPluginDisplayName();
        
        $wpfront_caps_translator = 'wpfront_user_role_editor_duplicator_translate_capability';
        $icon_svg = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNy4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMTMzLjMzMXB4IiBoZWlnaHQ9IjEzMy4zMzFweCIgdmlld0JveD0iMCAzMjYuODc2IDEzMy4zMzEgMTMzLjMzMSIgZW5hYmxlLWJhY2tncm91bmQ9Im5ldyAwIDMyNi44NzYgMTMzLjMzMSAxMzMuMzMxIg0KCSB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxnPg0KCTxwb2x5Z29uIGZpbGw9IiM0OTQ5NDkiIHBvaW50cz0iMTMzLjMzMSwzNjQuNjY5IDEyNS4wNDEsMzUwLjE5OCA4OS40OTYsMzcwLjcxMSAxMTAuMDA4LDMzNS4xNjUgOTUuNTM3LDMyNi44NzYgNjYuNzM2LDM3Ni44OTMgDQoJCTM3Ljc5MywzMjYuODc2IDIzLjMyMiwzMzUuMTY1IDQzLjgzNSwzNzAuNzExIDguMjg5LDM1MC4xOTggMCwzNjQuNjY5IDUwLjAxNywzOTMuNjEyIDAsNDIyLjQxMyA4LjI4OSw0MzYuODg0IDQzLjgzNSw0MTYuMzcyIA0KCQkyMy4zMjIsNDUxLjkxNyAzNy43OTMsNDYwLjIwNyA2Ni43MzYsNDEwLjE5IDk1LjUzNyw0NjAuMjA3IDExMC4wMDgsNDUxLjkxNyA4OS40OTYsNDE2LjM3MiAxMjUuMDQxLDQzNi44ODQgMTMzLjMzMSw0MjIuNDEzIA0KCQk4My4zMTQsMzkzLjYxMiAJIi8+DQo8L2c+DQo8L3N2Zz4NCg==';

        $user_perms = 'manage_options';
        $perms = apply_filters($wpfront_caps_translator, $user_perms);
        $settings_page = array(&$this, 'settingsPage');

        add_menu_page($displayName, $displayName, $perms, $this->getSettingsSlug(), $settings_page, $icon_svg);
        add_submenu_page($this->getSettingsSlug(), 'Re-index '.$displayName, 'Re-index '.$displayName, $user_perms, $this->reindex_url());
    }


    protected function addSettingsSubMenuPageToSettingsMenu() {
        $this->requireExtraPluginFiles();
        $displayName = $this->getPluginDisplayName();
        add_options_page($displayName,
                         $displayName,
                         'manage_options',
                         $this->getSettingsSlug(),
                         array(&$this, 'settingsPage'));
    }

    protected function prefixTableName($name) {
        global $wpdb;
        return $wpdb->prefix .  strtolower($this->prefix($name));
    }


    public function getAjaxUrl($actionName) {
        return admin_url('admin-ajax.php') . '?action=' . $actionName;
    }
}