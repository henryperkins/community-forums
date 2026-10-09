<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Request;
use App\Security\ClientIdentifier;
use PHPUnit\Framework\TestCase;

/**
 * The Cloud Run origin (docs/runbooks/deployment-cloud-run.md): what the
 * service, its cron jobs and the deploy pipeline must keep true.
 */
final class CloudRunDeploymentContractTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_service_is_one_private_instance_on_the_cloud_sql_connector_and_the_data_bucket(): void
    {
        $configure = $this->read('deploy/cloudrun/configure.sh');
        $env = $this->env();

        // One instance: the rate-limit ledger is a local file.
        self::assertStringContainsString('--max-instances=1', $configure);
        // Only the Worker's service account may call it (worker/origin.mjs).
        self::assertStringContainsString('--no-allow-unauthenticated', $configure);
        self::assertStringContainsString('--member="serviceAccount:$EDGE_SA" --role=roles/run.invoker', $configure);
        // Uploads live in the bucket mounted at /data, writable by www-data.
        self::assertStringContainsString('--execution-environment=gen2', $configure);
        self::assertStringContainsString('--add-volume-mount=volume=data,mount-path=/data', $configure);
        self::assertStringContainsString('mount-options=uid=33;gid=33;', $configure);
        self::assertSame('/data/media', $env['UPLOADS_PATH']);
        self::assertSame('/data/packages', $env['PACKAGES_STORAGE_PATH']);
        // The built-in Cloud SQL connector, by socket; it does its own TLS.
        self::assertStringContainsString('--set-cloudsql-instances="$SQL_INSTANCE"', $configure);
        self::assertSame('/cloudsql/rising-woods-449718-v6:us-east4:imladris-boards', $env['DB_SOCKET']);
        self::assertArrayNotHasKey('DB_HOST', $env);
        self::assertArrayNotHasKey('DB_SSL', $env);
        self::assertSame('https://forum.candidary.online', $env['APP_URL']);
    }

    public function test_secrets_come_from_secret_manager_never_from_plain_configuration(): void
    {
        $configure = $this->read('deploy/cloudrun/configure.sh');
        $env = $this->env();

        // Each secret env var names the Secret Manager secret behind it.
        foreach ([
            'APP_KEY' => 'retroboards-app-key',
            'DB_PASSWORD' => 'imladris-boards-app-password',
            'CLOUDFLARE_EMAIL_API_TOKEN' => 'retroboards-cloudflare-email-token',
        ] as $var => $secret) {
            self::assertArrayNotHasKey($var, $env);
            self::assertStringContainsString('"' . $var . ' ' . $secret . '"', $configure);
        }
        self::assertStringContainsString('SECRETS="${SECRETS:+$SECRETS,}$var=$secret:latest"', $configure);
        self::assertStringContainsString('--set-secrets="$SECRETS"', $configure);
        // --set-secrets replaces the whole mapping: only a definite NOT_FOUND
        // may leave the email token out; any other lookup failure must stop.
        self::assertStringContainsString('elif [[ "$email_state" != *NOT_FOUND* ]]; then', $configure);
    }

    /**
     * Worker -> Google Front End -> container: the Worker sets the visitor's
     * address, Google appends the Worker's egress address, and the container's
     * peer is Google's link-local proxy. The deployed trust list must land on
     * the visitor, not on Cloudflare or Google.
     */
    public function test_trusted_proxies_resolve_the_visitor_behind_the_worker_and_google_front_end(): void
    {
        $identifier = new ClientIdentifier(array_map('trim', explode(',', $this->env()['TRUSTED_PROXIES'])));

        foreach (['2a06:98c0:3600::103', '172.71.255.1', '104.23.240.9'] as $workerEgress) {
            foreach (['198.51.100.23', '2001:db8::7'] as $visitor) {
                self::assertSame(
                    $visitor,
                    $identifier->ipFor($this->request('169.254.169.126', $visitor . ',' . $workerEgress)),
                    $visitor . ' via ' . $workerEgress,
                );
            }
        }
        // A peer outside the trust list never gets its header honoured.
        self::assertSame('203.0.113.9', $identifier->ipFor($this->request('203.0.113.9', '198.51.100.23')));
    }

    public function test_cron_jobs_keep_the_former_worker_schedule_and_never_migrate(): void
    {
        $configure = $this->read('deploy/cloudrun/configure.sh');

        foreach ([
            '|*/5 * * * *|worker:email,worker:webhooks"',
            '|0 */6 * * *|worker:registry-refresh"',
            '|10 3 * * *|worker:purge-ips,worker:attachments,worker:packages"',
            '|0 7 * * *|worker:digest"',
        ] as $schedule) {
            self::assertStringContainsString($schedule, $configure);
        }
        self::assertStringContainsString('--args="retroboards-console-batch,$commands"', $configure);
        self::assertStringContainsString('--time-zone=Etc/UTC', $configure);
        self::assertStringContainsString('--max-retries=0', $configure);
        // The service migrates on boot; the jobs get a copy of env.yaml with that off.
        self::assertSame('true', $this->env()['RUN_MIGRATIONS']);
        self::assertStringContainsString(
            'sed \'s/^RUN_MIGRATIONS: .*/RUN_MIGRATIONS: "false"/\' "$HERE/env.yaml" > "$JOB_ENV"',
            $configure,
        );
        self::assertStringContainsString('--env-vars-file="$JOB_ENV"', $configure);
        self::assertStringContainsString('--labels=app=retroboards,role=cron', $configure);
    }

    public function test_pipeline_builds_the_pushed_commit_and_rolls_out_the_service_before_the_jobs(): void
    {
        $pipeline = $this->read('deploy/cloudrun/cloudbuild.yaml');

        self::assertStringContainsString('git fetch -q --depth=1 origin "${_SHA}"', $pipeline);
        self::assertStringContainsString('--tag=${_IMAGE}:${_SHA}', $pipeline);
        self::assertStringContainsString('[run, deploy, "${_SERVICE}", "--image=${_IMAGE}:${_SHA}"', $pipeline);
        // Finds the jobs by the labels configure.sh gives them.
        self::assertStringContainsString('metadata.labels.app=retroboards AND metadata.labels.role=cron', $pipeline);
        // The service deploys (and migrates) before any job runs new code.
        self::assertLessThan(strpos($pipeline, 'id: update-jobs'), strpos($pipeline, 'id: deploy-service'));
    }

    public function test_console_batch_runs_every_command_in_order_and_reports_any_failure(): void
    {
        $bin = sys_get_temp_dir() . '/rb-console-batch-' . bin2hex(random_bytes(4));
        mkdir($bin);
        $log = $bin . '/calls.log';
        // Stands in for `php /var/www/html/bin/console <command>`.
        file_put_contents($bin . '/php', "#!/bin/sh\necho \"\$2\" >> \"\$CALL_LOG\"\n[ \"\$2\" != worker:broken ]\n");
        chmod($bin . '/php', 0755);

        try {
            self::assertSame(1, $this->batch($bin, $log, ['worker:email', 'worker:broken', 'worker:webhooks']));
            self::assertSame("worker:email\nworker:broken\nworker:webhooks\n", file_get_contents($log));

            unlink($log);
            self::assertSame(0, $this->batch($bin, $log, ['worker:digest']));
            self::assertSame("worker:digest\n", file_get_contents($log));
        } finally {
            array_map('unlink', glob($bin . '/*') ?: []);
            rmdir($bin);
        }
    }

    public function test_image_carries_the_batch_runner_and_the_entrypoint_spares_fuse_mounts(): void
    {
        $dockerfile = $this->read('Dockerfile');
        $entrypoint = $this->read('deploy/entrypoint.sh');

        self::assertStringContainsString('COPY deploy/console-batch.sh /usr/local/bin/retroboards-console-batch', $dockerfile);
        // gcsfuse refuses chown; under `set -e` that would abort every boot.
        self::assertMatchesRegularExpression('/case "\$data_fstype" in\s+fuse\*\) ;;\s+\*\) chown -R www-data:www-data "\$DATA_DIR" ;;/', $entrypoint);
    }

    public function test_shared_image_migrates_before_apache_and_forwards_its_logs(): void
    {
        $dockerfile = $this->read('Dockerfile');
        $vhost = $this->read('deploy/apache-vhost.conf');
        $entrypoint = $this->read('deploy/entrypoint.sh');

        self::assertStringContainsString('EXPOSE 8080', $dockerfile);
        self::assertStringContainsString('ENTRYPOINT ["retroboards-entrypoint"]', $dockerfile);
        self::assertStringContainsString('ErrorLog /var/log/apache2/error.log', $vhost);
        self::assertStringContainsString('CustomLog /var/log/apache2/access.log combined', $vhost);
        self::assertStringContainsString('rm -f /var/log/apache2/error.log', $dockerfile);
        self::assertStringContainsString('tail -n 0 -F /var/log/apache2/error.log', $entrypoint);
        self::assertStringContainsString('php /var/www/html/bin/console migrate', $entrypoint);
        self::assertLessThan(
            strpos($entrypoint, 'exec docker-php-entrypoint'),
            strpos($entrypoint, 'php /var/www/html/bin/console migrate'),
        );
        self::assertStringNotContainsString('/etc/hosts', $entrypoint);
    }

    /** @param list<string> $commands */
    private function batch(string $bin, string $log, array $commands): int
    {
        $process = proc_open(
            array_merge(['sh', self::ROOT . '/deploy/console-batch.sh'], $commands),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['PATH' => $bin . ':' . getenv('PATH'), 'CALL_LOG' => $log],
        );
        self::assertIsResource($process);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }

    private function request(string $peer, string $forwardedFor): Request
    {
        return new Request('GET', '/', server: ['REMOTE_ADDR' => $peer, 'HTTP_X_FORWARDED_FOR' => $forwardedFor]);
    }

    /** @return array<string,string> deploy/cloudrun/env.yaml (flat `KEY: value` lines) */
    private function env(): array
    {
        $env = [];
        foreach (explode("\n", $this->read('deploy/cloudrun/env.yaml')) as $line) {
            if (preg_match('/^([A-Z][A-Z0-9_]*): (.*)$/', $line, $m) === 1) {
                $env[$m[1]] = trim($m[2], '"');
            }
        }

        return $env;
    }

    private function read(string $relativePath): string
    {
        $path = self::ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
