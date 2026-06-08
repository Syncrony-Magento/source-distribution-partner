/**
 * Plugin Name: Source Content Distribution
 * Description: Pull approved content from Source bundles and publish to WordPress with taxonomy mapping.
 * Version: 1.4.72
 * Author: Syncrony Digital
 * License: GPLv2 or later
 * Text Domain: source-distribution-partner
 */

/* global SourceDPVideo */
(function () {
  function loadYouTubeAPI(cb) {
    if (window.YT && window.YT.Player) return cb();
    var tag = document.createElement("script");
    tag.src = "https://www.youtube.com/iframe_api";
    window.onYouTubeIframeAPIReady = cb;
    document.head.appendChild(tag);
  }

  function postViewOnce() {
    if (postViewOnce._done) return;
    postViewOnce._done = true;

    var fd = new FormData();
    fd.append("action", "source_dp_video_view");
    fd.append("nonce", SourceDPVideo.nonce);
    fd.append("postId", String(SourceDPVideo.postId));

    fetch(SourceDPVideo.ajaxUrl, { method: "POST", body: fd, credentials: "same-origin" }).catch(function () {});
  }

  function init() {
    var el = document.querySelector(".source-dp-video[data-youtube-id]");
    if (!el) return;

    var youtubeId = el.getAttribute("data-youtube-id");
    if (!youtubeId) return;

    loadYouTubeAPI(function () {
      var watched = 0;
      var interval = null;

      new window.YT.Player(el, {
        videoId: youtubeId,
        playerVars: { rel: 0, modestbranding: 1 },
        events: {
          onStateChange: function (evt) {
            if (evt.data === window.YT.PlayerState.PLAYING) {
              if (interval) return;
              interval = setInterval(function () {
                watched += 1;
                if (watched >= 30) {
                  clearInterval(interval);
                  interval = null;
                  postViewOnce();
                }
              }, 1000);
            } else if (
              evt.data === window.YT.PlayerState.PAUSED ||
              evt.data === window.YT.PlayerState.ENDED
            ) {
              if (interval) {
                clearInterval(interval);
                interval = null;
              }
            }
          },
        },
      });
    });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
