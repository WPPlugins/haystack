<?php

require_once plugin_dir_path( __FILE__ ).'lib/wp-background-tasks/wp-async-request.php';
require_once plugin_dir_path( __FILE__ ).'lib/wp-background-tasks/wp-background-process.php';

class WP_Haystack_Queue extends WP_Background_Process {
	
	public function __construct($parent) {
		$this->prefix = $parent->getOptionNamePrefix();
		parent::__construct();
		//Parent, caller, must be defined as true parent is WP_Background_Process object
		$this->parent = $parent; 
	}

	public $types = array();

	protected $action = 'haystack_queue';

	protected function task($id) { //Single item
		$this->tmp_id = $id;
		$this->process_item($id);

		return false;
	}

	public function process_item($id) {
		$this->parent->process_item($id); //The actual lifting will be done by the parent caller

        $num = $this->parent->getOption('queue_status',false);
        $num = $num ? $num + 1 : 1;
        $this->parent->updateOption('queue_status',$num);
	}

	protected function complete() { //End, default
		parent::complete();
		$this->parent->deleteOption('queue_status');
		$this->parent->deleteOption('queue_count');
	}

	public function empty_queue() {
		$this->clear_scheduled_event();
		$this->cancel_process();
		$this->complete();

		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';
		
		//Manually delete all existing table cells used to store to-do ids
		$key = $this->identifier . '_batch_%';
		$query = $wpdb->prepare("DELETE FROM {$table} WHERE {$column} LIKE %s",$key);
		$wpdb->query($query);
	}

	public function status() {
		return array(
			'num' => $this->parent->getOption('queue_status',false),
			'total' => $this->parent->getOption('queue_count',false),
		);
	}
}
