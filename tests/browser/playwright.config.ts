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

function shellQuote(value: string): string {
  return "'" + value.replace(/'/g, "'\\''") + "'";
}

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
    command: `DB_DATABASE=${shellQuote(database)} RATELIMIT_PATH=${shellQuote(rateLimitPath)} PACKAGES_STORAGE_PATH=${shellQuote(packagesPath)} SESSION_SECURE=false MAIL_DRIVER=array APP_URL=${shellQuote(baseURL)} WEBHOOK_ALLOW_HTTP=true WEBHOOK_ALLOWED_PRIVATE_CIDRS=127.0.0.1/32 php -S ${shellQuote(`${serverHost}:${serverPort}`)} -t public public/index.php`,
    cwd: repoRoot,
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
