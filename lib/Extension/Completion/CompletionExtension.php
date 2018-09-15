<?php

namespace Phpactor\Extension\Completion;

use Phpactor\Completion\Bridge\TolerantParser\SourceCodeFilesystem\ScfClassCompletor;
use Phpactor\Completion\Bridge\TolerantParser\WorseReflection\WorseConstantCompletor;
use Phpactor\Completion\Bridge\TolerantParser\WorseReflection\WorseConstructorCompletor;
use Phpactor\Completion\Bridge\WorseReflection\Formatter\FunctionFormatter;
use Phpactor\Completion\Bridge\WorseReflection\Formatter\MethodFormatter;
use Phpactor\Completion\Bridge\WorseReflection\Formatter\VariableFormatter;
use Phpactor\Completion\Bridge\TolerantParser\ChainTolerantCompletor;
use Phpactor\Completion\Bridge\TolerantParser\WorseReflection\WorseClassMemberCompletor;
use Phpactor\Completion\Bridge\TolerantParser\WorseReflection\WorseFunctionCompletor;
use Phpactor\Completion\Bridge\TolerantParser\WorseReflection\WorseParameterCompletor;
use Phpactor\Completion\Bridge\TolerantParser\WorseReflection\WorseLocalVariableCompletor;
use Phpactor\Completion\Core\Formatter\ObjectFormatter;
use Phpactor\Completion\Bridge\WorseReflection\Formatter\ParameterFormatter;
use Phpactor\Completion\Bridge\WorseReflection\Formatter\PropertyFormatter;
use Phpactor\Completion\Bridge\WorseReflection\Formatter\TypeFormatter;
use Phpactor\Completion\Bridge\WorseReflection\Formatter\TypesFormatter;
use Phpactor\Completion\Core\ChainCompletor;
use Phpactor\Container\Extension;
use Phpactor\Container\ContainerBuilder;
use Phpactor\MapResolver\Resolver;
use Phpactor\Container\Container;
use Phpactor\Extension\Completion\Command\CompleteCommand;
use Phpactor\Extension\Completion\Application\Complete;

class CompletionExtension implements Extension
{
    const CLASS_COMPLETOR_LIMIT = 'completion.completor.class.limit';

    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container)
    {
        $this->registerCompletion($container);
        $this->registerCommands($container);
        $this->registerApplicationServices($container);
    }

    /**
     * {@inheritDoc}
     */
    public function configure(Resolver $schema)
    {
        $schema->setDefaults([
            self::CLASS_COMPLETOR_LIMIT => 100,
        ]);
    }

    private function registerCompletion(ContainerBuilder $container)
    {
        $container->register('completion.completor', function (Container $container) {
            $completors = [];
            foreach (array_keys($container->getServiceIdsForTag('completion.completor')) as $serviceId) {
                $completors[] = $container->get($serviceId);
            }
            return new ChainCompletor($completors);
        });

        $container->register('completion.completor.tolerant.chain', function (Container $container) {
            $completors = [];
            foreach (array_keys($container->getServiceIdsForTag('completion.tolerant_completor')) as $serviceId) {
                $completors[] = $container->get($serviceId);
            }

            return new ChainTolerantCompletor(
                $completors,
                $container->get('reflection.tolerant_parser')
            );
        }, [ 'completion.completor' => []]);

        $container->register('completion.completor.parameter', function (Container $container) {
            return new WorseParameterCompletor(
                $container->get('reflection.reflector'),
                $container->get('completion.formatter')
            );
        }, [ 'completion.tolerant_completor' => []]);

        $container->register('completion.completor.constructor', function (Container $container) {
            return new WorseConstructorCompletor(
                $container->get('reflection.reflector'),
                $container->get('completion.formatter')
            );
        }, [ 'completion.tolerant_completor' => []]);
        
        $container->register('completion.completor.tolerant.class_member', function (Container $container) {
            return new WorseClassMemberCompletor(
                $container->get('reflection.reflector'),
                $container->get('completion.formatter')
            );
        }, [ 'completion.tolerant_completor' => []]);

        $container->register('completion.completor.tolerant.class', function (Container $container) {
            return new ScfClassCompletor(
                $container->get('source_code_filesystem.registry')->get('composer'),
                $container->get('class_to_file.file_to_class'),
                $container->getParameter(self::CLASS_COMPLETOR_LIMIT)
            );
        }, [ 'completion.tolerant_completor' => []]);

        $container->register('completion.completor.local_variable', function (Container $container) {
            return new WorseLocalVariableCompletor(
                $container->get('reflection.reflector'),
                $container->get('completion.formatter')
            );
        }, [ 'completion.tolerant_completor' => []]);

        $container->register('completion.completor.function', function (Container $container) {
            return new WorseFunctionCompletor(
                $container->get('reflection.reflector'),
                $container->get('completion.formatter')
            );
        }, [ 'completion.tolerant_completor' => []]);

        $container->register('completion.completor.constant', function (Container $container) {
            return new WorseConstantCompletor();
        }, [ 'completion.tolerant_completor' => []]);

        $container->register('completion.formatter', function (Container $container) {
            return new ObjectFormatter([
                new TypeFormatter(),
                new TypesFormatter(),
                new MethodFormatter(),
                new ParameterFormatter(),
                new PropertyFormatter(),
                new FunctionFormatter(),
                new VariableFormatter(),
            ]);
        });
    }

    private function registerCommands(ContainerBuilder $container)
    {
        $container->register('command.complete', function (Container $container) {
            return new CompleteCommand(
                $container->get('application.complete'),
                $container->get('console.dumper_registry')
            );
        }, [ 'ui.console.command' => []]);
    }

    private function registerApplicationServices(ContainerBuilder $container)
    {
        $container->register('application.complete', function (Container $container) {
            return new Complete(
                $container->get('completion.completor'),
                $container->get('application.helper.class_file_normalizer')
            );
        });
    }
}
