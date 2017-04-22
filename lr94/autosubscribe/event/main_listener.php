<?php

/**
*
* @copyright (c) 2017 Luca Robbiano (lr94)
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace lr94\autosubscribe\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\user;
use \phpbb\config\config;
use phpbb\db\driver\driver_interface as db_interface;
use \phpbb\request\request;

class main_listener implements EventSubscriberInterface
{
	protected $config;
	protected $db;
	protected $user;
	protected $request;

	public function __construct(config $config, db_interface $db, user $user, request $request)
	{
		$this->config = $config;
		$this->db = $db;
		$this->user = $user;
		$this->request = $request;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'								=> 'load_language',
			'core.acp_manage_forums_display_form'			=> 'display_option',
			'core.acp_manage_forums_request_data'			=> 'request_forum_data',
			'core.acp_manage_forums_initialise_data'		=> 'init_forum_data',
			'core.submit_post_end'							=> 'submit_post',
		);
	}
	
	public function load_language($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'lr94/autosubscribe',
			'lang_set' => 'common',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}
	
	public function display_option($event)
	{
		$template_data = $event['template_data'];
		$template_data['S_AUTO_SUBSCRIBE'] = ($event['forum_data']['forum_auto_subscribe']) ? true : false;
		$event['template_data'] = $template_data;
	}
	
	public function request_forum_data($event)
	{
		$forum_data = $event['forum_data'];
		$forum_data['forum_auto_subscribe'] = $this->request->variable('auto_subscribe', false);
		$event['forum_data'] = $forum_data;
	}
	
	public function init_forum_data($event)
	{
		if(!$event['update'] && $event['action'] != 'edit')
		{
			$forum_data = $event['forum_data'];
			$forum_data['forum_auto_subscribe'] = false;
			$event['forum_data'] = $forum_data;
		}
	}
	
	public function submit_post($event)
	{
		if($event['mode'] != 'post')
		{
			return;
		}
		
		$forum_id = $event['data']['forum_id'];
		$topic_id = $event['data']['topic_id'];
		$poster_id = $event['data']['poster_id']; // It should be the same as $this->user->data['user_id']
		
		$sql = 'SELECT forum_auto_subscribe
				FROM ' . FORUMS_TABLE . '
				WHERE forum_id = ' . $forum_id;
		
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		
		if($row['forum_auto_subscribe'])
		{
			$sql_ary = array(
				'topic_id'		=> $topic_id,
				'user_id'		=> $poster_id,
				'notify_status'	=> NOTIFY_YES
			);
		
			$sql = 'INSERT INTO ' . TOPICS_WATCH_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
			
			$this->db->sql_query($sql);
		}
	}
}
