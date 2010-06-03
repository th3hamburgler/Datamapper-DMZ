LEAVE THIS GAP
<?php

/**
 * Data Mapper Class, OverZealous Edition
 *
 * Transforms database tables into objects.
 *
 * @license 	MIT License
 * @package		DMZ
 * @category	DMZ
 * @author  	Phil DeJarnett
 * @link    	http://www.overzealous.com/dmz/
 * @version 	1.7.1 ($Rev: 394 $)
 */

/**
 * Key for storing pre-converted classnames
 */
define('DMZ_CLASSNAMES_KEY', '_dmz_classnames');

/**
 * Data Mapper Class, OverZealous Edition
 *
 * Transforms database tables into objects.
 *
 * @package		DMZ
 * 
 * Properties (for code completion)
 * @property CI_DB_driver $db The CodeIgniter Database Library
 * @property CI_Loader $load The CodeIgnter Loader Library
 * @property CI_Language $lang The CodeIgniter Language Library
 * @property CI_Config $config The CodeIgniter Config Library
 * @property CI_Form_validation $form_validation The CodeIgniter Form Validation Library
 *
 *
 * Define some of the magic methods:
 *
 * Get By:
 * @method DataMapper get_by_id() get_by_id(int $value) Looks up an item by its ID.
 * @method DataMapper get_by_FIELD() get_by_FIELD(mixed $value) Looks up an item by a specific FIELD. Ex: get_by_name($user_name);
 * @method DataMapper get_by_related() get_by_related(mixed $related, string $field = NULL, string $value = NULL) Get results based on a related item.
 * @method DataMapper get_by_related_RELATEDFIELD() get_by_related_RELATEDFIELD(string $field = NULL, string $value = NULL) Get results based on a RELATEDFIELD. Ex: get_by_related_user('id', $userid);
 *
 * Save and Delete
 * @method DataMapper save_RELATEDFIELD() save_RELATEDFIELD(mixed $object) Saves relationship(s) using the specified RELATEDFIELD. Ex: save_user($user);
 * @method DataMapper delete_RELATEDFIELD() delete_RELATEDFIELD(mixed $object) Deletes relationship(s) using the specified RELATEDFIELD. Ex: delete_user($user);
 *
 * Related:
 * @method DataMapper where_related() where_related(mixed $related, string $field = NULL, string $value = NULL) Limits results based on a related field.
 * @method DataMapper or_where_related() or_where_related(mixed $related, string $field = NULL, string $value = NULL) Limits results based on a related field, via OR.
 * @method DataMapper where_in_related() where_in_related(mixed $related, string $field, array $values) Limits results by comparing a related field to a range of values.
 * @method DataMapper or_where_in_related() or_where_in_related(mixed $related, string $field, array $values) Limits results by comparing a related field to a range of values.
 * @method DataMapper where_not_in_related() where_not_in_related(mixed $related, string $field, array $values) Limits results by comparing a related field to a range of values.
 * @method DataMapper or_where_not_in_related() or_where_not_in_related(mixed $related, string $field, array $values) Limits results by comparing a related field to a range of values.
 * @method DataMapper like_related() like_related(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a related field to a value.
 * @method DataMapper or_like_related() like_related(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a related field to a value.
 * @method DataMapper not_like_related() like_related(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a related field to a value.
 * @method DataMapper or_not_like_related() like_related(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a related field to a value.
 * @method DataMapper ilike_related() like_related(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a related field to a value (case insensitive).
 * @method DataMapper or_ilike_related() like_related(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a related field to a value (case insensitive).
 * @method DataMapper not_ilike_related() like_related(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a related field to a value (case insensitive).
 * @method DataMapper or_not_ilike_related() like_related(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a related field to a value (case insensitive).
 * @method DataMapper group_by_related() group_by_related(mixed $related, string $field) Groups the query by a related field.
 * @method DataMapper having_related() having_related(mixed $related, string $field, string $value) Groups the querying using a HAVING clause.
 * @method DataMapper or_having_related() having_related(mixed $related, string $field, string $value) Groups the querying using a HAVING clause, via OR.
 * @method DataMapper order_by_related() order_by_related(mixed $related, string $field, string $direction) Orders the query based on a related field.
 *
 *
 * Join Fields:
 * @method DataMapper where_join_field() where_join_field(mixed $related, string $field = NULL, string $value = NULL) Limits results based on a join field.
 * @method DataMapper or_where_join_field() or_where_join_field(mixed $related, string $field = NULL, string $value = NULL) Limits results based on a join field, via OR.
 * @method DataMapper where_in_join_field() where_in_join_field(mixed $related, string $field, array $values) Limits results by comparing a join field to a range of values.
 * @method DataMapper or_where_in_join_field() or_where_in_join_field(mixed $related, string $field, array $values) Limits results by comparing a join field to a range of values.
 * @method DataMapper where_not_in_join_field() where_not_in_join_field(mixed $related, string $field, array $values) Limits results by comparing a join field to a range of values.
 * @method DataMapper or_where_not_in_join_field() or_where_not_in_join_field(mixed $related, string $field, array $values) Limits results by comparing a join field to a range of values.
 * @method DataMapper like_join_field() like_join_field(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a join field to a value.
 * @method DataMapper or_like_join_field() like_join_field(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a join field to a value.
 * @method DataMapper not_like_join_field() like_join_field(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a join field to a value.
 * @method DataMapper or_not_like_join_field() like_join_field(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a join field to a value.
 * @method DataMapper ilike_join_field() like_join_field(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a join field to a value (case insensitive).
 * @method DataMapper or_ilike_join_field() like_join_field(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a join field to a value (case insensitive).
 * @method DataMapper not_ilike_join_field() like_join_field(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a join field to a value (case insensitive).
 * @method DataMapper or_not_ilike_join_field() like_join_field(mixed $related, string $field, string $value, string $match = 'both') Limits results by matching a join field to a value (case insensitive).
 * @method DataMapper group_by_join_field() group_by_join_field(mixed $related, string $field) Groups the query by a join field.
 * @method DataMapper having_join_field() having_join_field(mixed $related, string $field, string $value) Groups the querying using a HAVING clause.
 * @method DataMapper or_having_join_field() having_join_field(mixed $related, string $field, string $value) Groups the querying using a HAVING clause, via OR.
 * @method DataMapper order_by_join_field() order_by_join_field(mixed $related, string $field, string $direction) Orders the query based on a join field.
 *
 * SQL Functions:
 * @method DataMapper select_func() select_func(string $function_name, mixed $args,..., string $alias) Selects the result of a SQL function. Alias is required.
 * @method DataMapper where_func() where_func(string $function_name, mixed $args,..., string $value) Limits results based on a SQL function.
 * @method DataMapper or_where_func() or_where_func(string $function_name, mixed $args,..., string $value) Limits results based on a SQL function, via OR.
 * @method DataMapper where_in_func() where_in_func(string $function_name, mixed $args,..., array $values) Limits results by comparing a SQL function to a range of values.
 * @method DataMapper or_where_in_func() or_where_in_func(string $function_name, mixed $args,..., array $values) Limits results by comparing a SQL function to a range of values.
 * @method DataMapper where_not_in_func() where_not_in_func(string $function_name, string $field, array $values) Limits results by comparing a SQL function to a range of values.
 * @method DataMapper or_where_not_in_func() or_where_not_in_func(string $function_name, mixed $args,..., array $values) Limits results by comparing a SQL function to a range of values.
 * @method DataMapper like_func() like_func(string $function_name, mixed $args,..., string $value) Limits results by matching a SQL function to a value.
 * @method DataMapper or_like_func() like_func(string $function_name, mixed $args,..., string $value) Limits results by matching a SQL function to a value.
 * @method DataMapper not_like_func() like_func(string $function_name, mixed $args,..., string $value) Limits results by matching a SQL function to a value.
 * @method DataMapper or_not_like_func() like_func(string $function_name, mixed $args,..., string $value) Limits results by matching a SQL function to a value.
 * @method DataMapper ilike_func() like_func(string $function_name, mixed $args,..., string $value) Limits results by matching a SQL function to a value (case insensitive).
 * @method DataMapper or_ilike_func() like_func(string $function_name, mixed $args,..., string $value) Limits results by matching a SQL function to a value (case insensitive).
 * @method DataMapper not_ilike_func() like_func(string $function_name, mixed $args,..., string $value) Limits results by matching a SQL function to a value (case insensitive).
 * @method DataMapper or_not_ilike_func() like_func(string $function_name, mixed $args,..., string $value) Limits results by matching a SQL function to a value (case insensitive).
 * @method DataMapper group_by_func() group_by_func(string $function_name, mixed $args,...) Groups the query by a SQL function.
 * @method DataMapper having_func() having_func(string $function_name, mixed $args,..., string $value) Groups the querying using a HAVING clause.
 * @method DataMapper or_having_func() having_func(string $function_name, mixed $args,..., string $value) Groups the querying using a HAVING clause, via OR.
 * @method DataMapper order_by_func() order_by_func(string $function_name, mixed $args,..., string $direction) Orders the query based on a SQL function.
 *
 * Field -> SQL functions:
 * @method DataMapper where_field_field_func() where_field_func($field, string $function_name, mixed $args,...) Limits results based on a SQL function.
 * @method DataMapper or_where_field_field_func() or_where_field_func($field, string $function_name, mixed $args,...) Limits results based on a SQL function, via OR.
 * @method DataMapper where_in_field_field_func() where_in_field_func($field, string $function_name, mixed $args,...) Limits results by comparing a SQL function to a range of values.
 * @method DataMapper or_where_in_field_field_func() or_where_in_field_func($field, string $function_name, mixed $args,...) Limits results by comparing a SQL function to a range of values.
 * @method DataMapper where_not_in_field_field_func() where_not_in_field_func($field, string $function_name, string $field) Limits results by comparing a SQL function to a range of values.
 * @method DataMapper or_where_not_in_field_field_func() or_where_not_in_field_func($field, string $function_name, mixed $args,...) Limits results by comparing a SQL function to a range of values.
 * @method DataMapper like_field_field_func() like_field_func($field, string $function_name, mixed $args,...) Limits results by matching a SQL function to a value.
 * @method DataMapper or_like_field_field_func() like_field_func($field, string $function_name, mixed $args,...) Limits results by matching a SQL function to a value.
 * @method DataMapper not_like_field_field_func() like_field_func($field, string $function_name, mixed $args,...) Limits results by matching a SQL function to a value.
 * @method DataMapper or_not_like_field_field_func() like_field_func($field, string $function_name, mixed $args,...) Limits results by matching a SQL function to a value.
 * @method DataMapper ilike_field_field_func() like_field_func($field, string $function_name, mixed $args,...) Limits results by matching a SQL function to a value (case insensitive).
 * @method DataMapper or_ilike_field_field_func() like_field_func($field, string $function_name, mixed $args,...) Limits results by matching a SQL function to a value (case insensitive).
 * @method DataMapper not_ilike_field_field_func() like_field_func($field, string $function_name, mixed $args,...) Limits results by matching a SQL function to a value (case insensitive).
 * @method DataMapper or_not_ilike_field_field_func() like_field_func($field, string $function_name, mixed $args,...) Limits results by matching a SQL function to a value (case insensitive).
 * @method DataMapper group_by_field_field_func() group_by_field_func($field, string $function_name, mixed $args,...) Groups the query by a SQL function.
 * @method DataMapper having_field_field_func() having_field_func($field, string $function_name, mixed $args,...) Groups the querying using a HAVING clause.
 * @method DataMapper or_having_field_field_func() having_field_func($field, string $function_name, mixed $args,...) Groups the querying using a HAVING clause, via OR.
 * @method DataMapper order_by_field_field_func() order_by_field_func($field, string $function_name, mixed $args,...) Orders the query based on a SQL function.
 *
 * Subqueries:
 * @method DataMapper select_subquery() select_subquery(DataMapper $subquery, string $alias) Selects the result of a function. Alias is required.
 * @method DataMapper where_subquery() where_subquery(mixed $subquery_or_field, mixed $value_or_subquery) Limits results based on a subquery.
 * @method DataMapper or_where_subquery() or_where_subquery(mixed $subquery_or_field, mixed $value_or_subquery) Limits results based on a subquery, via OR.
 * @method DataMapper where_in_subquery() where_in_subquery(mixed $subquery_or_field, mixed $values_or_subquery) Limits results by comparing a subquery to a range of values.
 * @method DataMapper or_where_in_subquery() or_where_in_subquery(mixed $subquery_or_field, mixed $values_or_subquery) Limits results by comparing a subquery to a range of values.
 * @method DataMapper where_not_in_subquery() where_not_in_subquery(mixed $subquery_or_field, string $field, mixed $values_or_subquery) Limits results by comparing a subquery to a range of values.
 * @method DataMapper or_where_not_in_subquery() or_where_not_in_subquery(mixed $subquery_or_field, mixed $values_or_subquery) Limits results by comparing a subquery to a range of values.
 * @method DataMapper like_subquery() like_subquery(DataMapper $subquery, string $value, string $match = 'both') Limits results by matching a subquery to a value.
 * @method DataMapper or_like_subquery() like_subquery(DataMapper $subquery, string $value, string $match = 'both') Limits results by matching a subquery to a value.
 * @method DataMapper not_like_subquery() like_subquery(DataMapper $subquery, string $value, string $match = 'both') Limits results by matching a subquery to a value.
 * @method DataMapper or_not_like_subquery() like_subquery(DataMapper $subquery, string $value, string $match = 'both') Limits results by matching a subquery to a value.
 * @method DataMapper ilike_subquery() like_subquery(DataMapper $subquery, string $value, string $match = 'both') Limits results by matching a subquery to a value (case insensitive).
 * @method DataMapper or_ilike_subquery() like_subquery(DataMapper $subquery, string $value, string $match = 'both') Limits results by matching a subquery to a value (case insensitive).
 * @method DataMapper not_ilike_subquery() like_subquery(DataMapper $subquery, string $value, string $match = 'both') Limits results by matching a subquery to a value (case insensitive).
 * @method DataMapper or_not_ilike_subquery() like_subquery(DataMapper $subquery, string $value, string $match = 'both') Limits results by matching a subquery to a value (case insensitive).
 * @method DataMapper having_subquery() having_subquery(string $field, DataMapper $subquery) Groups the querying using a HAVING clause.
 * @method DataMapper or_having_subquery() having_subquery(string $field, DataMapper $subquery) Groups the querying using a HAVING clause, via OR.
 * @method DataMapper order_by_subquery() order_by_subquery(DataMapper $subquery, string $direction) Orders the query based on a subquery.
 *
 * Related Subqueries:
 * @method DataMapper where_related_subquery() where_related_subquery(mixed $related_model, string $related_field, DataMapper $subquery) Limits results based on a subquery.
 * @method DataMapper or_where_related_subquery() or_where_related_subquery(mixed $related_model, string $related_field, DataMapper $subquery) Limits results based on a subquery, via OR.
 * @method DataMapper where_in_related_subquery() where_in_related_subquery(mixed $related_model, string $related_field, DataMapper $subquery) Limits results by comparing a subquery to a range of values.
 * @method DataMapper or_where_in_related_subquery() or_where_in_related_subquery(mixed $related_model, string $related_field, DataMapper $subquery) Limits results by comparing a subquery to a range of values.
 * @method DataMapper where_not_in_related_subquery() where_not_in_related_subquery(mixed $related_model, string $related_field, DataMapper $subquery) Limits results by comparing a subquery to a range of values.
 * @method DataMapper or_where_not_in_related_subquery() or_where_not_in_related_subquery(mixed $related_model, string $related_field, DataMapper $subquery) Limits results by comparing a subquery to a range of values.
 * @method DataMapper having_related_subquery() having_related_subquery(mixed $related_model, string $related_field, DataMapper $subquery) Groups the querying using a HAVING clause.
 * @method DataMapper or_having_related_subquery() having_related_subquery(mixed $related_model, string $related_field, DataMapper $subquery) Groups the querying using a HAVING clause, via OR.
 *
 * Array Extension:
 * @method array to_array() to_array($fields = '') NEEDS ARRAY EXTENSION.  Converts this object into an associative array.  @link DMZ_Array::to_array
 * @method array all_to_array() all_to_array($fields = '') NEEDS ARRAY EXTENSION.  Converts the all array into an associative array.  @link DMZ_Array::all_to_array
 * @method array|bool from_array() from_array($data, $fields = '', $save = FALSE) NEEDS ARRAY EXTENSION.  Converts $this->all into an associative array.  @link DMZ_Array::all_to_array
 *
 * CSV Extension
 * @method bool csv_export() csv_export($filename, $fields = '', $include_header = TRUE) NEEDS CSV EXTENSION.  Exports this object as a CSV file.
 * @method array csv_import() csv_import($filename, $fields = '', $header_row = TRUE, $callback = NULL) NEEDS CSV EXTENSION.  Imports a CSV file into this object.
 *
 * JSON Extension:
 * @method string to_json() to_json($fields = '', $pretty_print = FALSE) NEEDS JSON EXTENSION.  Converts this object into a JSON string.
 * @method string all_to_json() all_to_json($fields = '', $pretty_print = FALSE) NEEDS JSON EXTENSION.  Converts the all array into a JSON string.
 * @method bool from_json() from_json($json, $fields = '') NEEDS JSON EXTENSION.  Imports the values from a JSON string into this object.
 * @method void set_json_content_type() set_json_content_type() NEEDS JSON EXTENSION.  Sets the content type header to Content-Type: application/json.
 *
 * SimpleCache Extension:
 * @method DataMapper get_cached() get_cached($limit = '', $offset = '') NEEDS SIMPLECACHE EXTENSION.  Enables cacheable queries.
 * @method DataMapper clear_cache() get_cached($segment,...) NEEDS SIMPLECACHE EXTENSION.  Clears a cache for the specfied segment.
 *
 */
class DataMapper implements IteratorAggregate {
}