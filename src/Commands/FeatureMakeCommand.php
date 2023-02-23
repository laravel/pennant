<?php

namespace Laravel\Pennant\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'pennant:feature')]
class FeatureMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'pennant:feature';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new feature class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Feature';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return file_exists($customPath = $this->laravel->basePath('stubs/feature.stub'))
            ? $customPath
            : __DIR__.'/../../stubs/feature.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return "{$rootNamespace}\\Features";
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the feature already exists'],
        ];
    }
}
