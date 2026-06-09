/**
 * Plugin Name: Source Content Distribution
 * Description: Pull approved content from Source bundles and publish to WordPress with taxonomy mapping.
 * Version: 1.4.74
 * Author: Syncrony Digital
 * License: GPLv2 or later
 * Text Domain: source-distribution-partner
 */

/* global SourceDPFront */
(function () {
  if (!window.SourceDPFront) return;

  var ajaxUrl = SourceDPFront.ajaxUrl || "";
  var nonce = SourceDPFront.nonce || "";
  var postId = SourceDPFront.postId || 0;
  var enableTrack = !!SourceDPFront.enableTrack;
  var enableImpressions = !!SourceDPFront.enableImpressions;
  var enableClicks = !!SourceDPFront.enableClicks;
  var tracked = false;
  var pageviewTracked = false;
  var mediaPlayTracked = false;
  var mediaTimer = null;
  var observedImpressions = {};
  var sessionPrefix = "source_dp_analytics_";
  var sessionTtlMs = 12 * 60 * 60 * 1000;

  function analyticsSessionKey(eventName, id) {
    return sessionPrefix + String(eventName || "event") + "_" + String(id || "0");
  }

  function wasTrackedThisSession(eventName, id) {
    var key = analyticsSessionKey(eventName, id);
    try {
      if (window.sessionStorage && window.sessionStorage.getItem(key) === "1") return true;
    } catch (e) {}
    try {
      if (!window.localStorage) return false;
      var raw = window.localStorage.getItem(key);
      if (!raw) return false;
      var ts = parseInt(raw, 10);
      if (ts && (Date.now() - ts) < sessionTtlMs) return true;
      window.localStorage.removeItem(key);
    } catch (e2) {}
    return false;
  }

  function markTrackedThisSession(eventName, id) {
    var key = analyticsSessionKey(eventName, id);
    try {
      if (window.sessionStorage) window.sessionStorage.setItem(key, "1");
    } catch (e) {}
    try {
      if (window.localStorage) window.localStorage.setItem(key, String(Date.now()));
    } catch (e2) {}
  }

  var overlay = null;
  var youtubeFrame = null;
  var iframeFrame = null;
  var html5Video = null;
  var html5Audio = null;
  var titleNode = null;
  var fallbackNode = null;

  function extractYouTubeId(url) {
    if (!url) return "";
    var patterns = [
      /youtu\.be\/([A-Za-z0-9_-]{6,})/i,
      /[?&]v=([A-Za-z0-9_-]{6,})/i,
      /\/embed\/([A-Za-z0-9_-]{6,})/i,
      /\/shorts\/([A-Za-z0-9_-]{6,})/i
    ];
    for (var i = 0; i < patterns.length; i++) {
      var m = String(url).match(patterns[i]);
      if (m && m[1]) return m[1];
    }
    return "";
  }

  function isDirectMediaUrl(url) {
    return /\.(mp4|m4v|mov|webm|ogv|m3u8)(?:$|[?#])/i.test(String(url || ""));
  }

  function isAudioUrl(url) {
    return /\.(mp3|m4a|wav|ogg|aac)(?:$|[?#])/i.test(String(url || ""));
  }

  function ensureModal() {
    if (overlay) return;
    overlay = document.createElement("div");
    overlay.id = "source-dp-modal";
    overlay.style.cssText = "display:none;position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:999999;align-items:center;justify-content:center;padding:24px;";

    var box = document.createElement("div");
    box.style.cssText = "position:relative;width:min(100%,1100px);max-height:calc(100vh - 48px);background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.45);display:flex;flex-direction:column;";

    var header = document.createElement("div");
    header.style.cssText = "display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;border-bottom:1px solid rgba(0,0,0,.08);background:#fff;min-height:52px;";

    titleNode = document.createElement("div");
    titleNode.style.cssText = "font-size:16px;font-weight:600;color:#111;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;";
    header.appendChild(titleNode);

    var controls = document.createElement("div");
    controls.style.cssText = "display:flex;align-items:center;gap:10px;flex-shrink:0;";

    fallbackNode = document.createElement("a");
    fallbackNode.target = "_blank";
    fallbackNode.rel = "noopener noreferrer";
    fallbackNode.textContent = "Open in new tab";
    fallbackNode.style.cssText = "display:none;font-size:13px;color:#0a58ca;text-decoration:none;";
    controls.appendChild(fallbackNode);

    var close = document.createElement("button");
    close.type = "button";
    close.setAttribute("aria-label", "Close modal");
    close.innerHTML = "&times;";
    close.style.cssText = "border:0;background:transparent;color:#111;font-size:32px;line-height:1;width:36px;height:36px;border-radius:999px;cursor:pointer;flex-shrink:0;";
    controls.appendChild(close);

    header.appendChild(controls);

    var body = document.createElement("div");
    body.style.cssText = "position:relative;background:#000;flex:1;display:flex;align-items:center;justify-content:center;min-height:300px;";

    youtubeFrame = document.createElement("iframe");
    youtubeFrame.setAttribute("allow", "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share");
    youtubeFrame.setAttribute("allowfullscreen", "allowfullscreen");
    youtubeFrame.style.cssText = "display:none;width:100%;aspect-ratio:16/9;border:0;background:#000;";

    html5Video = document.createElement("video");
    html5Video.setAttribute("controls", "controls");
    html5Video.setAttribute("playsinline", "playsinline");
    html5Video.style.cssText = "display:none;width:100%;max-height:90%;background:#000;";

    html5Audio = document.createElement("audio");
    html5Audio.setAttribute("controls", "controls");
    html5Audio.style.cssText = "display:none;width:90%;max-width:800px;background:#111;padding:20px;border-radius:8px;";

    iframeFrame = document.createElement("iframe");
    iframeFrame.style.cssText = "display:none;width:100%;height:100%;border:0;background:#fff;";
    iframeFrame.setAttribute("referrerpolicy", "strict-origin-when-cross-origin");

    body.appendChild(youtubeFrame);
    body.appendChild(html5Video);
    body.appendChild(html5Audio);
    body.appendChild(iframeFrame);
    box.appendChild(header);
    box.appendChild(body);
    overlay.appendChild(box);
    document.body.appendChild(overlay);

    close.addEventListener("click", closeModal);
    overlay.addEventListener("click", function (e) {
      if (e.target === overlay) closeModal();
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && overlay && overlay.style.display !== "none") closeModal();
    });
    [html5Video, html5Audio].forEach(function(media){
      if (!media) return;
      media.addEventListener("play", startMediaPlayTimer);
      media.addEventListener("pause", function(){ if (mediaTimer) { clearTimeout(mediaTimer); mediaTimer = null; } });
      media.addEventListener("ended", function(){ if (mediaTimer) { clearTimeout(mediaTimer); mediaTimer = null; } });
    });
  }

  function closeModal() {
    if (mediaTimer) { clearTimeout(mediaTimer); mediaTimer = null; }
    if (!overlay) return;
    youtubeFrame.src = "";
    if (html5Video) {
      html5Video.pause();
      html5Video.removeAttribute("src");
      html5Video.load();
    }
    if (html5Audio) {
      html5Audio.pause();
      html5Audio.removeAttribute("src");
      html5Audio.load();
    }
    iframeFrame.src = "";
    youtubeFrame.style.display = "none";
    if (html5Video) html5Video.style.display = "none";
    if (html5Audio) html5Audio.style.display = "none";
    iframeFrame.style.display = "none";
    fallbackNode.style.display = "none";
    fallbackNode.removeAttribute("href");
    overlay.style.display = "none";
    document.documentElement.style.overflow = "";
  }

  function buildAjaxPayload(actionName, id) {
    var params = new URLSearchParams();
    params.append("action", actionName);
    params.append("nonce", nonce);
    params.append("postId", String(id));
    return params;
  }

  function buildBeaconPayload(actionName, id) {
    var fd = new FormData();
    fd.append("action", actionName);
    fd.append("nonce", nonce);
    fd.append("postId", String(id));
    return fd;
  }

  function postAjax(actionName, id, priority) {
    var payload = buildAjaxPayload(actionName, id);
    var payloadString = payload.toString();

    // Click requests can fire immediately before browser navigation. Prefer sendBeacon
    // with FormData so PHP reliably receives $_POST in admin-ajax.php.
    if (priority && navigator.sendBeacon) {
      try {
        if (navigator.sendBeacon(ajaxUrl, buildBeaconPayload(actionName, id))) return;
      } catch (e) {}
    }

    try {
      fetch(ajaxUrl, {
        method: "POST",
        body: payloadString,
        credentials: "same-origin",
        keepalive: !!priority,
        headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" }
      }).catch(function () {});
    } catch (e) {}
  }

  function sendMetric(actionName, targetPostId, priority) {
    var id = targetPostId || postId || 0;
    if (!ajaxUrl || !nonce || !id) return;
    if (actionName === "source_dp_track_impression" && !enableImpressions) return;
    if (actionName === "source_dp_track_click" && !enableClicks) return;

    // Do not send repeated impressions for the same imported post in one browser session.
    // This prevents refresh/back-forward navigation from inflating Source impressions.
    if (actionName === "source_dp_track_impression") {
      if (wasTrackedThisSession("impression", id)) return;
      markTrackedThisSession("impression", id);
    }

    postAjax(actionName, id, !!priority);
  }

  function markTracked() {
    tracked = true;
    try { window.sessionStorage.setItem("source_dp_media_played_" + postId, "1"); } catch (e) {}
  }

  function alreadyTracked() {
    if (tracked) return true;
    try { return window.sessionStorage.getItem("source_dp_media_played_" + postId) === "1"; } catch (e) { return false; }
  }

  function sendView() {

  if (!enableTrack || !ajaxUrl || !nonce || !postId) {
    return;
  }

  if (alreadyTracked()) {
    return;
  }

  var fd = new FormData();
  fd.append("action", "source_dp_video_view");
  fd.append("nonce", nonce);
  fd.append("postId", postId);

  fetch(ajaxUrl, { method: "POST", body: fd, credentials: "same-origin" })
    .then(function (response) {
      return response.text().then(function (text) {
        var data = null;
        try { data = JSON.parse(text); } catch (e) {}
        if (!response.ok || !data || data.success !== true) {
          var msg = (data && data.data && data.data.message) ? data.data.message : ("HTTP " + response.status);
          throw new Error(msg);
        }
        markTracked();
        return data;
      });
    })
    .catch(function (err) {
    });
}

  function sendPageview() {
    if (pageviewTracked || !ajaxUrl || !nonce || !postId) return;
    if (wasTrackedThisSession("pageview", postId)) return;
    pageviewTracked = true;
    markTrackedThisSession("pageview", postId);
    var fd = new FormData();
    fd.append("action", "source_dp_pageview");
    fd.append("nonce", nonce);
    fd.append("postId", postId);
    fetch(ajaxUrl, { method: "POST", body: fd, credentials: "same-origin" }).catch(function () {});
  }

  function startMediaPlayTimer() {
    if (mediaPlayTracked || !enableTrack) return;
    if (mediaTimer) clearTimeout(mediaTimer);
    mediaTimer = setTimeout(function(){
      mediaTimer = null;
      mediaPlayTracked = true;
      sendView();
    }, 30000);
  }

  function openYouTube(id, title) {
    if (!id) return;
    sendMetric("source_dp_track_click", null, true);
    ensureModal();
    titleNode.textContent = title || "Video";
    fallbackNode.style.display = "none";
    fallbackNode.removeAttribute("href");
    iframeFrame.style.display = "none";
    if (html5Audio) html5Audio.style.display = "none";
    if (html5Video) html5Video.style.display = "none";
    youtubeFrame.style.display = "block";
    youtubeFrame.src = "https://www.youtube.com/embed/" + encodeURIComponent(id) + "?autoplay=1&rel=0";
    overlay.style.display = "flex";
    document.documentElement.style.overflow = "hidden";
    startMediaPlayTimer();
  }

  function openDirectVideo(url, title) {
    if (!url) return;
    sendMetric("source_dp_track_click", null, true);

    var ytId = extractYouTubeId(url);
    if (ytId) {
      openYouTube(ytId, title || "Video");
      return;
    }

    ensureModal();
    titleNode.textContent = title || "Video";
    fallbackNode.href = url;
    fallbackNode.style.display = "inline-block";
    youtubeFrame.style.display = "none";
    youtubeFrame.src = "";
    iframeFrame.style.display = "none";
    iframeFrame.src = "";
    if (html5Video) html5Video.style.display = "none";
    if (html5Audio) html5Audio.style.display = "none";

    if (isAudioUrl(url) && html5Audio) {
      html5Audio.style.display = "block";
      html5Audio.src = url;
      try { html5Audio.play(); } catch (e) {}
    } else if (isDirectMediaUrl(url) && html5Video) {
      html5Video.style.display = "block";
      html5Video.src = url;
      try { html5Video.play(); } catch (e) {}
    } else {
      iframeFrame.style.display = "block";
      iframeFrame.src = url;
    }

    overlay.style.display = "flex";
    document.documentElement.style.overflow = "hidden";

    tracked = false;
    startMediaPlayTimer();
  }

  function openExternal(url, title) {
    if (!url) return;
    sendMetric("source_dp_track_click", null, true);

    var ytId = extractYouTubeId(url);
    if (ytId) {
      openYouTube(ytId, title || "Video");
      return;
    }

    ensureModal();
    titleNode.textContent = title || url;
    youtubeFrame.style.display = "none";
    youtubeFrame.src = "";
    if (html5Video) html5Video.style.display = "none";
    if (html5Audio) html5Audio.style.display = "none";
    if (html5Video) html5Video.pause();
    if (html5Video) html5Video.removeAttribute("src");
    if (html5Video) html5Video.load();
    if (html5Audio) {
      html5Audio.pause();
      html5Audio.removeAttribute("src");
      html5Audio.load();
    }
    iframeFrame.style.display = "block";
    iframeFrame.src = url;
    fallbackNode.href = url;
    fallbackNode.style.display = "inline-block";
    overlay.style.display = "flex";
    document.documentElement.style.overflow = "hidden";
  }

  function isExternalLink(url) {
    try {
      var u = new URL(url, window.location.href);
      if (!/^https?:$/i.test(u.protocol)) return false;
      return u.origin !== window.location.origin;
    } catch (e) {
      return false;
    }
  }

  function bindLinks() {
    var scope = document.querySelector(".entry-content, .wp-block-post-content, article, main, .site-main") || document;
    if (!document.getElementById('source-dp-front-style')) {
      var st = document.createElement('style');
      st.id = 'source-dp-front-style';
      st.textContent = '.source-dp-video{display:flex;align-items:center;margin:0 0 18px 0}.source-dp-podcast{margin:0 0 18px 0}.source-dp-play-btn{display:inline-flex;align-items:center;gap:10px;border:0;border-radius:999px;padding:12px 18px;background:#111;color:#fff;cursor:pointer;font-weight:600;line-height:1}.source-dp-play-icon{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:999px;background:#fff;color:#111;font-size:18px}.source-dp-play-text{font-size:15px}';
      document.head.appendChild(st);
    }

    var videoButtons = scope.querySelectorAll(".source-dp-video[data-youtube-id], .source-dp-video[data-video-url], .source-dp-video[data-external-url]");
    Array.prototype.forEach.call(videoButtons, function (wrap) {
      if (wrap.dataset.sourceDpBound === "1") return;
      wrap.dataset.sourceDpBound = "1";
      wrap.style.margin = "0 0 14px 0";
      var trigger = wrap.querySelector(".source-dp-play-btn") || wrap;
      trigger.addEventListener("click", function (e) {
        e.preventDefault();
        var title = document.title || "Media";
        var yt = wrap.getAttribute("data-youtube-id") || "";
        var videoUrl = wrap.getAttribute("data-video-url") || "";
        var externalUrl = wrap.getAttribute("data-external-url") || "";
        if (yt) openYouTube(yt, title);
        else if (videoUrl) openDirectVideo(videoUrl, title);
        else if (externalUrl) openExternal(externalUrl, title);
      });
    });

    var links = scope.querySelectorAll("a[href]");
    Array.prototype.forEach.call(links, function (link) {
      if (link.dataset.sourceDpBound === "1") return;
      var href = link.getAttribute("href") || "";
      if (!href || href.indexOf("#") === 0 || /^mailto:|^tel:/i.test(href)) return;

      var yt = extractYouTubeId(href);
      if (yt) {
        link.dataset.sourceDpBound = "1";
        link.addEventListener("click", function (e) {
          e.preventDefault();
          openYouTube(yt, link.textContent || document.title || "Video");
        });
        return;
      }

      if (!isExternalLink(href)) return;

      link.dataset.sourceDpBound = "1";
      var behavior = SourceDPFront.externalLinkBehavior || 'modal';

      if (behavior === 'disabled') {
        link.setAttribute('href', '#');
      } else if (behavior === 'new_tab') {
        link.setAttribute('target', '_blank');
        link.setAttribute('rel', 'noopener noreferrer');
        link.addEventListener("click", function () { sendMetric("source_dp_track_click", null, true); });
      } else {
        link.addEventListener("click", function (e) {
          e.preventDefault();
          openExternal(href, link.textContent || href);
        });
      }
    });
  }


  function normalizeUrl(url) {
    try {
      var u = new URL(url, window.location.href);
      u.hash = "";
      return u.href.replace(/\/$/, "");
    } catch (e) {
      return String(url || "").replace(/#.*$/, "").replace(/\/$/, "");
    }
  }

  function getPostIdFromClass(el) {
    if (!el || !el.className) return "";
    var classText = typeof el.className === "string" ? el.className : (el.className.baseVal || "");
    var m = classText.match(/(?:^|\s)source-dp-post-id-(\d+)(?:\s|$)/);
    return m && m[1] ? m[1] : "";
  }

  function bindNativePostClassAnalytics() {
    var nativeItems = document.querySelectorAll('[class*="source-dp-post-id-"]');
    Array.prototype.forEach.call(nativeItems, function (item) {
      var id = item.getAttribute('data-source-post-id') || getPostIdFromClass(item);
      if (!id) return;
      item.setAttribute('data-source-post-id', id);
      if (!item.classList.contains('source-dp-item')) item.classList.add('source-dp-item');
      var links = item.querySelectorAll('a[href]');
      Array.prototype.forEach.call(links, function (link) {
        var href = link.getAttribute('href') || '';
        if (!href || href.indexOf('#') === 0 || /^mailto:|^tel:/i.test(href)) return;
        if (!link.getAttribute('data-source-dp-click-post')) link.setAttribute('data-source-dp-click-post', id);
      });
    });
  }

  function bindArchiveAnalytics() {
    var posts = SourceDPFront.archivePosts || [];
    if (!posts || !posts.length) return;
    posts.forEach(function (p) {
      if (!p || !p.id || !p.url) return;
      var targetUrl = normalizeUrl(p.url);
      var anchors = document.querySelectorAll('a[href]');
      Array.prototype.forEach.call(anchors, function (link) {
        if (normalizeUrl(link.href) !== targetUrl) return;
        var card = link.closest('article, li, .post, .entry, .wp-block-post, .hentry') || link;
        if (!card.dataset.sourcePostId) card.dataset.sourcePostId = String(p.id);
        if (!card.classList.contains('source-dp-item')) card.classList.add('source-dp-item');
        if (!link.getAttribute('data-source-dp-click-post')) link.setAttribute('data-source-dp-click-post', String(p.id));
      });
    });
  }

  function shouldResolveHref(href) {
    if (!href || href.indexOf("#") === 0 || /^mailto:|^tel:|javascript:/i.test(href)) return false;
    try {
      var u = new URL(href, window.location.href);
      if (u.origin !== window.location.origin) return false;
      if (!/^https?:$/i.test(u.protocol)) return false;
      var path = u.pathname || "";
      if (/\.(jpg|jpeg|png|gif|webp|svg|pdf|zip|mp3|mp4|mov|webm)(?:$|[?#])/i.test(path)) return false;
      if (/\/wp-admin\//i.test(path) || /\/wp-json\//i.test(path) || /\/feed\/?$/i.test(path)) return false;
      return true;
    } catch (e) {
      return false;
    }
  }

  function markResolvedSourceLink(link, id) {
    if (!link || !id) return;
    var card = link.closest('article, li, .post, .entry, .wp-block-post, .wp-block-query .wp-block-post, .hentry, .card, .blog-post, .post-item') || link;
    if (!card.dataset.sourcePostId) card.dataset.sourcePostId = String(id);
    if (!card.classList.contains('source-dp-item')) card.classList.add('source-dp-item');
    if (!card.classList.contains('source-dp-native-resolved')) card.classList.add('source-dp-native-resolved');
    if (!link.getAttribute('data-source-dp-click-post')) link.setAttribute('data-source-dp-click-post', String(id));
  }

  function resolveNativeWordPressLinks() {
    if (!ajaxUrl || !nonce) return;

    var urlToLinks = {};
    var urls = [];
    var anchors = document.querySelectorAll('a[href]');
    Array.prototype.forEach.call(anchors, function (link) {
      var href = link.href || link.getAttribute('href') || '';
      if (!shouldResolveHref(href)) return;
      var key = normalizeUrl(href);
      if (!key) return;
      if (!urlToLinks[key]) {
        urlToLinks[key] = [];
        urls.push(href);
      }
      if (urlToLinks[key].length < 8) urlToLinks[key].push(link);
    });

    if (!urls.length) return;
    urls = urls.slice(0, 120);

    var params = new URLSearchParams();
    params.append('action', 'source_dp_resolve_links');
    params.append('nonce', nonce);
    params.append('urls', JSON.stringify(urls));

    fetch(ajaxUrl, {
      method: 'POST',
      body: params.toString(),
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var items = data && data.success && data.data && data.data.items ? data.data.items : [];
        items.forEach(function (item) {
          if (!item || !item.id) return;
          var keys = [];
          if (item.url) keys.push(normalizeUrl(item.url));
          if (item.requestedUrl) keys.push(normalizeUrl(item.requestedUrl));
          keys.forEach(function (key) {
            var links = urlToLinks[key] || [];
            links.forEach(function (link) { markResolvedSourceLink(link, item.id); });
          });
        });
        bindListAnalytics();
      })
      .catch(function () {});
  }

  function isElementInViewport(el) {
    if (!el || !el.getBoundingClientRect) return false;
    var rect = el.getBoundingClientRect();
    var vh = window.innerHeight || document.documentElement.clientHeight || 0;
    var vw = window.innerWidth || document.documentElement.clientWidth || 0;
    if (!vh || !vw) return false;
    return rect.bottom > 0 && rect.right > 0 && rect.top < vh && rect.left < vw;
  }

  function trackVisibleImpression(item) {
    var id = item && item.getAttribute ? item.getAttribute("data-source-post-id") : "";
    if (!id || observedImpressions[id]) return;
    observedImpressions[id] = true;
    sendMetric("source_dp_track_impression", id);
  }

  function bindListAnalytics() {
    var items = document.querySelectorAll(".source-dp-item[data-source-post-id]");
    if (items.length && "IntersectionObserver" in window) {
      var io = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
          if (!entry.isIntersecting || entry.intersectionRatio < 0.25) return;
          trackVisibleImpression(entry.target);
          io.unobserve(entry.target);
        });
      }, { threshold: [0.25, 0.5], rootMargin: "0px 0px -10% 0px" });
      Array.prototype.forEach.call(items, function(item){ io.observe(item); });
    } else {
      Array.prototype.forEach.call(items, function(item){
        if (isElementInViewport(item)) trackVisibleImpression(item);
      });
    }

    // Safety pass for themes/builders where IntersectionObserver does not fire on initial layout.
    window.setTimeout(function(){
      var currentItems = document.querySelectorAll(".source-dp-item[data-source-post-id]");
      Array.prototype.forEach.call(currentItems, function(item){
        if (isElementInViewport(item)) trackVisibleImpression(item);
      });
    }, 1200);

    var links = document.querySelectorAll(".source-dp-item a[data-source-dp-click-post]");
    Array.prototype.forEach.call(links, function(link){
      if (link.dataset.sourceDpListClickBound === "1") return;
      link.dataset.sourceDpListClickBound = "1";
      link.addEventListener("click", function(){
        sendMetric("source_dp_track_click", link.getAttribute("data-source-dp-click-post"), true);
      });
    });
  }

  function markSinglePostForTracking() {
    if (!postId) return;
    var card = document.querySelector('article, .single-post, .post, .entry, main, .site-main') || document.body;
    if (!card || !card.setAttribute) return;
    if (!card.getAttribute('data-source-post-id')) card.setAttribute('data-source-post-id', String(postId));
    if (card.classList && !card.classList.contains('source-dp-item')) card.classList.add('source-dp-item');
    var links = card.querySelectorAll ? card.querySelectorAll('a[href]') : [];
    Array.prototype.forEach.call(links, function(link){
      var href = link.getAttribute('href') || '';
      if (!href || href.indexOf('#') === 0 || /^mailto:|^tel:/i.test(href)) return;
      if (!link.getAttribute('data-source-dp-click-post')) link.setAttribute('data-source-dp-click-post', String(postId));
    });
  }

  function bindGlobalTrackedClicks() {
    if (document.documentElement.dataset.sourceDpGlobalClickBound === '1') return;
    document.documentElement.dataset.sourceDpGlobalClickBound = '1';
    document.addEventListener('click', function(e){
      if (!enableClicks) return;
      var target = e.target;
      if (!target || !target.closest) return;
      var link = target.closest('a[data-source-dp-click-post]');
      if (!link) return;
      var id = link.getAttribute('data-source-dp-click-post') || '';
      if (!id) return;
      sendMetric('source_dp_track_click', id, true);
    }, true);
  }

  function initSourceDPFront() {
    bindGlobalTrackedClicks();
    markSinglePostForTracking();
    bindNativePostClassAnalytics();
    bindArchiveAnalytics();
    resolveNativeWordPressLinks();
    bindLinks();
    bindListAnalytics();
    window.setTimeout(function(){
      markSinglePostForTracking();
      bindNativePostClassAnalytics();
      bindArchiveAnalytics();
      resolveNativeWordPressLinks();
      bindListAnalytics();
    }, 1800);
    if (postId) {
      window.setTimeout(function(){ sendMetric('source_dp_track_impression', postId); }, 600);
      setTimeout(sendPageview, 15000);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initSourceDPFront);
  } else {
    initSourceDPFront();
  }
})();