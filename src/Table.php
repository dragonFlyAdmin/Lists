<?php

namespace HappyDemon\Lists;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Input;
use Exception;

abstract class Table
{
    /**
     * Model instance we'll be working with
     *
     * @var Sources\Contract
     */
    public $dataSource = null;

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
        // Load the base model/query
        $data = $this->data();

        // Set the data source
        $this->dataSource = $this->prepareSource($data);

        // Set the kernel identifier if it wasn't previously
        if ($this->kernel_identifier == null)
        {
            $name = $this->dataSource->getIdName();

            // Seems like none was defined
            if ($name === false)
            {
                throw new Exception('No kernel identifier defined!');
            }

            $this->kernel_identifier = $name;
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

    protected function prepareSource($data)
    {
        if (is_a($data, '\Illuminate\Database\Eloquent\Model'))
        {
            return new Sources\Eloquent($data);
        }
        else if (is_a($data, '\Illuminate\Database\Query\Builder'))
        {
            return new Sources\DB($data);
        }

        Throw new Exception('Unsupported data type');
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
        $has_actions = count($this->actions) > 0;

        // Don't order if it's not defined
        if (!array_key_exists('order', $this->options))
        {
            $this->setOrder([]);
        }

        $options = json_encode($this->dataTableOptions(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (count($this->render_functions) == 0)
        {

            $parsed_options = $options;
        }
        else
        {
            $keys = array_keys($this->render_functions);

            array_walk($keys, function (&$func)
            {
                $func = '"' . $func . '"';
            });

            $parsed_options = str_replace($keys, array_values($this->render_functions), $options);
        }

        return view('lists::render_tag', [
            'options'    => $parsed_options,
            'tag'        => $this->html_id,
            'var'        => $this->js_var,
            'checkboxes' => $has_actions,
            'actions'    => str_replace(["\r", "\n"], '', view('lists::actions', ['actions' => $this->parseActions()]))
        ])->render();
    }

    /**
     * Contains the render functions that get injected into the column options
     *
     * @var array
     */
    protected $render_functions = [];

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
            $formatted = $this->dataSource->formatColumn($field);
            $field->set($formatted);
            $field->checkFormat();

            if ($ordering == false && $field->sortable == true)
            {
                $ordering = true;
            }

            $meta = $field->getDefinitionMeta($this);

            // Check if the table name is translatable
            if (Lang::has('tables.' . snake_case($this->kernel_identifier) . '.titles.' . $name))
            {
                // Check if there's a specific definition
                $name = trans('table_' . snake_case($this->kernel_identifier) . '.titles.' . $name);
            }
            else if (Lang::has('tables.titles.' . $name))
            {
                // Check if there's a global definition
                $name = trans('tables.titles.' . $name);
            }

            $format = [
                'name'       => $name,
                'orderable'  => $field->sortable,
                'searchable' => $field->searchable,
                'title'      => $field->title
            ];

            // If a render function was defined add and call it.
            if ($field->format === false && $field->render !== false)
            {
                $format['render'] = '<!' . $name . '!>';
                $this->render_functions[$format['render']] = (string) view('lists::render_func', ['content' => (string) call_user_func($field->render, $field)]);
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

        // Localise dataTables itself
        if (config('app.locale') !== 'en')
        {
            $locale_file = config('lists.locales.' . config('app.locale'), false);

            // Check if the locale is defined
            if ($locale_file !== false)
            {
                $this->options['language']['url'] = url('assets/locale/' . $locale_file . '.json');
            }
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
            $modelData = $this->dataSource
                ->setRequestColumns(Input::get('columns', []))
                ->prepare()
                ->search(Input::get('search', []), $this->searchables, $this->fields)
                ->order(Input::get('order', []), $this->fields)
                ->getPreparedData();

            // Count all records
            $this->total_records = $modelData['total'];

            // Limit records
            $data = $modelData['records'];

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
                $this->dataSource->addSelect($select);
            }
        }

        // Check if there's a format method and prepare the DB fields if needed.
        foreach ($this->fields as $key => $field)
        {

            $formatted = $this->dataSource->formatColumn($field);
            $field->set($formatted);
            $field->checkFormat();
        }

        // make sure the primary key is always loaded
        $pk = $this->dataSource->getPrimaryKey();

        if (!array_key_exists($pk, $this->select))
        {
            $this->select[$pk] = $this->dataSource->getFormattedPrimaryKey() . ' AS ' . $pk;
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
        $route = ($this->action_route) ?: config('lists.action_route');

        return route($route, ['table' => $this->kernel_identifier, 'action' => $action]);
    }

    /**
     * Prepend the table with a checkbox column
     */
    protected function prependCheckboxColumn()
    {
        $this->prependField('list_key', [
            'name'       => 'list_keys',
            'title'      => view('lists::header_checkbox')->render(),
            'orderable'  => false,
            'searchable' => false,
            'column'     => $this->dataSource->getFormattedPrimaryKey(),
            'as'         => 'list_keys'
        ]);

        return $this;
    }

    // Render the column as a checkbox on the client-side
    public function getListKeysRender($field)
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
    protected function parseData($records)
    {
        if(!is_a($records, 'Collection'))
        {
            $records = Collection::make($records);
        }
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

        try
        {
            $perform = call_user_func($definition['action'], $ids);

            // When it returns false return the default error msg
            if ($perform === false)
            {
                return [
                    'status'  => 'error',
                    'message' => $this->translatePerformMessage($definition['messages']['error'], $definition['slug'])
                ];
            }

            // If didn't return true and error occurred with a custom message
            if ($perform !== true)
            {
                return [
                    'status'  => 'error',
                    'message' => $this->translatePerformMessage($perform, $definition['slug'])
                ];
            }

            // Otherwise it's a success!
            return [
                'status'  => 'success',
                'type'    => 'complete',
                'message' => $this->translatePerformMessage($definition['messages']['success'], $definition['slug'])
            ];
        }
            // Catch exceptions to make sure the request completes
        catch (Exception $e)
        {
            return [
                'status'  => 'error',
                'message' => trans('lists::actions.messages.code_error', ['slug' => $definition['slug']]),
                'error'   => $e->getMessage()
            ];
        }
    }

    /**
     * Translate the provided message after performing an action.
     *
     * Checks for globally defined messages first, next local table definition
     *
     * @param $msg
     * @param $slug
     *
     * @return string
     */
    protected function translatePerformMessage($msg, $slug)
    {
        if (Lang::has($msg))
        {
            return trans($msg, compact('slug'));
        }
        else if (Lang::has('tables.messages.' . $msg))
        {
            return trans('tables.messages.' . $msg, compact('slug'));
        }
        else if (Lang::has('table_' . snake_case($this->kernel_identifier) . '.messages.' . $msg))
        {
            return trans('table' . snake_case($this->kernel_identifier) . '.messages.' . $msg, compact('slug'));
        }

        return $msg;
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
        $predefined_msgs = ($messages) ?: [];

        $messages = array_merge([
            'error'   => 'lists::actions.messages.error',
            'success' => 'lists::actions.messages.success',
            'active'  => 'lists::actions.messages.active',
        ], $predefined_msgs);


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
            'title'    => trans($title),
            'messages' => $messages,
            'action'   => $callable,
            'slug'     => $slug
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
     * Add a field to at the beginning of the list.
     *
     * @param       $name       Name of the field
     * @param array $properties see Column::$options
     *
     * @return $this
     */
    protected function prependField($name, $properties = [])
    {
        $this->fields = [$name => $this->newField()->set($properties)] + $this->fields;

        return $this;
    }

    /**
     * Initiate a new column instance
     *
     * @return Column
     */
    protected function newField()
    {
        return new Column($this);
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
        return $this->html_id . '-row-' . $row->{$this->dataSource->getPrimaryKey()};
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
    abstract public function data();

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
        if (starts_with($name, 'set'))
        {
            // remove 'set' from the name
            $option = lcfirst(substr($name, 3));

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