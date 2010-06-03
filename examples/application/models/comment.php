<?php

/**
 * Comment Class
 * Comments are 
 *
 * @license		MIT License
 * @category	Models
 * @author		Phil DeJarnett
 * @link		http://www.overzealous.com/dmz/
 */
class Comment extends DataMapper {

	// --------------------------------------------------------------------
	// Relationships
	// --------------------------------------------------------------------

	var $has_one = array(
		// Must be associated with a bug
		'bug',
		// Has a user
		'user'
	);
	
	// --------------------------------------------------------------------
	// Validation
	// --------------------------------------------------------------------	
	
	var $validation = array(
		'comment' => array(
			'rules' => array('required')
		),
		// Bug is required
		'bug' => array(
			'rules' => array('required')
		),
		// User is required
		'user' => array(
			'rules' => array('required')
		)
	);
	
	// Default to ordering by updated
	var $default_order_by = array('updated');
	
}

/* End of file comment.php */
/* Location: ./application/models/comment.php */