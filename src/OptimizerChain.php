<?php

namespace Spatie\ImageOptimizer;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Spatie\ImageOptimizer\Optimizers\Optimizer;

class OptimizerChain
{
    protected $optimizers = [];

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;

    protected $timeout = 60;

    public function __construct()
    {
        $this->useLogger(new DummyLogger());
    }

    public function getOptimizers(): array
    {
        return $this->optimizers;
    }

    public function addOptimizer(Optimizer $optimizer)
    {
        $this->optimizers[] = $optimizer;

        return $this;
    }

    public function setOptimizers(array $optimizers)
    {
        $this->optimizers = [];

        foreach ($optimizers as $optimizer) {
            $this->addOptimizer($optimizer);
        }

        return $this;
    }

    /*
     * Set the amount of seconds each separte optimizer may use
     */
    public function setTimeout(int $timeoutInSeconds)
    {
        $this->timeout = $timeoutInSeconds;
    }

    public function useLogger(LoggerInterface $log)
    {
        $this->logger = $log;

        return $this;
    }

    public function optimize(string $pathToImage)
    {
        $image = new Image($pathToImage);

        $this->logger->info("Start optimizing {$pathToImage}");

        foreach ($this->optimizers as $optimizer) {
            $this->applyOptimizer($optimizer, $image);
        }
    }

    protected function applyOptimizer(Optimizer $optimizer, Image $image)
    {
        if (! $optimizer->canHandle($image)) {
            return;
        }

        $optimizerClass = get_class($optimizer);

        $this->logger->info("Using optimizer: `{$optimizerClass}`");

        $optimizer->setImagePath($image->path());

        $command = $optimizer->getCommand();

        $this->logger->info("Executing `{$command}`");

        $process = new Process($command);

        $process
            ->setTimeout($this->timeout)
            ->run();

        $this->logResult($process);
    }

    protected function logResult(Process $process)
    {
        if ($process->isSuccessful()) {
            $this->logger->info("Process successfully ended with output `{$process->getOutput()}`");

            return;
        }

        $this->logger->error("Process errored with `{$process->getErrorOutput()}`}");
    }
}
