<?php
/**
*
* @package Titania
* @version $Id$
* @copyright (c) 2008 phpBB Customisation Database Team
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_TITANIA'))
{
	exit;
}

titania::$hook->register_ary('phpbb_com_', array(
	'titania_page_header',
	'titania_page_footer',
	array('titania_topic', '__construct'),
	array('titania_post', 'post'),
));

// Do we need to install the DB stuff?
if (!isset(phpbb::$config['titania_hook_phpbb_com']))
{
	phpbb::_include('../umil/umil', false, 'umil');

	$umil = new umil(true, phpbb::$db);

	$umil->run_actions('update', array(
		'1.0.0' => array(
			'table_column_add' => array(
				array(TITANIA_TOPICS_TABLE, 'phpbb_topic_id', array('UINT', 0)),
			),
		),
	),
	'titania_hook_phpbb_com');

	unset($umil);
}

function phpbb_com_titania_page_header($hook, $page_title)
{
	phpbb::$template->assign_vars(array(
		'S_BODY_CLASS'		=> 'customise customisation-database',
		'S_IS_WEBSITE'		=> true,
	));

	global $auth, $phpEx, $template, $user;
	$root_path = TITANIA_ROOT . '../../';
	$base_path = generate_board_url(true) . '/';
	include($root_path . 'vars.' . PHP_EXT);

	// Setup the phpBB.com header
	phpbb::$template->set_custom_template(TITANIA_ROOT . '../../template/', 'website');
	phpbb::$template->set_filenames(array(
		'phpbb_com_header'		=> 'overall_header.html',
	));
	phpbb::$template->assign_display('phpbb_com_header', 'PHPBB_COM_HEADER', false);

	titania::set_custom_template();
}

function phpbb_com_titania_page_footer($hook, $run_cron, $template_body)
{
	// Setup the phpBB.com footer
	phpbb::$template->set_custom_template(TITANIA_ROOT . '../../template/', 'website');
	phpbb::$template->set_filenames(array(
		'phpbb_com_footer'		=> 'overall_footer.html',
	));
	phpbb::$template->assign_display('phpbb_com_footer', 'PHPBB_COM_FOOTER', false);

	titania::set_custom_template();
}

function phpbb_com_titania_topic_submit($hook, &$topic_object)
{
	if (defined('IN_TITANIA_CONVERT') || $topic_object->topic_category != TITANIA_TYPE_MOD || $topic_object->phpbb_topic_id)
	{
		return;
	}

	$forum_id = false;
	switch ($topic_object->topic_type)
	{
		case TITANIA_QUEUE_DISCUSSION :
			$forum_id = 61;
		break;

		case TITANIA_QUEUE :
			$forum_id = 38;
		break;
	}

	if (!$forum_id)
	{
		return;
	}

	titania::_include('functions_posting', 'phpbb_post_add');

	$sql = 'SELECT post_text, post_text_uid FROM ' . TITANIA_POSTS_TABLE . '
		WHERE post_id = ' . $topic_object->topic_first_post_id;
	$result = phpbb::$db->sql_query($sql);
	$row = phpbb::$db->sql_fetchrow($result);
	phpbb::$db->sql_freeresult($result);

	decode_message($row['post_text'], $row['post_text_uid']);

	$post_text .= "\n\n" . $topic_object->get_url();

	$options = array(
		'poster_id'				=> $topic_object->topic_first_post_user_id,
		'forum_id' 				=> $forum_id,
		'topic_title'			=> $topic_object->topic_subject,
		'post_text'				=> $row['post_text'],
		'topic_status'			=> ITEM_LOCKED,
	);

	$topic_id = phpbb_topic_add($options);

	$topic_object->phpbb_topic_id = $topic_id;

	$sql = 'UPDATE ' . TITANIA_TOPICS_TABLE . '
		SET phpbb_topic_id = ' . (int) $topic_id . '
		WHERE topic_id = ' . $topic_object->topic_id;
	phpbb::$db->sql_query($sql);
}


function phpbb_com_titania_post_post($hook, &$post_object)
{
	if (defined('IN_TITANIA_CONVERT') || $post_object->topic->topic_category != TITANIA_TYPE_MOD || !$post_object->topic->phpbb_topic_id)
	{
		return;
	}

	$forum_id = false;
	switch ($post_object->post_type)
	{
		case TITANIA_QUEUE_DISCUSSION :
			$forum_id = 61;
		break;

		case TITANIA_QUEUE :
			$forum_id = 38;
		break;
	}

	if (!$forum_id)
	{
		return;
	}

	titania::_include('functions_posting', 'phpbb_post_add');

	$post_text = $post_object->post_text;
	decode_message($post_text, $post_object->post_text_uid);

	$post_text .= "\n\n" . $post_object->get_url();

	$options = array(
		'poster_id'				=> $post_object->post_user_id,
		'forum_id' 				=> $forum_id,
		'topic_id'				=> $post_object->topic->phpbb_topic_id,
		'topic_title'			=> $post_object->post_subject,
		'post_text'				=> $post_text,
	);

	phpbb_post_add($options);

	// Make sure the topic is locked
	$sql = 'UPDATE ' . TOPICS_TABLE . '
		SET topic_status = ' . ITEM_LOCKED . '
		WHERE topic_id = ' . $post_object->topic->phpbb_topic_id . '
			AND topic_moved_id = 0';
	phpbb::$db->sql_query($sql);
}

function phpbb_com_titania_topic___construct($hook, &$topic_object)
{
	$topic_object->object_config = array_merge($topic_object->object_config, array(
		'phpbb_topic_id'	=> array('default' => 0),
	));
}