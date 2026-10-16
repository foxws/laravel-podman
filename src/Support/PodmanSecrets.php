<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Lists and creates Podman secrets with the "podman secret" commands, so the
 * secrets a preset's units use can be set up from PHP, without lpod. Values
 * only go through stdin, never through the arguments.
 */
class PodmanSecrets
{
    /**
     * The names of the secrets that exist.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $result = $this->run(['ls', '--format', '{{.Name}}']);

        return array_values(array_filter(array_map('trim', explode("\n", $result->output()))));
    }

    public function exists(string $name): bool
    {
        return in_array($name, $this->names(), true);
    }

    /**
     * Create the secret with the given value, replacing it when it exists.
     */
    public function create(string $name, string $value): void
    {
        $this->run(['create', '--replace', $name, '-'], $value);
    }

    /**
     * Create the secret from a file, such as the app's ".env", replacing it
     * when it exists. Podman reads the file itself.
     */
    public function createFromFile(string $name, string $path): void
    {
        if (! is_file($path)) {
            throw new RuntimeException("The file {$path} does not exist.");
        }

        $this->run(['create', '--replace', $name, $path]);
    }

    /**
     * @param  list<string>  $arguments
     */
    protected function run(array $arguments, ?string $input = null): ProcessResult
    {
        $process = Process::timeout(30);

        if ($input !== null) {
            $process = $process->input($input);
        }

        $result = $process->run([Config::string('podman.binary', 'podman'), 'secret', ...$arguments]);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: "podman secret {$arguments[0]} failed with exit code {$result->exitCode()}.");
        }

        return $result;
    }
}
