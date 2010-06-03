<?php class DataMapper {
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	 *                                                                   *
	 * Main methods                                                      *
	 *                                                                   *
	 * The following are methods that form the main                      *
	 * functionality of DataMapper.                                      *
	 *                                                                   *
	 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */


	// --------------------------------------------------------------------

	/**
	 * Get
	 *
	 * Get objects from the database.
	 *
	 * @param	integer|NULL $limit Limit the number of results.
	 * @param	integer|NULL $offset Offset the results when limiting.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function get($limit = NULL, $offset = NULL)
	{
		// Check if this is a related object and if so, perform a related get
		if (! $this->_handle_related())
		{
			// invalid get request, return this for chaining.
			return $this;
		} // Else fall through to a normal get
		
		$query = FALSE;

		// Check if object has been validated (skipped for related items)
		if ($this->_validated && empty($this->parent))
		{
			// Reset validated
			$this->_validated = FALSE;

			// Use this objects properties
			$data = $this->_to_array(TRUE);

			if ( ! empty($data))
			{
				// Clear this object to make way for new data
				$this->clear();
				
				// Set up default order by (if available)
				$this->_handle_default_order_by();

				// Get by objects properties
				$query = $this->db->get_where($this->table, $data, $limit, $offset);
			} // FIXME: notify user if nothing was set?
		}
		else
		{
			// Clear this object to make way for new data
			$this->clear();
				
			// Set up default order by (if available)
			$this->_handle_default_order_by();

			// Get by built up query
			$query = $this->db->get($this->table, $limit, $offset);
		}
		
		// Convert the query result into DataMapper objects
		if($query)
		{
			$this->_process_query($query);
		}

		// For method chaining
		return $this;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Returns the SQL string of the current query (SELECTs ONLY).
	 * NOTE: This also _clears_ the current query info.
	 * 
	 * This can be used to generate subqueries. 
	 *
	 * @param	integer|NULL $limit Limit the number of results.
	 * @param	integer|NULL $offset Offset the results when limiting.
	 * @return	string SQL as a string.
	 */
	public function get_sql($limit = NULL, $offset = NULL, $handle_related = FALSE)
	{
		if($handle_related) {
			$this->_handle_related();
		}

		$this->db->_track_aliases($this->table);
		$this->db->from($this->table);

		$this->_handle_default_order_by();
		
		if ( ! is_null($limit))
		{
			$this->limit($limit, $offset);
		}
			
		$sql = $this->db->_compile_select();
		$this->_clear_after_query();
		return $sql;
	}

	// --------------------------------------------------------------------

	/**
	 * Runs the query, but returns the raw CodeIgniter results
	 * NOTE: This also _clears_ the current query info.
	 *
	 * @param	integer|NULL $limit Limit the number of results.
	 * @param	integer|NULL $offset Offset the results when limiting.
	 * @return	CI_DB_result Result Object
	 */
	public function get_raw($limit = NULL, $offset = NULL, $handle_related = TRUE)
	{
		if($handle_related) {
			$this->_handle_related();
		}

		$this->_handle_default_order_by();

		$query = $this->db->get($this->table, $limit, $offset);
		$this->_clear_after_query();
		return $query;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Returns a streamable result set for large queries.
	 * Usage:
	 * $rs = $object->get_iterated();
	 * $size = $rs->count;
	 * foreach($rs as $o) {
	 *     // handle $o
	 * }
	 * $rs can be looped through more than once.
	 *
	 * @param	integer|NULL $limit Limit the number of results.
	 * @param	integer|NULL $offset Offset the results when limiting.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function get_iterated($limit = NULL, $offset = NULL)
	{
		// clone $this, so we keep track of instantiations, etc.
		// because these are cleared after the call to get_raw
		$object = $this->get_clone();
		// need to clear query from the clone
		$object->db->_reset_select();
		// Clear the query related list from the clone
		$object->_query_related = array();

		// Build iterator
		$this->_dm_dataset_iterator = new DM_DatasetIterator($object, $this->get_raw($limit, $offset, TRUE));
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Convenience method that runs a query based on pages.
	 * This object will have two new values, $query_total_pages and
	 * $query_total_rows, which can be used to determine how many pages and
	 * how many rows are available in total, respectively.
	 *
	 * @param	int $page Page (1-based) to start on, or row (0-based) to start on
	 * @param	int $page_size Number of rows in a page
	 * @param	bool $page_num_by_rows When TRUE, $page is the starting row, not the starting page
	 * @param	bool $iterated Internal Use Only
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function get_paged($page = 1, $page_size = 50, $page_num_by_rows = FALSE, $info_object = 'paged', $iterated = FALSE)
	{
		// first, duplicate this query, so we have a copy for the query
		$count_query = $this->get_clone(TRUE);

		if($page_num_by_rows)
		{
			$page = 1 + floor(intval($page) / $page_size);
		}

		// never less than 1
		$page = max(1, intval($page));
		$offset = $page_size * ($page - 1);
		
		// for performance, we clear out the select AND the order by statements,
		// since they aren't necessary and might slow down the query.
		$count_query->db->ar_select = NULL;
		$count_query->db->ar_orderby = NULL;
		$total = $count_query->db->ar_distinct ? $count_query->count_distinct() : $count_query->count();
		
		// common vars
		$last_row = $page_size * floor($total / $page_size);
		$total_pages = ceil($total / $page_size);

		if($offset >= $last_row)
		{
			// too far!
			$offset = $last_row;
			$page = $total_pages;
		}

		// now query this object
		if($iterated)
		{
			$this->get_iterated($page_size, $offset);
		}
		else
		{
			$this->get($page_size, $offset);
		}

		$this->{$info_object} = new stdClass();

		$this->{$info_object}->page_size = $page_size;
		$this->{$info_object}->items_on_page = $this->result_count();
		$this->{$info_object}->current_page = $page;
		$this->{$info_object}->current_row = $offset;
		$this->{$info_object}->total_rows = $total;
		$this->{$info_object}->last_row = $last_row;
		$this->{$info_object}->total_pages = $total_pages;
		$this->{$info_object}->has_previous = $offset > 0;
		$this->{$info_object}->previous_page = max(1, $page-1);
		$this->{$info_object}->previous_row = max(0, $offset-$page_size);
		$this->{$info_object}->has_next = $page < $total_pages;
		$this->{$info_object}->next_page = min($total_pages, $page+1);
		$this->{$info_object}->next_row = min($last_row, $offset+$page_size);

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Runs get_paged, but as an Iterable.
	 *
	 * @see get_paged
	 * @param	int $page Page (1-based) to start on, or row (0-based) to start on
	 * @param	int $page_size Number of rows in a page
	 * @param	bool $page_num_by_rows When TRUE, $page is the starting row, not the starting page
	 * @param	bool $iterated Internal Use Only
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function get_paged_iterated($page = 1, $page_size = 50, $page_num_by_rows = FALSE, $info_object = 'paged')
	{
		return $this->get_paged($page, $page_size, $page_num_by_rows, $info_object, TRUE);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Forces this object to be INSERTed, even if it has an ID.
	 * 
	 * @param	mixed $object  See save.
	 * @param	string $related_field See save.
	 * @return	bool Result of the save.
	 */
	public function save_as_new($object = '', $related_field = '')
	{
		$this->_force_save_as_new = TRUE;
		return $this->save($object, $related_field);
	}

	// --------------------------------------------------------------------

	/**
	 * Save
	 *
	 * Saves the current record, if it validates.
	 * If object is supplied, saves relations between this object and the supplied object(s).
	 *
	 * @param	mixed $object Optional object to save or array of objects to save.
	 * @param	string $related_field Optional string to save the object as a specific relationship.
	 * @return	bool Success or Failure of the validation and save.
	 */
	public function save($object = '', $related_field = '')
	{
		// Temporarily store the success/failure
		$result = array();

		// Validate this objects properties
		$this->validate($object, $related_field);

		// If validation passed
		if ($this->valid)
		{
			
			// Begin auto transaction
			$this->_auto_trans_begin();
			
			$trans_complete_label = array();
			
			// Get current timestamp
			$timestamp = $this->_get_generated_timestamp();

			// Check if object has a 'created' field, and it is not already set
			if (in_array($this->created_field, $this->fields) && empty($this->{$this->created_field}))
			{
				$this->{$this->created_field} = $timestamp;
			}

			// Check if object has an 'updated' field
			if (in_array($this->updated_field, $this->fields))
			{
				// Update updated datetime
				$this->{$this->updated_field} = $timestamp;
			}
			
			// SmartSave: if there are objects being saved, and they are stored
			// as in-table foreign keys, we can save them at this step.
			if( ! empty($object))
			{
				if( ! is_array($object))
				{
					$object = array($object);
				}
				$this->_save_itfk($object, $related_field);
			}

			// Convert this object to array
			$data = $this->_to_array();

			if ( ! empty($data))
			{
				if ( ! $this->_force_save_as_new && ! empty($data['id']))
				{
					// Prepare data to send only changed fields
					foreach ($data as $field => $value)
					{
						// Unset field from data if it hasn't been changed
						if ($this->{$field} === $this->stored->{$field})
						{
							unset($data[$field]);
						}
					}

					// Check if only the 'updated' field has changed, and if so, revert it
					if (count($data) == 1 && isset($data[$this->updated_field]))
					{
						// Revert updated
						$this->{$this->updated_field} = $this->stored->{$this->updated_field}; 

						// Unset it
						unset($data[$this->updated_field]);
					}

					// Only go ahead with save if there is still data
					if ( ! empty($data))
					{
						// Update existing record
						$this->db->where('id', $this->id);
						$this->db->update($this->table, $data);
						
						$trans_complete_label[] = 'update';
					}

					// Reset validated
					$this->_validated = FALSE;

					$result[] = TRUE;
				}
				else
				{
					// Prepare data to send only populated fields
					foreach ($data as $field => $value)
					{
						// Unset field from data
						if ( ! isset($value))
						{
							unset($data[$field]);
						}
					}

					// Create new record
					$this->db->insert($this->table, $data);

					if( ! $this->_force_save_as_new)
					{
						// Assign new ID
						$this->id = $this->db->insert_id();
					}

					$trans_complete_label[] = 'insert';

					// Reset validated
					$this->_validated = FALSE;

					$result[] = TRUE;
				}
			}

			$this->_refresh_stored_values();

			// Check if a relationship is being saved
			if ( ! empty($object))
			{
				// save recursively
				$this->_save_related_recursive($object, $related_field);
				
				$trans_complete_label[] = 'relationships';
			}
			
			if(!empty($trans_complete_label))
			{
				$trans_complete_label = 'save (' . implode(', ', $trans_complete_label) . ')';
			}
			else
			{
				$trans_complete_label = '-nothing done-';
			}
			
			$this->_auto_trans_complete($trans_complete_label);
			
		}
		
		$this->force_save_as_new = FALSE;

		// If no failure was recorded, return TRUE
		return ( ! empty($result) && ! in_array(FALSE, $result));
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Recursively saves arrays of objects if they are In-Table Foreign Keys.
	 * @ignore
	 * @param	object $objects Objects to save.  This array may be modified.
	 * @param	object $related_field Related Field name (empty is OK)
	 */
	protected function _save_itfk( &$objects, $related_field)
	{
		foreach($objects as $index => $o)
		{
			if(is_int($index))
			{
				$rf = $related_field;
			}
			else
			{
				$rf = $index;
			}
			if(is_array($o))
			{
				$this->_save_itfk($o, $rf);
				if(empty($o))
				{
					unset($objects[$index]);
				}
			}
			else
			{
				if(empty($rf)) {
					$rf = $o->model;
				}
				$related_properties = $this->_get_related_properties($rf);
				$other_column = $related_properties['join_other_as'] . '_id';
				if(isset($this->has_one[$rf]) && in_array($other_column, $this->fields))
				{
					if($this->{$other_column} != $o->id)
					{
						// ITFK: store on the table
						$this->{$other_column} = $o->id;
						
						// unset, so that it doesn't get re-saved later.
						unset($objects[$index]);
						
						// Remove reverse relationships for one-to-ones
						$this->_remove_other_one_to_one($rf, $o);
					}
				}
			}
		}
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Recursively saves arrays of objects.
	 *
	 * @ignore
	 * @param	object $object Array of objects to save, or single object
	 * @param	object $related_field Default related field name (empty is OK)
	 * @return	bool TRUE or FALSE if an error occurred.
	 */
	protected function _save_related_recursive($object, $related_field)
	{
		if(is_array($object))
		{
			$success = TRUE;
			foreach($object as $rk => $o)
			{
				if(is_int($rk))
				{
					$rk = $related_field;
				}
				$rec_success = $this->_save_related_recursive($o, $rk);
				$success = $success && $rec_success;
			}
			return $success;
		}
		else
		{
			return $this->_save_relation($object, $related_field);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * _Save
	 *
	 * Used by __call to process related saves.
	 *
	 * @ignore
	 * @param	mixed $related_field
	 * @param	array $arguments
	 * @return	bool
	 */
	private function _save($related_field, $arguments)
	{
		return $this->save($arguments[0], $related_field);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Update
	 * 
	 * Allows updating of more than one row at once.
	 * 
	 * @param	object $field A field to update, or an array of fields => values
	 * @param	object $value The new value
	 * @param	object $escape_values  If false, don't escape the values
	 * @return	bool TRUE or FALSE on success or failure
	 */
	public function update($field, $value = NULL, $escape_values = TRUE)
	{
		if( ! is_array($field))
		{
			$field = array($field => $value);
		}
		else if($value === FALSE)
		{
				$escape_values = FALSE;
		}
		if(empty($field))
		{
			show_error("Nothing was provided to update.");
		}

		// Check if object has an 'updated' field
		if (in_array($this->updated_field, $this->fields))
		{
			$timestamp = $this->_get_generated_timestamp();
			if( ! $escape_values)
			{
				$timestamp = $this->db->escape($timestamp);
			}
			// Update updated datetime
			$field[$this->updated_field] = $timestamp;
		}

		foreach($field as $k => $v)
		{
			if( ! $escape_values)
			{
				// attempt to add the table name
				$v = $this->add_table_name($v);
			}
			$this->db->set($k, $v, $escape_values);
		}
		return $this->db->update($this->table);
	}

	// --------------------------------------------------------------------

	/**
	 * Update All
	 * 
	 * Updates all items that are in the all array.
	 * 
	 * @param	object $field A field to update, or an array of fields => values
	 * @param	object $value The new value
	 * @param	object $escape_values  If false, don't escape the values
	 * @return	bool TRUE or FALSE on success or failure
	 */
	public function update_all($field, $value = NULL, $escape_values = TRUE)
	{
		$ids = array();
		foreach($this->all as $object)
		{
			$ids[] = $object->id;
		}
		if(empty($ids))
		{
			return FALSE;
		}
		
		$this->where_in('id', $ids);
		return $this->update($field, $value, $escape_values);
	}

	// --------------------------------------------------------------------

	/**
	 * Gets a timestamp to use when saving.
	 * @return mixed
	 */
	private function _get_generated_timestamp()
	{
		// Get current timestamp
		$timestamp = ($this->local_time) ? date($this->timestamp_format) : gmdate($this->timestamp_format);

		// Check if unix timestamp
		return ($this->unix_timestamp) ? strtotime($timestamp) : $timestamp;
	}

	// --------------------------------------------------------------------

	/**
	 * Delete
	 *
	 * Deletes the current record.
	 * If object is supplied, deletes relations between this object and the supplied object(s).
	 *
	 * @param	mixed $object If specified, delete the relationship to the object or array of objects.
	 * @param	string $related_field Can be used to specify which relationship to delete.
	 * @return	bool Success or Failure of the delete.
	 */
	public function delete($object = '', $related_field = '')
	{
		if (empty($object) && ! is_array($object))
		{
			if ( ! empty($this->id))
			{
				// Begin auto transaction
				$this->_auto_trans_begin();

				// Delete this object
				$this->db->where('id', $this->id);
				$this->db->delete($this->table);

				// Delete all "has many" and "has one" relations for this object
				foreach (array('has_many', 'has_one') as $type) {
					foreach ($this->{$type} as $model => $properties)
					{
						// Prepare model
						$class = $properties['class'];
						$object = new $class();
						
						$this_model = $properties['join_self_as'];
						$other_model = $properties['join_other_as'];
	
						// Determine relationship table name
						$relationship_table = $this->_get_relationship_table($object, $model);
						
						// We have to just set NULL for in-table foreign keys that
						// are pointing at this object 
						if($relationship_table == $object->table  && // ITFK
								 // NOT ITFKs that point at the other object
								 ! ($object->table == $this->table && // self-referencing has_one join
								 	in_array($other_model . '_id', $this->fields)) // where the ITFK is for the other object
								)
						{
							$data = array($this_model . '_id' => NULL);
							
							// Update table to remove relationships
							$this->db->where($this_model . '_id', $this->id);
							$this->db->update($object->table, $data);
						}
						else if ($relationship_table != $this->table)
						{
	
							$data = array($this_model . '_id' => $this->id);
		
							// Delete relation
							$this->db->delete($relationship_table, $data);
						}
						// Else, no reason to delete the relationships on this table
					}
				}

				// Complete auto transaction
				$this->_auto_trans_complete('delete');

				// Clear this object
				$this->clear();

				return TRUE;
			}
		}
		else if (is_array($object))
		{
			// Begin auto transaction
			$this->_auto_trans_begin();

			// Temporarily store the success/failure
			$result = array();

			foreach ($object as $rel_field => $obj)
			{
				if (is_int($rel_field))
				{
					$rel_field = $related_field;
				}
				if (is_array($obj))
				{
					foreach ($obj as $r_f => $o)
					{
						if (is_int($r_f))
						{
							$r_f = $rel_field;
						}
						$result[] = $this->_delete_relation($o, $r_f);
					}
				}
				else
				{
					$result[] = $this->_delete_relation($obj, $rel_field);
				}
			}

			// Complete auto transaction
			$this->_auto_trans_complete('delete (relationship)');

			// If no failure was recorded, return TRUE
			if ( ! in_array(FALSE, $result))
			{
				return TRUE;
			}
		}
		else
		{
			// Begin auto transaction
			$this->_auto_trans_begin();

			// Temporarily store the success/failure
			$result = $this->_delete_relation($object, $related_field);

			// Complete auto transaction
			$this->_auto_trans_complete('delete (relationship)');

			return $result;
		}

		return FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * _Delete
	 *
	 * Used by __call to process related deletes.
	 *
	 * @ignore
	 * @param	string $related_field
	 * @param	array $arguments
	 * @return	bool
	 */
	private function _delete($related_field, $arguments)
	{
		return $this->delete($arguments[0], $related_field);
	}

	// --------------------------------------------------------------------

	/**
	 * Delete All
	 *
	 * Deletes all records in this objects all list.
	 *
	 * @return	bool Success or Failure of the delete
	 */
	public function delete_all()
	{
		$success = TRUE;
		foreach($this as $item)
		{
			if ( ! empty($item->id))
			{
				$success_temp = $item->delete();
				$success = $success && $success_temp;
			}
		}
		$this->clear();
		return $success;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Refresh All
	 *
	 * Removes any empty objects in this objects all list.
	 * Only needs to be used if you are looping through the all list
	 * a second time and you have deleted a record the first time through.
	 *
	 * @return	bool FALSE if the $all array was already empty.
	 */
	public function refresh_all()
	{
		if ( ! empty($this->all))
		{
			$all = array();

			foreach ($this->all as $item)
			{
				if ( ! empty($item->id))
				{
					$all[] = $item;
				}
			}

			$this->all = $all;

			return TRUE;
		}

		return FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Validate
	 *
	 * Validates the value of each property against the assigned validation rules.
	 *
	 * @param	mixed $object Objects included with the validation [from save()].
	 * @param	string $related_field See save.
	 * @return	DataMapper Returns $this for method chanining.
	 */
	public function validate($object = '', $related_field = '')
	{
		// Return if validation has already been run
		if ($this->_validated)
		{
			// For method chaining
			return $this;
		}

		// Set validated as having been run
		$this->_validated = TRUE;

		// Clear errors
		$this->error = new DM_Error_Object();

		// Loop through each property to be validated
		foreach ($this->validation as $field => $validation)
		{
			if(empty($validation['rules']))
			{
				continue;
			}
			
			// Get validation settings
			$rules = $validation['rules'];

			// Will validate differently if this is for a related item
			$related = (isset($this->has_many[$field]) || isset($this->has_one[$field]));

			// Check if property has changed since validate last ran
			if ($related || ! isset($this->stored->{$field}) || $this->{$field} !== $this->stored->{$field})
			{
				// Only validate if field is related or required or has a value
				if ( ! $related && ! in_array('required', $rules) && ! in_array('always_validate', $rules))
				{
					if ( ! isset($this->{$field}) || $this->{$field} === '')
					{
						continue;
					}
				}
				
				$label = ( ! empty($validation['label'])) ? $validation['label'] : $field;

				// Loop through each rule to validate this property against
				foreach ($rules as $rule => $param)
				{
					// Check for parameter
					if (is_numeric($rule))
					{
						$rule = $param;
						$param = '';
					}

					// Clear result
					$result = '';
					// Clear message
					$line = FALSE;

					// Check rule exists
					if ($related)
					{
						// Prepare rule to use different language file lines
						$rule = 'related_' . $rule;
						
						$arg = $object;
						if( ! empty($related_field)) {
							$arg = array($related_field => $object);
						}

						if (method_exists($this, '_' . $rule))
						{
							// Run related rule from DataMapper or the class extending DataMapper
							$line = $result = $this->{'_' . $rule}($arg, $field, $param);
						}
						else if($this->_extension_method_exists('rule_' . $rule))
						{
							$line = $result = $this->{'rule_' . $rule}($arg, $field, $param);
						}
					}
					else if (method_exists($this, '_' . $rule))
					{
						// Run rule from DataMapper or the class extending DataMapper
						$line = $result = $this->{'_' . $rule}($field, $param);
					}
					else if($this->_extension_method_exists('rule_' . $rule))
					{
						// Run an extension-based rule.
						$line = $result = $this->{'rule_' . $rule}($field, $param);
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

					// Add an error message if the rule returned FALSE
					if (is_string($line) || $result === FALSE)
					{
						if(!is_string($line))
						{
							if (FALSE === ($line = $this->lang->line($rule)))
							{
								// Get corresponding error from language file
								$line = 'Unable to access an error message corresponding to your rule name: '.$rule.'.';
							}
						}

						// Check if param is an array
						if (is_array($param))
						{
							// Convert into a string so it can be used in the error message
							$param = implode(', ', $param);

							// Replace last ", " with " or "
							if (FALSE !== ($pos = strrpos($param, ', ')))
							{
								$param = substr_replace($param, ' or ', $pos, 2);
							}
						}

						// Check if param is a validation field
						if (isset($this->validation[$param]))
						{
							// Change it to the label value
							$param = $this->validation[$param]['label'];
						}

						// Add error message
						$this->error_message($field, sprintf($line, $label, $param));
						
						// Escape to prevent further error checks
						break;
					}
				}
			}
		}

		// Set whether validation passed
		$this->valid = empty($this->error->all);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Skips validation for the next call to save.
	 * Note that this also prevents the validation routine from running until the next get.
	 * 
	 * @param	object $skip If FALSE, re-enables validation.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function skip_validation($skip = TRUE)
	{
		$this->_validated = $skip;
		$this->valid = $skip;
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Clear
	 *
	 * Clears the current object.
	 */
	public function clear()
	{
		// Clear the all list
		$this->all = array();

		// Clear errors
		$this->error = new DM_Error_Object();

		// Clear this objects properties and set blank error messages in case they are accessed
		foreach ($this->fields as $field)
		{
			$this->{$field} = NULL;
		}

		// Clear this objects "has many" related objects
		foreach ($this->has_many as $related => $properties)
		{
			unset($this->{$related});
		}

		// Clear this objects "has one" related objects
		foreach ($this->has_one as $related => $properties)
		{
			unset($this->{$related});
		}

		// Clear the query related list
		$this->_query_related = array();

		// Clear and refresh stored values
		$this->stored = new stdClass();

		// Clear the saved iterator
		unset($this->_dm_dataset_iterator);

		$this->_refresh_stored_values();
	}

	// --------------------------------------------------------------------

	/**
	 * Clears the db object after processing a query, or returning the
	 * SQL for a query.
	 *
	 * @ignore
	 */
	protected function _clear_after_query()
	{
		// clear the query as if it was run
		$this->db->_reset_select();

		// in case some include_related instantiations were set up, clear them
		$this->_instantiations = NULL;

		// Clear the query related list (Thanks to TheJim)
		$this->_query_related = array();

		// Clear the saved iterator
		unset($this->_dm_dataset_iterator);
	}

	// --------------------------------------------------------------------

	/**
	 * Count
	 *
	 * Returns the total count of the object records from the database.
	 * If on a related object, returns the total count of related objects records.
	 *
	 * @param	array $exclude_ids A list of ids to exlcude from the count
	 * @return	int Number of rows in query.
	 */
	public function count($exclude_ids = NULL, $column = NULL, $related_id = NULL)
	{
		// Check if related object
		if ( ! empty($this->parent))
		{
			// Prepare model
			$related_field = $this->parent['model'];
			$related_properties = $this->_get_related_properties($related_field);
			$class = $related_properties['class'];
			$other_model = $related_properties['join_other_as'];
			$this_model = $related_properties['join_self_as'];
			$object = new $class();

			// To ensure result integrity, group all previous queries
			if( ! empty($this->db->ar_where))
			{
				array_unshift($this->db->ar_where, '( ');
				$this->db->ar_where[] = ' )';
			}

			// Determine relationship table name
			$relationship_table = $this->_get_relationship_table($object, $related_field);
			
			// We have to query special for in-table foreign keys that
			// are pointing at this object 
			if($relationship_table == $object->table  && // ITFK
					 // NOT ITFKs that point at the other object
					 ! ($object->table == $this->table && // self-referencing has_one join
					 	in_array($other_model . '_id', $this->fields)) // where the ITFK is for the other object
					)
			{
				// ITFK on the other object's table
				$this->db->where('id', $this->parent['id'])->where($this_model . '_id IS NOT NULL');
			}
			else
			{
				// All other cases
				$this->db->where($other_model . '_id', $this->parent['id']);
			}
			if(!empty($exclude_ids))
			{
				$this->db->where_not_in($this_model . '_id', $exclude_ids);
			}
			if($column == 'id')
			{
				$column = $relationship_table . '.' . $this_model . '_id';
			}
			if(!empty($related_id))
			{
				$this->db->where($this_model . '_id', $related_id);
			}
			$this->db->from($relationship_table);
		}
		else
		{
			$this->db->from($this->table);
			if(!empty($exclude_ids))
			{
				$this->db->where_not_in('id', $exclude_ids);
			}
			if(!empty($related_id))
			{
				$this->db->where('id', $related_id);
			}
			$column = $this->add_table_name($column);
		}

		// Manually overridden to allow for COUNT(DISTINCT COLUMN)
		$select = $this->db->_count_string;
		if(!empty($column))
		{
			// COUNT DISTINCT
			$select = 'SELECT COUNT(DISTINCT ' . $this->db->_protect_identifiers($column) . ') AS ';
		}
		$sql = $this->db->_compile_select($select . $this->db->_protect_identifiers('numrows'));

		$query = $this->db->query($sql);
		$this->db->_reset_select();

		if ($query->num_rows() == 0)
		{
			return 0;
		}

		$row = $query->row();
		return intval($row->numrows);
	}

	// --------------------------------------------------------------------

	/**
	 * Count Distinct
	 *
	 * Returns the total count of distinct object records from the database.
	 * If on a related object, returns the total count of related objects records.
	 *
	 * @param	array $exclude_ids A list of ids to exlcude from the count
	 * @param	string $column If provided, use this column for the DISTINCT instead of 'id'
	 * @return	int Number of rows in query.
	 */
	public function count_distinct($exclude_ids = NULL, $column = 'id')
	{
		return $this->count($exclude_ids, $column);
	}

	// --------------------------------------------------------------------

	/**
	 * Convenience method to return the number of items from
	 * the last call to get.
	 *
	 * @return	int
	 */
	public function result_count() {
		if(isset($this->_dm_dataset_iterator)) {
			return $this->_dm_dataset_iterator->result_count();
		} else {
			return count($this->all);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Exists
	 *
	 * Returns TRUE if the current object has a database record.
	 *
	 * @return	bool
	 */
	public function exists()
	{
		// returns TRUE if the id of this object is set and not empty, OR
		// there are items in the ALL array.
		return isset($this->id) ? !empty($this->id) : ($this->result_count() > 0);
	}

	// --------------------------------------------------------------------

	/**
	 * Query
	 *
	 * Runs the specified query and populates the current object with the results.
	 *
	 * Warning: Use at your own risk.  This will only be as reliable as your query.
	 *
	 * @param	string $sql The query to process
	 * @param	array|bool $binds Array of values to bind (see CodeIgniter)
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function query($sql, $binds = FALSE)
	{
		// Get by objects properties
		$query = $this->db->query($sql, $binds);

		$this->_process_query($query);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Check Last Query
	 * Renders the last DB query performed.
	 *
	 * @param	array $delims Delimiters for the SQL string.
	 * @param	bool $return_as_string If TRUE, don't output automatically.
	 * @return	string Last db query formatted as a string.
	 */
	public function check_last_query($delims = array('<pre>', '</pre>'), $return_as_string = FALSE) {
		$q = wordwrap($this->db->last_query(), 100, "\n\t");
		if(!empty($delims)) {
			$q = implode($q, $delims);
		}
		if($return_as_string === FALSE) {
			echo $q;
		}
		return $q;
	}

	// --------------------------------------------------------------------

	/**
	 * Error Message
	 *
	 * Adds an error message to this objects error object.
	 *
	 * @param string $field Field to set the error on.
	 * @param string $error Error message.
	 */
	public function error_message($field, $error)
	{
		if ( ! empty($field) && ! empty($error))
		{
			// Set field specific error
			$this->error->{$field} = $this->error_prefix . $error . $this->error_suffix;

			// Add field error to errors all list
			$this->error->all[] = $this->error->{$field};

			// Append field error to error message string
			$this->error->string .= $this->error->{$field};
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Get Clone
	 *
	 * Returns a clone of the current object.
	 *
	 * @return	DataMapper Cloned copy of this object.
	 */
	public function get_clone($force_db = FALSE)
	{
		$temp = clone($this);

		// This must be left in place, even with the __clone method,
		// or else the DB will not be copied over correctly.
		if($force_db ||
				(($this->db_params !== FALSE) && isset($this->db)) )
		{
			// create a copy of $this->db
			$temp->db = clone($this->db);
		}
		return $temp;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Copy
	 *
	 * Returns an unsaved copy of the current object.
	 *
	 * @return	DataMapper Cloned copy of this object with an empty ID for saving as new.
	 */
	public function get_copy($force_db = FALSE)
	{
		$copy = $this->get_clone($force_db);

		$copy->id = NULL;

		return $copy;
	}

	// --------------------------------------------------------------------

	/**
	 * Get By
	 *
	 * Gets objects by specified field name and value.
	 *
	 * @ignore
	 * @param	string $field Field to look at.
	 * @param	array $value Arguments to this method.
	 * @return	DataMapper Returns self for method chaining.
	 */
	private function _get_by($field, $value = array())
	{
		if (isset($value[0]))
		{
			$this->where($field, $value[0]);
		}

		return $this->get();
	}

	// --------------------------------------------------------------------

	/**
	 * Get By Related
	 *
	 * Gets objects by specified related object and optionally by field name and value.
	 *
	 * @ignore
	 * @param	mixed $model Related Model or Object
	 * @param	array $arguments Arguments to the where method
	 * @return	DataMapper Returns self for method chaining.
	 */
	private function _get_by_related($model, $arguments = array())
	{
		if ( ! empty($model))
		{
			// Add model to start of arguments
			$arguments = array_merge(array($model), $arguments);
		}

		$this->_related('where', $arguments);

		return $this->get();
	}

	// --------------------------------------------------------------------

	/**
	 * Handles the adding the related part of a query if $parent is set
	 *
	 * @ignore
	 * @return bool Success or failure
	 */
	protected function _handle_related()
	{
		if ( ! empty($this->parent))
		{
			$has_many = array_key_exists($this->parent['model'], $this->has_many);
			$has_one = array_key_exists($this->parent['model'], $this->has_one);

			// If this is a "has many" or "has one" related item
			if ($has_many || $has_one)
			{
				if( ! $this->_get_relation($this->parent['model'], $this->parent['id']))
				{
					return FALSE;
				}
			}
			else
			{
				// provide feedback on errors
				$parent = $this->parent['model'];
				$this_model = get_class($this);
				show_error("DataMapper Error: '$parent' is not a valid parent relationship for $this_model.  Are your relationships configured correctly?");
			}
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

}