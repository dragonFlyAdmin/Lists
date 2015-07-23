<?php

namespace HappyDemon\Lists;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;
use Input;
use Exception;

abstract class Table
{
    /**
     * Model instance we'll be working with
     *
     * @var Eloquent
     */
    public $model = null;

    /**
     * The name of the model (used for giving table TR an ID attribute
     *
     * @var null|string
     */
    protected $model_name = null;

    /**
     * Field definitions
     * @var Column[]
     */
    public $fields = [];

    /**
     * DataTables js object initialisation options
     * @var array
     */
    protected $options = [];

    /**
     * All searchable columns.
     * @var array
     */
    public $searchables = [];

    /**
     * If not false we'll return the assigned string as error.
     *
     * @var bool|string
     */
    protected $error = false;

    /**
     * @var int
     */
    protected $total_records = 0;


    public function __construct()
    {
        // Set the base model
        $this->model = $this->model();
        $this->model_name = strtolower(class_basename($this->model));

        // Set the kernel identifier if it wasn't previously
        if ($this->kernel_identifier == null)
        {
            $this->kernel_identifier = $this->model_name;
        }

        // If no table id was set
        if ($this->html_id == null)
        {
            $this->html_id = 'table-' . snake_case($this->kernel_identifier, '-');
        }

        // If no javascript var name was set
        if ($this->js_var == null)
        {
            $this->js_var = 'table' . studly_case($this->kernel_identifier);
        }

        // Load the fields
        $fields = $this->setup();

        // if fields were returned instead of set by $this->addField()
        if (is_array($fields))
        {
            $this->fields = $fields;
        }

        // If actions were defined, prepend the fields with a checkbox field
        if (count($this->actions))
        {
            $this->prependCheckboxColumn();
        }

        // Fire table init event
        Event::fire('table.' . $this->kernel_identifier . 'init', [$this]);
    }

    /**
     * Return fully formatted javascript.
     *
     * It will return a variable with a dataTables object
     *
     * @return string
     */
    public function definition()
    {
        $options = $this->dataTableOptions();

        return view('lists::render_tag', [
            'options'    => json_encode($options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'tag'        => $this->html_id,
            'var'        => $this->js_var,
            'checkboxes' => count($this->actions) > 0,
            'actions'    => $this->parseActions()
        ])->render();
    }

    /**
     * Prepare the options we should pass to the dataTables constructor.
     *
     * @return array
     */
    public function dataTableOptions()
    {
        $columns = [];
        $ordering = false;

        foreach ($this->fields as $name => $field)
        {
            if ($ordering == false && $field->sortable == true)
            {
                $ordering = true;
            }

            $meta = $field->getDefinitionMeta($this);

            $format = [
                'name'       => $name,
                'orderable'  => $field->sortable,
                'searchable' => $field->searchable,
                'title'      => $field->title
            ];

            // If a render function was defined add and call it.
            if ($field->format === false && $field->render !== false)
            {
                $format['render'] = view('lists::render_func', ['content' => (string) call_user_func($field->render, $field)]);
            }

            // If the field can be parsed, add it as orthogonal data
            if ($field->format !== false)
            {
                $format['data'] = $name;

                $format['render'] = [
                    '_'    => 'value',
                    'sort' => 'data'
                ];
            }
            // Otherwise normal data
            else
            {
                $format['data'] = $name;
            }

            // If default content is set add it
            if ($field->default !== false)
            {
                $format['defaultContent'] = $field->default;
            }

            // Set the column type if needed
            if ($meta['type'] !== false)
            {
                $format['type'] = $meta['type'];
            }

            // Register the column
            $columns[] = $format;
        }

        return array_merge($this->options, [
            'serverSide' => true,
            'processing' => true,
            'ajax'       => $this->route(), //Route to data
            'columns'    => $columns,
            'ordering'   => $ordering
        ]);
    }

    /**
     * Returns data formatted for a DataTables request
     *
     * @return array
     */
    public function output()
    {
        $data = $this->loadData();

        if ($this->error !== false)
        {
            return ['error' => $this->error];
        }

        return [
            'draw'            => (int) Input::get('draw', 0),
            'recordsTotal'    => $this->total_records,
            'recordsFiltered' => count($data),
            'data'            => $data,
        ];
    }

    /**
     * Load the data that's requested
     *
     * @return Collection
     */
    protected function loadData()
    {
        try
        {
            $this->prepareMetaData();

            // Prepare the model instance
            $query = (new Model($this, Input::get('columns', [])))
                ->setModel($this->model)
                ->relations()
                ->select()
                ->search(Input::get('search', []))
                ->order(Input::get('order', []))
                ->model;

            // Count all records
            $total = clone $query;
            $this->total_records = $total->count();

            // Limit records
            $data = $query->skip(Input::get('start', 0))
                          ->take(Input::get('length', 10))
                          ->get();

            // Return collection
            return $this->parseData($data);
        }
        catch (Exception $e)
        {
            $this->error = $e->getMessage();
        }
    }

    /**
     * Prepare meta data to avoid overhead on large data sets
     */
    public function prepareMetaData()
    {
        // Parse extra selects if they were defined
        if (count($this->select) > 0)
        {
            foreach ($this->select as $id => $select)
            {
                $table = $this->getModelTableName();

                if (str_contains($select, '.'))
                {
                    $column = explode('.', $select);

                    $as = array_pop($column);


                    // Get the table name
                    if ($column[0] != '(:table)')
                    {
                        // If the first column equals to the table name, remove it
                        if ($column[0] == $table)
                        {
                            unset($column[0]);
                        }

                        // There's still elements we'll check  the model's relations
                        if ($column > 0)
                        {
                            $relations = implode('.', $column);
                            $table = $this->getModelTableName($relations);
                        }
                    }
                }
                else
                {
                    $as = $select;
                }

                // Overwrite the select statement
                unset($this->select[$id]);
                $this->select[$as] = $table . '.' . $as . ' AS ' . $as;
            }
        }

        // Get the meta data of all the defined fields (columns)
        foreach ($this->fields as $key => $field)
        {
            $meta = $field->getRequestMeta($this);

            // If a relation was set, store it
            if ($meta['relation'] != null)
            {
                if (!in_array($meta['relation'], $this->relationships))
                {
                    $this->relationships[] = $meta['relation'];
                }
            }

            // Set the select
            if (isset($meta['select']))
            {
                $this->select[$key] = $meta['select'];
            }

            // Load all searchable files
            if ($meta['searchable'] !== false)
            {
                $this->searchables[] = $meta['searchable'];
            }
        }

        // make sure the primary key is always loaded
        $pk = $this->getModelPrimaryKey();

        if (!array_key_exists($pk, $this->select))
        {
            $this->select[$pk] = $this->getModelTableName() . '.' . $pk . ' AS ' . $pk;
        }
    }

    /**
     * Return the URL to send data requests to.
     *
     * @return string
     */
    protected function route()
    {
        $route = ($this->route) ?: config('lists.route');

        return route($route, ['table' => $this->kernel_identifier]);
    }

    /**
     * Return the URL to send action requests to.
     *
     * @return string
     */
    protected function action_route($action)
    {
        $route = ($this->action_route) ?: config('lists.perform');

        return route($route, ['table' => $this->kernel_identifier, 'action' => $action]);
    }

    /**
     * Get the correct table name.
     *
     * @param null|string $relations
     *
     * @return string
     */
    public function getModelTableName($relations = null)
    {
        return $this->getRelationInfo($relations)['table'];
    }

    /**
     * Get the correct primary key name.
     *
     * @param null|string $relations
     *
     * @return string
     */
    public function getModelPrimaryKey($relations = null)
    {
        return $this->getRelationInfo($relations)['primary_key'];
    }

    /**
     * Store the relation's table name & primary key
     * @var array
     */
    protected $relation_cache = [];

    /**
     * Retrieve a relation or this model's table name & primary key name.
     *
     * Loops over relations to get the needed data.
     *
     * @param $relation
     *
     * @return array
     */
    protected function getRelationInfo($relation)
    {
        if (!array_key_exists($relation, $this->relation_cache))
        {
            $instance = $this->model;

            // Loop over the (nested) relation to get the table name
            if ($relation != null)
            {
                $relations = explode('.', $relation);
                foreach ($relations as $rel)
                {
                    $instance = call_user_func([$instance, $rel]);
                }
            }

            // Store the table's & primary key's name
            $this->relation_cache[$relation] = [
                'table'       => $instance->getTable(),
                'primary_key' => $instance->getKeyName()
            ];
        }

        return $this->relation_cache[$relation];
    }

    /**
     * Prepend the table with a checkbox column
     */
    protected function prependCheckboxColumn()
    {
        $newColumn = $this->newField()
                          ->set([
                              'name'       => 'list_keys',
                              'title'      => view('lists::header_checkbox'),
                              'orderable'  => false,
                              'searchable' => false,
                              'render'     => [$this, 'renderCheckbox'],
                              'column'     => $this->getModelTableName() . '.' . $this->getModelPrimaryKey(),
                              'as'         => $this->getModelPrimaryKey()
                          ]);

        array_unshift($this->fields, ['list_keys' => $newColumn]);
    }

    // Render the column as a checkbox on the client-side
    protected function renderCheckbox($field)
    {
        return view('lists::render_checkbox', compact('field'));
    }

    /**
     * Parse the actions into data that the UI can use.
     *
     * @return array
     */
    protected function parseActions()
    {
        $actions = [];

        foreach ($this->actions as $slug => $definition)
        {
            $actions[] = [
                'url'    => $this->action_route($slug),
                'title'  => $definition['title'],
                'slug'   => $slug,
                'status' => $definition['messages']['active']
            ];
        }

        return $actions;
    }

    /**
     * Parse the result set into the format that DataTables needs
     *
     * @param $records
     *
     * @return array
     */
    protected function parseData(Collection $records)
    {
        $output = [];

        $prependCheckBox = count($this->actions) > 0;

        foreach ($records as $record)
        {
            // DataTable specific meta data
            $format = [
                'DT_RowId' => $this->row_format_id($record),
            ];

            // Add row TR specific attributes
            $attrs = $this->row_attributes($record);

            if (count($attrs) > 0)
            {
                $format['DT_RowAttr'] = $attrs;
            }

            // Add row TR specific class
            $class = $this->row_class($record);

            // If it's an array turn it into a string
            if (is_array($class))
            {
                $class = implode(' ', $class);
            }

            // If it's not empty, add it
            if ($class != '')
            {
                $format['DT_RowClass'] = $class;
            }

            // Add row TR specific data attributes
            $data_attr = $this->row_data_attributes($record);

            if (count($data_attr) > 0)
            {
                $format['DT_RowData'] = $data_attr;
            }

            // If we're adding checkboxes
            if ($prependCheckBox)
            {
                // Make sure the first field gets the primary key sent over.
                $format['list_checkbox'] = $record->{$this->getModelPrimaryKey()};
            }

            // Parse the columns
            foreach ($this->fields as $name => $field)
            {
                // Get the field's column data
                $format[$name] = ($field->as != null) ? $record->{$field->as} : null;

                // If there's a specific method to format the data call it
                if ($field->format !== false)
                {
                    // return as orthogonal data
                    $format[$name] = [
                        'value' => call_user_func($field->format, $format[$name], $record),
                        'data'  => (is_object($format[$name]) && is_a($format[$name], 'Carbon\Carbon')) ? (string) $format[$name] : $format[$name]
                    ];
                }

                unset($this->select[$name]);
            }

            // If there were extra selects, add their values to the result
            if (count($this->select) > 0)
            {
                foreach ($this->select as $name => $statement)
                {
                    $format[$name] = $record->{$name};
                }
            }

            $output[] = $format;
        }

        return $output;
    }

    /**
     * Perform an action on the supplied ids.
     *
     * @param string $action
     * @param array  $ids
     *
     * @return array
     */
    public function perform($action, $ids)
    {
        // If no ids were supplied return empty success type
        if (count($ids) == 0)
        {
            return [
                'status'  => 'success',
                'type'    => 'empty',
                'message' => ''
            ];
        }

        // Get the action definition and perform the action
        $definition = $this->actions[$action];

        $perform = call_user_func($definition['action'], $ids);

        // When it returns false return the default error msg
        if ($perform === false)
        {
            return [
                'status'  => 'error',
                'message' => trans($definition['messages']['error'])
            ];
        }

        // If didn't return true and error occurred with a custom message
        if ($perform !== true)
        {
            return [
                'status'  => 'error',
                'message' => trans($perform)
            ];
        }

        // Otherwise it's a success!
        return [
            'status'  => 'success',
            'type'    => 'complete',
            'message' => trans($definition['messages']['success'])
        ];
    }

    /**
     * Define an action that the end-user can perform on multiple entities from the table.
     *
     * @param string         $slug     url slug to route the action to
     * @param string         $title    title tat will be displayed in the action list
     * @param Callable|array $callable optional, an anon function or array [$object, $method] that can be called
     * @param array          $messages
     *
     * @return $this
     * @throws \Exception
     */
    protected function defineAction($slug, $title, $callable = false, array $messages = null)
    {
        $messages = ($messages)
            ?: array_merge([
                'error'   => 'The "' . $slug . '" action failed.',
                'success' => 'The "' . $slug . '" action was a success.',
                'active'  => 'Processing "' . $slug . '" action.'
            ], $messages);


        // If callable isn't set we'll check if there's a method defined on this object
        if ($callable == false)
        {
            $perform_method = 'perform' . studly_case($slug);

            if (!method_exists($this, $perform_method))
            {
                Throw new Exception('The "' . $slug . '" action has no method defined on "' . get_class($this) . '"');
            }

            $callable = [$this, $perform_method];
        }

        $this->actions[$slug] = [
            'title'    => $title,
            'messages' => $messages,
            'action'   => $callable
        ];

        return $this;
    }

    /**
     * Add a field to the list.
     *
     * @param       $name       Name of the field
     * @param array $properties see Column::$options
     *
     * @return $this
     */
    protected function addField($name, $properties = [])
    {
        $this->fields[$name] = $this->newField()->set($properties);

        return $this;
    }

    /**
     * Initiate a new column instance
     *
     * @return Column
     */
    protected function newField()
    {
        return new Column($this->model);
    }

    /**
     * A list of actions the user is able to perform on the table.
     *
     * @var array
     */
    public $actions = [];

    /**
     * The key to which this table definition will be bound in the Http kernel.
     *
     * @var string
     */
    protected $kernel_identifier = null;

    /**
     * The name of the route to use for the data requests.
     *
     * @var string|false
     */
    protected $route = false;

    /**
     * The name of the route to use for the action requests.
     *
     * @var string|false
     */
    protected $action_route = false;

    /**
     * The id of the table for which we'll generate the javascript.
     *
     * @var string
     */
    public $html_id = null;

    /**
     * The name of the javascript variable the dataTable object gets assigned to.
     *
     * @var string
     */
    public $js_var = null;

    /**
     * Contains all relationships that should also be loaded
     *
     * @var array
     */
    public $relationships = [];

    /**
     * Contains all the fields that need to be selected.
     *
     * @var array
     */
    public $select = [];

    /**
     * Return the 'id' attribute for every single row's TR in the table.
     *
     * ! Overwrite if needed.
     *
     * @param $row
     *
     * @return string
     */
    protected function row_format_id($row)
    {
        return $this->html_id . '-row-' . $row->{$this->getModelPrimaryKey()};
    }

    /**
     * Return extra attributes that should be added to a single row's TR in the table.
     *
     * ! Overwrite if needed.
     *
     * @param $row
     *
     * @return array
     */
    protected function row_attributes($row)
    {
        return [];
    }

    /**
     * Return data attributes that should be added to a single row's TR in the table.
     *
     * ! Overwrite if needed.
     *
     * @param $row
     *
     * @return array
     */
    protected function row_data_attributes($row)
    {
        return [];
    }

    /**
     * Return class name(s) that should be added to a single row's TR in the table.
     *
     * ! Overwrite if needed.
     *
     * @param $row
     *
     * @return string|array
     */
    protected function row_class($row)
    {
        return '';
    }

    /**
     * Define all your fields here.
     *
     * @return array
     */
    abstract public function setup();

    /**
     * Prepare the model instance for further queries.
     *
     * @return Eloquent
     */
    abstract public function model();

    /**
     * Set js dataTable options directly as a function.
     *
     * @param string $option Name of the option prefixed with 'set'
     * @param array  $arguments
     *
     * @return $this
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        $value = $arguments[0];

        // These are set by the object, the developer should not be allowed to change them
        $blackListed = ['serverSide', 'processing', 'ajax', 'data', 'columns', 'columnDefs', 'ordering'];

        // Only set options if the call was prefixed with 'set'
        if (starts_with('set', $name))
        {
            // remove 'set' from the name
            $option = lcfirst(substr($name, 0, 3));

            // Only set if not blacklisted
            if (!in_array($option, $blackListed))
            {
                $this->options[$option] = $value;

                return $this;
            }

            Throw new Exception($option . ' can\'t be set on "' . get_class($this) . '".');
        }
    }
}