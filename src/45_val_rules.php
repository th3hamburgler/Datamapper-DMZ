<?php class DataMapper {
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	 *                                                                   *
	 * Validation methods                                                *
	 *                                                                   *
	 * The following are methods used to validate the                    *
	 * values of this objects properties.                                *
	 *                                                                   *
	 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */


	// --------------------------------------------------------------------

	/**
	 * Always Validate
	 * 
	 * Does nothing, but forces a validation even if empty (for non-required fields)
	 *
	 * @ignore
	 */	
	protected function _always_validate()
	{
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha Dash Dot (pre-process)
	 *
	 * Alpha-numeric with underscores, dashes and full stops.
	 *
	 * @ignore
	 */	
	protected function _alpha_dash_dot($field)
	{
		return ( ! preg_match('/^([\.-a-z0-9_-])+$/i', $this->{$field})) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha Slash Dot (pre-process)
	 *
	 * Alpha-numeric with underscores, dashes, forward slashes and full stops.
	 *
	 * @ignore
	 */	
	protected function _alpha_slash_dot($field)
	{
		return ( ! preg_match('/^([\.\/-a-z0-9_-])+$/i', $this->{$field})) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Matches (pre-process)
	 *
	 * Match one field to another.
	 * This replaces the version in CI_Form_validation.
	 *
	 * @ignore
	 */
	protected function _matches($field, $other_field)
	{
		return ($this->{$field} !== $this->{$other_field}) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Min Date (pre-process)
	 *
	 * Checks if the value of a property is at least the minimum date.
	 *
	 * @ignore
	 */
	protected function _min_date($field, $date)
	{
		return (strtotime($this->{$field}) < strtotime($date)) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Max Date (pre-process)
	 *
	 * Checks if the value of a property is at most the maximum date.
	 *
	 * @ignore
	 */
	protected function _max_date($field, $date)
	{
		return (strtotime($this->{$field}) > strtotime($date)) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Min Size (pre-process)
	 *
	 * Checks if the value of a property is at least the minimum size.
	 *
	 * @ignore
	 */
	protected function _min_size($field, $size)
	{
		return ($this->{$field} < $size) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Max Size (pre-process)
	 *
	 * Checks if the value of a property is at most the maximum size.
	 *
	 * @ignore
	 */
	protected function _max_size($field, $size)
	{
		return ($this->{$field} > $size) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Unique (pre-process)
	 *
	 * Checks if the value of a property is unique.
 	 * If the property belongs to this object, we can ignore it.
 	 *
	 * @ignore
	 */
	protected function _unique($field)
	{
		if ( ! empty($this->{$field}))
		{
			$query = $this->db->get_where($this->table, array($field => $this->{$field}), 1, 0);

			if ($query->num_rows() > 0)
			{
				$row = $query->row();

				// If unique value does not belong to this object
				if ($this->id != $row->id)
				{
					// Then it is not unique
					return FALSE;
				}
			}
		}

		// No matches found so is unique
		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Unique Pair (pre-process)
	 *
	 * Checks if the value of a property, paired with another, is unique.
 	 * If the properties belongs to this object, we can ignore it.
	 *
	 * @ignore
	 */
	protected function _unique_pair($field, $other_field = '')
	{
		if ( ! empty($this->{$field}) && ! empty($this->{$other_field}))
		{
			$query = $this->db->get_where($this->table, array($field => $this->{$field}, $other_field => $this->{$other_field}), 1, 0);

			if ($query->num_rows() > 0)
			{
				$row = $query->row();
				
				// If unique pair value does not belong to this object
				if ($this->id != $row->id)
				{
					// Then it is not a unique pair
					return FALSE;
				}
			}
		}

		// No matches found so is unique
		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Valid Date (pre-process)
	 *
	 * Checks whether the field value is a valid DateTime.
	 *
	 * @ignore
	 */
	protected function _valid_date($field)
	{
		// Ignore if empty
		if (empty($this->{$field}))
		{
			return TRUE;
		}

		$date = date_parse($this->{$field});

		return checkdate($date['month'], $date['day'],$date['year']);
	}

	// --------------------------------------------------------------------

	/**
	 * Valid Date Group (pre-process)
	 *
	 * Checks whether the field value, grouped with other field values, is a valid DateTime.
	 *
	 * @ignore
	 */
	protected function _valid_date_group($field, $fields = array())
	{
		// Ignore if empty
		if (empty($this->{$field}))
		{
			return TRUE;
		}

		$date = date_parse($this->{$fields['year']} . '-' . $this->{$fields['month']} . '-' . $this->{$fields['day']});

		return checkdate($date['month'], $date['day'],$date['year']);
	}

	// --------------------------------------------------------------------

	/**
	 * Valid Match (pre-process)
	 *
	 * Checks whether the field value matches one of the specified array values.
	 *
	 * @ignore
	 */
	protected function _valid_match($field, $param = array())
	{
		return in_array($this->{$field}, $param);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Boolean (pre-process)
	 * 
	 * Forces a field to be either TRUE or FALSE.
	 * Uses PHP's built-in boolean conversion.
	 *
	 * @ignore
	 */
	protected function _boolean($field)
	{
		$this->{$field} = (boolean)$this->{$field};
	}

	// --------------------------------------------------------------------

	/**
	 * Encode PHP Tags (prep)
	 *
	 * Convert PHP tags to entities.
	 * This replaces the version in CI_Form_validation.
	 *
	 * @ignore
	 */	
	protected function _encode_php_tags($field)
	{
		$this->{$field} = encode_php_tags($this->{$field});
	}

	// --------------------------------------------------------------------

	/**
	 * Prep for Form (prep)
	 *
	 * Converts special characters to allow HTML to be safely shown in a form.
	 * This replaces the version in CI_Form_validation.
	 *
	 * @ignore
	 */	
	protected function _prep_for_form($field)
	{
		$this->{$field} = $this->form_validation->prep_for_form($this->{$field});
	}

	// --------------------------------------------------------------------

	/**
	 * Prep URL (prep)
	 *
	 * Adds "http://" to URLs if missing.
	 * This replaces the version in CI_Form_validation.
	 *
	 * @ignore
	 */	
	protected function _prep_url($field)
	{
		$this->{$field} = $this->form_validation->prep_url($this->{$field});
	}

	// --------------------------------------------------------------------

	/**
	 * Strip Image Tags (prep)
	 *
	 * Strips the HTML from image tags leaving the raw URL.
	 * This replaces the version in CI_Form_validation.
	 *
	 * @ignore
	 */	
	protected function _strip_image_tags($field)
	{
		$this->{$field} = strip_image_tags($this->{$field});
	}

	// --------------------------------------------------------------------

	/**
	 * XSS Clean (prep)
	 *
	 * Runs the data through the XSS filtering function, described in the Input Class page.
	 * This replaces the version in CI_Form_validation.
	 *
	 * @ignore
	 */	
	protected function _xss_clean($field, $is_image = FALSE)
	{
		$this->{$field} = xss_clean($this->{$field}, $is_image);
	}

	
	// --------------------------------------------------------------------
	
	/**
	 * Trim
	 * Custom trim rule that ignores NULL values
	 *
	 * @ignore
	 */	
	protected function _trim($field) {
		if( ! empty($this->{$field})) {
			$this->{$field} = trim($this->{$field});
		}
	}

	// --------------------------------------------------------------------

}
