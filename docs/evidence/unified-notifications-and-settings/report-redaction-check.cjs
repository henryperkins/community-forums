const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const script = fs.readFileSync(path.join(root, 'tests/browser/run-notifications-settings.cjs'), 'utf8');
const temp = fs.mkdtempSync('/tmp/unified-report-redaction-');
const checks = [];
try {
  for (const mode of ['success', 'failed_test', 'malformed_report']) {
    const evidence = path.join(temp, mode);
    let calls = 0;
    const fakeSpawn = (command, args, options) => {
      calls++;
      assert.equal(options.env.APP_KEY, '0'.repeat(64));
      assert.equal(options.env.OPENAI_API_KEY, 'browser-thread-intelligence-dummy-credential');
      assert.equal(options.env.MAIL_DRIVER, 'array');
      if (command === 'npx') {
        const report = { config: { webServer: [ { command:'php local', env: { CUSTOM_SECRET:'report-sentinel' } } ] }, stats: { expected:mode === 'success' ? 1 : 0, unexpected:mode === 'failed_test' ? 1 : 0 } };
        fs.writeFileSync(options.env.PLAYWRIGHT_JSON_OUTPUT_NAME, mode === 'malformed_report' ? '{raw report-sentinel' : JSON.stringify(report));
      }
      return { status: command === 'npx' && mode === 'failed_test' ? 1 : 0, signal:null };
    };
    let threw = false;
    try {
      vm.runInNewContext(script, {
        require: name => name === 'node:child_process' ? { spawnSync:fakeSpawn } : require(name),
        __dirname:path.join(root, 'tests/browser'), URL,
        process:{env:{DB_DATABASE:'retroboards_unified_e2e',RB_EVIDENCE_DIR:evidence,APP_KEY:'host-sentinel',OPENAI_API_KEY:'host-sentinel',CUSTOM_SECRET:'report-sentinel'}},
      }, {filename:'run-notifications-settings.cjs'});
    } catch (error) { threw = true; }
    assert.equal(threw, mode !== 'success');
    const result = JSON.parse(fs.readFileSync(path.join(evidence, 'browser-results.json')));
    assert.equal(result.completed, mode === 'success');
    assert.equal(result.scratch_removed, true);
    const reports = [];
    const walk = dir => { for (const entry of fs.readdirSync(dir, {withFileTypes:true})) { const item = path.join(dir,entry.name); if(entry.isDirectory())walk(item); else if(entry.name.endsWith('.json'))reports.push(item); } };
    walk(evidence);
    for (const file of reports) {
      const contents = fs.readFileSync(file,'utf8');
      assert.ok(!contents.includes('report-sentinel') && !contents.includes('host-sentinel'));
      if (file.endsWith('playwright-results.json'))assert.equal(JSON.parse(contents).config.webServer[0].env, undefined);
    }
    assert.equal(reports.length, mode === 'success' ? 21 : mode === 'failed_test' ? 2 : 1);
    checks.push({case:mode,passed:true,stubbed_child_processes:calls,published_reports:reports.length});
  }
} finally {fs.rmSync(temp,{recursive:true,force:true});}
const output = {kind:'Runner orchestration verification with stubbed child processes; not browser test execution',checks,temporary_files_removed:!fs.existsSync(temp)};
fs.writeFileSync(path.join(root,'docs/evidence/unified-notifications-and-settings/report-redaction-check.json'),JSON.stringify(output,null,2)+'\n');
process.stdout.write(JSON.stringify(output)+'\n');
