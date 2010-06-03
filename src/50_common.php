<?php class DataMapper {
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	 *                                                                   *
	 * Common methods                                                    *
	 *                                                                   *
	 * The following are common methods used by other methods.           *
	 *                                                                   *
	 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	// --------------------------------------------------------------------

	/**
	 * A specialized language lookup function that will automatically
	 * insert the model, table, and (optional) field into a key, and return the
	 * language result for the replaced key.
	 *
	 * @param string $key Basic key to use
	 * @param string $field Optional field value
	 * @return string|bool
	 */
	public function localize_by_model($key, $field = NULL)
	{
		$s = array('${model}', '${table}');
		$r = array($this->model, $this->table);
		if(!is_null($field))
		{
			$s[] = '${field}';
			$r[] = $field;
		}
		$key = str_replace($s, $r, $key);
		return $this->lang->line($key);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Variant that handles looking up a field labels
	 * @param string $field Name of field
	 * @param string|bool $label If not FALSE overrides default label.
	 * @return string|bool
	 */
	public function localize_label($field, $label = FALSE)
	{
		if($label === FALSE)
		{
			$label = $field;
			if(!empty($this->field_label_lang_format))
			{
				$label = $this->localize_by_model($this->field_label_lang_format, $field);
				if($label === FALSE)
				{
					$label = $field;
				}
			}
		}
		else if(strpos($label, 'lang:') === 0)
		{
			$label = $this->localize_by_model(substr($label, 5), $field);
		}
		return $label;
	}

	// --------------------------------------------------------------------

	/**
	 * To Array
	 *
	 * Converts this objects current record into an array for database queries.
	 * If validate is TRUE (getting by objects properties) empty objects are ignored.
	 *
	 * @ignore
	 * @param	bool $validate
	 * @return	array
	 */
	protected function _to_array($validate = FALSE)
	{
		$data = array();

		foreach ($this->fields as $field)
		{
			if ($validate && ! isset($this->{$field}))
			{
				continue;
			}
			
			$data[$field] = $this->{$field};
		}

		return $data;
	}

	// --------------------------------------------------------------------

	/**
	 * Process Query
	 *
	 * Converts a query result into an array of objects.
	 * Also updates this object
	 *
	 * @ignore
	 * @param	CI_DB_result $query
	 */
	protected function _process_query($query)
	{
		if ($query->num_rows() > 0)
		{
			// Populate all with records as objects
			$this->all = array();

			$this->_to_object($this, $query->row());

			// don't bother recreating the first item.
			$index = ($this->all_array_uses_ids && isset($this->id)) ? $this->id : 0;
			$this->all[$index] = $this->get_clone();

			if($query->num_rows() > 1)
			{
				$model = get_class($this);

				$first = TRUE;

				foreach ($query->result() as $row)
				{
					if($first)
					{
						$first = FALSE;
						continue;
					}
					
					$item = new $model();

					$this->_to_object($item, $row);

					if($this->all_array_uses_ids && isset($item->id))
					{
						$this->all[$item->id] = $item;
					}
					else
					{
						$this->all[] = $item;
					}
				}
			}
			
			// remove instantiations
			$this->_instantiations = NULL;
		
			// free large queries
			if($query->num_rows() > $this->free_result_threshold)
			{
				$query->free_result();
			}
		}
		else
		{
			// Refresh stored values is called by _to_object normally
			$this->_refresh_stored_values();
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * To Object
	 * Copies the values from a query result row to an object.
	 * Also initializes that object by running get rules, and
	 *   refreshing stored values on the object.
	 * 
	 * Finally, if any "instantiations" are requested, those related objects
	 *   are created off of query results
	 *
	 * This is only public so that the iterator can access it.
	 *
	 * @ignore
	 * @param	DataMapper $item Item to configure
	 * @param	object $row Query results
	 */
	public function _to_object($item, $row)
	{
		// Populate this object with values from first record
		foreach ($row as $key => $value)
		{
			$item->{$key} = $value;
		}

		foreach ($this->fields as $field)
		{
			if (! isset($row->{$field}))
			{
				$item->{$field} = NULL;
			}
		}

		// Force IDs to integers
		foreach($this->_field_tracking['intval'] as $field)
		{
			if(isset($item->{$field}))
			{
				$item->{$field} = intval($item->{$field});
			}
		}
		
		if (!empty($this->_field_tracking['get_rules']))
		{
			$item->_run_get_rules();
		}

		$item->_refresh_stored_values();
		
		if($this->_instantiations) {
			foreach($this->_instantiations as $related_field => $field_map) {
				// convert fields to a 'row'
				$row = array();
				foreach($field_map as $item_field => $c_field) {
					$row[$c_field] = $item->{$item_field};
				}
				
				// get the related item
				$c =& $item->_get_without_auto_populating($related_field);
				// set the values
				$c->_to_object($c, $row);
				
				// also set up the ->all array
				$c->all = array();
				$c->all[0] = $c->get_clone();
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Run Get Rules
	 *
	 * Processes values loaded from the database
	 *
	 * @ignore
	 */
	protected function _run_get_rules()
	{
		// Loop through each property to be validated
		foreach ($this->_field_tracking['get_rules'] as $field)
		{
			// Get validation settings
			$rules = $this->validation[$field]['get_rules'];
			
			// only process non-empty keys that are not specifically
			// set to be null
			if( ! isset($this->{$field}) && ! in_array('allow_null', $rules))
			{
				if(isset($this->has_one[$field]))
				{
					// automatically process $item_id values
					$field = $field . '_id';
					if( ! isset($this->{$field}) && ! in_array('allow_null', $rules))
					{
						continue;
					}
				} else {
					continue;
				}
			}
			
			// Loop through each rule to validate this property against
			foreach ($rules as $rule => $param)
			{
				// Check for parameter
				if (is_numeric($rule))
				{
					$rule = $param;
					$param = '';
				}
				if($rule == 'allow_null')
				{
					continue;
				}

				if (method_exists($this, '_' . $rule))
				{
					// Run rule from DataMapper or the class extending DataMapper
					$result = $this->{'_' . $rule}($field, $param);
				}
				else if($this->_extension_method_exists('rule_' . $rule))
				{
					// Run an extension-based rule.
					$result = $this->{'rule_' . $rule}($field, $param);
				}
				else if (method_exists($this->form_validation, $rule))
				{
					// Run rule from CI Form Validation
					$result = $this->form_validation->{$rule}($this->{$field}, $param);
				}
				else if (function_exists($rule))
				{
					// Run rule from PHP
					$this->{$field} = $rule($this->{$field});
				}
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Refresh Stored Values
	 *
	 * Refreshes the stored values with the current values.
	 *
	 * @ignore
	 */
	protected function _refresh_stored_values()
	{
		// Update stored values
		foreach ($this->fields as $field)
		{
			$this->stored->{$field} = $this->{$field};
		}

		// If there is a "matches" validation rule, match the field value with the other field value
		foreach ($this->_field_tracking['matches'] as $field_name => $match_name)
		{
			$this->{$field_name} = $this->stored->{$field_name} = $this->{$match_name};
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Assign Libraries
	 *
	 * Originally used by CodeIgniter, now just logs a warning.
	 *
	 * @ignore
	 */
	public function _assign_libraries()
	{
		log_message('debug', "Warning: A DMZ model ({$this->model}) was either loaded via autoload, or manually.  DMZ automatically loads models, so this is unnecessary.");
	}

	// --------------------------------------------------------------------

	/**
	 * Assign Libraries
	 *
	 * Assigns required CodeIgniter libraries to DataMapper.
	 *
	 * @ignore
	 */
	protected function _dmz_assign_libraries()
	{
		$CI =& get_instance();
		if ($CI)
		{
			$this->lang = $CI->lang;
			$this->load = $CI->load;
			$this->config = $CI->config;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Load Languages
	 *
	 * Loads required language files.
	 *
	 * @ignore
	 */
	protected function _load_languages()
	{

		// Load the DataMapper language file
		$this->lang->load('datamapper');
	}

	// --------------------------------------------------------------------

	/**
	 * Load Helpers
	 *
	 * Loads required CodeIgniter helpers.
	 *
	 * @ignore
	 */
	protected function _load_helpers()
	{
		// Load inflector helper for singular and plural functions
		$this->load->helper('inflector');

		// Load security helper for prepping functions
		$this->load->helper('security');
	}
}

/**
 * Simple class to prevent errors with unset fields.
 * @package DMZ
 *
 * @param string $FIELD Get the error message for a given field or custom error
 * @param string $RELATED Get the error message for a given relationship
 * @param string $transaction Get the transaction error.
 */
class DM_Error_Object {
	/**
	 * Array of all error messages.
	 * @var array
	 */
	public $all = array();

	/**
	 * String containing entire error message.
	 * @var string
	 */
	public $string = '';

	/**
	 * All unset fields are returned as empty strings by default.
	 * @ignore
	 * @param string $field
	 * @return string Empty string
	 */
	public function __get($field) {
		return '';
	}
}

// leave this line
