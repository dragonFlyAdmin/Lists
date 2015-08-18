<?php

namespace DragonFly\Lists;

class Column
{
    /**
     * Column definition.
     *
     * @var array
     */
    protected $options = [
        'title'      => null,
        'sortable'   => false,
        'searchable' => false,
        'relation'   => null,
        'column'     => null,
        'select'     => false,
        'as'         => null,
        'format'     => false,
        'render'     => false,
        'default'    => false
    ];

    /**
     * @var Table
     */
    protected $table = null;

    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    public function __set($key, $val)
    {
        $this->options[$key] = $val;
    }

    /**
     * @param $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->options[$key];
    }

    /**
     * Set multiple attributes at once
     *
     * @param $attributes
     */
    public function set($attributes)
    {
        $this->options = array_merge($this->options, $attributes);

        return $this;
    }

    /**
     * Check if there's a format method defined
     */
    public function checkFormat()
    {
        // Overwrite the format value if a method exists
        $this->format = $this->checkMethodExists('format', $this->as);

        return $this;
    }

    /**
     * Return the column's meta data needed for rendering the JS tag
     *
     * @return array
     */
    public function getDefinitionMeta()
    {
        // Check if there's a render function
        if ($this->render === false)
        {
            $this->render = $this->checkMethodExists('render', $this->as);
        }

        // Check if there's a format function
        $this->checkFormat();

        return [
            'type' => isset($this->options['type']) ? $this->options['type'] : false
        ];
    }

    /**
     * Check if a 'render' or 'format' method exists for the specified column.
     *
     * @param       $type   either 'render' or 'format'
     * @param       $column name of the column
     *
     * @return array|null
     */
    protected function checkMethodExists($type, $column)
    {
        $method = 'get' . ucfirst(camel_case($column)) . ucfirst($type);

        // Local methods always overwrite predefined ones
        if (method_exists($this->table, $method))
        {
            return [$this->table, $method];
        }

        // return predefined
        return $this->{$type};
    }
}