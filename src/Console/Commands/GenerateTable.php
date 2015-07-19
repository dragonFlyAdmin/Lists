<?php namespace HappyDemon\Lists\Console\Commands;

use Illuminate\Console\Command;
use Memio\Model\Phpdoc\VariableTag;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Memio\Memio\Config\Build;
use Memio\Model\File;
use Memio\Model\Object;
use Memio\Model\Method;
use Memio\Model\Argument;
use Memio\Model\Property;
use Memio\Model\Phpdoc\MethodPhpdoc;
use Memio\Model\Phpdoc\Description;
use Memio\Model\Phpdoc\ReturnTag;
use Memio\Model\Phpdoc\ParameterTag;
use Memio\Model\Phpdoc\PropertyPhpdoc;

class GenerateTable extends Command
{
    use \Illuminate\Console\AppNamespaceDetectorTrait;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:table';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a dataTable definition';
    
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
    
    protected $uses = [];
    
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $table_name = ucfirst($this->argument('name'));
        $model_name = $this->option('model');
        $fields = explode(',', $this->option('fields'));
        
        $app_namespace = $this->getAppNamespace();
        
        $extends = Object::make('HappyDemon\Lists\Table');
        $model = Object::make($model_name);
        
        // Add use statements
        $this->uses[] = 'use ' . $extends->getFullyQualifiedName() . ';';
        $this->uses[] = 'use ' . $model->getFullyQualifiedName() . ';';
        
        // Prepare the methods definition
        $methods = $this->prepareMethods($table_name, $model, $fields);
        
        // Initialise the object definition
        $table = Object::make($app_namespace . 'Tables\\' . $table_name)
                       ->extend($extends);

        // Add a kernel identifier
        $kernel_id = ($this->option('kernel')) ?: strtolower(snake_case($table_name));

        // Check if the kernel identifier is defined already
        if (app('Illuminate\Contracts\Http\Kernel')->isTableDefined($kernel_id))
        {
            $this->error('Seems like there\'s already a table defined in your Http kernel with the identifier: "' . $kernel_id . '"');

            return;
        }

        $table->addProperty(
            Property::make('kernel_identifier')
                    ->makeProtected()
                    ->setDefaultValue("'" . $kernel_id . "'")
                    ->setPhpdoc(
                        PropertyPhpdoc::make()
                                      ->setVariableTag(VariableTag::make('string The identifier used for routing through the kernel.'))
                    )
        );

        if ($this->option('route') !== false)
        {
            $table->addProperty(
                Property::make('route')
                        ->makeProtected()
                        ->setDefaultValue("'" . $this->option('route') . "'")
                        ->setPhpdoc(
                            PropertyPhpdoc::make()
                                          ->setVariableTag(VariableTag::make('string The name of the route to use for data requests'))
                        )
            );
        }

        // Add the methods to the object definition
        foreach ($methods as $name => $def)
        {
            $method = Method::make($name);
            
            // Add arguments, if any
            if (isset($def['arguments']))
            {
                foreach ($def['arguments'] as $argument)
                {
                    $method->addArgument($argument);
                }
            }

            // Add PHPDOCs to the method
            $docs = MethodPhpdoc::make();
            
            foreach ($def['docs'] as $doc)
            {
                call_user_func([$docs, $doc['type']], $doc['value']);
            }
            $method->setPhpdoc($docs);
            
            // Add body
            $method->setBody($def['body']);
            
            $table->addMethod($method);
        }
        
        // make the main instance
        $file = File::make(app_path('Http/Tables/' . $table_name . '.php'))
                    ->setStructure($table);
        
        
        // Parse everything into a string
        $prettyPrinter = Build::prettyPrinter();
        $generatedCode = $prettyPrinter->generateCode($file);
        
        // Add use statements
        $class = str_replace('namespace ' . $app_namespace . 'Tables;', "\n" . 'namespace ' . $app_namespace . 'Tables;' . "\n\n" . implode("\n", $this->uses), $generatedCode);

        if (!file_exists($file->getFilename()) || $this->option('overwrite'))
        {
            // Save the new class
            file_put_contents($file->getFilename(), $class);

            $this->info($app_namespace . 'Tables\\' . $table_name . ' was created successfully!');
            $this->comment('It\'s time to add it in your Http kernel:');
            $this->comment("'$kernel_id' => '" . $app_namespace . "Tables\\$table_name'");
        }
        else
        {
            $this->error('It seems like there\'s already a table definition with the same name.');
            $this->info('Add -o to your command to overwrite.');
        }
    }
    
    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the table definition.'],
        ];
    }
    
    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model\'s class name (with namespace).', null],
            ['kernel', 'k', InputOption::VALUE_OPTIONAL, 'The kernel key identifier.', null],
            ['fields', 'f', InputOption::VALUE_OPTIONAL, 'A comma-separated list of field names.', []],
            ['route', 'r', InputOption::VALUE_OPTIONAL, 'The name of the route to use for data requests.', false],
            ['overwrite', 'o', InputOption::VALUE_NONE,
             'Overwrite if there\'s already a definition with the same name.'],
        ];
    }

    /**
     * Prepare all the methods that the Table class definition will contain.
     *
     * @param string $table  Class name
     * @param Object $model  Model object definition
     * @param array  $fields The list of fields
     *
     * @return array
     */
    protected function prepareMethods($table, $model, $fields)
    {
        $parsed = $this->parseFields($fields);

        $methods = [
            'model'  => [
                'docs' => [
                    [
                        'type'  => 'setDescription',
                        'value' => Description::make('Return a model instance'),
                    ],
                    [
                        'type'  => 'setReturnTag',
                        'value' => ReturnTag::make($model->getName())
                    ]
                ],
                'body' => "\t\t return new " . $model->getName() . '();',
            ],
            'setup' => [
                'docs' => [
                    [
                        'type'  => 'setDescription',
                        'value' => Description::make('Describe fields for our ' . $table . ' table.'),
                    ]
                ],
                'body' => $parsed['content']
            ]
        ];
        
        return (count($parsed['extra']) > 0) ?
            array_merge($methods, $parsed['extra']) : $methods;
    }

    /**
     * Parse the fields method's content.
     *
     * @param array $fields
     *
     * @return array|string
     */
    protected function parseFields($fields)
    {
        if (count($fields) == 0)
        {
            return "\t\t// Don't forget to define your fields here";
        }

        // Get the dates that the model converts to Carbon objects
        $model = $this->option('model');
        $model_dates = (new $model())->getDates();
        
        $field_base = [
            'title'      => '',
            'column'     => '',
            'sortable'   => true,
            'searchable' => true
        ];
        
        $field_content = '';
        $formatMethods = [];
        
        foreach ($fields as $field)
        {
            $field_content .= "\t\t//Add the " . $field . " field \n\t\t" . '$this->addField(\'' . $field . "', ";
            $field_def = $field_base;
            
            $field_def['column'] = $field;
            $field_def['title'] = ucfirst(str_replace('_', ' ', $field));
            
            $field_content .= "" . str_replace("\n", "\n\t\t\t", var_export($field_def, true));
            $field_content .= "\n\t\t" . ');' . "\n\n";

            // Add a format method if the field gets parsed to a Carbon object
            if (in_array($field, $model_dates))
            {
                $formatName = 'get' . studly_case($field) . 'Format';
                
                $formatMethods[$formatName] = $this->formatMethodDefinition();
            }
        }
        
        return [
            'content' => $field_content,
            'extra'   => $formatMethods
        ];
    }

    /**
     * Return the content for a data format method.
     *
     * @return array
     */
    protected function formatMethodDefinition()
    {
        // Add carbon to the use statements if it's not there yet
        $use = 'use Carbon\Carbon;';
        if (!in_array($use, $this->uses))
        {
            $this->uses[] = $use;
        }

        // Return the method definition
        return [
            'docs'      => [
                [
                    'type'  => 'setDescription',
                    'value' => Description::make('Make this date human-readable'),
                ],
                [
                    'type'  => 'setReturnTag',
                    'value' => ReturnTag::make('string'),
                ],
                [
                    'type'  => 'addParameterTag',
                    'value' => ParameterTag::make('Carbon', 'data', 'The date column wrapped in a Carbon object.')
                ],
                [
                    'type'  => 'addParameterTag',
                    'value' => ParameterTag::make(class_basename($this->option('model')), 'record', 'All the data from the same record.')
                ]
            ],
            'arguments' => [
                Argument::make('Carbon\Carbon', 'data'),
                Argument::make('mixed', 'record'),
            ],
            'body'      => "\t\t return " . '$data->format(\'D m/d/Y H:i\');',
        ];
    }
}
