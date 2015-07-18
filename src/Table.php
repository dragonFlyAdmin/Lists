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
     * The key to which this table definition will be bound in the Http kernel.
     *
     * @var string
     */
    protected $kernel_identifier = null;

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
     * Contains all relationships that should also be loaded
     *
     * @var array
     */
    public $relationships = [];

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
        if ($this->table_id == null)
        {
            $this->table_id = 'table-' . $this->model_name;
        }

        // Load the fields
        $fields = $this->fields();

        // if fields were returned instead of set by $this->addField()
        if (is_array($fields))
        {
            $this->fields = $fields;
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
            'options' => json_encode($options, JSON_PRETTY_PRINT),
            'tag'     => 'table-' . snake_case($this->model_name),
            'var'     => 'table_' . snake_case($this->model_name)
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

            if ($field->default !== false)
            {
                $format['defaultContent'] = $field->default;
            }

            if ($meta['type'] !== false)
            {
                $format['type'] = $meta['type'];
            }

            $columns[] = $format;
        }

        return array_merge($this->options, [
            'serverSide' => true,
            'processing' => true,
            'ajax'       => route(config('lists.route'), ['table' => $this->kernel_identifier]), //Route to data
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
    }

    /**
     * Get the correct table name.
     *
     * Loops over relations to get the right name.
     *
     * @param null|string $relations
     *
     * @return string
     */
    public function getModelTableName($relations = null)
    {
        $instance = $this->model;

        // Loop over the (nested) relation to get the table name
        if ($relations != null)
        {
            $relations = explode('.', $relations);
            foreach ($relations as $rel)
            {
                $instance = call_user_func([$instance, $rel]);
            }
        }

        // Otherwise just return the model's table name
        return $instance->getTable();
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

        foreach ($records as $record)
        {
            // Datatable specific meta data
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
     * The HTML id for the table
     * @var string
     */
    public $table_id = null;

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
        return $this->model_name . '-' . $row->id;
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
    abstract function fields();

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