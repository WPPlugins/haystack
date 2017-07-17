<div class="wrap hay confirm">
    <h2><?php echo $this->getPluginDisplayName(); echo ' '; _e('Settings', 'haystack'); ?></h2>

    <form method="post" action="<?php echo $this->settings_url(); ?>">
        <?php echo $this->get_settings_fields(); ?>
        <table class="plugin-options-table form-table">
            <tbody>
                <?php if ($status['total'] > 0) { ?>
                    <tr class="haystack_stats_row"><td colspan="2">
                        <div>
                            <h4>Haystack Indexer Status</h4>
                            <div class="haystack_status"><div class="haystack_status_inner"></div></div>
                            <div class="haystack_status_num"></div>
                        </div>
                    </td></tr>
                <?php } ?>
                <?php
                if ($optionMetaData != null) {
                    foreach ($optionMetaData as $key => $val) {
                        $displayText = isset($val['title']) ? $val['title'] : false;
                        $error = isset($val['error']) ? $val['error'] : false;
                        $hide = isset($val['hide']) && $val['hide'];

                        if (!$hide) {

                        ?>
                            <tr valign="top">
                                <th scope="row"><p><label for="<?php echo $key ?>"><?php echo $displayText ?></label></p></th>
                                <td>
                                    <?php $this->createFormControl($key, $val, $this->getOption($key),$error); ?>
                                </td>
                            </tr>
                        <?php
                        }
                    }
                }
                ?>
                <tr valign="top">
                    <th scope="row"></th>
                    <td>
                        <input type="submit" class="button-primary" name="haystack_submit" value="<?php _e('Save Settings', 'haystack') ?>"/>
                        <?php if ($health > 1) { ?>
                            <a class="button-primary" href="<?php echo $reindex_url; ?>">Re-index Site</a>
                        <?php } ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</div>