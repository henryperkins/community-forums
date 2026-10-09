#!/usr/bin/env bash
set -euo pipefail

# Applies RetroBoards' Cloud Run configuration: the web service, the cron jobs,
# the Cloud Scheduler entries that run them, and the deploy trigger's copy of
# cloudbuild.yaml. Re-run it after changing anything here, in env.yaml or in
# cloudbuild.yaml; it converges, so a second run is harmless.
#
# Deploys change only the image (cloudbuild.yaml). This script keeps the image
# the service already runs, so it never rolls code back; pass IMAGE=... only on
# the first run, before the service exists.
#
# Runbook: docs/runbooks/deployment-cloud-run.md

PROJECT=rising-woods-449718-v6
REGION=us-east4
SERVICE=retroboards
SQL_INSTANCE="$PROJECT:$REGION:imladris-boards"
DATA_BUCKET="$PROJECT-retroboards-data"
RUNTIME_SA="retroboards-app@$PROJECT.iam.gserviceaccount.com"
EDGE_SA="retroboards-edge@$PROJECT.iam.gserviceaccount.com"
SCHEDULER_SA="retroboards-scheduler@$PROJECT.iam.gserviceaccount.com"
HERE="$(cd "$(dirname "$0")" && pwd)"

# The Worker's former cron triggers, one Cloud Run job each (UTC). A tick runs
# its commands in order through retroboards-console-batch.
CRON_JOBS=(
    "retroboards-cron-5m|*/5 * * * *|worker:email,worker:webhooks"
    "retroboards-cron-6h|0 */6 * * *|worker:registry-refresh"
    "retroboards-cron-0310|10 3 * * *|worker:purge-ips,worker:attachments,worker:packages"
    "retroboards-cron-0700|0 7 * * *|worker:digest"
)

# /data is the uploads bucket, owned by www-data (uid/gid 33) in the container.
DATA_VOLUME="name=data,type=cloud-storage,bucket=$DATA_BUCKET,mount-options=uid=33;gid=33;file-mode=644;dir-mode=755;implicit-dirs"

# Secret env var and the Secret Manager secret behind it (latest version).
# Kept as pairs and joined below, so no line reads like VAR=credential to a
# secret scanner: these are secret names, never values.
SECRET_REFS=(
    "APP_KEY retroboards-app-key"
    "DB_PASSWORD imladris-boards-app-password"
)
# Until this secret exists the mailer reports itself unconfigured and the
# outbox holds every message; nothing is dropped.
if gcloud secrets versions describe latest --secret=retroboards-cloudflare-email-token \
    --project="$PROJECT" >/dev/null 2>&1; then
    SECRET_REFS+=("CLOUDFLARE_EMAIL_API_TOKEN retroboards-cloudflare-email-token")
fi
SECRETS=""
for ref in "${SECRET_REFS[@]}"; do
    read -r var secret <<< "$ref"
    SECRETS="${SECRETS:+$SECRETS,}$var=$secret:latest"
done

IMAGE="${IMAGE:-$(gcloud run services describe "$SERVICE" --project="$PROJECT" --region="$REGION" \
    --format='value(spec.template.spec.containers[0].image)' 2>/dev/null || true)}"
if [ -z "$IMAGE" ]; then
    echo "configure.sh: no $SERVICE service yet; run once with IMAGE=<image> (runbook §4)" >&2
    exit 1
fi

# One instance, always: sessions are in the database, but the rate-limit
# ledger is a local file, so a second instance would split it.
gcloud run deploy "$SERVICE" --project="$PROJECT" --region="$REGION" --quiet \
    --image="$IMAGE" \
    --service-account="$RUNTIME_SA" \
    --no-allow-unauthenticated \
    --execution-environment=gen2 \
    --cpu=1 --memory=1Gi --concurrency=16 --cpu-boost --timeout=300 \
    --min-instances=0 --max-instances=1 \
    --set-cloudsql-instances="$SQL_INSTANCE" \
    --clear-volumes --clear-volume-mounts \
    --add-volume="$DATA_VOLUME" --add-volume-mount=volume=data,mount-path=/data \
    --env-vars-file="$HERE/env.yaml" \
    --set-secrets="$SECRETS" \
    --labels=app=retroboards,role=web

# Only the Cloudflare Worker may call the service (its key: runbook §6).
gcloud run services add-iam-policy-binding "$SERVICE" --project="$PROJECT" --region="$REGION" \
    --member="serviceAccount:$EDGE_SA" --role=roles/run.invoker --quiet >/dev/null

# Jobs never migrate: the service does that on boot, before a deploy's new
# revision takes traffic and before cloudbuild.yaml updates the jobs.
JOB_ENV="$(mktemp)"
trap 'rm -f "$JOB_ENV"' EXIT
sed 's/^RUN_MIGRATIONS: .*/RUN_MIGRATIONS: "false"/' "$HERE/env.yaml" > "$JOB_ENV"

for entry in "${CRON_JOBS[@]}"; do
    IFS='|' read -r job schedule commands <<< "$entry"

    gcloud run jobs deploy "$job" --project="$PROJECT" --region="$REGION" --quiet \
        --image="$IMAGE" \
        --service-account="$RUNTIME_SA" \
        --cpu=1 --memory=512Mi --tasks=1 --max-retries=0 --task-timeout=15m \
        --set-cloudsql-instances="$SQL_INSTANCE" \
        --clear-volumes --clear-volume-mounts \
        --add-volume="$DATA_VOLUME" --add-volume-mount=volume=data,mount-path=/data \
        --env-vars-file="$JOB_ENV" \
        --set-secrets="$SECRETS" \
        --args="retroboards-console-batch,$commands" \
        --labels=app=retroboards,role=cron

    gcloud run jobs add-iam-policy-binding "$job" --project="$PROJECT" --region="$REGION" \
        --member="serviceAccount:$SCHEDULER_SA" --role=roles/run.invoker --quiet >/dev/null

    verb=create
    if gcloud scheduler jobs describe "$job" --project="$PROJECT" --location="$REGION" >/dev/null 2>&1; then
        verb=update
    fi
    gcloud scheduler jobs "$verb" http "$job" --project="$PROJECT" --location="$REGION" --quiet \
        --schedule="$schedule" --time-zone=Etc/UTC \
        --uri="https://run.googleapis.com/v2/projects/$PROJECT/locations/$REGION/jobs/$job:run" \
        --http-method=POST \
        --oauth-service-account-email="$SCHEDULER_SA" >/dev/null

    echo "configure.sh: $job ($schedule UTC) -> $commands"
done

# The deploy trigger runs its own copy of cloudbuild.yaml (a webhook trigger
# cannot read the repo without a GitHub App connection). Re-import the whole
# trigger around this checkout's file so the copy never drifts; `triggers
# import` updates in place, while `triggers update webhook --inline-config`
# sends an invalid field mask (gcloud 588). The webhook secret, API key and
# GitHub hook are one-time setup (runbook §5).
if gcloud secrets describe retroboards-deploy-webhook --project="$PROJECT" >/dev/null 2>&1; then
    TRIGGER="$(mktemp)"
    trap 'rm -f "$JOB_ENV" "$TRIGGER"' EXIT
    {
        cat <<EOF
name: retroboards-main
description: Deploy every push to main to Cloud Run (GitHub push webhook)
webhookConfig:
  secret: projects/$PROJECT/secrets/retroboards-deploy-webhook/versions/1
substitutions:
  _SHA: \$(body.after)
  _REF: \$(body.ref)
filter: _REF == "refs/heads/main"
serviceAccount: projects/$PROJECT/serviceAccounts/retroboards-deploy@$PROJECT.iam.gserviceaccount.com
build:
EOF
        sed 's/^/  /' "$HERE/cloudbuild.yaml"
    } > "$TRIGGER"
    gcloud builds triggers import --source="$TRIGGER" --project="$PROJECT" --region="$REGION" >/dev/null
    echo "configure.sh: deploy trigger retroboards-main runs this checkout's cloudbuild.yaml"
fi
