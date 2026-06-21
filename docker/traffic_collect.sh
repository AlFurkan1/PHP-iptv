#!/bin/sh
# Start nginx in background
nginx -g 'daemon off;' &

CACHE="/var/www/iptv/cache/netstats.json"
SETTINGS="/var/www/iptv/cache/settings.json"

# Read network interface from shared settings (default eth0)
NET_IFACE="eth0"
if [ -f "$SETTINGS" ]; then
  IFACE=$(awk -F'"' '/net_interface/{print $4}' "$SETTINGS")
  [ -n "$IFACE" ] && NET_IFACE="$IFACE"
fi

# Collect traffic stats every 60s
while true; do
  awk "/$NET_IFACE:/"'{printf "{\"rx\":%s,\"tx\":%s}",$2,$10}' /proc/net/dev > "$CACHE"
  # Hit the PHP ingestion endpoint via BusyBox nc (no curl/wget needed)
  printf "GET /api/traffic_collect.php HTTP/1.0\r\nHost: localhost\r\n\r\n" | nc -w 5 localhost 80 > /dev/null 2>&1
  sleep 60
done