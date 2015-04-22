<?php

namespace mii\db;


class ORM
{

    // The database table name
    protected $table = '';

    /**
     * @var mixed
     */
    protected $_order_by = false;

    // The database fields
    protected $_data = array();

    /**
     * @var  array  Data that's changed since the object was loaded
     */
    protected $_changed = array();

    protected $_loaded;

    public static function find($id = null) {
        $class = new static();

        if($id) {
            return $class->query()->where('id', '=', $id)->one();
        }

        return $class->query();
    }

    public static function all() {
        $class = new static();

        return $class->query()->get();
    }

    public function query() {
        $query = (new Query)->select($this->fields())->from($this->get_table())->as_object(static::class);

        if($this->_order_by) {
            foreach($this->_order_by as $column => $direction) {
                $query->order_by($column, $direction);
            }
        }

        return $query;
    }


    /**
     * Create a new ORM model instance
     *
     * @param array $values
     * @return void
     */
    public function __construct($values = [], $loaded = NULL)
    {
        if($values)
            $this->fill_with($values);

        $this->_loaded = $loaded;
    }


    public function __get($key) {
        return $this->get($key);
    }

    /**
     * Retrieve items from the $data array.
     *
     * 	<h1><?=$blog_entry->title?></h1>
     * 	<p><?=$blog_entry->content?></p>
     *
     * @param string $key the field name to look for
     *
     * @throws ORM_Exception
     *
     * @return String
     */
    public function get($key)
    {
        if (isset($this->_data[$key]) OR array_key_exists($key, $this->_data))
            return $this->_data[$key];

        throw new ORMException('Field '.$key.' does not exist in '.get_class($this).'!', array(), '');
    }


    public function __set($key, $value) {
        return $this->set_field($key, $value);
    }


    public function set($values, $value = NULL) {

        if ( ! is_array($values))
        {
            $this->set_field($values, $value);
        } else {
            foreach ($values as $key => $value)
            {
                $this->set_field($key, $value);
            }
        }

        return $this;
    }

    /**
     * Set the items in the $data array.
     *
     * @param string $key   the field name to set
     * @param string $value the value to set to
     *
     * @throws ORM_Exception
     * @return $this
     */
    public function set_field($key, $value)
    {
        if (array_key_exists($key, $this->_data) AND $value !== $this->_data[$key]) {
            $this->_data[$key] = $value;
            if($this->_loaded !== NULL) {
                $this->_changed[$key] = true;
            }
        }
        return $this;
    }

    /**
     * sleep method for serialization
     *
     * @return array
     */
    public function __sleep()
    {
        // Store only information about the object without db property
        return array_diff(array_keys(get_object_vars($this)), array('_db'));
    }

    /**
     * Magic isset method to test _data
     *
     * @param string $name the property to test
     *
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->_data[$name]);
    }


    /**
     * Determine if this model is loaded.
     *
     * @return bool
     */
    public function loaded()
    {
        return (bool) $this->_loaded;
    }

    /**
     * Gets an array version of the model
     *
     * @return array
     */
    public function as_array()
    {
        return $this->_data;
    }

    /**
     * Gets the table name for this object
     *
     * @return string
     */
    public function get_table()
    {
        return $this->table;
    }


    public function changed($field_name = '') {
        return $this->_changed;
        return isset($this->_changed[$field_name]);
    }


    /**
     * Mass sets object properties. Never pass $_POST into this method directly.
     * Always use something like array_key_intersect() to filter the array.
     *
     * @param array $data the data to set
     *
     * @return null
     *
     */
    public function fill_with(array $data)
    {
        foreach (array_intersect_key($data, $this->_data) as $key => $value)
        {
            $this->$key = $value;
        }
        return $this;
    }



    /**
     * Saves the model to your database. If $data['id'] is empty, it will do a
     * database INSERT and assign the inserted row id to $data['id'].
     * If $data['id'] is not empty, it will do a database UPDATE.
     *
     * @param mixed $validation a manual validation object to combine the model properties with
     *
     * @return int
     */
    public function update($validation = NULL)
    {
        if(!count($this->_changed))
            return 0;

        if($this->on_update() === FALSE)
            return $this;


        return (new Query)
            ->update($this->get_table())
            ->set(array_intersect_key($this->_data, $this->_changed))
            ->where('id', '=', $this->_data['id'])
            ->execute();
    }

    /**
     * Saves the model to your database. If $data['id'] is empty, it will do a
     * database INSERT and assign the inserted row id to $data['id'].
     * If $data['id'] is not empty, it will do a database UPDATE.
     *
     * @param mixed $validation a manual validation object to combine the model properties with
     *
     * @return int
     */
    public function create($validation = NULL)
    {
        if($this->on_create() === FALSE) {
            return $this;
        }

        $columns = array_keys($this->_data);
        $id = (new Query())
            ->insert($this->get_table())
            ->columns($columns)
            ->values($this->_data)
            ->execute();

        $this->_loaded = true;

        $this->_data['id'] = $id[0];

        return $this;

    }


    public function by_field($field, $action, $value) {

        return $this->load(
            DB::select_array(array_keys($this->_data))->where($field, $action, $value),
            1);
    }



    public function on_create() {

    }

    public function on_update() {

    }

    /**
     * Deletes the current object's associated database row.
     * The object will still contain valid data until it is destroyed.
     *
     * @return integer
     */
    public function delete()
    {
        if ($this->loaded())
        {
            $this->_loaded = false;

            return (new Query)
                ->table($this->get_table())
                ->where('id', '=', $this->_data['id'])
                ->delete();
        }

        throw new ORM_Exception('Cannot delete a non-loaded model '.get_class($this).'!', array(), array());
    }

    /**
     * Returns an associative array, where the keys of the array is set to $key
     * column of each row, and the value is set to the $display column.
     * You can optionally specify the $query parameter to pass to filter for
     * different data.
     *
     * @param array  $key       the key to use for the array
     * @param array  $where     the value to use for the display
     * @param array  $where     the where clause
     *
     * @return Database_Result
     */
    public static function select_list($key, $display, $first = NULL, Database_Query_Builder_Select $query = NULL)
    {
        $instance = new static;
        $rows = array();


        if($first) {
            if (is_array($first))
            {
                $rows = $first;
            }
            else
            {
                $rows[0] = $first;
            }

        }

        $array_display = FALSE;
        $select_array = array($key);
        if (is_array($display))
        {
            $array_display = TRUE;
            $select_array = array_merge($select_array, $display);
        }
        else
        {
            $select_array[] = $display;
        }

        if ($query) // Fetch selected rows
        {
            $query = $instance->load($query->select_array($select_array), NULL);
        }
        else // Fetch all rows
        {
            $query = (new Query)->select($instance->fields())->from($instance->get_table())->as_object(static::class)->all();
        }


        foreach ($query as $row)
        {
            if ($array_display)
            {
                $display_str = array();
                foreach ($display as $text)
                    $display_str[] = $row->$text;
                $rows[$row->$key] = implode(' - ', $display_str);
            }
            else
            {
                $rows[$row->$key] = $row->$display;
            }
        }

        return $rows;
    }

    /**
     * Returns an array of the columns in this object.
     * Useful for DB::select_array().
     *
     * @return array
     */
    public function fields()
    {
        $fields = [];

        foreach ($this->_data as $key => $value)
            $fields[] = $this->table.'.'.$key;

        return $fields;
    }


    /**
     * Performs mass relations
     *
     * @param string $key    the key to set
     * @param array  $values an array of values to relate the model with
     *
     * @return none
     */
    public function relate($key, array $values)
    {
        if (in_array($key, $this->_has_many))
        {
            $related_table = ORM::factory(Inflector::singular($key))->get_table_name();

            $this_key = Inflector::singular($this->_table_name).'_id';
            $f_key = Inflector::singular($related_table).'_id';
            foreach ($values as $value)
                // See if this is already in the join table
                if ( ! count(DB::select('*')->from($this->_table_name.'_'.$related_table)->where($f_key, '=', $value)->where($this_key, '=', $this->_data['id'])->execute($this->_db)))
                {
                    // Insert
                    DB::insert($this->_table_name.'_'.$related_table)->columns(array($f_key, $this_key))->values(array($value, $this->_data['id']))->execute($this->_db);
                }
        }
    }

    /**
     * Finds relations of a has_many relationship
     *
     * 	// Finds all roles belonging to a user
     * 	$user->find_related('roles');
     *
     * @param string                        $key   the model name to look for
     * @param Database_Query_Builder_Select $query A select object to filter results with
     *
     * @return Database_Result
     */
    public function find_related($key, $foreign_key = NULL, Database_Query_Builder_Select $query = NULL)
    {
        $model = 'Model_'.Text::ucfirst($key, '_');

        $temp = new $model();
        if ( ! $query)
        {
            $query = DB::select_array($temp->fields());
        }

        if ($foreign_key OR $temp->field_exists(Inflector::singular($this->_table_name).'_id')) // Look for a one to many relationship
        {
            if($foreign_key)
                return $temp->load($query->where($foreign_key, '=', $this->id), NULL);
            return $temp->load($query->where(Inflector::singular($this->_table_name).'_id', '=', $this->id), NULL);
        }
        elseif (in_array($key, $this->_has_many)) // Get a many to many relationship.
        {
            $related_table = ORM::factory(Inflector::singular($key))->get_table_name();
            $join_table = $this->_table_name.'_'.$related_table;
            $this_key = Inflector::singular($this->_table_name).'_id';
            $f_key = Inflector::singular($related_table).'_id';

            $columns = ORM::factory(Inflector::singular($key))->fields();

            $query = $query->from($related_table)->join($join_table)->on($join_table.'.'.$f_key, '=', $related_table.'.id');
            $query->where($join_table.'.'.$this_key, '=', $this->_data['id']);
            return $temp->load($query, NULL);
        }
        else
        {
            throw new ORM_Exception('Relationship "'.$key.'" doesn\'t exist in '.get_class($this));
        }
    }

    /**
     * Finds parents of a belongs_to model
     *
     * 	// Finds all users related to a role
     * 	$role->find_parent('users');
     *
     * @param string                        $key   the model name to look for
     * @param Database_Query_Builder_Select $query A select object to filter results with
     *
     * @return Database_Result
     */
    public function find_parent($key, Database_Query_Builder_Select $query = NULL)
    {
        $parent = ORM::factory(Inflector::singular($key));
        $columns = $parent->fields();

        if ( ! $query)
        {
            $query = DB::select_array($parent->fields());
        }

        if ($this->field_exists(strtolower($key).'_id')) // Look for a one to many relationship
        {
            return $parent->load($query->where('id', '=', $this->_data[strtolower($key).'_id']), NULL);
        }
        elseif(in_array($key, $this->_belongs_to)) // Get a many to many relationship.
        {
            $related_table = $parent->get_table_name();
            $join_table = $related_table.'_'.$this->_table_name;
            $f_key = Inflector::singular($this->_table_name).'_id';
            $this_key = Inflector::singular($related_table).'_id';

            $columns = ORM::factory(Inflector::singular($key))->fields();

            $query = $query->join($join_table)->on($join_table.'.'.$this_key, '=', $related_table.'.id')->from($related_table)->where($join_table.'.'.$f_key, '=', $this->_data['id']);
            return $parent->load($query, NULL);
        }
        else
        {
            throw new ORM_Exception('Relationship "'.$key.'" doesn\'t exist in '.get_class($this));
        }
    }

    /**
     * Get model from the database
     *
     * @param  array  $columns
     * @return ORM|static[]
     */
    public static function find_by($field, $action, $value)
    {
        $instance = new static;

        return $instance->load(
            DB::select_array($instance->fields())->where($field, $action, $value),
            1);
    }

    public static function find_all_by($field, $action, $value)
    {
        $instance = new static;

        return $instance->load(
            DB::select_array(array_keys($instance->_data))->where($field, $action, $value));
    }

    public static function find_all_by_ids($ids)
    {
        $instance = new static;

        return $instance->load(
            DB::select_array(array_keys($instance->_data))
                ->where('id', 'IN', DB::expr('('.implode(',', $ids).')'))
        );
    }


    /**
     * Tests if a many to many relationship exists
     *
     * Model must have a _has_many relationship with the other model, which is
     * passed as the first parameter in plural form without the Model_ prefix.
     *
     * The second parameter is the id of the related model to test the relationship of.
     *
     * 	$user = new Model_User(1);
     * 	$user->has('roles', Model_Role::LOGIN);
     *
     * @param string $key   the model name to look for (plural)
     * @param string $value an id to search for
     *
     * @return bool
     */
    public function has($key, $value)
    {
        $related_table = ORM::factory(Inflector::singular($key))->get_table_name();
        $join_table = $this->_table_name.'_'.$related_table;
        $f_key = Inflector::singular($related_table).'_id';
        $this_key = Inflector::singular($this->_table_name).'_id';

        if (in_array($key, $this->_has_many))
        {
            return (bool) DB::select($related_table.'.id')->
            from(ORM::factory(Inflector::singular($key))->get_table_name())->
            where($join_table.'.'.$this_key, '=', $this->_data['id'])->
            where($join_table.'.'.$f_key, '=', $value)->
            join($join_table)->on($join_table.'.'.$f_key, '=', $related_table.'.id')->
            execute($this->_db)->count();
        }
        return FALSE;
    }

    /**
     * Removes a has_many relationship if you aren't using innoDB (shame on you!)
     *
     * Model must have a _has_many relationship with the other model, which is
     * passed as the first parameter in plural form without the Model_ prefix.
     *
     * The second parameter is the id of the related model to remove.
     *
     * @param string $key the model name to look for
     * @param string $id  an id to search for
     *
     * @return integer
     */
    public function remove($key, $id)
    {
        $related = ORM::factory(Inflector::singular($key));

        return DB::delete($this->_table_name.'_'.$related->get_table())->where(Inflector::singular($related->get_table_name()).'_id', '=', $id)->where(Inflector::singular($this->_table_name).'_id', '=', $this->_data['id'])->execute($this->_db);
    }

    /**
     * Removes all relationships of a model
     *
     * Model must have a _has_many or _belongs_to relationship with the other model, which is
     * passed as the first parameter in plural form without the Model_ prefix.
     *
     * @param string $key the model name to look for
     *
     * @return integer
     *
     */
    public function remove_all($key)
    {
        if (in_array($key, $this->_has_many))
        {
            return DB::delete($this->_table_name.'_'.ORM::factory(Inflector::singular($key))->get_table_name())->where(Inflector::singular($this->_table_name).'_id', '=', $this->id)->execute($this->_db);
        }
        else if (in_array($key, $this->_belongs_to))
        {
            return DB::delete(ORM::factory(Inflector::singular($key))->get_table_name().'_'.$this->_table_name)->where(Inflector::singular($this->_table_name).'_id', '=', $this->id)->execute($this->_db);
        }
    }

    /**
     * Removes a parent relationship of a belongs_to
     *
     * @param string $key the model name to look for in plural form, without Model_ prefix
     *
     * @return integer
     */
    public function remove_parent($key)
    {
        return DB::delete(ORM::factory(Inflector::singular($key))->get_table_name().'_'.$this->_table_name)->where(Inflector::singular($this->_table_name).'_id', '=', $this->id)->execute($this->_db);
    }


    /**
     * Returns if the specified field exists in the model
     *
     * @return bool
     */
    public function field_exists($field)
    {
        return array_key_exists($field, $this->_data);
    }

}
