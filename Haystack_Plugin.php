<?php

include_once('Haystack_LifeCycle.php');

class Haystack_Plugin extends Haystack_LifeCycle {

    function __construct() {     
        //Define some vars
        $this->ignorePostTypes = array('revision','nav_menu_item','attachment'); 
        $this->init_queue_listener();
    }

    //Must be running at all times, that's why it lives in construct
    public function init_queue_listener() {
        require_once plugin_dir_path( __FILE__ ).'Haystack_Queue.php';
        $this->process_queue = new WP_Haystack_Queue($this);
    }    
    
    public function getOptionMetaData() {
        $health = $this->health_check();

        $meta = array(
            'api_key' => array(
                'title' => 'API key',
            ),
            'client_hash' => array(
                'title' => 'Client Hash',
                'hide' => true,
            ),
        );

        if ($health > 0) {
            $meta['trig'] = array(
                'title' => __('Search ID','trig'),
            );
            $meta['queue_count'] = array(
                'title' => __('Queue Count','queue_count'),
                'hide' => true,
            );
            $meta['queue_status'] = array(
                'title' => __('Queue Status','queue_status'),
                'hide' => true,
            );
            $meta['analytics'] = array(
                'title' => __('Analytics','analytics'),
                'hide' => true,
            );
            $meta['post_types'] = array(
                'title' => __('Post types','post_types'),
            );
            $meta['quick_links_title'] = array(
                'title' => __('Title for the quick links field.'),
            );
            $meta['quick_links_text'] = array(
                'title' => __('Will not display'),
                'hide' => true,
            );
            $meta['suggest_menu'] = array(
                'title' => __('Use a Menu as qucik links or write your own <br />(formatted &lt;a href="LINK"&gt;TITLE&lt;/a&gt;)'),
            );
        }

        return $meta;
    }

    protected function initOptions() {
        $options = $this->getOptionMetaData();
        if (!empty($options)) {
            foreach ($options as $key => $arr) {
                if (is_array($arr) && count($arr > 1)) {
                    $this->addOption($key,'');
                }
            }
        }
    }

    public function getPluginDisplayName() {
        return 'Haystack';
    }

    protected function getMainPluginFileName() {
        return 'haystack.php';
    }

    protected function installDatabaseTables() {
    }

    protected function unInstallDatabaseTables() {
    }

    public function upgrade() {
    }

    public function addActionsAndFilters() {
        $health = $this->health_check();

        if ($health < 2) {
            add_action('admin_notices',array($this,'show_notice'));
        }

        // Add options administration page
        add_action('admin_menu', array($this, 'addSettingsSubMenuPage'));
        add_action('wp_print_styles', array($this,'haystack_css'));
        if (is_admin()) { //Admin
            add_action('wp_ajax_haystack',array($this,'haystack_callback'));
        } 
        else { //User
            //Actual Snippet added to footer
            add_action('wp_footer',array($this,'get_footer'));
        }

        //User saves posts, triggers an event.
        add_action('save_post',array($this,'save_post'));
        add_action('deleted_post',array($this,'delete_post'));
        add_action('trashed_post',array($this,'delete_post'));

        //Add AJAX ports for stats/ping/analytics and admin
        add_action('wp_ajax_haystack_ping',array($this,'haystack_ping'));
        add_action('wp_ajax_nopriv_haystack_ping',array($this,'haystack_ping'));
        add_action('wp_ajax_haystack_admin',array($this,'haystack_admin'));
        add_action('wp_ajax_haystack_menu_suggest',array($this,'menu_suggest'));


        if ($health == 2) {
            add_action('admin_bar_menu',array(&$this,'top_menu_item'),999);
        }
    }

    public function haystack_css($ver = false) {
        wp_enqueue_style('haystack_admin',plugin_dir_url(__FILE__).'css/haystack_front.css');
    }
    
    public function top_menu_item($admin_bar) {
        $args = array(
            'id'    => 'haystack-pop',
            'title' => '<span class="ab-icon h-icon h-icon-haystack" title="Haystack Pop"></span>',
            'href'  => '/#!haystack=pop',
            'meta'  => array(
                'title' => __('Haystack Pop'),
            ),
        );
        if (!is_admin()) { //Admin
            $args['meta']['onclick'] = 'Hay.pop();';
        }
        $admin_bar->add_menu($args);
    }

    //Add haystack to live pages
    public function get_footer() {
        
        //Data
        $client_hash        = $this->getOption('client_hash');
        $trig              = $this->getOption('trig');
        $quick_links       = $this->get_quick_links();
        $quick_links_title = $this->getOption('quick_links_title',false);
        $quick_links_title = $quick_links_title ? '<div>'.$quick_links_title.'</div>' : '';

        $data = array(
            'client_hash' => $client_hash,
            'analytics' => HAYSTACK_ANALYTICS,
            'quick_links' => $quick_links_title.$quick_links,
        );
        if ($trig != '') {
            $data['trig'] = $trig;
        }

        echo '
        <script type="text/javascript" src="'.HAYSTACK_FRONT.'haystack.min.js?_'.time().'"></script>
        <script type="text/javascript">
            var Hay = new Haystack('.json_encode($data,true).');
        </script>';
    }

    public function get_quick_links() {
        $menu = $this->getOption('suggest_menu',false);
        if (!$menu || $menu == 'default') {
            return str_replace('\\','',$this->getOption('quick_links_text'));
        }
        else {
            $list = wp_get_nav_menu_items($menu);
            $data = '';
            foreach ($list as $k => $v) {
                $data.= '<a href="'.$v->url.'">'.$v->title.'</a>';
            }
            return $data;
        }
    }

    public function save_post($post_id) {
        if ('trash' == get_post_status($post_id) ||
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
            wp_is_post_revision($post_id) ||
            !$this->is_post_type_enabled($post_id)) {
            return;
        }

        $package = $this->get_package($post_id);
        
        // Write changes to the Server.
        $res = $this->api_call($package);
    }
    
    public function delete_post($post_id) {
        if (!$this->is_post_type_enabled($post_id)) {
            return;
        }
        $package = array(
          'api_token' => $this->getOption('api_key'),
          'id'        => 'content-'.$post_id,
          'type'      => get_post_type($post_id),
        );

        // Write changes to the Server.
        $res = $this->api_call($package,'index','delete');
    }

    public function api_call($package, $type = 'index', $op = 'insert') { //DRUPAL SYNC
        //open connection
        $ch    = curl_init();
        $url   = HAYSTACK_API_SERVER . HAYSTACK_API_VERSION;
        $token = '?api_token=' . $package['api_token'];

        if ($op == 'delete') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            $url .= '/index/' . $package['type'] . '/' . $package['id'] . $token;
        }
        else if ($op == 'delete_all') { //We need to keep the index alive
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            $url .= '/flush/index' . $token;
        }
        else if ($op == 'delete_type') { //We clear only one type
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            $url .= '/flush/type/' . $type . $token;
        }
        else if ($op == 'stats') { //We clear only one type
            $url .= '/stats';
            $data = json_encode($package, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data)
            ));
            $package = $data;
        }
        else {
            $url .= '/' . $type;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($package));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $package);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        //execute post
        $result = curl_exec($ch);

        //close connection
        curl_close($ch);

        return $result;
    }

    public function is_post_type_enabled($post_id) {
        $post_type =  get_post_type($post_id);
        $enabled_post_types = array_keys($this->getOption('post_types'));
        return in_array($post_type, $enabled_post_types);
    }

    public function get_package($id) {
        $url = get_permalink($id);
        $og_tags = $this->get_meta($url);
        $body = $this->get_body($id);
        $title = get_the_title($id);
        $image = $this->get_image($id);
        $type = get_post_type($id);

        $package = array(
            'api_token' => $this->getOption('api_key'),
            'id'        => 'content-'.$id,
            'es_type'   => get_post_type($id),
            'type'      => '<type data-type="'.$type.'">'.$type.'</type>',
            'title'     => !empty($title) ? $title : $og_tags['title'],
            'link'      => $url,
            'menu'      => $this->get_menus($id),
            'body'      => !empty($body) ? $body : $og_tags['description'],
            'image'     => !empty($empty) ? $image : $og_tags['image'],
            'tags'      => $this->get_tags($id),
        );

        //Allow user to hook filter and add their own data
        foreach (array('type','image','body','tags') as $key => $val) {
            $tmp = apply_filters('haystack_set_'.$val,$id);
            if ($tmp != $id) {
                $package[$val] = $tmp;
            }
        }

        return $package;
    }

    //To-do
    public function get_menus($id) {
        $active_menus = array();
        $all = get_terms( 'nav_menu', array( 'hide_empty' => true ));
        foreach ($all as $menu) {
            $nav = wp_get_nav_menu_items($menu->term_id,array('include' => array('id')));
            foreach ($nav as $k => $v) {
                if ($id == $v->object_id) {
                    $active_menus[] = $menu->name;
                }
            }
        }
        $active_menus = implode(',',$active_menus);
        return $active_menus;
    }

    public function get_tags($id) {
        $res = array();
        foreach (wp_get_post_tags($id) as $key => $val) {
            $tag = get_tag($val->term_id);
            $res[] = $tag->name;
        }
        return implode(', ',$res);
    }

    public function get_body($id) {
        // Clean out tags and spaces from the body text.
        $body = get_post($id);
        $body = $body->post_content;
        $body = str_replace('<', ' <', $body);
        $body = strip_tags($body);
        $body = preg_replace('#\s*\[.+\]\s*#U',' ',$body); 
        $body = preg_replace('/&nbsp;/', ' ',$body);
        $body = preg_replace('!\s+!',' ', $body);

        $body = trim($body);

        return $body;
    }

    public function get_image($id) {
        $t = wp_get_attachment_image_src(get_post_thumbnail_id($id));
        if ($t && !empty($t)) {
            return $t[0];
        }
        else {
            return '';
        }
    }

    public function get_meta($url) { //DRUPAL SYNC
        $html = @file_get_contents($url);
        if ($html === FALSE) {
            //wordpress unable to crawl page
        }
        else {
            $doc = new DOMDocument();
            $doc->loadHTML($html);

            $metanodes = $doc->getElementsByTagName('meta');
            foreach ($metanodes as $node) {
              $key = $node->getAttribute('property');
              $val = $node->getAttribute('content');
              if (!empty($key)) {
                $mdata[$key] = $val;
              }
            }
        }

        return array(
            'title'       => isset($mdata['og:title']) ? $mdata['og:title'] : '',
            'description' => isset($mdata['og:description']) ? $mdata['og:description'] : '',
            'image'       => isset($mdata['og:image']) ? $mdata['og:image'] : ''
        );
    }

    public function get_credentials($apiKey) {
        $uri = HAYSTACK_API_SERVER . HAYSTACK_API_VERSION . '/credentials?api_token=' . $apiKey;
        if ($response = @file_get_contents($uri)) {
            $data = json_decode($response);

            if (isset($data->status) && $data->status == 'success') {
                $this->updateOption('client_hash', $data->siteHash);
                return true;
            }
        }

        return false;
    }
    
    public function health_check() {
        $i = 0;
        $steps = array(
            'key' => $this->getOption('api_key',false),
            'hash' => $this->getOption('client_hash', false),
            'index' => $this->getOption('haystack_first_index', false),
            'types' => $this->getOption('post_types', false),
        );

        if ($steps['key'] && $steps['hash']) {
            $i++;
            if ($steps['index']) {
                $i++;
            }
        }

        return $i;
    }

    public function process_item($id) { //Called in our queue object
        $package = $this->get_package($id);
        
        // Write changes to the Server.
        $res = $this->api_call($package);
    }

    public function reindex() {
        //Delete all from server
        $package = array(
            'api_token' => $this->getOption('api_key'),
        );
        $this->api_call($package,'index','delete_all');

        //Remove old queues
        $this->process_queue->empty_queue();
        
        //Reset types and process
        $types = $this->getOption('post_types',false);
        if ($types) {
            $this->process_queue->types = $types ? array_keys($types) : array();
            $this->push_type_to_queue();

            //Set the variable
            $this->addOption('haystack_first_index',true);
        }
    }

    public function push_type_to_queue() {
        $args = array(
            'post_type' => $this->process_queue->types,
            'post_status' => 'publish',
            'posts_per_page' => '-1', //Limitless
            'field' => 'ids',
        );
        $the_query = new WP_Query($args);
        if ($the_query->have_posts()) {
            while ($the_query->have_posts()){
                $the_query->the_post();
                $id = get_the_ID();
                $this->process_queue->push_to_queue($id);
            }

            $count = $this->getOption('queue_count',false);
            $count = $count ? $count + $the_query->post_count : $the_query->post_count;
            $this->updateOption('queue_count',$count);
            $this->process_queue->save()->dispatch();
        }
    }

    public function add_type($types) {
        //Add type to server
        $package = array(
            'api_token' => $this->getOption('api_key'),
        );
        $this->process_queue->types = $types;
        $this->push_type_to_queue();
    }

    public function sub_type($types) {
        //Delete type from server
        $package = array(
            'api_token' => $this->getOption('api_key'),
        );

        foreach ($types as $key => $val) {
            $this->api_call($package, $val, 'delete_type');
        }
    }

    public function haystack_ping() { //Temporarily on hold for meeting NWASAP
        $arr = $_REQUEST;
        unset($arr['action']);
        $arr['client'] = 'wordpress';

        $package = array(
            'api_token' => $this->getOption('api_key',false),
            'data'      => array($arr),
        );
        echo $this->api_call($package,'stats','stats');
        
        wp_die();
    }
    
    public function haystack_admin() { 
        echo json_encode($this->process_queue->status(),true);

        wp_die();
    }

    public function menu_suggest() { 
        $menus = get_terms('nav_menu');

        foreach ($menus as $key => $val) {
            $list = wp_get_nav_menu_items($val->term_id);
            $data[$val->slug]['title'] = $val->name;
            foreach ($list as $k => $v) {
                $data[$val->slug]['list'][] = '<a href="'.$v->url.'">'.$v->title.'</a>';
            }
        }
        echo json_encode($data,true);

        wp_die();
    }
}