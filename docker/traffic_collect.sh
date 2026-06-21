#!/bin/sh
# Start nginx in background
nginx -g 'daemon off;' &

# Collect eth0 traffic stats to shared cache every 5 minutes
while true; do
  awk '/eth0:/{printf "{\"rx\":%s,\"tx\":%s}",$2,$10}' /proc/net/dev > /var/www/iptv/cache/netstats.json
  sleep 60
done