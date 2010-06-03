<?php class DataMapper {
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	 *                                                                   *
	 * Magic methods                                                     *
	 *                                                                   *
	 * The following are methods to override the default PHP behaviour.  *
	 *                                                                   *
	 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	// --------------------------------------------------------------------

	/**
	 * Magic Get
	 *
	 * Returns the value of the named property.
	 * If named property is a related item, instantiate it first.
	 *
	 * This method also instantiates the DB object and the form_validation
	 * objects as necessary
	 *
	 * @ignore
	 * @param	string $name Name of property to look for
	 * @return	mixed
	 */
	public function __get($name)
	{
		// We dynamically get DB when needed, and create a copy.
		// This allows multiple queries to be generated at the same time.
		if($name == 'db')
		{
			$CI =& get_instance();
			if($this->db_params === FALSE)
			{
				$this->db =& $CI->db;
			}
			else
			{
				if($this->db_params == '' || $this->db_params === TRUE)
				{
					// ensure the shared DB is disconnected, even if the app exits uncleanly
					if(!isset($CI->db->_has_shutdown_hook))
					{
						register_shutdown_function(array($CI->db, 'close'));
						$CI->db->_has_shutdown_hook = TRUE;
					}
					// clone, so we don't create additional connections to the DB
					$this->db = clone($CI->db);
					$this->db->_reset_select();
				}
				else
				{
					// connecting to a different database, so we *must* create additional copies.
					// It is up to the developer to close the connection!
					$this->db = $CI->load->database($this->db_params, TRUE, TRUE);
				}
				// these items are shared (for debugging)
				if(isset($CI->db))
				{
					$this->db->queries =& $CI->db->queries;
					$this->db->query_times =& $CI->db->query_times;
				}
			}
			// ensure the created DB is disconnected, even if the app exits uncleanly
			if(!isset($this->db->_has_shutdown_hook))
			{
				register_shutdown_function(array($this->db, 'close'));
				$this->db->_has_shutdown_hook = TRUE;
			}
			return $this->db;
		}
		
		// Special case to get form_validation when first accessed
		if($name == 'form_validation')
		{
			$CI =& get_instance();
			if( ! isset($CI->form_validation))
			{
				$CI->load->library('form_validation');
			}
			$this->form_validation = $CI->form_validation;
			$this->lang->load('form_validation');
			return $this->form_validation;
		}

		$has_many = isset($this->has_many[$name]);
		$has_one = isset($this->has_one[$name]);

		// If named property is a "has many" or "has one" related item
		if ($has_many || $has_one)
		{
			$related_properties = $has_many ? $this->has_many[$name] : $this->has_one[$name];
			// Instantiate it before accessing
			$class = $related_properties['class'];
			$this->{$name} = new $class();

			// Store parent data
			$this->{$name}->parent = array('model' => $related_properties['other_field'], 'id' => $this->id);

			// Check if Auto Populate for "has many" or "has one" is on
			// (but only if this object exists in the DB, and we aren't instantiating)
			if ($this->exists() && 
					($has_many && $this->auto_populate_has_many) || ($has_one && $this->auto_populate_has_one))
			{
				$this->{$name}->get();
			}

			return $this->{$name};
		}
		
		$name_single = singular($name);
		if($name_single !== $name) {
			// possibly return single form of name
			$test = $this->{$name_single};
			if(is_object($test)) {
				return $test;
			}
		}

		return NULL;
	}

	// --------------------------------------------------------------------

	/**
	 * Used several places to temporarily override the auto_populate setting
	 * @ignore
	 * @param string $related Related Name
	 * @return DataMapper|NULL
	 */
	private function &_get_without_auto_populating($related)
	{
		$b_many = $this->auto_populate_has_many;
		$b_one = $this->auto_populate_has_one;
		$this->auto_populate_has_many = FALSE;
		$this->auto_populate_has_one = FALSE;
		$ret =& $this->{$related};
		$this->auto_populate_has_many = $b_many;
		$this->auto_populate_has_one = $b_one;
		return $ret;
	}

	// --------------------------------------------------------------------

	/**
	 * Magic Call
	 *
	 * Calls special methods, or extension methods.
	 *
	 * @ignore
	 * @param	string $method Method name
	 * @param	array $arguments Arguments to method
	 * @return	mixed
	 */
	public function __call($method, $arguments)
	{
		
		// List of watched method names
		// NOTE: order matters: make sure more specific items are listed before
		// less specific items
		$watched_methods = array(
			'save_', 'delete_',
			'get_by_related_', 'get_by_related', 'get_by_',
			'_related_subquery', '_subquery',
			'_related_', '_related',
			'_join_field',
			'_field_func', '_func'
		);

		foreach ($watched_methods as $watched_method)
		{
			// See if called method is a watched method
			if (strpos($method, $watched_method) !== FALSE)
			{
				$pieces = explode($watched_method, $method);
				if ( ! empty($pieces[0]) && ! empty($pieces[1]))
				{
					// Watched method is in the middle
					return $this->{'_' . trim($watched_method, '_')}($pieces[0], array_merge(array($pieces[1]), $arguments));
				}
				else
				{
					// Watched method is a prefix or suffix
					return $this->{'_' . trim($watched_method, '_')}(str_replace($watched_method, '', $method), $arguments);
				}
			}
		}
		
		// attempt to call an extension
		$ext = NULL;
		if($this->_extension_method_exists($method, 'local'))
		{
			$name = $this->extensions['_methods'][$method];
			$ext = $this->extensions[$name];
		}
		else if($this->_extension_method_exists($method, 'global'))
		{
			$name = DataMapper::$global_extensions['_methods'][$method];
			$ext = DataMapper::$global_extensions[$name];
		}
		if( ! is_null($ext))
		{
			array_unshift($arguments, $this);
			return call_user_func_array(array($ext, $method), $arguments);
		}
		
		// show an error, for debugging's sake.
		throw new Exception("Unable to call the method \"$method\" on the class " . get_class($this));
	}

	// --------------------------------------------------------------------

	/**
	 * Returns TRUE or FALSE if the method exists in the extensions.
	 *
	 * @param	object $method Method to look for.
	 * @param	object $which One of 'both', 'local', or 'global'
	 * @return	bool TRUE if the method can be called.
	 */
	private function _extension_method_exists($method, $which = 'both') {
		$found = FALSE;
		if($which != 'global') {
			$found =  ! empty($this->extensions) && isset($this->extensions['_methods'][$method]);
		}
		if( ! $found && $which != 'local' ) {
			$found =  ! empty(DataMapper::$global_extensions) && isset(DataMapper::$global_extensions['_methods'][$method]);
		}
		return $found;
	}

	// --------------------------------------------------------------------

	/**
	 * Magic Clone
	 *
	 * Allows for a less shallow clone than the default PHP clone.
	 *
	 * @ignore
	 */
	public function __clone()
	{
		foreach ($this as $key => $value)
		{
			if (is_object($value) && $key != 'db')
			{
				$this->{$key} = clone($value);
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * To String
	 *
	 * Converts the current object into a string.
	 * Should be overridden by extended objects.
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return ucfirst($this->model);
	}

	// --------------------------------------------------------------------

	/**
	 * Allows the all array to be iterated over without
	 * having to specify it.
	 * 
	 * @return	Iterator An iterator for the all array
	 */
	public function getIterator() {
		if(isset($this->_dm_dataset_iterator)) {
			return $this->_dm_dataset_iterator;
		} else {
			return new ArrayIterator($this->all);
		}
	}

	// --------------------------------------------------------------------

}