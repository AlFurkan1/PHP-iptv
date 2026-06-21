(function () {
    'use strict';

    const playerWrapper  = document.getElementById('playerWrapper');
    const bottomCarousel = document.getElementById('bottomCarousel');
    const viewerCount    = document.getElementById('viewerCount');

    let player            = null;
    let currentChannelId  = null;
    let viewerInterval    = null;
    let heartbeatInterval = null;
    let loadTimeout       = null;
    let streamStarted     = false;

    // Category collapse/expand
    document.querySelectorAll('.category-header').forEach(function (h) {
        h.addEventListener('click', function () {
            this.classList.toggle('collapsed');
            var sibling = this.nextElementSibling;
            while (sibling && sibling.classList.contains('channel-item')) {
                sibling.style.display = sibling.style.display === 'none' ? '' : 'none';
                sibling = sibling.nextElementSibling;
            }
        });
    });

    // Carousel scroll buttons
    document.querySelector('.left-arrow').addEventListener('click', function () {
        bottomCarousel.scrollBy({ left: -300, behavior: 'smooth' });
    });
    document.querySelector('.right-arrow').addEventListener('click', function () {
        bottomCarousel.scrollBy({ left: 300, behavior: 'smooth' });
    });

    function getAllClickableItems() {
        var items = [];
        document.querySelectorAll('.channel-item[data-id]').forEach(function (el) { items.push(el); });
        document.querySelectorAll('.channel-card[data-id]').forEach(function (el) { items.push(el); });
        return items;
    }

    function loadStream(channelId, name, streamType) {
        disposePlayer();
        currentChannelId = channelId;
        playerWrapper.classList.remove('playing');
        playerWrapper.innerHTML = '<div class="text-center"><div class="spinner-border text-info" role="status" style="width:3rem;height:3rem"></div></div>';

        var activeSidebar = document.querySelector('.channel-item[data-id="' + channelId + '"]');
        var activeCarousel = document.querySelector('.channel-card[data-id="' + channelId + '"]');

        loadTimeout = setTimeout(function () {
            if (!streamStarted) {
                disposePlayer();
                playerWrapper.innerHTML = '<div class="text-center" style="color:rgba(255,255,255,0.35)"><div class="mb-2 opacity-50" style="font-size:4rem">▶</div><p class="small mb-0">Stream timed out. Try again later.</p></div>';
            }
        }, 60000);

        fetch('api/stream.php?id=' + channelId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    clearTimeout(loadTimeout);
                    playerWrapper.innerHTML = '<div class="text-center" style="color:rgba(255,255,255,0.35)"><div class="mb-2 opacity-50" style="font-size:4rem">▶</div><p class="small mb-0">' + data.error + '</p></div>';
                    return;
                }

                initPlayer(data.url, data.mime);

                startViewerPoll(channelId);
            })
            .catch(function () {
                playerWrapper.innerHTML = '<div class="text-center" style="color:rgba(255,255,255,0.35)"><div class="mb-2 opacity-50" style="font-size:4rem">▶</div><p class="small mb-0">Failed to load stream.</p></div>';
            });

        getAllClickableItems().forEach(function (el) { el.classList.remove('active'); });
        if (activeSidebar) activeSidebar.classList.add('active');
        if (activeCarousel) activeCarousel.classList.add('active');
    }

    function initPlayer(url, mime) {
        disposePlayer();
        playerWrapper.innerHTML =
            '<video id="vid" class="video-js vjs-default-skin w-100 h-100" controls preload="auto" style="object-fit:contain">' +
            '  <source src="' + url + '" type="' + mime + '">' +
            '</video>';

        player = videojs('vid', {
            autoplay: true,
            preload: 'auto',
            controls: true,
            fluid: true,
            liveui: true,
            html5: {
                hls: {
                    overrideNative: true,
                    enableLowInitialPlaylist: true,
                    lowLatencyMode: false,
                    liveSyncDuration: 8,
                    liveMaxLatencyDuration: 15,
                    liveSyncDurationCount: 2,
                    maxLiveSyncPlaybackRate: 1.0,
                    maxBufferLength: 120,
                    backBufferLength: 30,
                    progressive: false,
                    fastQualityChange: true,
                    smoothQualityChange: true,
                    useBandwidthFromLocalStorage: true,
                    playlistExclusionDuration: 30
                },
                nativeAudioTracks: false,
                nativeVideoTracks: false
            }
        });

        player.one('error', function () {
            var errMsg = 'Stream unavailable.';
            if (player && player.error) {
                errMsg = 'Error ' + player.error().code + ': ' + player.error().message;
            }
            console.warn('Video.js error:', errMsg);
            playerWrapper.innerHTML = '<div class="text-center" style="color:rgba(255,255,255,0.35)"><div class="mb-2 opacity-50" style="font-size:4rem">▶</div><p class="small mb-0">' + errMsg + '</p></div>';
        });

        playerWrapper.classList.add('playing');
    }

    function startViewerPoll(channelId) {
        stopViewerPoll();
        heartbeat(channelId);
        fetchViewerCount(channelId);
        viewerInterval = setInterval(fetchViewerCount, 10000, channelId);
        heartbeatInterval = setInterval(heartbeat, 30000, channelId);
    }

    function stopViewerPoll() {
        if (viewerInterval) {
            clearInterval(viewerInterval);
            viewerInterval = null;
        }
        if (heartbeatInterval) {
            clearInterval(heartbeatInterval);
            heartbeatInterval = null;
        }
    }

    function fetchViewerCount(channelId) {
        fetch('api/viewers.php?channel_id=' + channelId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                viewerCount.innerHTML = '<i class="fas fa-eye"></i> ' + data.count;
            })
            .catch(function () {});
    }

    function disposePlayer() {
        stopViewerPoll();
        if (loadTimeout) { clearTimeout(loadTimeout); loadTimeout = null; }
        streamStarted = false;
        viewerCount.innerHTML = '<i class="fas fa-eye"></i> —';
        playerWrapper.classList.remove('playing');
        if (player) {
            try { player.dispose(); } catch (e) {}
            player = null;
        }
    }

    function heartbeat(channelId) {
        var payload = new URLSearchParams();
        payload.append('channel_id', channelId);
        navigator.sendBeacon('api/heartbeat.php', payload);
    }

    // Bind clicks on all channel items (sidebar + carousel)
    getAllClickableItems().forEach(function (el) {
        el.addEventListener('click', function () {
            var id   = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            var type = this.getAttribute('data-type');
            if (id) loadStream(id, name, type);
        });
    });

})();
