<?php

namespace ZeroDaHero\LaravelWorkflow\Commands;

use Config;
use Storage;
use Workflow;
use Exception;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Dumper\StateMachineGraphvizDumper;

/**
 * @author Boris Koumondji <brexis@yahoo.fr>
 */
class WorkflowDumpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:dump
        {workflow : name of workflow from configuration}
        {--class= : the support class name}
        {--format=png : the image format}
        {--disk=local : the storage disk name}
        {--path= : the optional path within selected disk}
        {--with-metadata : dumps metadata beneath the label }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'GraphvizDumper dumps a workflow as a graphviz file.
        You can convert the generated dot file with the dot utility (http://www.graphviz.org/):';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $workflowName = $this->argument('workflow');
        $format = $this->option('format');
        $class = $this->option('class');
        $config = Config::get('workflow');
        $disk = $this->option('disk');
        $optionalPath = $this->option('path');
        $withMetadata = $this->option('with-metadata');

        if ($disk === 'local') {
            $optionalPath ??= '.';
        }
        $path = Storage::disk($disk)->path($optionalPath);

        if ($optionalPath && ! Storage::disk($disk)->exists($optionalPath)) {
            Storage::disk($disk)->makeDirectory($optionalPath);
        }

        if (! isset($config[$workflowName])) {
            throw new Exception("Workflow {$workflowName} is not configured.");
        }

        if (false === array_search($class, $config[$workflowName]['supports'])) {
            throw new Exception("Workflow {$workflowName} has no support for class {$class}." .
                ' Please specify a valid support class with the --class option.');
        }

        $dumperOptions = [
            'with-metadata' => $withMetadata,
        ];

        $subject = new $class();
        $workflow = Workflow::get($subject, $workflowName);
        $definition = $workflow->getDefinition();

        $dumper = new GraphvizDumper();

        if ($workflow instanceof StateMachine) {
            $dumper = new StateMachineGraphvizDumper();
        }

        $dotCommand = ['dot', "-T{$format}", '-o', "{$workflowName}.{$format}"];

        $process = new Process($dotCommand);
        $process->setWorkingDirectory($path);
        $process->setInput($dumper->dump($definition, options: $dumperOptions));
        $process->mustRun();
    }
}
