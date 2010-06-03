<?php class DataMapper {

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	 *                                                                   *
	 * Active Record methods                                             *
	 *                                                                   *
	 * The following are methods used to provide Active Record           *
	 * functionality for data retrieval.                                 *
	 *                                                                   *
	 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */


	// --------------------------------------------------------------------

	/**
	 * Add Table Name
	 *
	 * Adds the table name to a field if necessary
	 *
	 * @param	string $field Field to add the table name to.
	 * @return	string Possibly modified field name.
	 */
	public function add_table_name($field)
	{
		// only add table if the field doesn't contain a dot (.) or open parentheses
		if (preg_match('/[\.\(]/', $field) == 0)
		{
			// split string into parts, add field
			$field_parts = explode(',', $field);
			$field = '';
			foreach ($field_parts as $part)
			{
				if ( ! empty($field))
				{
					$field .= ', ';
				}
				$part = ltrim($part);
				// handle comparison operators on where
				$subparts = explode(' ', $part, 2);
				if ($subparts[0] == '*' || in_array($subparts[0], $this->fields))
				{
					$field .= $this->table  . '.' . $part;
				}
				else
				{
					$field .= $part;
				}
			}
		}
		return $field;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Creates a SQL-function with the given (optional) arguments.
	 * 
	 * Each argument can be one of several forms:
	 * 1) An un escaped string value, which will be automatically escaped: "hello"
	 * 2) An escaped value or non-string, which is copied directly: "'hello'" 123, etc
	 * 3) An operator, *, or a non-escaped string is copied directly: "[non-escaped]" ">", etc
	 * 4) A field on this model: "@property"  (Also, "@<whatever>" will be copied directly
	 * 5) A field on a related or deeply related model: "@model/property" "@model/other_model/property"
	 * 6) An array, which is processed recursively as a forumla.
	 * 
	 * @param	string $function_name Function name.
	 * @param	mixed $args,... (Optional) Any commands that need to be passed to the function.
	 * @return	string The new SQL function string.
	 */
	public function func($function_name)
	{
		$ret = $function_name . '(';
		$args = func_get_args();
		// pop the function name
		array_shift($args);
		$comma = '';
		foreach($args as $arg)
		{
			$ret .= $comma . $this->_process_function_arg($arg);
			if(empty($comma))
			{
				$comma = ', ';
			}
		}
		$ret .= ')';
		return $ret;
	}
	
	// private method to convert function arguments into SQL
	private function _process_function_arg($arg, $is_formula = FALSE)
	{
		$ret = '';
		if(is_array($arg)) {
			// formula
			foreach($arg as $func => $formula_arg) {
				if(!empty($ret)) {
					$ret .= ' ';
				}
				if(is_numeric($func)) {
					// process non-functions
					$ret .= $this->_process_function_arg($formula_arg, TRUE);
				} else {
					// recursively process functions within functions
					$func_args = array_merge(array($func), (array)$formula_arg);
					$ret .= call_user_func_array(array($this, 'func'), $func_args);
				}
			}
			return $ret;
		}
		
		$operators = array(
			'AND', 'OR', 'NOT', // binary logic
			'<', '>', '<=', '>=', '=', '<>', '!=', // comparators
			'+', '-', '*', '/', '%', '^', // basic maths
			'|/', '||/', '!', '!!', '@', '&', '|', '#', '~', // advanced maths
			'<<', '>>'); // binary operators
		
		if(is_string($arg))
		{
			if( ($is_formula && in_array($arg, $operators)) ||
				 $arg == '*' ||
				 ($arg[0] == "'" && $arg[strlen($arg)-1] == "'") ||
				 ($arg[0] == "[" && $arg[strlen($arg)-1] == "]") )
			{
				// simply add already-escaped strings, the special * value, or operators in formulas
				if($arg[0] == "[" && $arg[strlen($arg)-1] == "]") {
					// Arguments surrounded by square brackets are added directly, minus the brackets
					$arg = substr($arg, 1, -1);
				}
				$ret .= $arg;
			}
			else if($arg[0] == '@')
			{
				// model or sub-model property
				$arg = substr($arg, 1);
				if(strpos($arg, '/') !== FALSE)
				{
					// related property
					if(strpos($arg, 'parent/') === 0)
					{
						// special parent property for subqueries
						$ret .= str_replace('parent/', '${parent}.', $arg);
					}
					else
					{
						$rel_elements = explode('/', $arg);
						$property = array_pop($rel_elements);
						$table = $this->_add_related_table(implode('/', $rel_elements));
						$ret .= $this->db->protect_identifiers($table . '.' . $property);
					}
				}
				else
				{
					$ret .= $this->db->protect_identifiers($this->add_table_name($arg));
				}
			}
			else
			{
				$ret .= $this->db->escape($arg);
			}
		}
		else
		{
			$ret .= $arg;
		}
		return $ret;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Used by the magic method for select_func, {where}_func, etc
	 *
	 * @ignore
	 * @param	object $query Name of query function
	 * @param	array $args Arguments for func()
	 * @return	DataMapper Returns self for method chaining.
	 */
	private function _func($query, $args)
	{
		if(count($args) < 2)
		{
			throw new Exception("Invalid number of arguments to {$query}_func: must be at least 2 arguments.");
		}
		if($query == 'select')
		{
			$alias = array_pop($args);
			$value = call_user_func_array(array($this, 'func'), $args);
			$value .= " AS $alias";

			// we can't use the normal select method, because CI likes to breaky
			$this->_add_to_select_directly($value);

			return $this;
		}
		else
		{
			$param = array_pop($args);
			$value = call_user_func_array(array($this, 'func'), $args);
			return $this->{$query}($value, $param);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Used by the magic method for {where}_field_func, etc.
	 *
	 * @ignore
	 * @param	string $query Name of query function
	 * @param	array $args Arguments for func()
	 * @return	DataMapper Returns self for method chaining.
	 */
	private function _field_func($query, $args)
	{
		if(count($args) < 2)
		{
			throw new Exception("Invalid number of arguments to {$query}_field_func: must be at least 2 arguments.");
		}
		$field = array_shift($args);
		$func = call_user_func_array(array($this, 'func'), $args);
		return $this->_process_special_query_clause($query, $field, $func);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Used by the magic method for select_subquery {where}_subquery, etc
	 *
	 * @ignore
	 * @param	string $query Name of query function
	 * @param	array $args Arguments for subquery
	 * @return	DataMapper Returns self for method chaining.
	 */
	private function _subquery($query, $args)
	{
		if(count($args) < 1)
		{
			throw new Exception("Invalid arguments on {$query}_subquery: must be at least one argument.");
		}
		if($query == 'select')
		{
			if(count($args) < 2)
			{
				throw new Exception('Invalid number of arguments to select_subquery: must be exactly 2 arguments.');
			}
			$sql = $this->_parse_subquery_object($args[0]);
			$alias = $args[1];
			// we can't use the normal select method, because CI likes to breaky
			$this->_add_to_select_directly("$sql AS $alias");
			return $this;
		}
		else
		{
			$object = $field = $value = NULL;
			if(is_object($args[0]) ||
					(is_string($args[0]) && !isset($args[1])) )
			{
				$field = $this->_parse_subquery_object($args[0]);
				if(isset($args[1])) {
					$value = $this->db->protect_identifiers($this->add_table_name($args[1]));
				}
			}
			else
			{
				$field = $this->add_table_name($args[0]);
				$value = $args[1];
				if(is_object($value))
				{
					$value = $this->_parse_subquery_object($value);
				}
			}
			$extra = NULL;
			if(isset($args[2])) {
				$extra = $args[2];
			}
			return $this->_process_special_query_clause($query, $field, $value, $extra);
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * Parses and protects a subquery.
	 * Automatically replaces the special ${parent} argument with a reference to
	 * this table.
	 * 
	 * Also replaces all table references that would overlap with this object.
	 *
	 * @ignore
	 * @param	object $sql SQL string to process
	 * @return	string Processed SQL string.
	 */
	protected function _parse_subquery_object($sql)
	{
		if(is_object($sql))
		{
			$sql = '(' . $sql->get_sql() . ')';
		}
		
		// Table Name pattern should be
		$tablename = $this->db->_escape_identifiers($this->table);
		$table_pattern = '(?:' . preg_quote($this->table) . '|' . preg_quote($tablename) . ')';
		
		$fieldname = $this->db->_escape_identifiers('__field__');
		$field_pattern = '([-\w]+|' . str_replace('__field__', '[-\w]+', preg_quote($fieldname)) . ')';
		
		// replace all table.field references
		// pattern ends up being [^_](table|`table`).(field|`field`)
		// the NOT _ at the beginning is to prevent replacing of advanced relationship table references.
		$pattern = '/([^_])' . $table_pattern . '\.' . $field_pattern . '/i';
		// replacement ends up being `table_subquery`.`$1`
		$replacement = '$1' . $this->db->_escape_identifiers($this->table . '_subquery') . '.$2';
		$sql = preg_replace($pattern, $replacement, $sql);
		
		// now replace all "table table" aliases
		// important: the space at the end is required
		$pattern = "/$table_pattern $table_pattern /i";
		$replacement = $tablename . ' ' . $this->db->_escape_identifiers($this->table . '_subquery') . ' ';
		$sql = preg_replace($pattern, $replacement, $sql);

		// now replace "FROM table" for self relationships
		$pattern = "/FROM $table_pattern([,\\s])/i";
		$replacement = "FROM $tablename " . $this->db->_escape_identifiers($this->table . '_subquery') . '$1';
		$sql = preg_replace($pattern, $replacement, $sql);
		
		$sql = str_replace("\n", "\n\t", $sql);
		
		return str_replace('${parent}', $this->table, $sql);
	}

	// --------------------------------------------------------------------

	/**
	 * Manually adds an item to the SELECT column, to prevent it from
	 * being broken by AR->select
	 *
	 * @ignore
	 * @param	string $value New SELECT value
	 */
	protected function _add_to_select_directly($value)
	{
		// copied from system/database/DB_activerecord.php
		$this->db->ar_select[] = $value;

		if ($this->db->ar_caching === TRUE)
		{
			$this->ar_cache_select[] = $value;
			$this->ar_cache_exists[] = 'select';
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Handles specialized where clauses, like subqueries and functions
	 *
	 * @ignore
	 * @param	string $query Query function
	 * @param	string $field Field for Query function
	 * @param	mixed $value Value for Query function
	 * @param	mixed $extra If included, overrides the default assumption of FALSE for the third parameter to $query
	 * @return	DataMapper Returns self for method chaining.
	 */
	private function _process_special_query_clause($query, $field, $value, $extra = NULL) {
		if(strpos($query, 'where_in') !== FALSE) {
			$query = str_replace('_in', '', $query);
			$field .= ' IN ';
		} else if(strpos($query, 'where_not_in') !== FALSE) {
			$query = str_replace('_not_in', '', $query);
			$field .= ' NOT IN ';
		}
		if(is_null($extra)) {
			$extra = FALSE;
		}
		return $this->{$query}($field, $value, $extra);
	}

	// --------------------------------------------------------------------

	/**
	 * Select
	 *
	 * Sets the SELECT portion of the query.
	 *
	 * @param	string $select Field(s) to select
	 * @param	bool $escape If FALSE, don't escape this field (Probably won't work)
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function select($select = '*', $escape = NULL)
	{
		if ($escape !== FALSE)
		{
			$select = $this->add_table_name($select);
		}
		$this->db->select($select, $escape);
		
		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Select Max
	 *
	 * Sets the SELECT MAX(field) portion of a query.
	 *
	 * @param	string $select Field to look at.
	 * @param	string $alias Alias of the MAX value.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function select_max($select = '', $alias = '')
	{
		// Check if this is a related object
		if ( ! empty($this->parent))
		{
			$alias = ($alias != '') ? $alias : $select;
		}
		$this->db->select_max($this->add_table_name($select), $alias);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Select Min
	 *
	 * Sets the SELECT MIN(field) portion of a query.
	 *
	 * @param	string $select Field to look at.
	 * @param	string $alias Alias of the MIN value.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function select_min($select = '', $alias = '')
	{
		// Check if this is a related object
		if ( ! empty($this->parent))
		{
			$alias = ($alias != '') ? $alias : $select;
		}
		$this->db->select_min($this->add_table_name($select), $alias);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Select Avg
	 *
	 * Sets the SELECT AVG(field) portion of a query.
	 *
	 * @param	string $select Field to look at.
	 * @param	string $alias Alias of the AVG value.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function select_avg($select = '', $alias = '')
	{
		// Check if this is a related object
		if ( ! empty($this->parent))
		{
			$alias = ($alias != '') ? $alias : $select;
		}
		$this->db->select_avg($this->add_table_name($select), $alias);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Select Sum
	 *
	 * Sets the SELECT SUM(field) portion of a query.
	 *
	 * @param	string $select Field to look at.
	 * @param	string $alias Alias of the SUM value.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function select_sum($select = '', $alias = '')
	{
		// Check if this is a related object
		if ( ! empty($this->parent))
		{
			$alias = ($alias != '') ? $alias : $select;
		}
		$this->db->select_sum($this->add_table_name($select), $alias);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Distinct
	 *
	 * Sets the flag to add DISTINCT to the query.
	 *
	 * @param	bool $value Set to FALSE to turn back off DISTINCT
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function distinct($value = TRUE)
	{
		$this->db->distinct($value);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Where
	 *
	 * Get items matching the where clause.
	 *
	 * @param	mixed $where See where()
	 * @param	integer|NULL $limit Limit the number of results.
	 * @param	integer|NULL $offset Offset the results when limiting.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function get_where($where = array(), $limit = NULL, $offset = NULL)
	{
		$this->where($where);

		return $this->get($limit, $offset);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Starts a query group.
	 *
	 * @param	string $not (Internal use only)
	 * @param	string $type (Internal use only)
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function group_start($not = '', $type = 'AND ')
	{
		// in case groups are being nested
		$type = $this->_get_prepend_type($type);
		
		$prefix = (count($this->db->ar_where) == 0 AND count($this->db->ar_cache_where) == 0) ? '' : $type;
		$this->db->ar_where[] = $prefix . $not .  ' (';
		$this->_where_group_started = TRUE;
		return $this;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Starts a query group, but ORs the group
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function or_group_start()
	{
		return $this->group_start('', 'OR ');
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Starts a query group, but NOTs the group
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function not_group_start()
	{
		return $this->group_start('NOT ', 'OR ');
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Starts a query group, but OR NOTs the group
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function or_not_group_start()
	{
		return $this->group_start('NOT ', 'OR ');
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Ends a query group.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function group_end()
	{
		$this->db->ar_where[] = ')';
		$this->_where_group_started = FALSE;
		return $this;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * protected function to convert the AND or OR prefix to '' when starting
	 * a group.
	 *
	 * @ignore
	 * @param	object $type Current type value
	 * @return	New type value
	 */
	protected function _get_prepend_type($type)
	{
		if($this->_where_group_started)
		{
			$type = '';
			$this->_where_group_started = FALSE;
		}
		return $type;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Where
	 *
	 * Sets the WHERE portion of the query.
	 * Separates multiple calls with AND.
	 *
	 * Called by get_where()
	 *
	 * @param	mixed $key A field or array of fields to check.
	 * @param	mixed $value For a single field, the value to compare to.
	 * @param	bool $escape If FALSE, the field is not escaped.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function where($key, $value = NULL, $escape = TRUE)
	{
		return $this->_where($key, $value, 'AND ', $escape);
	}

	// --------------------------------------------------------------------

	/**
	 * Or Where
	 *
	 * Sets the WHERE portion of the query.
	 * Separates multiple calls with OR.
	 *
	 * @param	mixed $key A field or array of fields to check.
	 * @param	mixed $value For a single field, the value to compare to.
	 * @param	bool $escape If FALSE, the field is not escaped.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function or_where($key, $value = NULL, $escape = TRUE)
	{
		return $this->_where($key, $value, 'OR ', $escape);
	}

	// --------------------------------------------------------------------

	/**
	 * Where
	 *
	 * Called by where() or or_where().
	 *
	 * @ignore
	 * @param	mixed $key A field or array of fields to check.
	 * @param	mixed $value For a single field, the value to compare to.
	 * @param	string $type Type of addition (AND or OR)
	 * @param	bool $escape If FALSE, the field is not escaped.
	 * @return	DataMapper Returns self for method chaining.
	 */
	protected function _where($key, $value = NULL, $type = 'AND ', $escape = NULL)
	{
		if ( ! is_array($key))
		{
			$key = array($key => $value);
		}
		foreach ($key as $k => $v)
		{
			$new_k = $this->add_table_name($k);
			if ($new_k != $k)
			{
				$key[$new_k] = $v;
				unset($key[$k]);
			}
		}
		
		$type = $this->_get_prepend_type($type);
		
		$this->db->_where($key, $value, $type, $escape);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Where In
	 *
	 * Sets the WHERE field IN ('item', 'item') SQL query joined with
	 * AND if appropriate.
	 *
	 * @param	string $key A field to check.
	 * @param	array $values An array of values to compare against
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function where_in($key = NULL, $values = NULL)
	{
	 	return $this->_where_in($key, $values);
	}

	// --------------------------------------------------------------------

	/**
	 * Or Where In
	 *
	 * Sets the WHERE field IN ('item', 'item') SQL query joined with
	 * OR if appropriate.
	 *
	 * @param	string $key A field to check.
	 * @param	array $values An array of values to compare against
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function or_where_in($key = NULL, $values = NULL)
	{
	 	return $this->_where_in($key, $values, FALSE, 'OR ');
	}

	// --------------------------------------------------------------------

	/**
	 * Where Not In
	 *
	 * Sets the WHERE field NOT IN ('item', 'item') SQL query joined with
	 * AND if appropriate.
	 *
	 * @param	string $key A field to check.
	 * @param	array $values An array of values to compare against
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function where_not_in($key = NULL, $values = NULL)
	{
		return $this->_where_in($key, $values, TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * Or Where Not In
	 *
	 * Sets the WHERE field NOT IN ('item', 'item') SQL query joined wuth
	 * OR if appropriate.
	 *
	 * @param	string $key A field to check.
	 * @param	array $values An array of values to compare against
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function or_where_not_in($key = NULL, $values = NULL)
	{
		return $this->_where_in($key, $values, TRUE, 'OR ');
	}

	// --------------------------------------------------------------------

	/**
	 * Where In
	 *
	 * Called by where_in(), or_where_in(), where_not_in(), or or_where_not_in().
	 *
	 * @ignore
	 * @param	string $key A field to check.
	 * @param	array $values An array of values to compare against
	 * @param	bool $not If TRUE, use NOT IN instead of IN.
	 * @param	string $type The type of connection (AND or OR)
	 * @return	DataMapper Returns self for method chaining.
	 */
	protected function _where_in($key = NULL, $values = NULL, $not = FALSE, $type = 'AND ')
	{	
		$type = $this->_get_prepend_type($type);
		
	 	$this->db->_where_in($this->add_table_name($key), $values, $not, $type);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Like
	 *
	 * Sets the %LIKE% portion of the query.
	 * Separates multiple calls with AND.
	 *
	 * @param	mixed $field A field or array of fields to check.
	 * @param	mixed $match For a single field, the value to compare to.
	 * @param	string $side One of 'both', 'before', or 'after'
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function like($field, $match = '', $side = 'both')
	{
		return $this->_like($field, $match, 'AND ', $side);
	}

	// --------------------------------------------------------------------

	/**
	 * Not Like
	 *
	 * Sets the NOT LIKE portion of the query.
	 * Separates multiple calls with AND.
	 *
	 * @param	mixed $field A field or array of fields to check.
	 * @param	mixed $match For a single field, the value to compare to.
	 * @param	string $side One of 'both', 'before', or 'after'
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function not_like($field, $match = '', $side = 'both')
	{
		return $this->_like($field, $match, 'AND ', $side, 'NOT');
	}

	// --------------------------------------------------------------------

	/**
	 * Or Like
	 *
	 * Sets the %LIKE% portion of the query.
	 * Separates multiple calls with OR.
	 *
	 * @param	mixed $field A field or array of fields to check.
	 * @param	mixed $match For a single field, the value to compare to.
	 * @param	string $side One of 'both', 'before', or 'after'
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function or_like($field, $match = '', $side = 'both')
	{
		return $this->_like($field, $match, 'OR ', $side);
	}

	// --------------------------------------------------------------------

	/**
	 * Or Not Like
	 *
	 * Sets the NOT LIKE portion of the query.
	 * Separates multiple calls with OR.
	 *
	 * @param	mixed $field A field or array of fields to check.
	 * @param	mixed $match For a single field, the value to compare to.
	 * @param	string $side One of 'both', 'before', or 'after'
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function or_not_like($field, $match = '', $side = 'both')
	{
		return $this->_like($field, $match, 'OR ', $side, 'NOT');
	}

	// --------------------------------------------------------------------

	/**
	 * ILike
	 *
	 * Sets the case-insensitive %LIKE% portion of the query.
	 *
	 * @param	mixed $field A field or array of fields to check.
	 * @param	mixed $match For a single field, the value to compare to.
	 * @param	string $side One of 'both', 'before', or 'after'
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function ilike($field, $match = '', $side = 'both')
	{
		return $this->_like($field, $match, 'AND ', $side, '', TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * Not ILike
	 *
	 * Sets the case-insensitive NOT LIKE portion of the query.
	 * Separates multiple calls with AND.
	 *
	 * @param	mixed $field A field or array of fields to check.
	 * @param	mixed $match For a single field, the value to compare to.
	 * @param	string $side One of 'both', 'before', or 'after'
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function not_ilike($field, $match = '', $side = 'both')
	{
		return $this->_like($field, $match, 'AND ', $side, 'NOT', TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * Or Like
	 *
	 * Sets the case-insensitive %LIKE% portion of the query.
	 * Separates multiple calls with OR.
	 *
	 * @param	mixed $field A field or array of fields to check.
	 * @param	mixed $match For a single field, the value to compare to.
	 * @param	string $side One of 'both', 'before', or 'after'
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function or_ilike($field, $match = '', $side = 'both')
	{
		return $this->_like($field, $match, 'OR ', $side, '', TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * Or Not Like
	 *
	 * Sets the case-insensitive NOT LIKE portion of the query.
	 * Separates multiple calls with OR.
	 *
	 * @param	mixed $field A field or array of fields to check.
	 * @param	mixed $match For a single field, the value to compare to.
	 * @param	string $side One of 'both', 'before', or 'after'
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function or_not_ilike($field, $match = '', $side = 'both')
	{
		return $this->_like($field, $match, 'OR ', $side, 'NOT', TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * _Like
	 *
	 * Private function to do actual work.
	 * NOTE: this does NOT use the built-in ActiveRecord LIKE function.
	 *
	 * @ignore
	 * @param	mixed $field A field or array of fields to check.
	 * @param	mixed $match For a single field, the value to compare to.
	 * @param	string $type The type of connection (AND or OR)
	 * @param	string $side One of 'both', 'before', or 'after'
	 * @param	string $not 'NOT' or ''
	 * @param	bool $no_case If TRUE, configure to ignore case.
	 * @return	DataMapper Returns self for method chaining.
	 */
	protected function _like($field, $match = '', $type = 'AND ', $side = 'both', $not = '', $no_case = FALSE)
	{
		if ( ! is_array($field))
		{
			$field = array($field => $match);
		}

		foreach ($field as $k => $v)
		{
			$new_k = $this->add_table_name($k);
			if ($new_k != $k)
			{
				$field[$new_k] = $v;
				unset($field[$k]);
			}
		}
		
		// Taken from CodeIgniter's Active Record because (for some reason)
		// it is stored separately that normal where statements.
 	
		foreach ($field as $k => $v)
		{
			if($no_case)
			{
				$k = 'UPPER(' . $this->db->protect_identifiers($k) .')';
				$v = strtoupper($v);
			}
			$f = "$k $not LIKE";

			if ($side == 'before')
			{
				$m = "%{$v}";
			}
			elseif ($side == 'after')
			{
				$m = "{$v}%";
			}
			else
			{
				$m = "%{$v}%";
			}
			
			$this->_where($f, $m, $type);
		}

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Group By
	 *
	 * Sets the GROUP BY portion of the query.
	 *
	 * @param	string $by Field to group by
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function group_by($by)
	{
		$this->db->group_by($this->add_table_name($by));

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Having
	 *
	 * Sets the HAVING portion of the query.
	 * Separates multiple calls with AND.
	 *
	 * @param	string $key Field to compare.
	 * @param	string $value value to compare to.
	 * @param	bool $escape If FALSE, don't escape the value.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function having($key, $value = '', $escape = TRUE)
	{
		return $this->_having($key, $value, 'AND ', $escape);
	}

	// --------------------------------------------------------------------

	/**
	 * Or Having
	 *
	 * Sets the OR HAVING portion of the query.
	 * Separates multiple calls with OR.
	 *
	 * @param	string $key Field to compare.
	 * @param	string $value value to compare to.
	 * @param	bool $escape If FALSE, don't escape the value.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function or_having($key, $value = '', $escape = TRUE)
	{
		return $this->_having($key, $value, 'OR ', $escape);
	}

	// --------------------------------------------------------------------

	/**
	 * Having
	 *
	 * Sets the HAVING portion of the query.
	 * Separates multiple calls with AND.
	 *
	 * @ignore
	 * @param	string $key Field to compare.
	 * @param	string $value value to compare to.
	 * @param	string $type Type of connection (AND or OR)
	 * @param	bool $escape If FALSE, don't escape the value.
	 * @return	DataMapper Returns self for method chaining.
	 */
	protected function _having($key, $value = '', $type = 'AND ', $escape = TRUE)
	{	
		$this->db->_having($this->add_table_name($key), $value, $type, $escape);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Order By
	 *
	 * Sets the ORDER BY portion of the query.
	 *
	 * @param	string $orderby Field to order by
	 * @param	string $direction One of 'ASC' or 'DESC'  Defaults to 'ASC'
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function order_by($orderby, $direction = '')
	{
		$this->db->order_by($this->add_table_name($orderby), $direction);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Adds in the defaut order_by items, if there are any, and
	 * order_by hasn't been overridden.
	 * @ignore
	 */
	protected function _handle_default_order_by()
	{
		if(empty($this->default_order_by))
		{
			return;
		}
		$sel = $this->table . '.' . '*';
		$sel_protect = $this->db->protect_identifiers($sel);
		// only add the items if there isn't an existing order_by,
		// AND the select statement is empty or includes * or table.* or `table`.*
		if(empty($this->db->ar_orderby) &&
			(
				empty($this->db->ar_select) ||
				in_array('*', $this->db->ar_select) ||
				in_array($sel_protect, $this->db->ar_select) ||
			 	in_array($sel, $this->db->ar_select)
			 	
			))
		{
			foreach($this->default_order_by as $k => $v) {
				if(is_int($k)) {
					$k = $v;
					$v = '';
				}
				$k = $this->add_table_name($k);
				$this->order_by($k, $v);
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Limit
	 *
	 * Sets the LIMIT portion of the query.
	 *
	 * @param	integer $limit Limit the number of results.
	 * @param	integer|NULL $offset Offset the results when limiting.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function limit($value, $offset = '')
	{
		$this->db->limit($value, $offset);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Offset
	 *
	 * Sets the OFFSET portion of the query.
	 *
	 * @param	integer $offset Offset the results when limiting.
	 * @return	DataMapper Returns self for method chaining.
	 */
	public function offset($offset)
	{
		$this->db->offset($offset);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Start Cache
	 *
	 * Starts AR caching.
	 */		
	public function start_cache()
	{
		$this->db->start_cache();
	}

	// --------------------------------------------------------------------

	/**
	 * Stop Cache
	 *
	 * Stops AR caching.
	 */		
	public function stop_cache()
	{
		$this->db->stop_cache();
	}

	// --------------------------------------------------------------------

	/**
	 * Flush Cache
	 *
	 * Empties the AR cache.
	 */	
	public function flush_cache()
	{	
		$this->db->flush_cache();
	}

	// --------------------------------------------------------------------

}