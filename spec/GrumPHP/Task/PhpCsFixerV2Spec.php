<?php

namespace spec\GrumPHP\Task;

use GrumPHP\Collection\FilesCollection;
use GrumPHP\Collection\ProcessArgumentsCollection;
use GrumPHP\Configuration\GrumPHP;
use GrumPHP\Formatter\PhpCsFixerFormatter;
use GrumPHP\Process\AsyncProcessRunner;
use GrumPHP\Process\ProcessBuilder;
use GrumPHP\Runner\TaskResult;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use GrumPHP\Task\Context\RunContext;
use GrumPHP\Task\PhpCsFixer;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;

/**
 * @mixin PhpCsFixer
 */
class PhpCsFixerV2Spec extends ObjectBehavior
{

    function let(GrumPHP $grumPHP, ProcessBuilder $processBuilder, AsyncProcessRunner $processRunner, PhpCsFixerFormatter $formatter)
    {
        $grumPHP->getTaskConfiguration('phpcsfixer2')->willReturn([]);

        $formatter->format(Argument::any())->willReturn('');
        $formatter->formatSuggestion(Argument::any())->willReturn('');
        $formatter->formatErrorMessage(Argument::cetera())->willReturn('');

        $this->beConstructedWith($grumPHP, $processBuilder, $processRunner, $formatter);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('GrumPHP\Task\PhpCsFixerV2');
    }

    function it_should_have_a_name()
    {
        $this->getName()->shouldBe('phpcsfixer2');
    }

    function it_should_have_configurable_options()
    {
        $options = $this->getConfigurableOptions();
        $options->shouldBeAnInstanceOf('Symfony\Component\OptionsResolver\OptionsResolver');
        $options->getDefinedOptions()->shouldContain('allow_risky');
        $options->getDefinedOptions()->shouldContain('cache_file');
        $options->getDefinedOptions()->shouldContain('config');
        $options->getDefinedOptions()->shouldContain('rules');
        $options->getDefinedOptions()->shouldContain('using_cache');
        $options->getDefinedOptions()->shouldContain('path_mode');
        $options->getDefinedOptions()->shouldContain('verbose');
    }

    function it_does_not_do_anything_if_there_are_no_files(ProcessBuilder $processBuilder, ContextInterface $context)
    {
        $processBuilder->createArgumentsForCommand('php-cs-fixer')->shouldNotBeCalled();
        $processBuilder->buildProcess()->shouldNotBeCalled();
        $context->getFiles()->willReturn(new FilesCollection());

        $result = $this->run($context);
        $result->shouldBeAnInstanceOf('GrumPHP\Runner\TaskResultInterface');
        $result->getResultCode()->shouldBe(TaskResult::SKIPPED);
    }

    function it_should_run_in_git_pre_commit_context(GitPreCommitContext $context)
    {
        $this->canRunInContext($context)->shouldReturn(true);
    }

    function it_should_run_in_run_context(RunContext $context)
    {
        $this->canRunInContext($context)->shouldReturn(true);
    }

    function it_runs_the_suite_for_all_files(
        GrumPHP $grumPHP,
        ProcessBuilder $processBuilder,
        Process $process,
        RunContext $context,
        PhpCsFixerFormatter $formatter
    ) {
        $grumPHP->getTaskConfiguration('phpcsfixer2')->willReturn(['config' => '.php_cs']);
        $formatter->resetCounter()->shouldBeCalled();

        $context->getFiles()->willReturn(new FilesCollection([
            $file1 = new SplFileInfo('file1.php', '.', 'file1.php'),
            $file2 = new SplFileInfo('file2.php', '.', 'file2.php'),
        ]));

        $processBuilder->createArgumentsForCommand('php-cs-fixer')->willReturn(new ProcessArgumentsCollection());
        $processBuilder->buildProcess(Argument::that(function (ProcessArgumentsCollection $args) use ($file1, $file2) {
            return !($args->contains($file1) || $args->contains($file2));
        }))->willReturn($process);

        $process->run()->shouldBeCalled();
        $process->isSuccessful()->willReturn(true);

        $result = $this->run($context);
        $result->shouldBeAnInstanceOf('GrumPHP\Runner\TaskResultInterface');
        $result->isPassed()->shouldBe(true);
    }

    function it_runs_the_suite_for_changed_files(
        ProcessBuilder $processBuilder,
        AsyncProcessRunner $processRunner,
        Process $process,
        ContextInterface $context,
        PhpCsFixerFormatter $formatter
    ) {
        $formatter->resetCounter()->shouldBeCalled();
        $context->getFiles()->willReturn(new FilesCollection([
            $file1 = new SplFileInfo('file1.php', '.', 'file1.php'),
            $file2 = new SplFileInfo('file2.php', '.', 'file2.php'),
        ]));

        $processBuilder->createArgumentsForCommand('php-cs-fixer')->willReturn(new ProcessArgumentsCollection());
        $processBuilder->buildProcess(Argument::that(function (ProcessArgumentsCollection $args) use ($file1, $file2) {
            return $args->contains($file1) || $args->contains($file2);
        }))->willReturn($process);

        $processRunner->run(Argument::type('array'))->shouldBeCalled();
    $process->isSuccessful()->willReturn(true);

        $result = $this->run($context);
        $result->shouldBeAnInstanceOf('GrumPHP\Runner\TaskResultInterface');
        $result->isPassed()->shouldBe(true);
    }

    function it_throws_exception_if_the_process_fails(
        ProcessBuilder $processBuilder,
        AsyncProcessRunner $processRunner,
        Process $process,
        ContextInterface $context,
        PhpCsFixerFormatter $formatter
    ) {
        $formatter->resetCounter()->shouldBeCalled();

        $arguments = new ProcessArgumentsCollection();
        $processBuilder->createArgumentsForCommand('php-cs-fixer')->willReturn($arguments);
        $processBuilder->buildProcess(Argument::type('GrumPHP\Collection\ProcessArgumentsCollection'))->willReturn($process);

        $processRunner->run(Argument::type('array'))->shouldBeCalled();
    $process->isSuccessful()->willReturn(false);

        $context->getFiles()->willReturn(new FilesCollection([
            new SplFileInfo('file1.php', '.', 'file1.php'),
        ]));

        $result = $this->run($context);
        $result->shouldBeAnInstanceOf('GrumPHP\Runner\TaskResultInterface');
        $result->isPassed()->shouldBe(false);
    }
}
