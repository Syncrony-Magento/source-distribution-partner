/**
 * Plugin Name: Source Content Distribution 
 * Description: Pull approved content from Source bundles and publish to WordPress with taxonomy mapping.
 * Version: 1.4.74
 * Author: Syncrony Digital
 * License: GPLv2 or later
 * Text Domain: source-distribution-partner
 */

/* global jQuery, SourceDP */
(function ($) {
  function setStatus($el, text, ok) {
    $el.text(text);
    $el.css("color", ok ? "#1d2327" : "#b32d2e");
  }


  function applyTheme(theme) {
    var isDark = theme === "dark";
    $(".source-dp-admin").toggleClass("is-dark", isDark);
    $("#source-dp-theme-toggle").text(isDark ? "Light mode" : "Dark mode");
  }


  function getAnalyticsSeries() {
    var $chart = $("#source-dp-line-chart");
    var raw = $chart.attr("data-series") || "[]";
    try { return JSON.parse(raw) || []; } catch (e) { return []; }
  }

  function renderSparkline($el, values) {
    values = (values || []).map(function(v){ return Number(v || 0); });
    if (!values.length) values = [0];
    if (values.length === 1) values = [0, values[0]];
    var max = Math.max.apply(null, values.concat([1]));
    var w = 180, h = 42, pad = 3;
    var x = function(i){ return pad + (values.length === 1 ? 0 : (i / (values.length - 1)) * (w - pad * 2)); };
    var y = function(v){ return h - pad - (v / max) * (h - pad * 2); };
    var pts = values.map(function(v, i){ return x(i).toFixed(1) + ',' + y(v).toFixed(1); }).join(' ');
    var area = 'M' + pts.replace(/ /g, ' L') + ' L' + (w-pad) + ',' + (h-pad) + ' L' + pad + ',' + (h-pad) + ' Z';
    var svg = '<svg viewBox="0 0 '+w+' '+h+'" aria-hidden="true">'
      + '<path d="'+area+'" fill="rgba(113,227,186,.18)"></path>'
      + '<polyline fill="none" stroke="#2eaee8" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="'+pts+'"></polyline>'
      + '</svg>';
    $el.html(svg);
  }

  function renderKpiSparklines() {
    var series = getAnalyticsSeries();
    $("[data-source-dp-spark]").each(function(){
      var $el = $(this);
      var key = $el.data("source-dp-spark");
      if (key === "imported") {
        var v = Number($el.attr("data-value") || 0);
        renderSparkline($el, [Math.max(0, v - 4), Math.max(0, v - 2), v]);
        return;
      }
      renderSparkline($el, series.map(function(row){ return Number(row[key] || 0); }));
    });
  }

  function renderAnalyticsChart() {
    var $chart = $("#source-dp-line-chart");
    if (!$chart.length) return;
    var raw = $chart.attr("data-series") || "[]";
    var series = [];
    try { series = JSON.parse(raw); } catch (e) { series = []; }
    if (!series.length) {
      $chart.html('<div class="source-dp-chart-empty">No analytics data yet.</div>');
      return;
    }

    var minDate = series[0].date;
    var maxDate = series[series.length - 1].date;
    var $from = $("#source-dp-analytics-from");
    var $to = $("#source-dp-analytics-to");
    if (!$from.val()) $from.val(minDate);
    if (!$to.val()) $to.val(maxDate);

    var from = $from.val() || minDate;
    var to = $to.val() || maxDate;
    var filtered = series.filter(function (row) { return row.date >= from && row.date <= to; });
    if (!filtered.length) {
      var $emptyTotals = $("#source-dp-analytics-filtered");
      if ($emptyTotals.length) {
        $emptyTotals.html('<span>Filtered totals</span><div>Impressions: <strong>0</strong></div><div>Clicks: <strong>0</strong></div><div>Pageviews: <strong>0</strong></div><div>Media views: <strong>0</strong></div>');
      }
      $chart.html('<div class="source-dp-chart-empty">No analytics data for this date range.</div>');
      return;
    }

    var totals = { impressions: 0, clicks: 0, views: 0, video_views: 0 };
    var max = 1;
    filtered.forEach(function (row) {
      totals.impressions += Number(row.impressions || 0);
      totals.clicks += Number(row.clicks || 0);
      totals.views += Number(row.views || 0);
      totals.video_views += Number(row.video_views || 0);
      max = Math.max(max, Number(row.impressions || 0), Number(row.clicks || 0), Number(row.views || 0), Number(row.video_views || 0));
    });

    var $filteredTotals = $("#source-dp-analytics-filtered");
    if ($filteredTotals.length) {
      $filteredTotals.html(
        '<span>Filtered totals</span>' +
        '<div>Impressions: <strong>' + totals.impressions + '</strong></div>' +
        '<div>Clicks: <strong>' + totals.clicks + '</strong></div>' +
        '<div>Pageviews: <strong>' + totals.views + '</strong></div>' +
        '<div>Media views: <strong>' + totals.video_views + '</strong></div>'
      );
    }

    var w = 900, h = 240, padL = 48, padR = 18, padT = 18, padB = 34;
    var plotW = w - padL - padR;
    var plotH = h - padT - padB;
    var x = function (i) { return padL + (filtered.length === 1 ? plotW : (i / (filtered.length - 1)) * plotW); };
    var y = function (v) { return padT + plotH - (Number(v || 0) / max) * plotH; };
    var points = function (key) {
      return filtered.map(function (row, i) { return x(i).toFixed(1) + "," + y(row[key]).toFixed(1); }).join(" ");
    };
    var grid = "";
    for (var g = 0; g <= 4; g++) {
      var gy = padT + (plotH / 4) * g;
      var val = Math.round(max - (max / 4) * g);
      grid += '<line x1="'+padL+'" y1="'+gy+'" x2="'+(w-padR)+'" y2="'+gy+'" stroke="#dbe4f0" stroke-dasharray="4 4" />';
      grid += '<text x="8" y="'+(gy+4)+'" font-size="12" fill="currentColor">'+val+'</text>';
    }
    var labels = "";
    filtered.forEach(function (row, i) {
      labels += '<text x="'+x(i)+'" y="'+(h-8)+'" text-anchor="middle" font-size="12" fill="currentColor">'+row.date.slice(5).replace('-', '/')+'</text>';
    });
    var dots = function (key, color) {
      return filtered.map(function (row, i) {
        return '<circle cx="'+x(i).toFixed(1)+'" cy="'+y(row[key]).toFixed(1)+'" r="4" fill="'+color+'"><title>'+row.date+' '+key+': '+(row[key]||0)+'</title></circle>';
      }).join("");
    };
    var svg = '<svg viewBox="0 0 '+w+' '+h+'" role="img" aria-label="Analytics chart">'
      + grid + labels
      + '<polyline fill="none" stroke="#4f6df5" stroke-width="3" points="'+points('clicks')+'" />'
      + '<polyline fill="none" stroke="#71e3ba" stroke-width="3" points="'+points('impressions')+'" />'
      + '<polyline fill="none" stroke="#ff8585" stroke-width="3" points="'+points('views')+'" />'
      + '<polyline fill="none" stroke="#9b5de5" stroke-width="3" points="'+points('video_views')+'" />'
      + dots('clicks', '#4f6df5') + dots('impressions', '#71e3ba') + dots('views', '#ff8585') + dots('video_views', '#9b5de5')
      + '<text x="'+(w/2)+'" y="'+(h+20)+'"></text></svg>';
    $chart.html(svg + '<div class="source-dp-chart-legend"><span class="source-dp-legend-clicks">clicks</span><span class="source-dp-legend-impressions">impressions</span><span class="source-dp-legend-views">pageviews</span><span class="source-dp-legend-media">media views</span></div>');
  }

  function collectCurrentMappings() {
    var mappings = {};
    var prefix = "source_dp_mappings";
    $("[name^='" + prefix + "']").each(function () {
      var $field = $(this);
      var name = $field.attr("name") || "";
      var match = name.match(/^source_dp_mappings\[([^\]]+)\]\[([^\]]+)\]$/);
      if (!match) return;
      var catId = match[1];
      var key = match[2];
      if (!mappings[catId]) mappings[catId] = {};
      if ($field.attr("type") === "checkbox") {
        if ($field.is(":checked")) mappings[catId][key] = "1";
        return;
      }
      mappings[catId][key] = $field.val() || "";
    });
    return mappings;
  }

  function collectCurrentImportConfig() {
    return {
      current_mappings: collectCurrentMappings(),
      current_post_type: $("#source_dp_post_type").val() || "post",
      current_global_tag: $("#source_dp_global_tag").val() || "",
      current_overwrite: $("input[name='source_dp_overwrite']").is(":checked") ? 1 : 0
    };
  }

  function postAjax(action, extraData) {
    return $.post(
      SourceDP.ajaxUrl,
      $.extend(
        {
          action: action,
          nonce: SourceDP.nonce,
          uuid: $("#source_dp_uuid").val() || "",
        },
        extraData || {}
      )
    );
  }

  function renderSyncResult(r) {
    var html = "";
    html += "<p><strong>Created:</strong> " + (r.created || 0) + "</p>";
    html += "<p><strong>Updated:</strong> " + (r.updated || 0) + "</p>";
    html += "<p><strong>Skipped posts:</strong> " + (r.skipped || 0) + "</p>";
    html += "<p><strong>Errors:</strong> " + (r.errors || 0) + "</p>";

    if (r.messages && r.messages.length) {
      html += "<hr /><p><strong>Messages</strong></p><ul>";
      for (var i = 0; i < r.messages.length; i++) {
        html += "<li>" + $("<div/>").text(r.messages[i]).html() + "</li>";
      }
      html += "</ul>";
    }
    return html;
  }

  function renderBackgroundImportBrief(job) {
    if (!job) return "";
    var status = $("<div/>").text(job.status || "").html();
    var label = job.progress && job.progress.label ? job.progress.label : "";
    return '<div><strong>Status:</strong> ' + status + '</div>' +
      '<div><strong>Topic rows checked:</strong> ' + (job.processed || 0) + ' / ' + (job.total_expected || 0) + '</div>' +
            '<div><strong>Created:</strong> ' + (job.created || 0) + '</div>' +
      '<div><strong>Updated existing:</strong> ' + (job.updated || 0) + '</div>' +
      '<div><strong>Category links added:</strong> ' + (job.category_linked || 0) + '</div>' +
      '<div><strong>Category overlap rows:</strong> ' + (job.already_seen || 0) + '</div>' +
      '<div><strong>Content already handled:</strong> ' + (job.content_reused || 0) + '</div>' +
      '<div><strong>Skipped posts:</strong> ' + (job.skipped || 0) + '</div>' +
            '<div><strong>Media skipped:</strong> ' + (job.media_skipped || 0) + '</div>' +
      '<div><strong>Images deferred:</strong> ' + (job.media_deferred || 0) + '</div>' +
      '<div><strong>Errors:</strong> ' + (job.errors || 0) + '</div>' +
      (label ? '<div><strong>Now:</strong> ' + $("<div/>").text(label).html() + '</div>' : '');
  }

  function renderBackgroundImport(job) {
    if (!job) return "";
    var html = "";
    html += "<p><strong>Status:</strong> " + $("<div/>").text(job.status || "").html() + "</p>";
    html += "<p><strong>Current imported posts:</strong> " + (job.actual_imported_total || 0) + "</p>";
    if (job.actual_imported_types) {
      html += "<p><strong>Current imported types:</strong> Blog " + (job.actual_imported_types.blog || 0) + " / Podcast " + (job.actual_imported_types.podcast || 0) + " / Video " + (job.actual_imported_types.video || 0) + "</p>";
    }
    html += "<hr />";
    html += "<p><strong>Created this run:</strong> " + (job.created || 0) + "</p>";
    html += "<p><strong>Updated existing posts this run:</strong> " + (job.updated || 0) + "</p>";
    html += "<p><strong>Category links added this run:</strong> " + (job.category_linked || 0) + "</p>";
    html += "<p><strong>Category overlap rows this run:</strong> " + (job.already_seen || 0) + "</p>";
    html += "<p><strong>Content already handled this run:</strong> " + (job.content_reused || 0) + "</p>";
    html += "<p><strong>Skipped existing posts this run:</strong> " + (job.skipped || 0) + "</p>";
    html += "<p><strong>Featured/media images skipped this run:</strong> " + (job.media_skipped || 0) + "</p>";
    html += "<p><strong>Featured images deferred to media queue:</strong> " + (job.media_deferred || 0) + "</p>";
    if ((job.already_seen || 0) > 0) {
      html += "<p class=\"description\">These are Source posts that appeared again under another selected Source category. The plugin now links the category to the existing WordPress post instead of updating/duplicating the post.</p>";
    }
    html += "<p><strong>Errors:</strong> " + (job.errors || 0) + "</p>";
    html += "<p><strong>Source topic rows checked:</strong> " + (job.processed || 0) + " / " + (job.total_expected || 0) + "</p>";
    if (job.current_step) {
      html += "<p><strong>Current step:</strong> " + $("<div/>").text(job.current_step).html() + "</p>";
    }
    if (job.last_error) {
      html += "<p><strong>Last error:</strong> " + $("<div/>").text(job.last_error).html() + "</p>";
    }
    if (job.messages && job.messages.length) {
      html += "<hr /><p><strong>Messages</strong></p><ul>";
      for (var i = 0; i < job.messages.length; i++) {
        html += "<li>" + $("<div/>").text(job.messages[i]).html() + "</li>";
      }
      html += "</ul>";
    }
    if (job.failed_pages && job.failed_pages.length) {
      html += "<hr /><p><strong>Skipped Source API pages</strong></p><ul>";
      for (var j = 0; j < job.failed_pages.length; j++) {
        var fp = job.failed_pages[j];
        html += "<li>" + $("<div/>").text((fp.category_name || fp.category_id || "Category") + " start " + (fp.start || 0) + ": " + (fp.message || "")).html() + "</li>";
      }
      html += "</ul>";
    }
    return html;
  }

  function renderImportLog(log) {
    if (!log || !log.length) return "No import log yet.";
    var rows = log.slice(-160);
    var out = [];
    for (var i = 0; i < rows.length; i++) {
      var e = rows[i] || {};
      var ctx = e.context ? " " + JSON.stringify(e.context) : "";
      out.push("[" + (e.time_human || "") + "] " + String(e.level || "info").toUpperCase() + " - " + (e.message || "") + ctx);
    }
    return out.join("\n");
  }

  function updateImportLog(log) {
    var $log = $("#source-dp-import-log");
    if (!$log.length || !log) return;
    $log.text(renderImportLog(log));
    $log.scrollTop($log[0].scrollHeight);
  }

  function refreshImportLog() {
    var $status = $("#source-dp-log-status");
    setStatus($status, "Refreshing log…", true);
    postAjax("source_dp_get_import_log")
      .done(function (res) {
        if (res && res.success) {
          if (res.data && res.data.log) updateImportLog(res.data.log);
          setStatus($status, "Log refreshed.", true);
        } else {
          setStatus($status, (res && res.data && res.data.message) || "Could not refresh log.", false);
        }
      })
      .fail(function () {
        setStatus($status, "Could not refresh log (network).", false);
      });
  }

  $(function () {
    applyTheme(window.localStorage.getItem("source_dp_theme") || "light");
    renderAnalyticsChart();
    renderKpiSparklines();

    $(document).on("click", ".source-dp-tab", function () {
      var tab = $(this).data("source-dp-tab");
      if (!tab) return;
      $(".source-dp-tab").removeClass("active");
      $(this).addClass("active");
      $(".source-dp-panel").removeClass("active");
      $(".source-dp-panel").filter("[data-source-dp-panel='" + tab + "']").addClass("active");
    });

    $(document).on("click", "#source-dp-theme-toggle", function () {
      var next = $(".source-dp-admin").hasClass("is-dark") ? "light" : "dark";
      window.localStorage.setItem("source_dp_theme", next);
      applyTheme(next);
    });

    $(document).on("click", "#source-dp-uuid-edit-toggle, #source-dp-header-status", function () {
      $("#source-dp-uuid-edit").slideToggle(160);
      $("#source-dp-header-status").addClass("is-pending").text("Connect");
    });

    $(document).on("input", "#source-dp-header-uuid", function () {
      $("#source_dp_uuid").val($(this).val());
      $("#source-dp-header-status").addClass("is-pending").text("Connect");
    });

    $(document).on("click change input", "#source-dp-analytics-apply, #source-dp-analytics-from, #source-dp-analytics-to", function () {
      renderAnalyticsChart();
      renderKpiSparklines();
    });

    $(document).on("click", "#source-dp-import-toggle", function () {
      var $summary = $("#source-dp-import-summary");
      var visible = $summary.hasClass("is-visible");
      $summary.toggleClass("is-visible", !visible);
      $(this).attr("aria-expanded", visible ? "false" : "true").text(visible ? "View details" : "Hide details");
    });

    $(document).on("click", "#source-dp-import-close", function () {
      $("#source-dp-import-overlay").removeClass("is-active is-complete is-error");
    });

    $("#source-dp-test-connection").on("click", function () {
      if ($("#source-dp-header-uuid").length) { $("#source_dp_uuid").val($("#source-dp-header-uuid").val()); }
      var $status = $("#source-dp-connection-status");
      setStatus($status, "Connecting…", true);

      postAjax("source_dp_test_connection")
        .done(function (res) {
          if (res && res.success) {
            setStatus($status, (res.data && res.data.message) || "Connected.", true);
            $("#source-dp-header-status").removeClass("is-pending").text("Connected");
            var $landing = $("#source-dp-landing");
            if ($landing.length) {
              $landing.fadeOut(220, function () { window.location.reload(); });
            }
          } else {
            setStatus($status, (res && res.data && res.data.message) || "Connection failed.", false);
          }
        })
        .fail(function (xhr) {
          var msg = "Connection failed (network).";
          if (xhr && xhr.status) msg += " HTTP " + xhr.status + ".";
          setStatus($status, msg, false);
        });
    });

    $("#source-dp-fetch-approved").on("click", function () {
      var $status = $("#source-dp-fetch-status");
      setStatus($status, "Fetching approved config and post counts…", true);

      postAjax("source_dp_fetch_approved")
        .done(function (res) {
          if (res && res.success) {
            setStatus($status, (res.data && res.data.message) || "Fetched approved config and counts.", true);
            window.location.reload();
          } else {
            setStatus($status, (res && res.data && res.data.message) || "Fetch failed.", false);
          }
        })
        .fail(function () {
          setStatus($status, "Fetch failed (network).", false);
        });
    });

    $("#source-dp-fetch-counts").on("click", function () {
      var $status = $("#source-dp-fetch-status");
      setStatus($status, "Counting…", true);

      postAjax("source_dp_fetch_counts")
        .done(function (res) {
          if (res && res.success) {
            setStatus($status, "Counts updated.", true);
            var counts = (res.data && res.data.counts) || {};
            $(".source-dp-count").each(function () {
              var cid = $(this).data("source-cat-id");
              if (counts[cid] !== undefined) $(this).text(counts[cid]);
            });
          } else {
            setStatus($status, (res && res.data && res.data.message) || "Count failed.", false);
          }
        })
        .fail(function () {
          setStatus($status, "Count failed (network).", false);
        });
    });

    var sourceDpPollTimer = null;

    function pollBackgroundImport() {
      var $status = $("#source-dp-sync-status");
      var $box = $("#source-dp-sync-result");
      var $overlay = $("#source-dp-import-overlay");
      var $label = $("#source-dp-progress-label");
      var $bar = $("#source-dp-progress-bar");
      var $summary = $("#source-dp-import-summary");
      var $title = $("#source-dp-import-title");

      postAjax("source_dp_import_status")
        .done(function (res) {
          if (!res || !res.success) {
            setStatus($status, (res && res.data && res.data.message) || "Could not read import status.", false);
            sourceDpPollTimer = setTimeout(pollBackgroundImport, 8000);
            return;
          }

          var job = res.data && res.data.job;
          if (job && job.log) updateImportLog(job.log);
          if (!job) return;

          if (job.status === "running" || job.status === "queued") {
            $overlay.removeClass("is-complete is-error").addClass("is-active");
            $title.text("Background import running");
          }

          if (job.progress) {
            $label.text(job.progress.label || job.message || "Import running…");
            $bar.css("width", (job.progress.pct || 0) + "%");
          }

          $box.html(renderBackgroundImport(job)).show();
          $("#source-dp-import-brief").html(renderBackgroundImportBrief(job));
          $summary.html(renderBackgroundImport(job));

          if (job.status === "complete") {
            setStatus($status, "Background import complete.", true);
            $title.text("Import complete");
            $bar.css("width", "100%");
            $overlay.removeClass("is-error").addClass("is-active is-complete");
            if (sourceDpPollTimer) clearTimeout(sourceDpPollTimer);
            return;
          }

          if (job.status === "cancelled") {
            setStatus($status, "Import cancelled.", false);
            $title.text("Import cancelled");
            $overlay.removeClass("is-complete").addClass("is-active is-error");
            if (sourceDpPollTimer) clearTimeout(sourceDpPollTimer);
            return;
          }

          setStatus($status, job.message || "Background import running…", true);
          sourceDpPollTimer = setTimeout(pollBackgroundImport, 1500);
        })
        .fail(function () {
          setStatus($status, "Could not read import status (network). The background job may still be running.", false);
          sourceDpPollTimer = setTimeout(pollBackgroundImport, 10000);
        });
    }

    function startBackgroundImport() {
      var $status = $("#source-dp-sync-status");
      var $box = $("#source-dp-sync-result");
      var $progress = $("#source-dp-progress");
      var $overlay = $("#source-dp-import-overlay");
      var $label = $("#source-dp-progress-label");
      var $bar = $("#source-dp-progress-bar");
      var $summary = $("#source-dp-import-summary");
      var $title = $("#source-dp-import-title");

      if (sourceDpPollTimer) clearTimeout(sourceDpPollTimer);

      $box.hide().empty();
      $progress.show();
      $overlay.removeClass("is-complete is-error").addClass("is-active");
      $summary.removeClass("is-visible").empty();
      $("#source-dp-import-brief").empty();
      $("#source-dp-import-toggle").attr("aria-expanded", "false").text("View details");
      $title.text("Background import queued");
      $label.text("Queueing background import…");
      $bar.css("width", "0%");
      setStatus($status, "Queueing…", true);

      var cfg = collectCurrentImportConfig();
      postAjax("source_dp_start_background_import", {
        limit: 8,
        mappings: cfg.current_mappings,
        current_post_type: cfg.current_post_type,
        current_global_tag: cfg.current_global_tag,
        current_overwrite: cfg.current_overwrite
      })
        .done(function (res) {
          if (!res || !res.success) {
            setStatus($status, (res && res.data && res.data.message) || "Could not start import.", false);
            $title.text("Import failed");
            $summary.html("<p>" + $("<div/>").text((res && res.data && res.data.message) || "Could not start import.").html() + "</p>").addClass("is-visible");
            $overlay.removeClass("is-complete").addClass("is-active is-error");
            return;
          }
          setStatus($status, "Background import started. You can leave this page open to monitor progress.", true);
          var job = res.data && res.data.job;
          if (job && job.log) updateImportLog(job.log);
          if (job && job.progress) {
            $label.text(job.progress.label || "Queued");
            $bar.css("width", (job.progress.pct || 0) + "%");
            $box.html(renderBackgroundImport(job)).show();
            $("#source-dp-import-brief").html(renderBackgroundImportBrief(job));
          $summary.html(renderBackgroundImport(job));
          }
          sourceDpPollTimer = setTimeout(pollBackgroundImport, 1500);
        })
        .fail(function (xhr) {
          var msg = "Could not start import (network).";
          if (xhr && xhr.status) msg += " HTTP " + xhr.status + ".";
          setStatus($status, msg, false);
        });
    }

    $("#source-dp-sync-now").on("click", function () {
      startBackgroundImport();
    });

    $("#source-dp-refresh-log").on("click", function () {
      refreshImportLog();
    });

    $("#source-dp-clear-log").on("click", function () {
      var $status = $("#source-dp-log-status");
      setStatus($status, "Clearing log…", true);
      postAjax("source_dp_clear_import_log")
        .done(function (res) {
          if (res && res.success) {
            updateImportLog([]);
            setStatus($status, "Log cleared.", true);
          } else {
            setStatus($status, (res && res.data && res.data.message) || "Could not clear log.", false);
          }
        })
        .fail(function () {
          setStatus($status, "Could not clear log (network).", false);
        });
    });

    $(document).on("click", "#source-dp-kick-import, #source-dp-kick-import-overlay", function () {
      var $status = $("#source-dp-log-status");
      setStatus($status, "Kicking import…", true);
      postAjax("source_dp_kick_import")
        .done(function (res) {
          if (res && res.success) {
            setStatus($status, (res.data && res.data.message) || "Import kicked.", true);
            if (res.data && res.data.job) {
              $("#source-dp-sync-result").html(renderBackgroundImport(res.data.job)).show();
            }
            if (sourceDpPollTimer) clearTimeout(sourceDpPollTimer);
            sourceDpPollTimer = setTimeout(pollBackgroundImport, 1500);
          } else {
            setStatus($status, (res && res.data && res.data.message) || "Could not kick import.", false);
          }
        })
        .fail(function () {
          setStatus($status, "Could not kick import (network).", false);
        });
    });

    $(document).on("click", ".source-dp-cancel-import", function () {
      if (!window.confirm("Stop the current Source import? Any post already being processed may finish, but no new background batches will be queued.")) return;

      var $status = $("#source-dp-sync-status");
      var $logStatus = $("#source-dp-log-status");
      var $overlay = $("#source-dp-import-overlay");
      var $title = $("#source-dp-import-title");
      var $summary = $("#source-dp-import-summary");

      setStatus($status, "Cancelling import…", false);
      setStatus($logStatus, "Cancelling import…", false);
      $(".source-dp-cancel-import").prop("disabled", true);

      postAjax("source_dp_cancel_import")
        .done(function (res) {
          $(".source-dp-cancel-import").prop("disabled", false);
          if (res && res.success) {
            var msg = (res.data && res.data.message) || "Import cancelled.";
            setStatus($status, msg, false);
            setStatus($logStatus, "Import cancelled.", false);
            $title.text("Import cancelled");
            $overlay.removeClass("is-complete").addClass("is-active is-error");
            if (res.data && res.data.job) {
              $("#source-dp-sync-result").html(renderBackgroundImport(res.data.job)).show();
              $("#source-dp-import-brief").html(renderBackgroundImportBrief(res.data.job));
              $summary.html(renderBackgroundImport(res.data.job));
            }
            if (sourceDpPollTimer) clearTimeout(sourceDpPollTimer);
            refreshImportLog();
          } else {
            setStatus($status, (res && res.data && res.data.message) || "Could not cancel import.", false);
          }
        })
        .fail(function () {
          $(".source-dp-cancel-import").prop("disabled", false);
          setStatus($status, "Could not cancel import (network).", false);
        });
    });

    // Pick up an already-running background import after refresh.
    postAjax("source_dp_import_status")
      .done(function (res) {
        var job = res && res.success && res.data ? res.data.job : null;
        if (job && (job.status === "running" || job.status === "queued")) {
          $("#source-dp-import-overlay").addClass("is-active");
          $("#source-dp-sync-result").html(renderBackgroundImport(job)).show();
          $("#source-dp-import-brief").html(renderBackgroundImportBrief(job));
          $("#source-dp-import-summary").html(renderBackgroundImport(job));
          if (job.log) updateImportLog(job.log);
          sourceDpPollTimer = setTimeout(pollBackgroundImport, 1500);
        }
      });

    $("#source-dp-run-cron-now").on("click", function () {
      var $status = $("#source-dp-cron-status");
      setStatus($status, "Checking Source…", true);
      var cronCategories = [];
      $("#source_dp_auto_import_categories option:selected").each(function () {
        cronCategories.push($(this).val());
      });
      postAjax("source_dp_run_auto_import_now", {
        cron_interval: $("#source_dp_auto_import_interval").val() || "daily",
        cron_categories: cronCategories
      })
        .done(function (res) {
          if (res && res.success) {
            setStatus($status, (res.data && res.data.message) || "Cron check complete.", true);
            if (res.data && res.data.job) {
              $("#source-dp-import-overlay").addClass("is-active");
              $("#source-dp-import-brief").html(renderBackgroundImportBrief(res.data.job));
              $("#source-dp-import-summary").html(renderBackgroundImport(res.data.job));
              pollBackgroundImport();
            }
          } else {
            setStatus($status, (res && res.data && res.data.message) || "Cron check failed.", false);
          }
        })
        .fail(function (xhr) {
          var msg = "Cron check failed (network).";
          if (xhr && xhr.status) msg += " HTTP " + xhr.status + ".";
          setStatus($status, msg, false);
        });
    });


    $("#source-dp-delete-imported").on("click", function () {
      if (!window.confirm("Delete imported Source posts from this WordPress site? This cannot be undone.")) return;
      var $status = $("#source-dp-delete-status");
      setStatus($status, "Deleting…", true);

      var categoryId = $("#source-dp-delete-category-id").val() || "";
      var deleteTerms = $("#source-dp-delete-created-terms").is(":checked") ? 1 : 0;

      postAjax("source_dp_delete_imported", { category_id: categoryId, delete_terms: deleteTerms })
        .done(function (res) {
          if (res && res.success) {
            var msg = "Deleted " + ((res.data && res.data.deleted) || 0) + " posts.";
            if (res.data && res.data.deleted_terms) {
              msg += " Terms deleted: category=" + (res.data.deleted_terms.category || 0) + ", tag=" + (res.data.deleted_terms.post_tag || 0) + ".";
            }
            setStatus($status, msg, true);
          } else {
            setStatus($status, (res && res.data && res.data.message) || "Delete failed.", false);
          }
        })
        .fail(function () {
          setStatus($status, "Delete failed (network).", false);
        });
    });


    $("#source-dp-contact-send").on("click", function () {
      var $status = $("#source-dp-contact-status");
      setStatus($status, "Sending…", true);
      postAjax("source_dp_contact_source", {
        to: $("#source-dp-contact-to").val() || "",
        from: $("#source-dp-contact-from").val() || "",
        subject: $("#source-dp-contact-subject").val() || "",
        message: $("#source-dp-contact-message").val() || ""
      })
        .done(function (res) {
          if (res && res.success) {
            setStatus($status, (res.data && res.data.message) || "Sent.", true);
            window.location.reload();
          } else {
            setStatus($status, (res && res.data && res.data.message) || "Send failed.", false);
          }
        })
        .fail(function (xhr) {
          var msg = "Send failed (network).";
          if (xhr && xhr.status) msg += " HTTP " + xhr.status + ".";
          setStatus($status, msg, false);
        });
    });
  });
})(jQuery);
