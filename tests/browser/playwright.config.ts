import { defineConfig } from '@playwright/test';
import path from 'node:path';

/**
 * Browser-evidence harness for RetroBoards Gate A.
 *
 * Boots the real server-rendered app (PHP built-in server) against a dedicated,
 * pre-seeded database and captures full-page screenshots of every key Gate A
 * surface at a desktop and a mobile viewport. The DB is prepared by prepare.sh
 * (run via `npm run evidence`) BEFORE Playwright launches the web server.
 *
 * Screenshots are written to docs/evidence/browser/<viewport>/<page>.png.
 */

const repoRoot = path.resolve(__dirname, '..', '..');
const PORT = Number(process.env.E2E_PORT ?? 8011);
// WebAuthn treats localhost as a development exception, while 127.0.0.1 is
// rejected as an invalid RP ID by Chromium's real credential APIs.
const baseURL = process.env.E2E_BASE_URL ?? `http://localhost:${PORT}`;
const parsedBaseURL = new URL(baseURL);
const serverHost = parsedBaseURL.hostname;
const serverPort = Number(parsedBaseURL.port || (parsedBaseURL.protocol === 'https:' ? 443 : 80));
const database = process.env.DB_DATABASE ?? 'retroboards_e2e';
const rateLimitPath = process.env.RATELIMIT_PATH ?? path.join(repoRoot, 'storage', 'ratelimit-e2e');
const packagesPath = process.env.PACKAGES_STORAGE_PATH ?? path.join(repoRoot, 'storage', 'packages-e2e');
const skipWebServer = process.env.E2E_SKIP_WEBSERVER === '1';
const appKey = process.env.APP_KEY?.trim() || '0000000000000000000000000000000000000000000000000000000000000000';
const openAiApiKey = process.env.OPENAI_API_KEY?.trim() || 'browser-thread-intelligence-dummy-credential';

process.env.APP_KEY = appKey;
process.env.OPENAI_API_KEY = openAiApiKey;

const inheritedEnv = Object.fromEntries(
  Object.entries(process.env).filter((entry): entry is [string, string] => entry[1] !== undefined),
);

export default defineConfig({
  testDir: __dirname,
  outputDir: path.join(repoRoot, 'docs/evidence/browser/.artifacts'),
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  reporter: [['list']],
  use: {
    baseURL,
    // The spec writes its own named screenshots; disable Playwright's automatic ones.
    screenshot: 'off',
    trace: 'off',
  },
  // The app uses HTTP locally, so the session cookie must not require Secure; mail
  // is captured in-memory. The DB is already migrated + seeded by prepare.sh.
  webServer: skipWebServer ? undefined : {
    command: `php -S ${serverHost}:${serverPort} -t public public/index.php`,
    cwd: repoRoot,
    env: {
      ...inheritedEnv,
      APP_KEY: appKey,
      OPENAI_API_KEY: openAiApiKey,
      DB_DATABASE: database,
      RATELIMIT_PATH: rateLimitPath,
      PACKAGES_STORAGE_PATH: packagesPath,
      SESSION_SECURE: 'false',
      MAIL_DRIVER: 'array',
      APP_URL: baseURL,
      WEBHOOK_ALLOW_HTTP: 'true',
      WEBHOOK_ALLOWED_PRIVATE_CIDRS: '127.0.0.1/32',
    },
    url: baseURL,
    reuseExistingServer: !process.env.CI,
    timeout: 30_000,
  },
  projects: [
    {
      name: 'desktop',
      use: { browserName: 'chromium', viewport: { width: 1280, height: 800 } },
    },
    {
      name: 'mobile',
      use: {
        browserName: 'chromium',
        viewport: { width: 390, height: 844 },
        deviceScaleFactor: 2,
        isMobile: true,
        hasTouch: true,
      },
    },
  ],
});
