# PHP IPTV — Live TV Streaming Platform

A self-hosted IPTV streaming server with live channel management, HLS proxy, M3U playlist generation, and **OBS/vMix live streaming ingest** via RTMP.

## Features

- **Channel Management** — Add, edit, delete channels with support for M3U8, MP4, RTMP, Dash, YouTube, TS, and custom Live streams
- **Live Ingest (OBS/vMix)** — RTMP ingest via MediaMTX; OBS pushes a live feed that is immediately served as HLS to viewers
- **HLS Proxy** — Rewrites HLS manifest URLs through the server to avoid CORS/IP blocking; nginx handles segment delivery without PHP overhead
- **M3U Playlist** — Auto-generated playlist for external IPTV players (VLC, IPTV Smarters, etc.)
- **YouTube Streaming** — Uses yt-dlp to resolve YouTube live URLs into playable HLS streams
- **Viewer Tracking** — Real-time viewer count with heartbeat API and live session monitoring
- **Admin Dashboard** — Manage channels, categories, branding, cache settings, activity logs, and admin credentials
- **Docker Deployment** — Fully containerized with Docker Compose (nginx, PHP-FPM, MariaDB, MediaMTX)

## Quick Start

```bash
git clone https://github.com/AlFurkan1/PHP-iptv.git
cd PHP-iptv
docker-compose up -d
```

Then access:
- **Viewer**: http://localhost/
- **Admin Panel**: http://localhost/admin/login.php (default: `admin` / `admin123`)

## Streaming with OBS / vMix

1. Go to **Admin → Channels → + Add Channel**
2. Select **"Live (OBS/vMix)"** type
3. Copy the auto-generated **Ingest Server URL** and **Stream Key**
4. In OBS/vMix: Settings → Stream → Custom...
   - Server: `rtmp://YOUR-SERVER-IP:1935/live`
   - Stream Key: *(paste the key)*
5. Start streaming — viewers see it live on the IPTV page

## Architecture

```
OBS/vMix ──RTMP──> MediaMTX ──HLS──> nginx ──> Browser
                    :1935           :8888      :80/live/
```

- **MediaMTX**: RTMP ingest server — accepts pushes from OBS/vMix, outputs HLS
- **nginx**: Front-end web server — serves the viewer page, proxies HLS from MediaMTX, and relays CDN segments via `/cdn/`
- **PHP-FPM**: Application server — resolves stream URLs, rewrites HLS manifests, manages the admin dashboard
- **MariaDB**: Database — stores channels, categories, viewers, and activity logs

## Docker Images

| Image | Description |
|-------|-------------|
| `alfurkan1/iptv-nginx` | nginx with CDN proxy and HLS reverse-proxy config |
| `alfurkan1/iptv-php` | PHP 8.2 FPM with ffmpeg, yt-dlp, and PDO MySQL |
| `alfurkan1/iptv-mariadb` | MariaDB 10.11 with pre-initialized schema |
| `bluenviron/mediamtx` | RTMP/HLS/WebRTC media server for live ingest |

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `MYSQL_ROOT_PASSWORD` | `iptv_root_pass` | MariaDB root password |
| `MYSQL_DATABASE` | `iptv_system` | Database name |
| `MYSQL_USER` | `iptv_user` | Database user |
| `MYSQL_PASSWORD` | `iptv_pass` | Database password |

## License

MIT
