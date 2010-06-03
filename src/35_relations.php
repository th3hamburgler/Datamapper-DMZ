<?php class DataMapper {
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	 *                                                                   *
	 * Related methods                                                   *
	 *                                                                   *
	 * The following are methods used for managing related records.      *
	 *                                                                   *
	 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	// --------------------------------------------------------------------

	/**
	 * get_related_properties
	 *
	 * Located the relationship properties for a given field or model
	 * Can also optionally attempt to convert the $related_field to
	 * singular, and look up on that.  It will modify the $related_field if
	 * the conversion to singular returns a result.
	 * 
	 * $related_field can also be a deep relationship, such as
	 * 'post/editor/group', in which case the $related_field will be processed
	 * recursively, and the return value will be $user->has_NN['group']; 
	 *
	 * @ignore
	 * @param	mixed $related_field Name of related field or related object.
	 * @param	bool $try_singular If TRUE, automatically tries to look for a singular name if not found.
	 * @return	array Associative array of related properties.
	 */
	public function _get_related_properties(&$related_field, $try_singular = FALSE)
	{
		// Handle deep relationships
		if(strpos($related_field, '/') !== FALSE)
		{
			$rfs = explode('/', $related_field);
			$last = $this;
			$prop = NULL;
			foreach($rfs as &$rf)
			{
				$prop = $last->_get_related_properties($rf, $try_singular);
				if(is_null($prop))
				{
					break;
				}
				$last =& $last->_get_without_auto_populating($rf);
			}
			if( ! is_null($prop))
			{
				// update in case any items were converted to singular.
				$related_field = implode('/', $rfs);
			}
			return $prop;
		}
		else
		{
			if (isset($this->has_many[$related_field]))
			{
				return $this->has_many[$related_field];
			}
			else if (isset($this->has_one[$related_field]))
			{
				return $this->has_one[$related_field];
			}
			else
			{
				if($try_singular)
				{
					$rf = singular($related_field);
					$ret = $this->_get_related_properties($rf);
					if( is_null($ret))
					{
						show_error("Unable to relate {$this->model} with $related_field.");
					}
					else
					{
						$related_field = $rf;
						return $ret;
					}
				}
				else
				{
					// not related
					return NULL;
				}
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Add Related Table
	 *
	 * Adds the table of a related item, and joins it to this class.
	 * Returns the name of that table for further queries.
	 * 
	 * If $related_field is deep, then this adds all necessary relationships
	 * to the query.
	 *
	 * @ignore
	 * @param	mixed $object The object (or related field) to look up.
	 * @param	string $related_field  Related field name for object
	 * @param	string $id_only  Private, do not use.
	 * @param	object $db  Private, do not use.
	 * @param	array $query_related  Private, do not use.
	 * @param	string $name_prepend  Private, do not use.
	 * @param	string $this_table  Private, do not use.
	 * @return	string Name of the related table, or table.field if ID_Only
	 */
	public function _add_related_table($object, $related_field = '', $id_only = FALSE, $db = NULL, &$query_related = NULL, $name_prepend = '', $this_table = NULL)
	{
		if ( is_string($object))
		{
			// only a model was passed in, not an object
			$related_field = $object;
			$object = NULL;
		}
		else if (empty($related_field))
		{
			// model was not passed, so get the Object's native model
			$related_field = $object->model;
		}
		
		$related_field = strtolower($related_field);
		
		// Handle deep relationships
		if(strpos($related_field, '/') !== FALSE)
		{
			$rfs = explode('/', $related_field);
			$last = $this;
			$prepend = '';
			$object_as = NULL;
			foreach($rfs as $index => $rf)
			{
				// if this is the last item added, we can use the $id_only
				// shortcut to prevent unnecessarily adding the last table.
				$temp_id_only = $id_only;
				if($temp_id_only) {
					if($index < count($rfs)-1) {
						$temp_id_only = FALSE;
					}
				}
				$object_as = $last->_add_related_table($rf, '', $temp_id_only, $this->db, $this->_query_related, $prepend, $object_as);
				$prepend .= $rf . '_'; 
				$last =& $last->_get_without_auto_populating($rf);
			}
			return $object_as;
		}
		
		$related_properties = $this->_get_related_properties($related_field);
		$class = $related_properties['class'];
		$this_model = $related_properties['join_self_as'];
		$other_model = $related_properties['join_other_as'];
		
		if (empty($object))
		{
			// no object was passed in, so create one
			$object = new $class();
		}
		
		if(is_null($query_related))
		{
			$query_related =& $this->_query_related;
		}
		
		if(is_null($this_table))
		{
			$this_table = $this->table;
		}
		
		// Determine relationship table name
		$relationship_table = $this->_get_relationship_table($object, $related_field);
		
		// only add $related_field to the table name if the 'class' and 'related_field' aren't equal
		// and the related object is in a different table
		if ( ($class == $related_field) && ($this->table != $object->table) )
		{
			$object_as = $name_prepend . $object->table;
			$relationship_as = $name_prepend . $relationship_table;
		}
		else
		{
			$object_as = $name_prepend . $related_field . '_' . $object->table;
			$relationship_as = $name_prepend . $related_field . '_' . $relationship_table;
		}
		
		$other_column = $other_model . '_id';
		$this_column = $this_model . '_id' ;
		
		
		if(is_null($db)) {
			$db = $this->db;
		}

		// Force the selection of the current object's columns
		if (empty($db->ar_select))
		{
			$db->select($this->table . '.*');
		}
		
		// the extra in_array column check is for has_one self references
		if ($relationship_table == $this->table && in_array($other_column, $this->fields))
		{
			// has_one relationship without a join table
			if($id_only)
			{
				// nothing to join, just return the correct data
				$object_as = $this_table . '.' . $other_column;
			}
			else if ( ! in_array($object_as, $query_related))
			{
				$db->join($object->table . ' ' .$object_as, $object_as . '.id = ' . $this_table . '.' . $other_column, 'LEFT OUTER');
				$query_related[] = $object_as;
			}
		}
		// the extra in_array column check is for has_one self references
		else if ($relationship_table == $object->table && in_array($this_column, $object->fields))
		{
			// has_one relationship without a join table
			if ( ! in_array($object_as, $query_related))
			{
				$db->join($object->table . ' ' .$object_as, $this_table . '.id = ' . $object_as . '.' . $this_column, 'LEFT OUTER');
				$query_related[] = $object_as;
			}
			if($id_only)
			{
				// include the column name
				$object_as .= '.id';
			}
		}
		else
		{
			// has_one or has_many with a normal join table
			
			// Add join if not already included
			if ( ! in_array($relationship_as, $query_related))
			{
				$db->join($relationship_table . ' ' . $relationship_as, $this_table . '.id = ' . $relationship_as . '.' . $this_column, 'LEFT OUTER');
				
				if($this->_include_join_fields) {
					$fields = $db->field_data($relationship_table);
					foreach($fields as $key => $f)
					{
						if($f->name == 'id' || $f->name == $this_column || $f->name == $other_column)
						{
							unset($fields[$key]);
						}
					}
					// add all other fields
					$selection = '';
					foreach ($fields as $field)
					{
						$new_field = 'join_'.$field->name;
						if (!empty($selection))
						{
							$selection .= ', ';
						}
						$selection .= $relationship_as.'.'.$field->name.' AS '.$new_field;
					}
					$db->select($selection);
					
					// now reset the flag
					$this->_include_join_fields = FALSE;
				}
	
				$query_related[] = $relationship_as;
			}

			if($id_only)
			{
				// no need to add the whole table
				$object_as = $relationship_as . '.' . $other_column;
			}
			else if ( ! in_array($object_as, $query_related))
			{
				// Add join if not already included
				$db->join($object->table . ' ' . $object_as, $object_as . '.id = ' . $relationship_as . '.' . $other_column, 'LEFT OUTER');

				$query_related[] = $object_as;
			}
		}
		
		return $object_as;
	}

	// --------------------------------------------------------------------

	/**
	 * Related
	 *
	 * Sets the specified related query.
	 *
	 * @ignore
	 * @param	string $query Query String
	 * @param	array $arguments Arguments to process
	 * @param	mixed $extra Used to prevent escaping in special circumstances.
	 * @return	DataMapper Returns self for method chaining.
	 */
	private function _related($query, $arguments = array(), $extra = NULL)
	{
		if ( ! empty($query) && ! empty($arguments))
		{
			$object = $field = $value = NULL;

			$next_arg = 1;

			// Prepare model
			if (is_object($arguments[0]))
			{
				$object = $arguments[0];
				$related_field = $object->model; 

				// Prepare field and value
				$field = (isset($arguments[1])) ? $arguments[1] : 'id';
				$value = (isset($arguments[2])) ? $arguments[2] : $object->id;
				$next_arg = 3;
			}
			else
			{
				$related_field = $arguments[0];
				// the TRUE allows conversion to singular
				$related_properties = $this->_get_related_properties($related_field, TRUE);
				$class = $related_properties['class'];
				// enables where_related_{model}($object)
				if(isset($arguments[1]) && is_object($arguments[1]))
				{
					$object = $arguments[1];
					// Prepare field and value
					$field = (isset($arguments[2])) ? $arguments[2] : 'id';
					$value = (isset($arguments[3])) ? $arguments[3] : $object->id;
					$next_arg = 4;
				}
				else
				{
					$object = new $class();
					// Prepare field and value
					$field = (isset($arguments[1])) ? $arguments[1] : 'id';
					$value = (isset($arguments[2])) ? $arguments[2] : NULL;
					$next_arg = 3;
				}
			}

			if($field == 'id')
			{
				// special case to prevent joining unecessary tables
				$field = $this->_add_related_table($object, $related_field, TRUE);
			}
			else
			{
				// Determine relationship table name, and join the tables
				$object_table = $this->_add_related_table($object, $related_field);
				$field = $object_table . '.' . $field;
			}

			if(is_string($value) && strpos($value, '${parent}') !== FALSE) {
				$extra = FALSE;
			}

			// allow special arguments to be passed into query methods
			if(is_null($extra)) {
				if(isset($arguments[$next_arg])) {
					$extra = $arguments[$next_arg];
				}
			}

			// Add query clause
			if(is_null($extra))
			{
				$this->{$query}($field, $value);
			}
			else
			{
				$this->{$query}($field, $value, $extra);
			}
		}

		// For method chaining
		return $this;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Magic method to process a subquery for a related object.
	 * The format for this should be
	 *   $object->{where}_related_subquery($related_item, $related_field, $subquery)
	 * related_field is optional
	 *
	 * @ignore
	 * @param	string $query Query Method
	 * @param	object $args Arguments for the query
	 * @return	DataMapper Returns self for method chaining.
	 */
	private function _related_subquery($query, $args)
	{
		$rel_object = $args[0];
		$field = $value = NULL;
		if(isset($args[2])) {
			$field = $args[1];
			$value = $args[2];
		} else {
			$field = 'id';
			$value = $args[1];
		}
		if(is_object($value))
		{
			// see 25_activerecord.php
			$value = $this->_parse_subquery_object($value);
		}
		if(strpos($query, 'where_in') !== FALSE) {
			$query = str_replace('_in', '', $query);
			$field .= ' IN ';
		}
		return $this->_related($query, array($rel_object, $field, $value), FALSE);
	}

	// --------------------------------------------------------------------

	/**
	 * Is Related To
	 * If this object is related to the provided object, returns TRUE.
	 * Otherwise returns FALSE.
	 * Optionally can be provided a related field and ID.
	 *
	 * @param	mixed $related_field The related object or field name
	 * @param	int $id ID to compare to if $related_field is a string
	 * @return	bool TRUE or FALSE if this object is related to $related_field
	 */
	public function is_related_to($related_field, $id = NULL)
	{
		if(is_object($related_field))
		{
			$id = $related_field->id;
			$related_field = $related_field->model;
		}
		return ($this->{$related_field}->count(NULL, NULL, $id) > 0);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Include Related
	 *
	 * Joins specified values of a has_one object into the current query
	 * If $fields is NULL or '*', then all columns are joined (may require instantiation of the other object)
	 * If $fields is a single string, then just that column is joined.
	 * Otherwise, $fields should be an array of column names.
	 * 
	 * $append_name can be used to override the default name to append, or set it to FALSE to prevent appending.
	 *
	 * @param	mixed $related_field The related object or field name
	 * @param	array $fields The fields to join (NULL or '*' means all fields, or use a single field or array of fields)
	 * @param	bool $append_name The name to use for joining (with '_'), or FALSE to disable.
	 * @param	bool $instantiate If TRUE, the results are instantiated into objects
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function include_related($related_field, $fields = NULL, $append_name = TRUE, $instantiate = FALSE)
	{
		if (is_object($related_field))
		{
			$object = $related_field;
			$related_field = $object->model;
			$related_properties = $this->_get_related_properties($related_field);
		}
		else
		{
			// the TRUE allows conversion to singular
			$related_properties = $this->_get_related_properties($related_field, TRUE);
			$class = $related_properties['class'];
			$object = new $class();
		}
		
		if(is_null($fields) || $fields == '*')
		{
			$fields = $object->fields;
		}
		else if ( ! is_array($fields))
		{
			$fields = array((string)$fields);
		}
		
		$rfs = explode('/', $related_field);
		$last = $this;
		foreach($rfs as $rf)
		{
			if ( ! isset($last->has_one[$rf]) )
			{
				show_error("Invalid request to include_related: $rf is not a has_one relationship to {$last->model}.");
			}
			// prevent populating the related items.
			$last =& $last->_get_without_auto_populating($rf);
		}
		
		$table = $this->_add_related_table($object, $related_field);
		
		$append = '';
		if($append_name !== FALSE)
		{
			if($append_name === TRUE)
			{
				$append = str_replace('/', '_', $related_field);
			}
			else
			{
				$append = $append_name;
			}
			$append .= '_';
		}
		
		// now add fields
		$selection = '';
		$property_map = array();
		foreach ($fields as $field)
		{
			$new_field = $append . $field;
			// prevent collisions
			if(in_array($new_field, $this->fields)) {
				if($instantiate && $field == 'id' && $new_field != 'id') {
					$property_map[$new_field] = $field;
				}
				continue;
			}
			if (!empty($selection))
			{
				$selection .= ', ';
			}
			$selection .= $table.'.'.$field.' AS '.$new_field;
			if($instantiate) {
				$property_map[$new_field] = $field;
			}
		}
		if(empty($selection))
		{
			log_message('debug', "DataMapper Warning (include_related): No fields were selected for {$this->model} on $related_field.");
		}
		else
		{
			if($instantiate)
			{
				if(is_null($this->_instantiations))
				{
					$this->_instantiations = array();
				}
				$this->_instantiations[$related_field] = $property_map;
			}
			$this->db->select($selection);
		}
		
		// For method chaining
		return $this;
	}
	
	/**
	 * Legacy version of include_related
	 * DEPRECATED: Will be removed by 2.0
	 * @deprecated Please use include_related
	 */
	public function join_related($related_field, $fields = NULL, $append_name = TRUE)
	{
		return $this->include_related($related_field, $fields, $append_name);
	}

	// --------------------------------------------------------------------

	/**
	 * Includes the number of related items using a subquery.
	 * 
	 * Default alias is {$related_field}_count
	 * 
	 * @param	mixed $related_field Field to count
	 * @param	string $alias  Alternative alias.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function include_related_count($related_field, $alias = NULL)
	{
		if (is_object($related_field))
		{
			$object = $related_field;
			$related_field = $object->model;
			$related_properties = $this->_get_related_properties($related_field);
		}
		else
		{
			// the TRUE allows conversion to singular
			$related_properties = $this->_get_related_properties($related_field, TRUE);
			$class = $related_properties['class'];
			$object = new $class();
		}
		
		if(is_null($alias))
		{
			$alias = $related_field . '_count';
		}
		
		// Force the selection of the current object's columns
		if (empty($this->db->ar_select))
		{
			$this->db->select($this->table . '.*');
		}
		
		// now generate a subquery for counting the related objects
		$object->select_func('COUNT', '*', 'count');
		$this_rel = $related_properties['other_field'];
		$tablename = $object->_add_related_table($this, $this_rel);
		$object->where($tablename . '.id  = ', $this->db->_escape_identifiers('${parent}.id'), FALSE);
		$this->select_subquery($object, $alias);
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Relation
	 *
	 * Finds all related records of this objects current record.
	 *
	 * @ignore
	 * @param	mixed $related_field Related field or object
	 * @param	int $id ID of related field or object
	 * @return	bool Sucess or Failure
	 */
	private function _get_relation($related_field, $id)
	{
		// No related items
		if (empty($related_field) || empty($id))
		{
			// Reset query
			$this->db->_reset_select();

			return FALSE;
		}
		
		// To ensure result integrity, group all previous queries
		if( ! empty($this->db->ar_where))
		{
			array_unshift($this->db->ar_where, '( ');
			$this->db->ar_where[] = ' )';
		}
		
		// query all items related to the given model
		$this->where_related($related_field, 'id', $id);
				
		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Save Relation
	 *
	 * Saves the relation between this and the other object.
	 *
	 * @ignore
	 * @param	DataMapper DataMapper Object to related to this object
	 * @param	string Specific related field if necessary.
	 * @return	bool Success or Failure
	 */
	protected function _save_relation($object, $related_field = '')
	{
		if (empty($related_field))
		{
			$related_field = $object->model;
		}
		
		// the TRUE allows conversion to singular
		$related_properties = $this->_get_related_properties($related_field, TRUE);
		
		if ( ! empty($related_properties) && $this->exists() && $object->exists())
		{
			$this_model = $related_properties['join_self_as'];
			$other_model = $related_properties['join_other_as'];
			$other_field = $related_properties['other_field'];
			
			// Determine relationship table name
			$relationship_table = $this->_get_relationship_table($object, $related_field);

			if($relationship_table == $this->table &&
			 		// catch for self relationships.
					in_array($other_model . '_id', $this->fields))
			{
				$this->{$other_model . '_id'} = $object->id;
				$ret =  $this->save();
				// remove any one-to-one relationships with the other object
				$this->_remove_other_one_to_one($related_field, $object);
				return $ret;
			}
			else if($relationship_table == $object->table)
			{
				$object->{$this_model . '_id'} = $this->id;
				$ret = $object->save();
				// remove any one-to-one relationships with this object
				$object->_remove_other_one_to_one($other_field, $this);
				return $ret;
			}
			else
			{
				$data = array($this_model . '_id' => $this->id, $other_model . '_id' => $object->id);
	
				// Check if relation already exists
				$query = $this->db->get_where($relationship_table, $data, NULL, NULL);
	
				if ($query->num_rows() == 0)
				{
					// If this object has a "has many" relationship with the other object
					if (isset($this->has_many[$related_field]))
					{
						// If the other object has a "has one" relationship with this object
						if (isset($object->has_one[$other_field]))
						{
							// And it has an existing relation
							$query = $this->db->get_where($relationship_table, array($other_model . '_id' => $object->id), 1, 0);
	
							if ($query->num_rows() > 0)
							{
								// Find and update the other objects existing relation to relate with this object
								$this->db->where($other_model . '_id', $object->id);
								$this->db->update($relationship_table, $data);
							}
							else
							{
								// Add the relation since one doesn't exist
								$this->db->insert($relationship_table, $data);
							}
	
							return TRUE;
						}
						else if (isset($object->has_many[$other_field]))
						{
							// We can add the relation since this specific relation doesn't exist, and a "has many" to "has many" relationship exists between the objects
							$this->db->insert($relationship_table, $data);
	
							return TRUE;
						}
					}
					// If this object has a "has one" relationship with the other object
					else if (isset($this->has_one[$related_field]))
					{
						// And it has an existing relation
						$query = $this->db->get_where($relationship_table, array($this_model . '_id' => $this->id), 1, 0);
							
						if ($query->num_rows() > 0)
						{
							// Find and update the other objects existing relation to relate with this object
							$this->db->where($this_model . '_id', $this->id);
							$this->db->update($relationship_table, $data);
						}
						else
						{
							// Add the relation since one doesn't exist
							$this->db->insert($relationship_table, $data);
						}
	
						return TRUE;
					}
				}
				else
				{
					// Relationship already exists
					return TRUE;
				}
			}
		}
		else
		{
			if( ! $object->exists())
			{
				$msg = 'dm_save_rel_noobj';
			}
			else if( ! $this->exists())
			{
				$msg = 'dm_save_rel_nothis';
			}
			else
			{
				$msg = 'dm_save_rel_failed';
			}
			$msg = $this->lang->line($msg);
			$this->error_message($related_field, sprintf($msg, $related_field));
		}

		return FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Remove Other One-to-One
	 * Removes other relationships on a one-to-one ITFK relationship
	 * 
	 * @ignore
	 * @param string $rf Related field to look at.
	 * @param DataMapper $object Object to look at.
	 */
	private function _remove_other_one_to_one($rf, $object)
	{
		if( ! $object->exists())
		{
			return;
		}
		$related_properties = $this->_get_related_properties($rf, TRUE);
		if( ! array_key_exists($related_properties['other_field'], $object->has_one))
		{
			return;
		}
		// This should be a one-to-one relationship with an ITFK if we got this far.
		$other_column = $related_properties['join_other_as'] . '_id';
		$c = get_class($this);
		$update = new $c();
		
		$update->where($other_column, $object->id);
		if($this->exists())
		{
			$update->where('id <>', $this->id);
		}
		$update->update($other_column, NULL);
	}

	// --------------------------------------------------------------------

	/**
	 * Delete Relation
	 *
	 * Deletes the relation between this and the other object.
	 *
	 * @ignore
	 * @param	DataMapper $object Object to remove the relationship to.
	 * @param	string $related_field Optional specific related field
	 * @return	bool Success or Failure
	 */
	protected function _delete_relation($object, $related_field = '')
	{
		if (empty($related_field))
		{
			$related_field = $object->model;
		}
		
		// the TRUE allows conversion to singular
		$related_properties = $this->_get_related_properties($related_field, TRUE);
		
		if ( ! empty($related_properties) && ! empty($this->id) && ! empty($object->id))
		{
			$this_model = $related_properties['join_self_as'];
			$other_model = $related_properties['join_other_as'];
			
			// Determine relationship table name
			$relationship_table = $this->_get_relationship_table($object, $related_field);

			if ($relationship_table == $this->table &&
			 		// catch for self relationships.
					in_array($other_model . '_id', $this->fields))
			{
				$this->{$other_model . '_id'} = NULL;
				$this->save();
			}
			else if ($relationship_table == $object->table)
			{
				$object->{$this_model . '_id'} = NULL;
				$object->save();
			}
			else
			{
				$data = array($this_model . '_id' => $this->id, $other_model . '_id' => $object->id);

				// Delete relation
				$this->db->delete($relationship_table, $data);
			}

			// Clear related object so it is refreshed on next access
			unset($this->{$related_field});

			return TRUE;
		}

		return FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Relationship Table
	 *
	 * Determines the relationship table between this object and $object.
	 *
	 * @ignore
	 * @param	DataMapper $object Object that we are interested in.
	 * @param	string $related_field Optional specific related field.
	 * @return	string The name of the table this relationship is stored on.
	 */
	public function _get_relationship_table($object, $related_field = '')
	{
		$prefix = $object->prefix;
		$table = $object->table;
		
		if (empty($related_field))
		{
			$related_field = $object->model;
		}
		
		$related_properties = $this->_get_related_properties($related_field);
		$this_model = $related_properties['join_self_as'];
		$other_model = $related_properties['join_other_as'];
		$other_field = $related_properties['other_field'];
		
		if (isset($this->has_one[$related_field]))
		{
			// see if the relationship is in this table
			if (in_array($other_model . '_id', $this->fields))
			{
				return $this->table;
			}
		}
		
		if (isset($object->has_one[$other_field]))
		{
			// see if the relationship is in this table
			if (in_array($this_model . '_id', $object->fields))
			{
				return $object->table;
			}
		}

		$relationship_table = '';
		
 		// Check if self referencing
		if ($this->table == $table)
		{
			// use the model names from related_properties
			$p_this_model = plural($this_model);
			$p_other_model = plural($other_model);
			$relationship_table = ($p_this_model < $p_other_model) ? $p_this_model . '_' . $p_other_model : $p_other_model . '_' . $p_this_model;
		}
		else
		{
			$relationship_table = ($this->table < $table) ? $this->table . '_' . $table : $table . '_' . $this->table;
		}

		// Remove all occurances of the prefix from the relationship table
		$relationship_table = str_replace($prefix, '', str_replace($this->prefix, '', $relationship_table));

		// So we can prefix the beginning, using the join prefix instead, if it is set
		$relationship_table = (empty($this->join_prefix)) ? $this->prefix . $relationship_table : $this->join_prefix . $relationship_table;

		return $relationship_table;
	}

	// --------------------------------------------------------------------

	/**
	 * Count Related
	 *
	 * Returns the number of related items in the database and in the related object.
	 * Used by the _related_(required|min|max) validation rules.
	 *
	 * @ignore
	 * @param	string $related_field The related field.
	 * @param	mixed $object Object or array to include in the count.
	 * @return	int Number of related items.
	 */
	protected function _count_related($related_field, $object = '')
	{
		$count = 0;
		
		// lookup relationship info
		// the TRUE allows conversion to singular
		$rel_properties = $this->_get_related_properties($related_field, TRUE);
		$class = $rel_properties['class'];
		
		$ids = array();
		
		if ( ! empty($object))
		{
			$count = $this->_count_related_objects($related_field, $object, '', $ids);
			$ids = array_unique($ids);
		}

		if ( ! empty($related_field) && ! empty($this->id))
		{
			$one = isset($this->has_one[$related_field]);
			
			// don't bother looking up relationships if this is a $has_one and we already have one.
			if( (!$one) || empty($ids))
			{
				// Prepare model
				$object = new $class();
	
				// Store parent data
				$object->parent = array('model' => $rel_properties['other_field'], 'id' => $this->id);
				
				// pass in IDs to exclude from the count 
				
				$count += $object->count($ids);
			}
		}

		return $count;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Private recursive function to count the number of objects
	 * in a passed in array (or a single object)
	 *
	 * @ignore
	 * @param	string $compare related field (model) to compare to
	 * @param	mixed $object Object or array to count
	 * @param	string $related_field related field of $object
	 * @param	array $ids list of IDs we've already found.
	 * @return	int Number of items found.
	 */
	private function _count_related_objects($compare, $object, $related_field, &$ids)
	{
		$count = 0;
		if (is_array($object))
		{
			// loop through array to check for objects
			foreach ($object as $rel_field => $obj)
			{
				if ( ! is_string($rel_field))
				{
					// if this object doesn't have a related field, use the parent related field
					$rel_field = $related_field;
				}
				$count += $this->_count_related_objects($compare, $obj, $rel_field, $ids);
			}
		}
		else
		{
			// if this object doesn't have a related field, use the model
			if (empty($related_field))
			{
				$related_field = $object->model;
			}
			// if this object is the same relationship type, it counts
			if ($related_field == $compare && $object->exists())
			{
				$ids[] = $object->id;
				$count++;
			}
		}
		return $count;
	}

	// --------------------------------------------------------------------

	/**
	 * Include Join Fields
	 *
	 * If TRUE, the any extra fields on the join table will be included
	 *
	 * @param	bool $include If FALSE, turns back off the directive.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function include_join_fields($include = TRUE)
	{
		$this->_include_join_fields = $include;
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Set Join Field
	 *
	 * Sets the value on a join table based on the related field
	 * If $related_field is an array, then the array should be
	 * in the form $related_field => $object or array($object)
	 *
	 * @param	mixed $related_field An object or array.
	 * @param	mixed $field Field or array of fields to set.
	 * @param	mixed $value Value for a single field to set.
	 * @param	mixed $object Private for recursion, do not use.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function set_join_field($related_field, $field, $value = NULL, $object = NULL)
	{
		$related_ids = array();
		
		if (is_array($related_field))
		{
			// recursively call this on the array passed in.
			foreach ($related_field as $key => $object)
			{
				$this->set_join_field($key, $field, $value, $object);
			}
			return;
		}
		else if (is_object($related_field))
		{
			$object = $related_field;
			$related_field = $object->model; 
			$related_ids[] = $object->id;
			$related_properties = $this->_get_related_properties($related_field);
		}
		else
		{
			// the TRUE allows conversion to singular
			$related_properties = $this->_get_related_properties($related_field, TRUE);
			if (is_null($object))
			{
				$class = $related_properties['class'];
				$object = new $class();
			}
		}
		
		// Determine relationship table name
		$relationship_table = $this->_get_relationship_table($object, $related_field);
		
		if (empty($object))
		{
			// no object was passed in, so create one
			$class = $related_properties['class'];
			$object = new $class();
		}
		
		$this_model = $related_properties['join_self_as'];
		$other_model = $related_properties['join_other_as'];
		
		if (! is_array($field))
		{
			$field = array( $field => $value );
		}
		
		if ( ! is_array($object))
		{
			$object = array($object);
		}
		
		if (empty($object))
		{
			$this->db->where($this_model . '_id', $this->id);
			$this->db->update($relationship_table, $field);
		}
		else
		{
			foreach ($object as $obj)
			{
				$this->db->where($this_model . '_id', $this->id);
				$this->db->where($other_model . '_id', $obj->id);
				$this->db->update($relationship_table, $field);
			}
		}
		
		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Join Field
	 *
	 * Adds a query of a join table's extra field
	 * Accessed via __call
	 * 
	 * @ignore
	 * @param	string $query Query method.
	 * @param	array $arguments Arguments for query.
	 * @return	DataMapper Returns self for method chaining.
	 */
	private function _join_field($query, $arguments)
	{
		if ( ! empty($query) && count($arguments) >= 3)
		{
			$object = $field = $value = NULL;

			// Prepare model
			if (is_object($arguments[0]))
			{
				$object = $arguments[0];
				$related_field = $object->model; 
			}
			else
			{
				$related_field = $arguments[0];
				// the TRUE allows conversion to singular
				$related_properties = $this->_get_related_properties($related_field, TRUE);
				$class = $related_properties['class'];
				$object = new $class();
			}
			

			// Prepare field and value
			$field = $arguments[1];
			$value = $arguments[2];

			// Determine relationship table name, and join the tables
			$rel_table = $this->_get_relationship_table($object, $related_field);

			// Add query clause
			$extra = NULL;
			if(count($arguments) > 3) {
				$extra = $arguments[3];
			}
			if(is_null($extra)) {
				$this->{$query}($rel_table . '.' . $field, $value);
			} else {
				$this->{$query}($rel_table . '.' . $field, $value, $extra);
			}
		}

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

}
