<?php class DataMapper {
	/**
	 * Constructor
	 *
	 * Initialize DataMapper.
	 * @param	int $id if provided, load in the object specified by that ID.
	 */
	public function DataMapper($id = NULL)
	{
		$this->_dmz_assign_libraries();

		$this_class = strtolower(get_class($this));
		$is_dmz = $this_class == 'datamapper';

		if($is_dmz)
		{
			$this->_load_languages();

			$this->_load_helpers();
		}

		// this is to ensure that singular is only called once per model
		if(isset(DataMapper::$common[DMZ_CLASSNAMES_KEY][$this_class])) {
			$common_key = DataMapper::$common[DMZ_CLASSNAMES_KEY][$this_class];
		} else {
			DataMapper::$common[DMZ_CLASSNAMES_KEY][$this_class] = $common_key = singular($this_class);
		}
		
		// Determine model name
		if (empty($this->model))
		{
			$this->model = $common_key;
		}

		// Load stored config settings by reference
		foreach (DataMapper::$config as $config_key => &$config_value)
		{
			// Only if they're not already set
			if (empty($this->{$config_key}))
			{
				$this->{$config_key} =& $config_value;
			}
		}

		// Load model settings if not in common storage
		if ( ! isset(DataMapper::$common[$common_key]))
		{
			// If model is 'datamapper' then this is the initial autoload by CodeIgniter
			if ($is_dmz)
			{
				// Load config settings
				$this->config->load('datamapper', TRUE, TRUE);

				// Get and store config settings
				DataMapper::$config = $this->config->item('datamapper');

				// now double check that all required config values were set
				foreach(DataMapper::$_dmz_config_defaults as $config_key => $config_value)
				{
					if(empty(DataMapper::$config[$config_key]))
					{
						DataMapper::$config[$config_key] = $config_value;
					}
				}
				
				DataMapper::_load_extensions(DataMapper::$global_extensions, DataMapper::$config['extensions']);
				unset(DataMapper::$config['extensions']);

				return;
			}

			// load language file, if requested and it exists
			if(!empty($this->lang_file_format))
			{
				$lang_file = str_replace(array('${model}', '${table}'), array($this->model, $this->table), $this->lang_file_format);
				$deft_lang = $this->config->item('language');
				$idiom = ($deft_lang == '') ? 'english' : $deft_lang;
				if(file_exists(APPPATH.'language/'.$idiom.'/'.$lang_file.'_lang'.EXT))
				{
					$this->lang->load($lang_file, $idiom);
				}
			}
			
			$loaded_from_cache = FALSE;
			
			// Load in the production cache for this model, if it exists
			if( ! empty(DataMapper::$config['production_cache']))
			{
				// attempt to load the production cache file
				$cache_folder = APPPATH . DataMapper::$config['production_cache'];
				if(file_exists($cache_folder) && is_dir($cache_folder) && is_writeable($cache_folder))
				{
					$cache_file = $cache_folder . '/' . $common_key . EXT;
					if(file_exists($cache_file))
					{
						include($cache_file);
						if(isset($cache))
						{
							DataMapper::$common[$common_key] =& $cache;
							unset($cache);
			
							// allow subclasses to add initializations
							if(method_exists($this, 'post_model_init'))
							{
								$this->post_model_init(TRUE);
							}
							
							// Load extensions (they are not cacheable)
							$this->_initiate_local_extensions($common_key);
							
							$loaded_from_cache = TRUE;
						}
					}
				}
			}
			
			if(! $loaded_from_cache)
			{

				// Determine table name
				if (empty($this->table))
				{
					$this->table = plural(get_class($this));
				}
	
				// Add prefix to table
				$this->table = $this->prefix . $this->table;

				$this->_field_tracking = array(
					'get_rules' => array(),
					'matches' => array(),
					'intval' => array('id')
				);
	
				// Convert validation into associative array by field name
				$associative_validation = array();
	
				foreach ($this->validation as $name => $validation)
				{
					if(is_string($name)) {
						$validation['field'] = $name;
					} else {
						$name = $validation['field'];
					}
					
					// clean up possibly missing fields
					if( ! isset($validation['rules']))
					{
						$validation['rules'] = array();
					}
					
					// Populate associative validation array
					$associative_validation[$name] = $validation;

					if (!empty($validation['get_rules']))
					{
						$this->_field_tracking['get_rules'][] = $name;
					}

					// Check if there is a "matches" validation rule
					if (isset($validation['rules']['matches']))
					{
						$this->_field_tracking['matches'][$name] = $validation['rules']['matches'];
					}
				}
				
				// set up id column, if not set
				if(!isset($associative_validation['id']))
				{
					// label is set below, to prevent caching language-based labels
					$associative_validation['id'] = array(
						'field' => 'id',
						'rules' => array('integer')
					);
				}
	
				$this->validation = $associative_validation;

				// Force all other has_one ITFKs to integers on get
				foreach($this->has_one as $related => $rel_props)
				{
					$field = $related . '_id';
					if(	in_array($field, $this->fields) &&
						( ! isset($this->validation[$field]) || // does not have a validation key or...
							! isset($this->validation[$field]['get_rules'])) &&  // a get_rules key...
						( ! isset($this->validation[$related]) || // nor does the related have a validation key or...
							! isset($this->validation[$related]['get_rules'])) ) // a get_rules key
					{
						// assume an int
						$this->_field_tracking['intval'][] = $field;
					}
				}
	
				// Get and store the table's field names and meta data
				$fields = $this->db->field_data($this->table);
	
				// Store only the field names and ensure validation list includes all fields
				foreach ($fields as $field)
				{
					// Populate fields array
					$this->fields[] = $field->name;
	
					// Add validation if current field has none
					if ( ! isset($this->validation[$field->name]))
					{
						// label is set below, to prevent caching language-based labels
						$this->validation[$field->name] = array('field' => $field->name, 'rules' => array());
					}
				}
				
				// convert simple has_one and has_many arrays into more advanced ones
				foreach(array('has_one', 'has_many') as $arr)
				{
					$new = array();
					foreach ($this->{$arr} as $related_field => $rel_props)
					{
						// allow for simple (old-style) associations
						if (is_int($related_field))
						{
							$related_field = $rel_props;
						}
						// convert value into array if necessary
						if ( ! is_array($rel_props))
						{
							$rel_props = array('class' => $rel_props);
						} else if ( ! isset($rel_props['class']))
						{
							// if already an array, ensure that the class attribute is set
							$rel_props['class'] = $related_field;
						}
						if( ! isset($rel_props['other_field']))
						{
							// add this model as the model to use in queries if not set
							$rel_props['other_field'] = $this->model;
						}
						if( ! isset($rel_props['join_self_as']))
						{
							// add this model as the model to use in queries if not set
							$rel_props['join_self_as'] = $rel_props['other_field'];
						}
						if( ! isset($rel_props['join_other_as']))
						{
							// add the key as the model to use in queries if not set
							$rel_props['join_other_as'] = $related_field;
						}
						$new[$related_field] = $rel_props;

						// load in labels for each not-already-set field
						if(!isset($this->validation[$related_field]))
						{
							$label = $this->localize_label($related_field);
							if(!empty($label))
							{
								// label is re-set below, to prevent caching language-based labels
								$this->validation[$related_field] = array('field' => $related_field, 'rules' => array());
							}
						}
					}
					// replace the old array
					$this->{$arr} = $new;
				}
				
				// allow subclasses to add initializations
				if(method_exists($this, 'post_model_init'))
				{
					$this->post_model_init(FALSE);
				}
	
				// Store common model settings
				foreach (array('table', 'fields', 'validation',
							'has_one', 'has_many', '_field_tracking') as $item)
				{
					DataMapper::$common[$common_key][$item] = $this->{$item};
				}
				
				// if requested, store the item to the production cache
				if( ! empty(DataMapper::$config['production_cache']))
				{
					// attempt to load the production cache file
					$cache_folder = APPPATH . DataMapper::$config['production_cache'];
					if(file_exists($cache_folder) && is_dir($cache_folder) && is_writeable($cache_folder))
					{
						$cache_file = $cache_folder . '/' . $common_key . EXT;
						$cache = "<"."?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed'); \n";
						
						$cache .= '$cache = ' . var_export(DataMapper::$common[$common_key], TRUE) . ';';
				
						if ( ! $fp = @fopen($cache_file, 'w'))
						{
							show_error('Error creating production cache file: ' . $cache_file);
						}
						
						flock($fp, LOCK_EX);	
						fwrite($fp, $cache);
						flock($fp, LOCK_UN);
						fclose($fp);
					
						@chmod($cache_file, FILE_WRITE_MODE);
					}
				}
				
				// Load extensions last, so they aren't cached.
				$this->_initiate_local_extensions($common_key);
			}

			// Finally, localize the labels here (because they shouldn't be cached
			// This also sets any missing labels.
			$validation =& DataMapper::$common[$common_key]['validation'];
			foreach($validation as $field => &$val)
			{
				// Localize label if necessary
				$val['label'] = $this->localize_label($field,
						isset($val['label']) ?
							$val['label'] :
							FALSE);
			}
			unset($validation);
		}

		// Load stored common model settings by reference
		foreach(DataMapper::$common[$common_key] as $key => &$value)
		{
			$this->{$key} =& $value;
		}

		// Clear object properties to set at default values
		$this->clear();
		
		if( ! empty($id) && is_numeric($id))
		{
			$this->get_by_id(intval($id));
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Reloads in the configuration data for a model.  This is mainly
	 * used to handle language changes.  Only this instance and new instances
	 * will see the changes.
	 */
	public function reinitialize_model()
	{
		// this is to ensure that singular is only called once per model
		if(isset(DataMapper::$common[DMZ_CLASSNAMES_KEY][$this_class])) {
			$common_key = DataMapper::$common[DMZ_CLASSNAMES_KEY][$this_class];
		} else {
			DataMapper::$common[DMZ_CLASSNAMES_KEY][$this_class] = $common_key = singular($this_class);
		}
		unset(DataMapper::$common[$common_key]);
		$model = get_class($this);
		new $model(); // re-initialze
		
		// Load stored common model settings by reference
		foreach(DataMapper::$common[$common_key] as $key => &$value)
		{
			$this->{$key} =& $value;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Autoload
	 *
	 * Autoloads object classes that are used with DataMapper.
	 * This method will look in any model directories available to CI.
	 *
	 * Note:
	 * It is important that they are autoloaded as loading them manually with
	 * CodeIgniter's loader class will cause DataMapper's __get and __set functions
	 * to not function.
	 *
	 * @param	string $class Name of class to load.
	 */
	public static function autoload($class)
    {
        // Don't attempt to autoload CI_ or MY_ prefixed classes
        if (in_array(substr($class, 0, 3), array('CI_', 'EE_', 'MY_')))
        {
            return;
        }

        // Prepare class
        $class = strtolower($class);

		$CI =& get_instance();

        // Prepare path
        if (isset($CI->load->_ci_model_paths) && is_array($CI->load->_ci_model_paths))
        {
            // use CI loader's model path
            $paths = $CI->load->_ci_model_paths;
        }
        else
        {
            $paths = array(APPPATH);
        }

        foreach ($paths as $path)
        {
            // Prepare file
            $file = $path . 'models/' . $class . EXT;

            // Check if file exists, require_once if it does
            if (file_exists($file))
            {
                require_once($file);
                break;
            }
        }

        // if class not loaded, do a recursive search of model paths for the class
        if (! class_exists($class))
        {
			foreach($paths as $path)
			{
				$found = DataMapper::recursive_require_once($class, $path . 'models');
				if($found)
				{
					break;
				}
			}
        }
    }

	// --------------------------------------------------------------------

	/**
	 * Recursive Require Once
	 *
	 * Recursively searches the path for the class, require_once if found.
	 *
	 * @param	string $class Name of class to look for
	 * @param	string $path Current path to search
	 */
	protected static function recursive_require_once($class, $path)
	{
		$found = FALSE;
		$handle = opendir($path);
		if ($handle)
		{
			while (FALSE !== ($dir = readdir($handle)))
			{
				// If dir does not contain a dot
				if (strpos($dir, '.') === FALSE)
				{
					// Prepare recursive path
					$recursive_path = $path . '/' . $dir;

					// Prepare file
					$file = $recursive_path . '/' . $class . EXT;

					// Check if file exists, require_once if it does
					if (file_exists($file))
					{
						require_once($file);
						$found = TRUE;

						break;
					}
					else if (is_dir($recursive_path))
					{
						// Do a recursive search of the path for the class
						DataMapper::recursive_require_once($class, $recursive_path);
					}
				}
			}

			closedir($handle);
		}
		return $found;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Loads in any extensions used by this class or globally.
	 *
	 * @param	array $extensions List of extensions to add to.
	 * @param	array $name List of new extensions to load.
	 */
	protected static function _load_extensions(&$extensions, $names)
	{
		$CI =& get_instance();
		$class_prefixes = array(
			0 => 'DMZ_',
			1 => 'DataMapper_',
			2 => $CI->config->item('subclass_prefix'),
			3 => 'CI_'
		);
		foreach($names as $name => $options)
		{
			if( ! is_string($name))
			{
				$name = $options;
				$options = NULL;
			}
			// only load an extension if it wasn't already loaded in this context
			if(isset($extensions[$name]))
			{
				return;
			}
			
			if( ! isset($extensions['_methods']))
			{
				$extensions['_methods'] = array();
			}
			
			// determine the file name and class name
			if(strpos($name, '/') === FALSE)
			{
				$file = APPPATH . DataMapper::$config['extensions_path'] . '/' . $name . EXT;
				$ext = $name;
			}
			else
			{
				$file = APPPATH . $name . EXT;
				$ext = array_pop(explode('/', $name));
			}
			
			if(!file_exists($file))
			{
				show_error('DataMapper Error: loading extension ' . $name . ': File not found.');
			}
			
			// load class
			include_once($file);
			
			// Allow for DMZ_Extension, DataMapper_Extension, etc.
			foreach($class_prefixes as $index => $prefix)
			{
				if(class_exists($prefix.$ext))
				{
					if($index == 2) // "MY_"
					{
						// Load in the library this class is based on
						$CI->load->libary($ext);
					}
					$ext = $prefix.$ext;
					break;
				}
			}
			if(!class_exists($ext))
			{
				show_error("DataMapper Error: Unable to find a class for extension $name.");
			}
			// create class
			if(is_null($options))
			{
				$o = new $ext();
			}
			else
			{
				$o = new $ext($options);
			}
			$extensions[$name] = $o;
			
			// figure out which methods can be called on this class.
			$methods = get_class_methods($ext);
			foreach($methods as $m)
			{
				// do not load private methods or methods already loaded.
				if($m[0] !== '_' &&
						is_callable(array($o, $m)) &&
						! isset($extensions['_methods'][$m])
						) {
					// store this method.
					$extensions['_methods'][$m] = $name;
				}
			}
		}
	}
	
	// --------------------------------------------------------------------

	/**
	 * Loads the extensions that are local to this model.
	 * @param	string $common_key Shared key to save extenions to.
	 */
	private function _initiate_local_extensions($common_key)
	{
		if(!empty($this->extensions))
		{
			$extensions = $this->extensions;
			$this->extensions = array();
			DataMapper::_load_extensions($this->extensions, $extensions);
		}
		else
		{
			// ensure an empty array
			$this->extensions = array('_methods' => array());
		}
		// bind to the shared key, for dynamic loading
		DataMapper::$common[$common_key]['extensions'] =& $this->extensions;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Dynamically load an extension when needed.
	 * @param	object $name Name of the extension (or array of extensions).
	 * @param	array $options Options for the extension
	 * @param	boolean $local If TRUE, only loads the extension into this object
	 */
	public function load_extension($name, $options = NULL, $local = FALSE)
	{
		if( ! is_array($name))
		{
			if( ! is_null($options))
			{
				$name = array($name => $options);
			}
			else
			{
				$name = array($name);
			}
		}
		// called individually to ensure that the array is modified directly
		// (and not copied instead)
		if($local)
		{
			DataMapper::_load_extensions($this->extensions, $name);
		}
		else
		{
			DataMapper::_load_extensions(DataMapper::$global_extensions, $name);
		}
		
	}

	// --------------------------------------------------------------------
	
}