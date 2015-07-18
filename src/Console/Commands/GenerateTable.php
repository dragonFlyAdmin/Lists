<?php namespace HappyDemon\Lists\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Memio\Memio\Config\Build;
use Memio\Model\File;
use Memio\Model\Object;
use Memio\Model\Method;
use Memio\Model\Argument;
use Memio\Model\Phpdoc\MethodPhpdoc;
use Memio\Model\Phpdoc\Description;
use Memio\Model\Phpdoc\ReturnTag;

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

        $uses = [];

        $extends = Object::make('HappyDemon\Lists\Table');
        $model = Object::make($model_name);

        // Add use statements
        $uses[] = 'use ' . $extends->getFullyQualifiedName() . ';';
        $uses[] = 'use ' . $model->getFullyQualifiedName() . ';';

        // Prepare the methods definition
        $methods = $this->prepareMethods($table_name, $model, $fields);

        // Initialise the object definition
        $table = Object::make($app_namespace . 'Tables\\' . $table_name)
                       ->extend($extends);

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

            $docs = MethodPhpdoc::make();

            foreach ($def['docs'] as $type => $doc)
            {
                $call = 'set' . studly_case($type);
                call_user_func([$docs, $call], $doc);
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
        $class = str_replace('namespace ' . $app_namespace . 'Tables;', "\n" . 'namespace ' . $app_namespace . 'Tables;' . "\n\n" . implode("\n", $uses), $generatedCode);

        // Save the new class
        file_put_contents($file->getFilename(), $class);

        $this->info($app_namespace . '\Tables\\' . $table_name . ' was created successfully! in: '.$file->getFilename());
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
            ['fields', 'f', InputOption::VALUE_OPTIONAL, 'A comma-separated list of field names.', []],
        ];
    }

    protected function prepareMethods($table, $model, $fields)
    {
        return [
            'model'  => [
                'docs'      => [
                    'description' => Description::make('Return a model instance'),
                    'returnTag'   => ReturnTag::make($model->getName())
                ],
                'body'      => "\t\t return new " . $model->getName() . '();',
            ],
            'fields' => [
                'docs' => [
                    'description' => Description::make('Describe fields for our ' . $table . ' table.'),
                ],
                'body' => $this->getFieldsBody($fields)
            ]
        ];
    }

    protected function getFieldsBody($fields)
    {
        if (count($fields) == 0)
        {
            return "\t\t// Don't forget to define your fields here";
        }

        $field_base = [
            'title'      => '',
            'column'     => '',
            'sortable'   => true,
            'searchable' => true
        ];

        $field_content = '';

        foreach ($fields as $field)
        {
            $field_content .= "\t\t//Add the " . $field . " field \n\t\t" . '$this->addField(\'' . $field . "', ";
            $field_def = $field_base;

            $field_def['column'] = $field;
            $field_def['title'] = ucfirst(str_replace('_', ' ', $field));

            $field_content .= "" . str_replace("\n", "\n\t\t\t", var_export($field_def, true));
            $field_content .= "\n\t\t" . ');' . "\n\n";
        }

        return $field_content;
    }
}
