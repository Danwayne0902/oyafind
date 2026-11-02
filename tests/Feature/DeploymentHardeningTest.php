<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Deployment files are part of the security boundary. These tests fail if a
 * future change quietly stops ignoring .env, or removes the dotfile block
 * from the web server configuration.
 */
class DeploymentHardeningTest extends TestCase
{
    public function test_env_files_are_ignored_and_only_examples_are_tracked(): void
    {
        $ignore = (string) file_get_contents(base_path('.gitignore'));

        $this->assertMatchesRegularExpression('/^\.env$/m', $ignore);
        $this->assertMatchesRegularExpression('/^\.env\.\*$/m', $ignore);
        $this->assertMatchesRegularExpression('/^!\.env\.example$/m', $ignore);

        $tracked = $this->gitTrackedFiles();

        if ($tracked === null) {
            $this->markTestSkipped('git is not available in this environment.');
        }

        $envFiles = array_values(array_filter(
            $tracked,
            static fn (string $path): bool => preg_match('#(^|/)\.env($|\.)#', $path) === 1,
        ));

        sort($envFiles);

        $this->assertSame([
            '.env.example',
            'deploy/.env.production.example',
        ], $envFiles, 'Only the example environment files may be tracked.');
    }

    public function test_the_web_server_configuration_refuses_dotfiles_and_repository_paths(): void
    {
        $caddy = (string) file_get_contents(base_path('deploy/Caddyfile'));

        $this->assertStringContainsString('path_regexp hidden (^|/)\.', $caddy);
        $this->assertStringContainsString('respond @hidden 404', $caddy);
        $this->assertStringContainsString('vendor|storage|bootstrap|database|tests|deploy|node_modules', $caddy);
        $this->assertStringContainsString('composer\.(json|lock)', $caddy);

        $htaccess = (string) file_get_contents(base_path('public/.htaccess'));

        $this->assertStringContainsString('(^|/)\.(?!well-known/)', $htaccess);
        $this->assertStringContainsString('vendor|storage|bootstrap|database|tests|deploy|node_modules', $htaccess);
        $this->assertStringContainsString('composer\.(json|lock)', $htaccess);
    }

    public function test_the_image_build_context_never_includes_secrets(): void
    {
        $dockerignore = (string) file_get_contents(base_path('.dockerignore'));

        $this->assertMatchesRegularExpression('/^\*\*\/\.env$/m', $dockerignore);
        $this->assertMatchesRegularExpression('/^\*\*\/\.env\.\*$/m', $dockerignore);
        $this->assertMatchesRegularExpression('/^!\.env\.example$/m', $dockerignore);
    }

    /**
     * @return list<string>|null
     */
    private function gitTrackedFiles(): ?array
    {
        $output = [];
        $status = 0;
        exec('git -C '.escapeshellarg(base_path()).' ls-files 2>/dev/null', $output, $status);

        return $status === 0 ? $output : null;
    }
}
