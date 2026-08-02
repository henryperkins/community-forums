FROM mariadb:11.4

HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=5 \
    CMD healthcheck.sh --connect --innodb_initialized || exit 1
