#!/usr/bin/env python3
"""Convergent operations-only monitoring setup; credentials stay in process.

Preview: python3 deploy/cloudrun/monitoring/configure.py plan
Apply:   python3 deploy/cloudrun/monitoring/configure.py apply --recipient-file /private/owner.json
Verify:  python3 deploy/cloudrun/monitoring/configure.py verify --evidence /private/summary.json
The private recipient JSON has one key, address. Never commit it.
"""

import argparse
import datetime
import hashlib
import json
import pathlib
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request

PROJECT = "rising-woods-449718-v6"
REGION = "us-east4"
JOB = "retroboards-monitor"
SA = f"{JOB}@{PROJECT}.iam.gserviceaccount.com"
SCHEDULER_SA = f"retroboards-scheduler@{PROJECT}.iam.gserviceaccount.com"
HERE = pathlib.Path(__file__).resolve().parent
MONITORING = f"https://monitoring.googleapis.com/v3/projects/{PROJECT}"
RUN = f"https://run.googleapis.com/v2/projects/{PROJECT}/locations/{REGION}/jobs"
LOGGING = "https://logging.googleapis.com/v2"
MANAGED = {"app": "retroboards", "managed_by": "retroboards-monitoring"}


class SetupError(Exception):
    pass


def gcloud(*args, missing_ok=False):
    result = subprocess.run(["gcloud", *args, f"--project={PROJECT}", "--quiet"], capture_output=True, text=True)
    if result.returncode:
        known_missing_job = (args[:3] == ("run", "jobs", "describe") and len(args) > 3
                             and f"Cannot find job [{args[3]}]" in result.stderr)
        if missing_ok and ("NOT_FOUND" in result.stderr or known_missing_job):
            return None
        # gcloud/API diagnostics may contain addresses; do not print them.
        raise SetupError(f"gcloud operation failed: {' '.join(args[:3])} (exit {result.returncode})")
    return result.stdout.strip()


class Api:
    def __init__(self):
        self.token = gcloud("auth", "print-access-token")

    def call(self, method, url, body=None):
        request = urllib.request.Request(
            url,
            data=None if body is None else json.dumps(body).encode(),
            headers={"Authorization": f"Bearer {self.token}", "Content-Type": "application/json"},
            method=method,
        )
        try:
            with urllib.request.urlopen(request, timeout=45) as response:
                payload = response.read()
                return json.loads(payload) if payload else {}
        except urllib.error.HTTPError as error:
            # Keep private API response bodies out of stdout/stderr.
            raise SetupError(f"API {method} {urllib.parse.urlparse(url).path}: HTTP {error.code}") from None

    def items(self, url, key):
        items = []
        for _ in range(20):
            data = self.call("GET", url)
            items.extend(data.get(key, []))
            token = data.get("nextPageToken")
            if not token:
                return items
            url = url.split("?", 1)[0] + "?pageToken=" + urllib.parse.quote(token)
        raise SetupError("API listing exceeded bounded pagination")


def uptime_config():
    return {
        "displayName": "RetroBoards public health",
        "monitoredResource": {"type": "uptime_url", "labels": {"project_id": PROJECT, "host": "forum.candidary.online"}},
        "httpCheck": {"requestMethod": "GET", "useSsl": True, "validateSsl": True, "port": 443, "path": "/healthz", "acceptedResponseStatusCodes": [{"statusValue": 200}]},
        "period": "60s", "timeout": "10s", "selectedRegions": ["USA", "EUROPE", "ASIA_PACIFIC"],
        "checkerType": "STATIC_IP_CHECKERS",
        "contentMatchers": [{"content": json.dumps("ok"), "matcher": "MATCHES_JSON_PATH", "jsonPathMatcher": {"jsonPath": "$.status", "jsonMatcher": "EXACT_MATCH"}}],
        "userLabels": MANAGED,
    }


def policy_configs(check_id, channel):
    policies = json.loads((HERE / "policies.json").read_text())
    for policy in policies:
        key = policy.pop("key")
        policy = json.loads(json.dumps(policy).replace("{UPTIME_ID}", check_id))
        policy["userLabels"]["policy"] = key
        policy["notificationChannels"] = [channel]
        yield policy


def ensure_channel(api, recipient_file):
    channels = api.items(MONITORING + "/notificationChannels", "notificationChannels")
    candidates = [channel for channel in channels if channel.get("type") == "email" and channel.get("enabled", True)
                  and channel.get("userLabels", {}).get("managed_by") == MANAGED["managed_by"]]
    if candidates:
        if len(candidates) != 1:
            raise SetupError("Multiple managed owner channels; resolve explicitly")
        return candidates[0]
    if not recipient_file:
        raise SetupError("No managed owner channel: --recipient-file is required")
    path = pathlib.Path(recipient_file)
    if path.stat().st_mode & 0o077:
        raise SetupError("Recipient file must be readable only by its owner (0600)")
    address = json.loads(path.read_text())["address"]
    if not isinstance(address, str) or "@" not in address or any(c in address for c in "\r\n"):
        raise SetupError("Invalid protected recipient")
    # Reuse an existing enabled channel to the privately resolved owner.
    for channel in channels:
        if channel.get("type") == "email" and channel.get("enabled", True) and channel.get("labels", {}).get("email_address") == address:
            if channel.get("verificationStatus") == "UNVERIFIED":
                raise SetupError("Existing owner channel requires verification")
            return channel
    return api.call("POST", MONITORING + "/notificationChannels", {
        "type": "email", "displayName": "RetroBoards owner", "description": "Operational alerts to the authenticated project owner",
        "labels": {"email_address": address}, "enabled": True, "userLabels": MANAGED,
    })


def ensure_collector(api):
    if gcloud("iam", "service-accounts", "describe", SA, missing_ok=True) is None:
        gcloud("iam", "service-accounts", "create", JOB, "--display-name=RetroBoards read-only monitoring")
    for role in ["roles/cloudsql.client", "roles/cloudsql.viewer"]:
        gcloud("projects", "add-iam-policy-binding", PROJECT, f"--member=serviceAccount:{SA}", f"--role={role}")
    gcloud("secrets", "add-iam-policy-binding", "imladris-boards-app-password", f"--member=serviceAccount:{SA}", "--role=roles/secretmanager.secretAccessor")
    gcloud("iam", "service-accounts", "add-iam-policy-binding", SA,
           f"--member=serviceAccount:retroboards-deploy@{PROJECT}.iam.gserviceaccount.com", "--role=roles/iam.serviceAccountUser")
    for job in ["retroboards-cron-5m", "retroboards-cron-6h", "retroboards-cron-0310", "retroboards-cron-0700"]:
        gcloud("run", "jobs", "add-iam-policy-binding", job, f"--region={REGION}", f"--member=serviceAccount:{SA}", "--role=roles/run.viewer")
    image = gcloud("run", "services", "describe", "retroboards", f"--region={REGION}", "--format=value(spec.template.spec.containers[0].image)")
    if not image:
        raise SetupError("Missing production image")
    # Use the current immutable revision reference, with no new web deployment.
    env = []
    allowed = {"DB_SOCKET", "DB_DATABASE", "DB_USERNAME", "DB_EMULATE_PREPARES"}
    for line in (HERE.parent / "env.yaml").read_text().splitlines():
        if ":" in line and not line.startswith("#"):
            key, value = line.split(":", 1)
            if key in allowed:
                env.append({"name": key, "value": value.strip().strip('"')})
    if {item["name"] for item in env} != allowed:
        raise SetupError("Missing bounded database environment")
    env.append({"name": "DB_PASSWORD", "valueSource": {"secretKeyRef": {"secret": "imladris-boards-app-password", "version": "latest"}}})
    probe = (HERE / "probe.php").read_text()
    if not probe.startswith("<?php\n"):
        raise SetupError("Unexpected probe format")
    body = {
        "labels": {"app": "retroboards", "role": "monitor"},
        "template": {"taskCount": 1, "parallelism": 1, "template": {
            "serviceAccount": SA, "timeout": "180s", "maxRetries": 0,
            "containers": [{"image": image, "command": ["php"], "args": ["-r", probe.removeprefix("<?php\n")], "env": env,
                            "resources": {"limits": {"cpu": "1", "memory": "512Mi"}},
                            "volumeMounts": [{"name": "cloudsql", "mountPath": "/cloudsql"}]}],
            "volumes": [{"name": "cloudsql", "cloudSqlInstance": {"instances": [f"{PROJECT}:{REGION}:imladris-boards"]}}],
        }},
    }
    existing = gcloud("run", "jobs", "describe", JOB, f"--region={REGION}", "--format=value(metadata.name)", missing_ok=True)
    if existing:
        # Cloud Run v2 Jobs.UpdateJob has no updateMask parameter. The job
        # object supplies the complete owned template and labels instead.
        api.call("PATCH", RUN + "/" + JOB, {"name": RUN.replace("https://run.googleapis.com/v2/", "") + "/" + JOB, **body})
    else:
        api.call("POST", RUN + "?jobId=" + JOB, body)
    # gcloud's IAM command waits until the just-created resource is ready.
    gcloud("run", "jobs", "add-iam-policy-binding", JOB, f"--region={REGION}", f"--member=serviceAccount:{SCHEDULER_SA}", "--role=roles/run.invoker")
    exists = gcloud("scheduler", "jobs", "describe", JOB, f"--location={REGION}", "--format=value(name)", missing_ok=True)
    gcloud("scheduler", "jobs", "update" if exists else "create", "http", JOB,
           f"--location={REGION}", "--schedule=*/15 * * * *", "--time-zone=Etc/UTC",
           f"--uri={RUN}/{JOB}:run", "--http-method=POST", f"--oauth-service-account-email={SCHEDULER_SA}")


def validate_filters(api, policies):
    now = datetime.datetime.now(datetime.timezone.utc)
    for policy in policies:
        for condition in policy["conditions"]:
            if "conditionMatchedLog" in condition:
                api.call("POST", LOGGING + "/entries:list", {"resourceNames": [f"projects/{PROJECT}"], "filter": condition["conditionMatchedLog"]["filter"], "pageSize": 1})
            else:
                value = condition.get("conditionThreshold", condition.get("conditionAbsent"))
                query = urllib.parse.urlencode({"filter": value["filter"], "interval.startTime": (now - datetime.timedelta(hours=1)).isoformat(), "interval.endTime": now.isoformat(), "view": "HEADERS"})
                api.call("GET", MONITORING + "/timeSeries?" + query)


def apply(api, args):
    channel = ensure_channel(api, args.recipient_file)
    if channel.get("verificationStatus") == "UNVERIFIED":
        raise SetupError("Owner email channel requires verification before alert setup")
    config = uptime_config()
    existing = [x for x in api.items(MONITORING + "/uptimeCheckConfigs", "uptimeCheckConfigs") if x.get("userLabels", {}).get("managed_by") == MANAGED["managed_by"]]
    if len(existing) > 1:
        raise SetupError("Multiple managed uptime checks")
    if existing:
        check = api.call("PATCH", "https://monitoring.googleapis.com/v3/" + existing[0]["name"], {"name": existing[0]["name"], **config})
    else:
        check = api.call("POST", MONITORING + "/uptimeCheckConfigs", config)
    metric_url = LOGGING + f"/projects/{PROJECT}/metrics/retroboards_monitor_heartbeat"
    metric = {"name": "retroboards_monitor_heartbeat", "description": "Successful read-only aggregate monitoring snapshots; no member fields",
              "filter": 'resource.type="cloud_run_job" AND resource.labels.job_name="retroboards-monitor" AND jsonPayload.event="retroboards_monitor" AND jsonPayload.status="ok"',
              "metricDescriptor": {"metricKind": "DELTA", "valueType": "INT64", "unit": "1"}}
    api.call("PUT", metric_url, metric)
    ensure_collector(api)
    policies = list(policy_configs(check["name"].rsplit("/", 1)[-1], channel["name"]))
    validate_filters(api, policies)
    existing = {x.get("userLabels", {}).get("policy"): x for x in api.items(MONITORING + "/alertPolicies", "alertPolicies") if x.get("userLabels", {}).get("managed_by") == MANAGED["managed_by"]}
    for policy in policies:
        previous = existing.get(policy["userLabels"]["policy"])
        if previous:
            # Retain condition IDs across convergent runs; replacing them can
            # reset absence-condition measurement history.
            condition_names = {x["displayName"]: x["name"] for x in previous.get("conditions", []) if "name" in x}
            for condition in policy["conditions"]:
                if condition["displayName"] in condition_names:
                    condition["name"] = condition_names[condition["displayName"]]
            api.call("PATCH", "https://monitoring.googleapis.com/v3/" + previous["name"], {"name": previous["name"], **policy})
        else:
            api.call("POST", MONITORING + "/alertPolicies", policy)
    return verify(api)


def verify(api):
    policies = [x for x in api.items(MONITORING + "/alertPolicies", "alertPolicies") if x.get("userLabels", {}).get("managed_by") == MANAGED["managed_by"]]
    channels = {x["name"]: x for x in api.items(MONITORING + "/notificationChannels", "notificationChannels")}
    checks = [x for x in api.items(MONITORING + "/uptimeCheckConfigs", "uptimeCheckConfigs") if x.get("userLabels", {}).get("managed_by") == MANAGED["managed_by"]]
    expected = {x["key"] for x in json.loads((HERE / "policies.json").read_text())}
    actual = {x.get("userLabels", {}).get("policy") for x in policies}
    valid = expected == actual and len(checks) == 1 and all(x.get("enabled") for x in policies)
    summaries = []
    for policy in policies:
        linked = [channels.get(name) for name in policy.get("notificationChannels", [])]
        channel_ok = bool(linked) and all(x and x.get("enabled", True) and x.get("verificationStatus") != "UNVERIFIED" for x in linked)
        valid = valid and channel_ok and not policy.get("validity")
        summaries.append({"name": policy["name"], "key": policy["userLabels"]["policy"], "enabled": policy.get("enabled"), "channel_valid": channel_ok, "validity": policy.get("validity", {})})
    job = api.call("GET", RUN + "/" + JOB)
    template = job["template"]["template"]
    container = template["containers"][0]
    environment = container.get("env", [])
    secret_refs = {x["name"]: x["valueSource"]["secretKeyRef"]["secret"] for x in environment if "valueSource" in x}
    probe = (HERE / "probe.php").read_text().removeprefix("<?php\n")
    scheduler = json.loads(gcloud("scheduler", "jobs", "describe", JOB, f"--location={REGION}", "--format=json"))
    collector_ok = (template.get("serviceAccount") == SA and container.get("command") == ["php"]
                    and container.get("args") == ["-r", probe]
                    and secret_refs == {"DB_PASSWORD": "imladris-boards-app-password"}
                    and {x["name"] for x in environment} == {"DB_SOCKET", "DB_DATABASE", "DB_USERNAME", "DB_EMULATE_PREPARES", "DB_PASSWORD"}
                    and template.get("maxRetries", 0) == 0 and template.get("timeout") == "180s"
                    and scheduler.get("state") == "ENABLED" and scheduler.get("schedule") == "*/15 * * * *"
                    and scheduler.get("timeZone") == "Etc/UTC"
                    and scheduler.get("httpTarget", {}).get("oauthToken", {}).get("serviceAccountEmail") == SCHEDULER_SA)
    valid = valid and collector_ok
    return {"captured_at": datetime.datetime.now(datetime.timezone.utc).isoformat(), "project": PROJECT, "configuration_valid": bool(valid), "policies": summaries,
            "uptime_checks": [{"name": x["name"], "host": x["monitoredResource"]["labels"]["host"], "path": x["httpCheck"]["path"], "period": x["period"], "validate_ssl": x["httpCheck"].get("validateSsl"), "selected_regions": x.get("selectedRegions")} for x in checks],
            "notification_channels": [{"name": x["name"], "type": x.get("type"), "enabled": x.get("enabled", True), "verification_status": x.get("verificationStatus", "VERIFICATION_STATUS_UNSPECIFIED")} for x in channels.values() if x["name"] in {name for policy in policies for name in policy.get("notificationChannels", [])}],
            "collector": {"name": job["name"], "configuration_valid": bool(collector_ok), "service_account": template.get("serviceAccount"), "image": container["image"],
                          "probe_sha256": hashlib.sha256(probe.encode()).hexdigest(), "runtime_probe_matches_source": container.get("args") == ["-r", probe],
                          "secret_references": secret_refs, "environment_names": sorted(x["name"] for x in environment), "resources": container.get("resources"),
                          "task_timeout": template.get("timeout"), "max_retries": template.get("maxRetries", 0), "scheduler_state": scheduler.get("state"), "schedule": scheduler.get("schedule"), "time_zone": scheduler.get("timeZone"),
                          "database_scope": "Existing application DB credential has application write privileges. This collector enforces START TRANSACTION READ ONLY and selects only aggregate counts/ages; no separate read-only DB user was created."},
            "privacy": "No notification address, token, member identifiers or message data included. Email channels do not require an SMS-style verification code; UNVERIFIED is rejected."}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=["plan", "apply", "verify"])
    parser.add_argument("--recipient-file")
    parser.add_argument("--evidence")
    args = parser.parse_args()
    try:
        if args.action == "plan":
            result = {"uptime_check": uptime_config(), "policies": list(policy_configs("REPLACED_ON_APPLY", "PROTECTED_OWNER_CHANNEL")), "collector": {"job": JOB, "schedule": "*/15 * * * *", "identity": SA, "database_read_only": True, "secret_names": ["imladris-boards-app-password"], "grace_seconds": 1800, "automated_backup_stale_seconds": 129600}}
        else:
            api = Api()
            result = apply(api, args) if args.action == "apply" else verify(api)
        rendered = json.dumps(result, indent=2) + "\n"
        if args.evidence:
            pathlib.Path(args.evidence).write_text(rendered)
        print(rendered, end="")
        if args.action != "plan" and not result["configuration_valid"]:
            return 1
        return 0
    except (SetupError, KeyError, ValueError, OSError) as error:
        print(f"monitoring setup: {error if isinstance(error, SetupError) else type(error).__name__}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
