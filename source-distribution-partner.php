<?php
/**
 * Plugin Name: Source Content Distribution 
 * Description: Pull approved content from Source bundles and publish to WordPress with taxonomy mapping. See Settings - Source
 * Version: 1.4.72
 * Author: Syncrony Digital
 * Update URI: https://github.com/Syncrony-Magento/source-distribution-partner
 * License: GPLv2 or later
 * Text Domain: source-distribution-partner
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Source_Distribution_Partner_Plugin
{
    private const VERSION = '1.4.72';
    private const API_BASE = 'https://api.wearesource.earth';

    private const PAGE_SLUG = 'source-dp';
    private const NONCE_ACTION = 'source_dp_admin_nonce';

    private const OPT_UUID = 'source_dp_uuid';
    private const OPT_POST_TYPE = 'source_dp_post_type';
    private const OPT_GLOBAL_TAG = 'source_dp_global_tag';
    private const OPT_MAPPINGS = 'source_dp_mappings';
    private const OPT_BUNDLE_CACHE = 'source_dp_bundle_cache';

    private const OPT_OVERWRITE = 'source_dp_overwrite';
    private const OPT_SEO_MODE = 'source_dp_seo_mode'; 
    private const OPT_CREATED_TERMS = 'source_dp_created_terms';
    private const OPT_ENABLE_VIDEO_TRACK = 'source_dp_enable_video_track';
    private const OPT_EXTERNAL_LINK_BEHAVIOR = 'source_dp_external_link_behavior';
    private const OPT_SHOW_SOURCE_META = 'source_dp_show_source_meta';
    private const OPT_ENABLE_IMPRESSION_TRACK = 'source_dp_enable_impression_track';
    private const OPT_ENABLE_CLICK_TRACK = 'source_dp_enable_click_track';
    private const OPT_CUSTOM_CSS = 'source_dp_custom_css';
    private const OPT_AUTO_IMPORT_INTERVAL = 'source_dp_auto_import_interval';
    private const OPT_AUTO_IMPORT_CATEGORIES = 'source_dp_auto_import_categories';
    private const OPT_AUTO_IMPORT_SCHEDULED_INTERVAL = 'source_dp_auto_import_scheduled_interval';
    private const OPT_CONTACT_LOG = 'source_dp_contact_log';
    private const OPT_ANALYTICS_DAILY = 'source_dp_analytics_daily';
    private const OPT_IMPORT_RUNS = 'source_dp_import_runs';
    private const OPT_IMPORT_JOB = 'source_dp_import_job';
    private const OPT_IMPORT_LOG = 'source_dp_import_log';
    private const OPT_MEDIA_QUEUE = 'source_dp_media_queue';
    private const AS_HOOK_IMPORT_BATCH = 'source_dp_action_scheduler_import_batch';
    private const CRON_HOOK_IMPORT_BATCH = 'source_dp_cron_import_batch';
    private const CRON_HOOK_AUTO_IMPORT = 'source_dp_cron_fetch_new_posts';
    private const CRON_HOOK_MEDIA_BATCH = 'source_dp_cron_process_media_batch';
    private const IMPORT_GROUP = 'source-distribution-partner';
    private const IMPORT_LOCK_TTL = 180;
    private const IMPORT_FALLBACK_IDLE_SECONDS = 30;

    public static function init(): void
    {
        $self = new self();

        add_action('admin_menu', [$self, 'register_menu']);
        add_action('admin_init', [$self, 'register_settings']);
        add_action('admin_enqueue_scripts', [$self, 'enqueue_admin_assets']);

        add_action('wp_ajax_source_dp_test_connection', [$self, 'ajax_test_connection']);
        add_action('wp_ajax_source_dp_fetch_approved', [$self, 'ajax_fetch_approved']);
        add_action('wp_ajax_source_dp_fetch_counts', [$self, 'ajax_fetch_counts']);
        add_action('wp_ajax_source_dp_sync_step', [$self, 'ajax_sync_step']);
        add_action('wp_ajax_source_dp_start_background_import', [$self, 'ajax_start_background_import']);
        add_action('wp_ajax_source_dp_import_status', [$self, 'ajax_import_status']);
        add_action('wp_ajax_source_dp_get_import_log', [$self, 'ajax_get_import_log']);
        add_action('wp_ajax_source_dp_clear_import_log', [$self, 'ajax_clear_import_log']);
        add_action('wp_ajax_source_dp_cancel_import', [$self, 'ajax_cancel_import']);
        add_action('wp_ajax_source_dp_kick_import', [$self, 'ajax_kick_import']);
        add_action('wp_ajax_source_dp_run_auto_import_now', [$self, 'ajax_run_auto_import_now']);

        add_filter('cron_schedules', [$self, 'add_cron_schedules']);
        add_action(self::AS_HOOK_IMPORT_BATCH, [$self, 'run_background_import_batch'], 10, 1);
        add_action(self::CRON_HOOK_IMPORT_BATCH, [$self, 'run_background_import_batch'], 10, 1);
        add_action(self::CRON_HOOK_AUTO_IMPORT, [$self, 'cron_fetch_new_posts']);
        add_action(self::CRON_HOOK_MEDIA_BATCH, [$self, 'process_media_queue']);
        $self->ensure_auto_import_schedule();
        add_action('wp_ajax_source_dp_delete_imported', [$self, 'ajax_delete_imported']);
        add_action('wp_ajax_source_dp_contact_source', [$self, 'ajax_contact_source']);
        add_action('wp_ajax_nopriv_source_dp_track_impression', [$self, 'ajax_track_impression']);
        add_action('wp_ajax_source_dp_track_impression', [$self, 'ajax_track_impression']);
        add_action('wp_ajax_nopriv_source_dp_track_click', [$self, 'ajax_track_click']);
        add_action('wp_ajax_source_dp_track_click', [$self, 'ajax_track_click']);
        add_action('wp_ajax_nopriv_source_dp_resolve_links', [$self, 'ajax_resolve_links']);
        add_action('wp_ajax_source_dp_resolve_links', [$self, 'ajax_resolve_links']);

        add_action('wp_ajax_nopriv_source_dp_pageview', [$self, 'ajax_pageview']);
        add_action('wp_ajax_source_dp_pageview', [$self, 'ajax_pageview']);
        add_action('wp_ajax_nopriv_source_dp_video_view', [$self, 'ajax_video_view']);
        add_action('wp_ajax_source_dp_video_view', [$self, 'ajax_video_view']);

        add_action('wp_enqueue_scripts', [$self, 'enqueue_front_assets']);
        add_action('wp_head', [$self, 'output_seo_meta'], 1);
        add_action('wp_head', [$self, 'output_custom_css'], 99);
        add_filter('post_class', [$self, 'add_source_tracking_post_class'], 10, 3);

        add_shortcode('source_dp_feed', [$self, 'shortcode_feed']);
        add_shortcode('source_dp_tabs', [$self, 'shortcode_tabs']);

        add_shortcode('source_dp_all', fn($a = []) => $self->shortcode_feed(array_merge((array)$a, ['type' => 'all'])));
        add_shortcode('source_dp_videos', fn($a = []) => $self->shortcode_feed(array_merge((array)$a, ['type' => 'video'])));
        add_shortcode('source_dp_podcasts', fn($a = []) => $self->shortcode_feed(array_merge((array)$a, ['type' => 'podcast'])));
        add_shortcode('source_dp_articles', fn($a = []) => $self->shortcode_feed(array_merge((array)$a, ['type' => 'articles'])));
        add_shortcode('source_dp_latest', fn($a = []) => $self->shortcode_feed(array_merge((array)$a, ['type' => 'all', 'latest' => 1])));
        add_shortcode('source_dp_latest_videos', fn($a = []) => $self->shortcode_feed(array_merge((array)$a, ['type' => 'video', 'latest' => 1])));
        add_shortcode('source_dp_latest_podcasts', fn($a = []) => $self->shortcode_feed(array_merge((array)$a, ['type' => 'podcast', 'latest' => 1])));
        add_shortcode('source_dp_latest_articles', fn($a = []) => $self->shortcode_feed(array_merge((array)$a, ['type' => 'articles', 'latest' => 1])));
        add_shortcode('source_dp_category', function ($a = []) use ($self) {
            $a = is_array($a) ? $a : [];
            if (!isset($a['id'])) {
                return '<p>Missing category id.</p>';
            }
            return $self->shortcode_feed(array_merge($a, ['category_id' => (string)$a['id']]));
        });
    }

    public static function activate(): void
    {
        $self = new self();
        add_filter('cron_schedules', [$self, 'add_cron_schedules']);
        $self->ensure_auto_import_schedule(true);
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK_AUTO_IMPORT);
        wp_clear_scheduled_hook(self::CRON_HOOK_IMPORT_BATCH);
        wp_clear_scheduled_hook(self::CRON_HOOK_MEDIA_BATCH);
    }

    public function add_cron_schedules(array $schedules): array
    {
        if (!isset($schedules['source_dp_weekly'])) {
            $schedules['source_dp_weekly'] = [
                'interval' => WEEK_IN_SECONDS,
                'display' => __('Once weekly', 'source-distribution-partner'),
            ];
        }
        return $schedules;
    }

    public function ensure_auto_import_schedule(bool $force = false): void
    {
        $interval = $this->get_auto_import_interval();
        $stored = (string)get_option(self::OPT_AUTO_IMPORT_SCHEDULED_INTERVAL, '');
        $next = wp_next_scheduled(self::CRON_HOOK_AUTO_IMPORT);

        if ($interval === 'manual') {
            if ($next || $stored !== 'manual' || $force) {
                wp_clear_scheduled_hook(self::CRON_HOOK_AUTO_IMPORT);
                update_option(self::OPT_AUTO_IMPORT_SCHEDULED_INTERVAL, 'manual', false);
            }
            return;
        }

        if ($force || $stored !== $interval || !$next) {
            wp_clear_scheduled_hook(self::CRON_HOOK_AUTO_IMPORT);
            wp_schedule_event(time() + 300, $interval, self::CRON_HOOK_AUTO_IMPORT);
            update_option(self::OPT_AUTO_IMPORT_SCHEDULED_INTERVAL, $interval, false);
        }
    }

    private function get_auto_import_interval(): string
    {
        return $this->sanitize_auto_import_interval((string)get_option(self::OPT_AUTO_IMPORT_INTERVAL, 'daily'));
    }

    public function register_menu(): void
    {
        add_options_page(
            __('Source', 'source-distribution-partner'),
            __('Source', 'source-distribution-partner'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void
    {
        register_setting('source_dp_settings', self::OPT_UUID, [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_uuid'],
            'default' => '',
        ]);

        register_setting('source_dp_settings', self::OPT_POST_TYPE, [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_post_type'],
            'default' => 'post',
        ]);

        register_setting('source_dp_settings', self::OPT_GLOBAL_TAG, [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_text_field_relaxed'],
            'default' => '',
        ]);

        register_setting('source_dp_settings', self::OPT_MAPPINGS, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_mappings'],
            'default' => [],
        ]);

        //  Save Settings & Mappings clicked, sending the admin back to the landing/connect state.

        register_setting('source_dp_settings', self::OPT_OVERWRITE, [
            'type' => 'boolean',
            'sanitize_callback' => static fn($v) => (bool)$v,
            'default' => false,
        ]);

        register_setting('source_dp_settings', self::OPT_SEO_MODE, [
            'type' => 'string',
            'sanitize_callback' => static function ($v) {
                $v = is_string($v) ? sanitize_key($v) : 'canonical_source';
                $allowed = ['canonical_source', 'canonical_self', 'noindex', 'none'];
                return in_array($v, $allowed, true) ? $v : 'canonical_source';
            },
            'default' => 'canonical_source',
        ]);

        register_setting('source_dp_settings', self::OPT_ENABLE_VIDEO_TRACK, [
            'type' => 'boolean',
            'sanitize_callback' => static fn($v) => (bool)$v,
            'default' => true,
        ]);

        register_setting('source_dp_settings', self::OPT_EXTERNAL_LINK_BEHAVIOR, [
            'type' => 'string',
            'sanitize_callback' => static function ($v) {
                $v = is_string($v) ? sanitize_key($v) : 'modal';
                $allowed = ['modal', 'new_tab', 'disabled'];
                return in_array($v, $allowed, true) ? $v : 'modal';
            },
            'default' => 'modal',
        ]);

        register_setting('source_dp_settings', self::OPT_SHOW_SOURCE_META, [
            'type' => 'boolean',
            'sanitize_callback' => static fn($v) => (bool)$v,
            'default' => true,
        ]);

        register_setting('source_dp_settings', self::OPT_ENABLE_IMPRESSION_TRACK, [
            'type' => 'boolean',
            'sanitize_callback' => static fn($v) => (bool)$v,
            'default' => true,
        ]);

        register_setting('source_dp_settings', self::OPT_ENABLE_CLICK_TRACK, [
            'type' => 'boolean',
            'sanitize_callback' => static fn($v) => (bool)$v,
            'default' => true,
        ]);

        register_setting('source_dp_settings', self::OPT_CUSTOM_CSS, [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_custom_css'],
            'default' => '',
        ]);

        register_setting('source_dp_settings', self::OPT_AUTO_IMPORT_INTERVAL, [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_auto_import_interval'],
            'default' => 'daily',
        ]);

        register_setting('source_dp_settings', self::OPT_AUTO_IMPORT_CATEGORIES, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_auto_import_categories'],
            'default' => [],
        ]);

        register_setting('source_dp_settings', self::OPT_CREATED_TERMS, [
            'type' => 'array',
            'sanitize_callback' => static fn($v) => is_array($v) ? $v : [],
            'default' => ['category' => [], 'post_tag' => []],
        ]);
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if ($hook !== 'settings_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_script(
            'source-dp-admin',
            plugin_dir_url(__FILE__) . 'admin.js',
            ['jquery'],
            self::VERSION,
            true
        );

        wp_localize_script('source-dp-admin', 'SourceDP', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
        ]);
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $uuid = (string) get_option(self::OPT_UUID, '');
        $post_type = (string) get_option(self::OPT_POST_TYPE, 'post');
        $global_tag = (string) get_option(self::OPT_GLOBAL_TAG, '');
        $mappings = (array) get_option(self::OPT_MAPPINGS, []);
        $bundle_cache = (array) get_option(self::OPT_BUNDLE_CACHE, []);
        $overwrite = (bool) get_option(self::OPT_OVERWRITE, false);
        $seo_mode = (string) get_option(self::OPT_SEO_MODE, 'canonical_source');
        $enable_video = (bool) get_option(self::OPT_ENABLE_VIDEO_TRACK, true);
        $external_link_behavior = (string) get_option(self::OPT_EXTERNAL_LINK_BEHAVIOR, 'modal');
        $show_source_meta = (bool) get_option(self::OPT_SHOW_SOURCE_META, true);
        $enable_impressions = (bool) get_option(self::OPT_ENABLE_IMPRESSION_TRACK, true);
        $enable_clicks = (bool) get_option(self::OPT_ENABLE_CLICK_TRACK, true);
        $custom_css = (string) get_option(self::OPT_CUSTOM_CSS, '');
        $auto_import_interval = $this->sanitize_auto_import_interval((string) get_option(self::OPT_AUTO_IMPORT_INTERVAL, 'daily'));
        $auto_import_categories = $this->sanitize_auto_import_categories(get_option(self::OPT_AUTO_IMPORT_CATEGORIES, []));

        if ($uuid !== '') {
            $this->maybe_backfill_content_type_tags($uuid);
        }

        $public_post_types = get_post_types(['public' => true], 'objects');

        $bundle_name = (string) ($bundle_cache['bundle']['name'] ?? ($bundle_cache['name'] ?? ''));
        $bundle_uuid = (string) ($bundle_cache['bundle']['uuid'] ?? ($bundle_cache['uuid'] ?? ''));
        $bundle_flags = is_array($bundle_cache['bundle']['flags'] ?? null) ? $bundle_cache['bundle']['flags'] : ['blog' => 0, 'podcast' => 0, 'video' => 0];
        $content_type_counts = is_array($bundle_cache['content_type_counts'] ?? null) ? $bundle_cache['content_type_counts'] : ['blog' => 0, 'podcast' => 0, 'video' => 0];
        $content_type_counts = array_merge(['blog' => 0, 'podcast' => 0, 'video' => 0], array_intersect_key($content_type_counts, ['blog' => 1, 'podcast' => 1, 'video' => 1]));

        // Never display category/count cache that belongs to a different integration UUID.
        // This prevents a newly entered UUID from showing topic rows fetched for an older bundle.
        if ($uuid !== '' && $bundle_uuid !== '' && $bundle_uuid !== $uuid) {
            $bundle_cache = [];
            $bundle_name = '';
            $bundle_uuid = '';
            $bundle_flags = ['blog' => 0, 'podcast' => 0, 'video' => 0];
            $content_type_counts = ['blog' => 0, 'podcast' => 0, 'video' => 0];
            update_option(self::OPT_BUNDLE_CACHE, $bundle_cache, false);
        }

        if ($uuid !== '' && ($bundle_name === '' || array_sum(array_map('intval', $content_type_counts)) === 0)) {
            $fresh_bundle = $this->api_get_bundle($uuid);
            if (!is_wp_error($fresh_bundle) && is_array($fresh_bundle)) {
                $fresh_bundle_name = (string)($fresh_bundle['name'] ?? '');
                $fresh_content_type_counts = $this->get_content_type_counts_from_bundle($fresh_bundle);

                if (!isset($bundle_cache['bundle']) || !is_array($bundle_cache['bundle'])) {
                    $bundle_cache['bundle'] = [];
                }

                if ($fresh_bundle_name !== '') {
                    $bundle_name = $fresh_bundle_name;
                    $bundle_cache['bundle']['name'] = $fresh_bundle_name;
                }

                $bundle_cache['bundle']['uuid'] = $uuid;
                $bundle_cache['bundle']['flags'] = [
                    'blog' => !empty($fresh_bundle['blog']) ? 1 : 0,
                    'podcast' => !empty($fresh_bundle['podcast']) ? 1 : 0,
                    'video' => !empty($fresh_bundle['video']) ? 1 : 0,
                ];

                if (array_sum($fresh_content_type_counts) > 0) {
                    $content_type_counts = $fresh_content_type_counts;
                    $bundle_cache['content_type_counts'] = $fresh_content_type_counts;
                }

                update_option(self::OPT_BUNDLE_CACHE, $bundle_cache, false);
            }
        }
        if ($uuid !== '' && array_sum(array_map('intval', $content_type_counts)) === 0) {
            $local_content_type_counts = $this->get_local_content_type_counts($uuid);
            if (array_sum($local_content_type_counts) > 0) {
                $content_type_counts = $local_content_type_counts;
            }
        }
        $source_unique_total = (int) array_sum(array_map('intval', $content_type_counts));
        $categories = is_array($bundle_cache['categories'] ?? null) ? $bundle_cache['categories'] : [];
        $counts = is_array($bundle_cache['counts'] ?? null) ? $bundle_cache['counts'] : [];
        $connected = ($uuid !== '' && ($bundle_uuid !== '' || $bundle_name !== ''));

        $wp_categories = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
        $analytics_totals = $this->get_local_analytics_totals($uuid);
        $imported_total = $this->get_imported_post_count($uuid);
        $imported_content_type_counts = $this->get_local_content_type_counts($uuid);
        $contact_log = (array) get_option(self::OPT_CONTACT_LOG, []);
        $logo_url = plugin_dir_url(__FILE__) . 'source-logo-full.png';
        $approved_total = (int) array_sum(array_map('intval', $counts));
        $approved_category_breakdown = $this->get_approved_category_breakdown($categories, $counts, 6);
        $imported_category_breakdown = $this->get_imported_category_breakdown($uuid, $categories, 6);
        $import_reconciliation_rows = $this->get_import_reconciliation_rows($uuid, $categories, $counts);
        $max_metric = max(1, (int)$analytics_totals['impressions'], (int)$analytics_totals['clicks'], (int)$analytics_totals['pageviews'], (int)$analytics_totals['video_views']);
        $analytics_series = $this->get_local_analytics_series($uuid);
        $analytics_kpi_tooltips = $this->get_analytics_kpi_tooltips($analytics_series, $analytics_totals);
        $analytics_series_json = esc_attr(wp_json_encode($analytics_series));
        $import_job = (array) get_option(self::OPT_IMPORT_JOB, []);
        $import_log = $this->get_import_log();
        ?>
        <div class="wrap source-dp-admin">
            <style>
                .source-dp-admin{--sdp-bg:#f4f1fb;--sdp-card:#fff;--sdp-line:#e8e0f5;--sdp-ink:#101828;--sdp-muted:#667085;--sdp-dark:#07131d;--sdp-accent:#71e3ba;--sdp-pink:#ef5d8f;margin-right:18px}.source-dp-admin *{box-sizing:border-box}.source-dp-admin.is-dark{--sdp-bg:rgb(15 23 42);--sdp-card:#111c31;--sdp-line:#26354f;--sdp-ink:#f8fafc;--sdp-muted:#b9c5d7}.source-dp-admin.is-dark .source-dp-shell{background:rgb(15 23 42)}.source-dp-admin.is-dark .source-dp-header,.source-dp-admin.is-dark .source-dp-card,.source-dp-admin.is-dark .source-dp-kpi,.source-dp-admin.is-dark .source-dp-step,.source-dp-admin.is-dark .source-dp-tab:not(.active),.source-dp-admin.is-dark .source-dp-landing-card{background:var(--sdp-card);color:var(--sdp-ink)}.source-dp-admin.is-dark code{background:#17233a;border-color:#31415f;color:#e6edf7}.source-dp-logo{max-width:210px;height:auto;display:block}.source-dp-landing{min-height:70vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#f6f2ff 0%,#fff 45%,#fff3f7 100%);border-radius:24px;margin:22px 0;padding:34px}.source-dp-landing-card{width:min(560px,100%);background:#fff;border:1px solid var(--sdp-line);border-radius:26px;padding:38px;text-align:center;box-shadow:0 20px 60px rgba(16,24,40,.10)}.source-dp-landing-card .source-dp-logo{margin:0 auto 22px}.source-dp-landing-card h1{margin:0 0 10px;font-size:28px}.source-dp-landing-card p{color:var(--sdp-muted);margin:0 0 22px}.source-dp-connect-row{display:flex;gap:10px;justify-content:center}.source-dp-connect-row input{min-width:310px;min-height:42px;border-radius:12px;border-color:var(--sdp-line)}.source-dp-shell{background:var(--sdp-bg);border-radius:24px;padding:18px;margin:18px 0}.source-dp-header{display:flex;align-items:center;justify-content:space-between;gap:18px;background:#fff;border:1px solid var(--sdp-line);border-radius:22px;padding:18px 22px;margin:0 0 16px;box-shadow:0 12px 34px rgba(16,24,40,.06)}.source-dp-header-right{text-align:right;color:var(--sdp-muted);font-size:13px}.source-dp-header-tools{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:8px}.source-dp-pill{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:999px;padding:6px 10px;background:#ecfdf3;color:#027a48;font-weight:700;margin-top:6px;cursor:pointer}.source-dp-pill:before{content:"";width:8px;height:8px;border-radius:50%;background:#12b76a}.source-dp-pill.is-pending{background:#fff7e6;color:#9a5b00}.source-dp-pill.is-pending:before{background:#f59e0b}.source-dp-uuid-btn{border:0;background:transparent;padding:0;cursor:pointer;color:inherit}.source-dp-uuid-edit{display:none;margin-top:8px}.source-dp-uuid-edit input{min-height:34px;border-radius:10px;border:1px solid var(--sdp-line)}.source-dp-theme-toggle{border:1px solid var(--sdp-line);background:var(--sdp-card);color:var(--sdp-ink);border-radius:999px;padding:7px 12px;cursor:pointer;font-weight:700}.source-dp-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 16px}.source-dp-tab{border:1px solid var(--sdp-line);background:#fff;color:var(--sdp-ink);border-radius:999px;padding:10px 16px;cursor:pointer;font-weight:700}.source-dp-tab.active{background:#07131d;color:#fff;border-color:#07131d}.source-dp-admin.is-dark .source-dp-tab.active{background:var(--sdp-accent);border-color:var(--sdp-accent);color:#07131d}.source-dp-panel{display:none}.source-dp-panel.active{display:block;animation:sdpFade .2s ease-in}.source-dp-tab-stage{position:relative;max-height:none;overflow:visible}.source-dp-import-overlay{display:none;position:sticky;top:32px;z-index:20;background:rgba(255,255,255,.96);border:1px solid var(--sdp-line);border-radius:16px;padding:12px 14px;margin:0 0 14px;box-shadow:0 10px 26px rgba(16,24,40,.08)}.source-dp-import-overlay.is-active{display:block}.source-dp-import-overlay-head{display:flex;align-items:center;justify-content:space-between;gap:12px}.source-dp-import-controls{display:flex;align-items:center;gap:8px}.source-dp-import-close{display:none;border:0;background:transparent;color:var(--sdp-ink);font-size:18px;line-height:1;cursor:pointer;border-radius:999px;padding:4px 8px}.source-dp-import-overlay.is-active .source-dp-cancel-import{display:inline-flex}.source-dp-import-overlay.is-complete .source-dp-cancel-import,.source-dp-import-overlay.is-error .source-dp-cancel-import{display:none}.source-dp-import-overlay.is-complete .source-dp-import-close,.source-dp-import-overlay.is-error .source-dp-import-close{display:inline-flex}.source-dp-cancel-import{border-color:#d63638!important;color:#b32d2e!important}.source-dp-cancel-import:hover{border-color:#b32d2e!important;color:#8a2424!important}.source-dp-import-summary{display:none;margin-top:10px;padding-top:10px;border-top:1px solid var(--sdp-line)}.source-dp-import-summary.is-visible{display:block}.source-dp-admin.is-dark .source-dp-import-overlay{background:rgba(17,28,49,.97)}.source-dp-card{background:#fff;border:1px solid var(--sdp-line);border-radius:18px;padding:20px 24px;margin:0 0 18px;box-shadow:0 10px 26px rgba(16,24,40,.06)}.source-dp-card h2{margin:0 0 14px;font-size:20px;color:var(--sdp-ink)}.source-dp-card h3{margin:18px 0 10px;font-size:16px}.source-dp-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:0 0 18px}.source-dp-kpi{background:linear-gradient(180deg,#fff,#fbf9ff);border:1px solid var(--sdp-line);border-radius:18px;padding:18px;box-shadow:0 10px 24px rgba(16,24,40,.05);overflow:hidden}.source-dp-kpi-top{display:flex;align-items:center;justify-content:space-between;gap:10px}.source-dp-kpi-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:14px;background:rgba(113,227,186,.20);color:#07131d;font-size:20px}.source-dp-kpi strong{display:block;font-size:28px;color:var(--sdp-ink);line-height:1.1}.source-dp-kpi span{color:var(--sdp-muted);font-size:12px;text-transform:uppercase;letter-spacing:.06em}.source-dp-kpi-mini{height:7px;border-radius:999px;background:#ede8f6;margin-top:16px;overflow:hidden}.source-dp-kpi-mini i{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,var(--sdp-accent),#7b61ff)}.source-dp-kpi-spark{height:44px;margin-top:10px}.source-dp-kpi-spark svg{width:100%;height:44px;display:block}.source-dp-kpi-note{display:block;margin-top:8px;color:var(--sdp-muted);font-size:11px;line-height:1.4;text-transform:none;letter-spacing:0}.source-dp-type-bars{display:flex;gap:4px;height:28px;align-items:end;margin-top:12px}.source-dp-type-bars i{display:block;flex:1;min-height:4px;border-radius:8px 8px 2px 2px;background:linear-gradient(180deg,var(--sdp-accent),#7b61ff)}.source-dp-type-labels{display:grid;grid-template-columns:repeat(3,1fr);gap:4px;margin-top:8px;color:var(--sdp-muted);font-size:11px;line-height:1.25;text-align:center}.source-dp-type-labels b{display:block;color:var(--sdp-ink);font-size:12px;font-weight:700}.source-dp-category-grid{display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:14px;margin:0 0 18px}.source-dp-category-graph{border:1px solid var(--sdp-line);border-radius:16px;padding:16px;background:linear-gradient(180deg,#fff,#fbf9ff)}.source-dp-category-graph h3{margin:0 0 12px;font-size:16px;color:var(--sdp-ink)}.source-dp-category-row{display:grid;grid-template-columns:minmax(130px,1fr) 1.6fr 58px;gap:10px;align-items:center;margin:10px 0}.source-dp-category-name{color:var(--sdp-ink);font-weight:600;line-height:1.25;overflow:hidden;text-overflow:ellipsis}.source-dp-category-track{height:12px;border-radius:999px;background:#ede8f6;overflow:hidden}.source-dp-category-track i{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,var(--sdp-accent),#7b61ff)}.source-dp-category-count{text-align:right;color:var(--sdp-muted);font-weight:700}.source-dp-category-empty{color:var(--sdp-muted);margin:0}.source-dp-chart-controls{display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:12px}.source-dp-chart-controls label{font-size:12px;color:var(--sdp-muted);text-transform:uppercase;letter-spacing:.04em}.source-dp-chart-controls input{display:block;margin-top:4px;min-height:36px;border:1px solid var(--sdp-line);border-radius:10px}.source-dp-line-chart{width:100%;height:320px;border:1px solid var(--sdp-line);border-radius:16px;background:linear-gradient(180deg,rgba(255,255,255,.72),rgba(255,255,255,.35));padding:12px}.source-dp-line-chart svg{width:100%;height:260px;display:block;overflow:visible}.source-dp-chart-legend{display:flex;gap:16px;justify-content:center;margin-top:8px}.source-dp-chart-legend span:before{content:"";display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px}.source-dp-legend-clicks:before{background:#4f6df5}.source-dp-legend-impressions:before{background:var(--sdp-accent)}.source-dp-legend-views:before{background:#ff8585}.source-dp-legend-media:before{background:#9b5de5}.source-dp-chart-empty{color:var(--sdp-muted);padding:40px;text-align:center}.source-dp-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.source-dp-step{border:1px solid var(--sdp-line);border-radius:16px;padding:16px;background:#fff}.source-dp-step b{display:inline-grid;place-items:center;width:30px;height:30px;border-radius:50%;background:#07131d;color:#fff;margin-right:8px}.source-dp-shortcodes{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:10px}.source-dp-shortcodes code{display:block}.source-dp-admin .button-primary{background:#07131d;border-color:#07131d;border-radius:8px}.source-dp-admin .button-primary:hover{background:#122638;border-color:#122638}.source-dp-admin .button{border-radius:8px}.source-dp-admin .widefat{border-radius:12px;overflow:hidden;border-color:var(--sdp-line)}.source-dp-admin code{background:#f7f5ff;border:1px solid #ece6ff;border-radius:6px;padding:2px 6px}.source-dp-status{margin-left:10px;font-weight:600}.source-dp-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.source-dp-contact-log{max-height:260px;overflow:auto;border:1px solid var(--sdp-line);border-radius:12px;background:#fbfaff;padding:12px}.source-dp-contact-log-entry{padding:10px;border-bottom:1px solid var(--sdp-line)}.source-dp-contact-log-entry:last-child{border-bottom:0}.source-dp-stats-tools{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 12px}.source-dp-stats-tools input{min-height:36px;border:1px solid var(--sdp-line);border-radius:10px;min-width:260px}.source-dp-stats-table th,.source-dp-stats-table td{vertical-align:middle}.source-dp-stats-table small{display:block;color:var(--sdp-muted)}.source-dp-stats-pagination{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px}.source-dp-stats-pagination a,.source-dp-stats-pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;border-radius:8px;border:1px solid var(--sdp-line);text-decoration:none;background:var(--sdp-card)}.source-dp-stats-pagination .current{background:#07131d;color:#fff;border-color:#07131d}@keyframes sdpFade{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}@media(max-width:1300px){.source-dp-kpis{grid-template-columns:repeat(3,1fr)}}@media(max-width:1100px){.source-dp-kpis,.source-dp-steps{grid-template-columns:repeat(2,1fr)}.source-dp-shortcodes{grid-template-columns:1fr}}@media(max-width:720px){.source-dp-header{align-items:flex-start;flex-direction:column}.source-dp-header-right{text-align:left}.source-dp-kpis,.source-dp-steps{grid-template-columns:1fr}.source-dp-connect-row{flex-direction:column}.source-dp-connect-row input{min-width:0;width:100%}}

                .source-dp-admin{--source-bar-gradient:linear-gradient(90deg,#e8c777 0%,#d7a6bd 30%,#a261ff 62%,#6ea8ff 100%)}
                .source-dp-type-bars i,.source-dp-kpi-mini i,.source-dp-category-track i{background:var(--source-bar-gradient)!important}
                .source-dp-admin.is-dark .source-dp-category-graph,.source-dp-admin.is-dark .source-dp-imported-stats{background:var(--sdp-card)!important;color:var(--sdp-ink);border-color:var(--sdp-line)}
                .source-dp-admin.is-dark .source-dp-kpi{background:#111c31!important;color:var(--sdp-ink)}
                .source-dp-admin.is-dark .source-dp-line-chart{background:#111c31;border-color:var(--sdp-line);color:var(--sdp-ink)}
                .source-dp-admin.is-dark .source-dp-category-track,.source-dp-admin.is-dark .source-dp-kpi-mini{background:rgba(255,255,255,.09)!important}
                .source-dp-admin.is-dark .widefat,.source-dp-admin.is-dark .widefat thead tr,.source-dp-admin.is-dark .widefat tfoot tr{background:#111c31;color:var(--sdp-ink);border-color:var(--sdp-line)}
                .source-dp-admin.is-dark .widefat th,.source-dp-admin.is-dark .widefat td{color:var(--sdp-ink);border-color:var(--sdp-line)}
                .source-dp-admin.is-dark .widefat.striped>tbody>:nth-child(odd){background:#0f172a}
                .source-dp-admin.is-dark .widefat.striped>tbody>:nth-child(even){background:#111c31}
                .source-dp-admin.is-dark input,.source-dp-admin.is-dark select,.source-dp-admin.is-dark textarea{background:#0f172a;color:var(--sdp-ink);border-color:var(--sdp-line)}
                .source-dp-admin.is-dark .source-dp-contact-log{background:#0f172a;color:var(--sdp-ink);border-color:var(--sdp-line)}
                .source-dp-admin.is-dark .source-dp-stats-pagination a,.source-dp-admin.is-dark .source-dp-stats-pagination span{background:#0f172a;color:var(--sdp-ink);border-color:var(--sdp-line)}
                .source-dp-admin.is-dark .source-dp-stats-pagination .current{background:var(--sdp-accent);color:#07131d;border-color:var(--sdp-accent)}

                .source-dp-background-status{background:var(--sdp-card);border:1px solid var(--sdp-line);border-radius:14px;padding:16px;margin-top:12px}.source-dp-background-status p{margin:0 0 10px}.source-dp-log-box{background:#08111f;color:#d8e5f7;border-radius:14px;min-height:180px;max-height:360px;overflow:auto;padding:14px;white-space:pre-wrap;font-family:Consolas,Monaco,monospace;font-size:12px;line-height:1.45}.source-dp-log-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:10px 0}.source-dp-admin.is-dark .source-dp-background-status{background:var(--sdp-card);border-color:var(--sdp-line);color:var(--sdp-ink)}.source-dp-admin.is-dark .source-dp-card,.source-dp-admin.is-dark .source-dp-category-graph,.source-dp-admin.is-dark .source-dp-imported-stats{background:var(--sdp-card)!important;color:var(--sdp-ink);border-color:var(--sdp-line)}.source-dp-admin.is-dark .source-dp-kpi{background:#111c31!important;color:var(--sdp-ink)}.source-dp-admin.is-dark .source-dp-line-chart{background:#111c31;border-color:var(--sdp-line);color:var(--sdp-ink)}.source-dp-admin.is-dark .source-dp-category-track,.source-dp-admin.is-dark .source-dp-kpi-mini{background:rgba(255,255,255,.09)!important}.source-dp-admin.is-dark .widefat,.source-dp-admin.is-dark .widefat thead tr,.source-dp-admin.is-dark .widefat tfoot tr{background:#111c31;color:var(--sdp-ink);border-color:var(--sdp-line)}.source-dp-admin.is-dark .widefat th,.source-dp-admin.is-dark .widefat td{color:var(--sdp-ink);border-color:var(--sdp-line)}.source-dp-admin.is-dark .widefat.striped>tbody>:nth-child(odd){background:#0f172a}.source-dp-admin.is-dark .widefat.striped>tbody>:nth-child(even){background:#111c31}.source-dp-admin.is-dark input,.source-dp-admin.is-dark select,.source-dp-admin.is-dark textarea{background:#0f172a;color:var(--sdp-ink);border-color:var(--sdp-line)}.source-dp-admin.is-dark .source-dp-contact-log{background:#0f172a;color:var(--sdp-ink);border-color:var(--sdp-line)}.source-dp-admin.is-dark .source-dp-stats-pagination a,.source-dp-admin.is-dark .source-dp-stats-pagination span{background:#0f172a;color:var(--sdp-ink);border-color:var(--sdp-line)}.source-dp-admin.is-dark .source-dp-stats-pagination .current{background:var(--sdp-accent);color:#07131d;border-color:var(--sdp-accent)}

                .source-dp-import-brief{display:flex;gap:14px;flex-wrap:wrap;margin-top:10px;color:var(--sdp-muted);font-size:13px}.source-dp-import-brief strong{color:var(--sdp-ink)}.source-dp-import-toggle{margin-top:10px}.source-dp-import-summary{display:none;margin-top:10px;padding-top:10px;border-top:1px solid var(--sdp-line)}.source-dp-import-summary.is-visible{display:block}.source-dp-analytics-filtered{display:flex;gap:16px;flex-wrap:wrap;margin:0 0 12px;padding:10px 12px;border:1px solid var(--sdp-line);border-radius:12px;background:rgba(255,255,255,.62)}.source-dp-analytics-filtered span{font-weight:700;color:var(--sdp-ink)}.source-dp-reconcile table{margin-top:12px}.source-dp-reconcile .description{margin-top:0;color:var(--sdp-muted)}.source-dp-kpi[data-tooltip]{position:relative;overflow:visible}.source-dp-kpi[data-tooltip]:hover:after{content:attr(data-tooltip);position:absolute;left:18px;right:18px;top:calc(100% - 8px);z-index:50;background:#07131d;color:#fff;border-radius:12px;padding:10px 12px;box-shadow:0 12px 30px rgba(16,24,40,.22);font-size:12px;line-height:1.45;white-space:pre-line;text-transform:none;letter-spacing:0}.source-dp-admin.is-dark .source-dp-kpi[data-tooltip]:hover:after{background:#f8fafc;color:#07131d}.source-dp-cron-grid{display:grid;grid-template-columns:minmax(260px,.8fr) minmax(320px,1.2fr);gap:18px;align-items:start}.source-dp-cron-grid select[multiple]{min-height:170px;width:100%}.source-dp-custom-css{width:100%;font-family:Consolas,Monaco,monospace;min-height:180px}.source-dp-contact-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin:12px 0}.source-dp-contact-meta input{width:100%}@media(max-width:900px){.source-dp-cron-grid{grid-template-columns:1fr}}
            .source-dp-admin .button #source-dp-import-toggle, .source-dp-import-overlay.is-active #source-dp-import-toggle{border:unset; padding:unset; float: right}
            .source-dp-admin .button:active #source-dp-import-toggle, .wp-core-ui .button:focus  #source-dp-import-toggle{border:unset; box-shadow:unset}
            #source-dp-import-toggle.button:focus{border:unset; box-shadow:unset}
            .none{display:none}
            </style>

            <?php if (!$connected): ?>
                <div class="source-dp-landing" id="source-dp-landing">
                    <div class="source-dp-landing-card">
                        <img src="<?php echo esc_url($logo_url); ?>" alt="Source" class="source-dp-logo" />
                        <h1><?php echo esc_html__('Connect Source', 'source-distribution-partner'); ?></h1>
                        <p><?php echo esc_html__('Enter your Integration UUID to unlock the Source Distribution Partner dashboard and mapping tools.', 'source-distribution-partner'); ?></p>
                        <div class="source-dp-connect-row">
                            <input type="text" id="source_dp_uuid" value="<?php echo esc_attr($uuid); ?>" placeholder="Integration UUID" autocomplete="off" />
                            <button type="button" class="button button-primary" id="source-dp-test-connection"><?php echo esc_html__('Connect', 'source-distribution-partner'); ?></button>
                        </div>
                        <p id="source-dp-connection-status" style="margin-top:14px;"></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="source-dp-shell">
                    <div class="source-dp-header">
                        <div>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="Source" class="source-dp-logo" />
                        </div>
                        <div class="source-dp-header-right">
                            <div><strong><?php echo esc_html__('Integration UUID:', 'source-distribution-partner'); ?></strong> <button type="button" class="source-dp-uuid-btn" id="source-dp-uuid-edit-toggle"><code><?php echo esc_html($uuid); ?></code></button></div>
                            <div><strong><?php echo esc_html__('Bundle:', 'source-distribution-partner'); ?></strong> <?php echo esc_html($bundle_name ?: 'Connected'); ?></div>
                            <div class="source-dp-header-tools">
                                <button type="button" class="source-dp-theme-toggle" id="source-dp-theme-toggle"><?php echo esc_html__('Dark mode', 'source-distribution-partner'); ?></button>
                                <button type="button" class="source-dp-pill" id="source-dp-header-status"><?php echo esc_html__('Connected', 'source-distribution-partner'); ?></button>
                            </div>
                            <div class="source-dp-uuid-edit" id="source-dp-uuid-edit">
                                <input type="text" id="source-dp-header-uuid" value="<?php echo esc_attr($uuid); ?>" />
                                <button type="button" class="button button-primary" id="source-dp-test-connection"><?php echo esc_html__('Connect', 'source-distribution-partner'); ?></button>
                                <div id="source-dp-connection-status" style="margin-top:8px;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="source-dp-tabs" role="tablist">
                        <button type="button" class="source-dp-tab active" data-source-dp-tab="dashboard"><?php echo esc_html__('Dashboard', 'source-distribution-partner'); ?></button>
                        <button type="button" class="source-dp-tab" data-source-dp-tab="mapping"><?php echo esc_html__('Configuration', 'source-distribution-partner'); ?></button>
                        <button type="button" class="source-dp-tab" data-source-dp-tab="topics"><?php echo esc_html__('Topic Mappings', 'source-distribution-partner'); ?></button>
                        <button type="button" class="source-dp-tab" data-source-dp-tab="contact"><?php echo esc_html__('Contact', 'source-distribution-partner'); ?></button>
                    </div>

                    <div class="source-dp-tab-stage">
                        <div class="source-dp-import-overlay" id="source-dp-import-overlay">
                            <div class="source-dp-import-overlay-head">
                                <div class="source-dp-actions">
                                    <strong id="source-dp-import-title"><?php echo esc_html__('Import running', 'source-distribution-partner'); ?></strong>
                                    <span id="source-dp-sync-status"></span>
                                </div>
                                <div class="source-dp-import-controls">
                                    <button type="button" class="button" id="source-dp-kick-import-overlay"><?php echo esc_html__('Continue Now', 'source-distribution-partner'); ?></button>
                                    <button type="button" class="button button-secondary source-dp-cancel-import" id="source-dp-cancel-import-overlay"><?php echo esc_html__('Stop Import', 'source-distribution-partner'); ?></button>
                                    <button type="button" class="source-dp-import-close" id="source-dp-import-close" aria-label="Close import status">×</button>
                                </div>
                            </div>
                            <div id="source-dp-progress" style="margin:8px 0 0;">
                                <div id="source-dp-progress-label" style="margin:0 0 6px;"></div>
                                <div style="background:#e5e5e5;border-radius:8px;overflow:hidden;height:14px;">
                                    <div id="source-dp-progress-bar" style="height:14px;width:0%;background:#2271b1;"></div>
                                </div>
                            </div>
                            <button  type="button" class="button source-dp-import-toggle" id="source-dp-import-toggle" aria-expanded="false"><?php echo esc_html__('View details', 'source-distribution-partner'); ?></button>
                            
                            <div id="source-dp-import-brief" class="source-dp-import-brief"></div>
                            <div id="source-dp-import-summary" class="source-dp-import-summary"></div>
                        </div>

                        <section class="source-dp-panel active" data-source-dp-panel="dashboard">
                            

                            

                            <?php //echo $this->render_imported_posts_stats_table($uuid); ?>

                            <div class="source-dp-card">
                                <h2><?php echo esc_html__('Setup Steps', 'source-distribution-partner'); ?></h2>
                                <div class="source-dp-steps">
                                    <div class="source-dp-step"><p><b>1</b><strong><?php echo esc_html__('Fetch', 'source-distribution-partner'); ?></strong></p><p><?php echo esc_html__('Go to Topic Mappings and fetch approved Source types, topics and post counts.', 'source-distribution-partner'); ?></p></div>
                                    <div class="source-dp-step"><p><b>2</b><strong><?php echo esc_html__('Map', 'source-distribution-partner'); ?></strong></p><p><?php echo esc_html__('Select included topics and map them to WordPress categories or new terms. Content type tags are added automatically.', 'source-distribution-partner'); ?></p></div>
                                    <div class="source-dp-step"><p><b>3</b><strong><?php echo esc_html__('Import', 'source-distribution-partner'); ?></strong></p><p><?php echo esc_html__('Save settings, then start the background import from Topic Mappings. You can switch tabs while it runs.', 'source-distribution-partner'); ?></p></div>
                                </div>
                            </div>

                            <div class="source-dp-card">
                                <h2><?php echo esc_html__('Shortcodes', 'source-distribution-partner'); ?></h2>
                                <div class="source-dp-shortcodes">
                                    <code>[source_dp_tabs]</code>
                                    <code>[source_dp_feed type="all|video|podcast|articles" category_id="50" latest="1" per_page="12"]</code>
                                    <code>[source_dp_all]</code>
                                    <code>[source_dp_videos]</code>
                                    <code>[source_dp_podcasts]</code>
                                    <code>[source_dp_articles]</code>
                                    <code>[source_dp_latest]</code>
                                    <code>[source_dp_category id="50"]</code>
                                </div>
                            </div>
                        </section>

                        <form method="post" action="options.php">
                            <?php settings_fields('source_dp_settings'); ?>
                            <input type="hidden" id="source_dp_uuid" name="<?php echo esc_attr(self::OPT_UUID); ?>" value="<?php echo esc_attr($uuid); ?>" />

                            <section class="source-dp-panel" data-source-dp-panel="mapping">
                                <div class="source-dp-card"><h2><?php echo esc_html__('Configuration', 'source-distribution-partner'); ?></h2>
                                <table class="form-table" role="presentation">
                                    <tr><th scope="row"><label for="source_dp_post_type"><?php echo esc_html__('Global Post Type', 'source-distribution-partner'); ?></label></th><td><select id="source_dp_post_type" name="<?php echo esc_attr(self::OPT_POST_TYPE); ?>"><?php foreach ($public_post_types as $pt): ?><option value="<?php echo esc_attr($pt->name); ?>" <?php selected($post_type, $pt->name); ?>><?php echo esc_html($pt->labels->singular_name . ' (' . $pt->name . ')'); ?></option><?php endforeach; ?></select></td></tr>
                                    <tr><th scope="row"><label for="source_dp_global_tag"><?php echo esc_html__('Global Tag (optional)', 'source-distribution-partner'); ?></label></th><td><input type="text" id="source_dp_global_tag" name="<?php echo esc_attr(self::OPT_GLOBAL_TAG); ?>" value="<?php echo esc_attr($global_tag); ?>" class="regular-text" /></td></tr>
                                    <tr><th scope="row"><?php echo esc_html__('Overwrite Imported Posts', 'source-distribution-partner'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT_OVERWRITE); ?>" value="1" <?php checked($overwrite, true); ?> /> <?php echo esc_html__('Update imported posts on sync (otherwise skip existing).', 'source-distribution-partner'); ?></label></td></tr>
                                    <tr><th scope="row"><?php echo esc_html__('SEO for Imported Posts', 'source-distribution-partner'); ?></th><td><select name="<?php echo esc_attr(self::OPT_SEO_MODE); ?>"><option value="canonical_source" <?php selected($seo_mode, 'canonical_source'); ?>>Canonical to Source URL (recommended)</option><option value="canonical_self" <?php selected($seo_mode, 'canonical_self'); ?>>Canonical to this page</option><option value="noindex" <?php selected($seo_mode, 'noindex'); ?>>Noindex imported posts</option><option value="none" <?php selected($seo_mode, 'none'); ?>>Do nothing</option></select></td></tr>
                                    <tr><th scope="row"><label for="source_dp_external_link_behavior"><?php echo esc_html__('External Links', 'source-distribution-partner'); ?></label></th><td><select id="source_dp_external_link_behavior" name="<?php echo esc_attr(self::OPT_EXTERNAL_LINK_BEHAVIOR); ?>"><option value="new_tab" <?php selected($external_link_behavior, 'new_tab'); ?>><?php echo esc_html__('Open in new window', 'source-distribution-partner'); ?></option><option value="disabled" <?php selected($external_link_behavior, 'disabled'); ?>><?php echo esc_html__('Do nothing (treat link as #)', 'source-distribution-partner'); ?></option><option value="modal" <?php selected($external_link_behavior, 'modal'); ?>><?php echo esc_html__('Open in modal', 'source-distribution-partner'); ?></option></select></td></tr>
                                    <tr class="none"><th scope="row"><?php echo esc_html__('Frontend Source Meta Text', 'source-distribution-partner'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT_SHOW_SOURCE_META); ?>" value="1" <?php checked($show_source_meta, true); ?> /> <?php echo esc_html__('Show Source feed, published date and content type ID inside imported post content.', 'source-distribution-partner'); ?></label></td></tr>
                                    <tr class="none"><th scope="row"><?php echo esc_html__('Video Tracking (30s)', 'source-distribution-partner'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT_ENABLE_VIDEO_TRACK); ?>" value="1" <?php checked($enable_video, true); ?> /> <?php echo esc_html__('Track YouTube views and post to Source analytics endpoint.', 'source-distribution-partner'); ?></label></td></tr>
                                    <tr class="none"><th scope="row"><?php echo esc_html__('Impressions, Clicks and Pageviews', 'source-distribution-partner'); ?></th><td><label style="display:block;margin-bottom:6px;"><input type="checkbox" name="<?php echo esc_attr(self::OPT_ENABLE_IMPRESSION_TRACK); ?>" value="1" <?php checked($enable_impressions, true); ?> /> <?php echo esc_html__('Track impressions for imported Source posts and send to Source analytics.', 'source-distribution-partner'); ?></label><label style="display:block;"><input type="checkbox" name="<?php echo esc_attr(self::OPT_ENABLE_CLICK_TRACK); ?>" value="1" <?php checked($enable_clicks, true); ?> /> <?php echo esc_html__('Track external/source clicks for imported Source posts and send to Source analytics.', 'source-distribution-partner'); ?></label></td></tr>
                                </table>
                                <?php submit_button(__('Save configuration', 'source-distribution-partner')); ?>
                                </div>

                                <div class="source-dp-card">
                                    <h2><?php echo esc_html__('Cron / New Post Fetch', 'source-distribution-partner'); ?></h2>
                                    <p class="description"><?php echo esc_html__('Automatically checks Source for new posts and queues an import for the selected topics only. Existing imported posts are not overwritten by this cron.', 'source-distribution-partner'); ?></p><br>
                                    <div class="source-dp-cron-grid">
                                        <div>
                                            <table class="form-table" role="presentation">
                                                <tr><th scope="row"><label for="source_dp_auto_import_interval"><?php echo esc_html__('Run schedule', 'source-distribution-partner'); ?></label></th><td><select id="source_dp_auto_import_interval" name="<?php echo esc_attr(self::OPT_AUTO_IMPORT_INTERVAL); ?>"><option value="manual" <?php selected($auto_import_interval, 'manual'); ?>><?php echo esc_html__('Manual only', 'source-distribution-partner'); ?></option><option value="hourly" <?php selected($auto_import_interval, 'hourly'); ?>><?php echo esc_html__('Every hour', 'source-distribution-partner'); ?></option><option value="twicedaily" <?php selected($auto_import_interval, 'twicedaily'); ?>><?php echo esc_html__('Every 12 hours', 'source-distribution-partner'); ?></option><option value="daily" <?php selected($auto_import_interval, 'daily'); ?>><?php echo esc_html__('Every 24 hours', 'source-distribution-partner'); ?></option><option value="source_dp_weekly" <?php selected($auto_import_interval, 'source_dp_weekly'); ?>><?php echo esc_html__('Weekly', 'source-distribution-partner'); ?></option></select></td></tr>
                                            </table>
                                            <p><button type="button" class="button button-primary" id="source-dp-run-cron-now"><?php echo esc_html__('Run Cron Now', 'source-distribution-partner'); ?></button><span id="source-dp-cron-status" style="margin-left:10px;"></span></p>
                                        </div>
                                        <div>
                                            <label for="source_dp_auto_import_categories none"><strong><?php //echo esc_html__('Cron categories', 'source-distribution-partner'); ?></strong></label>
                                            <input type="hidden" name="<?php echo esc_attr(self::OPT_AUTO_IMPORT_CATEGORIES); ?>[]" value="" />
                                            <select id="source_dp_auto_import_categories" name="<?php echo esc_attr(self::OPT_AUTO_IMPORT_CATEGORIES); ?>[]" multiple>
                                                <?php foreach ($categories as $cat): ?>
                                                    <?php $source_cat_id = (string)($cat['id'] ?? ''); if ($source_cat_id === '') continue; ?>
                                                    <option value="<?php echo esc_attr($source_cat_id); ?>" <?php selected(in_array($source_cat_id, $auto_import_categories, true), true); ?>><?php echo esc_html((string)($cat['name'] ?? $source_cat_id)); ?><?php echo isset($counts[$source_cat_id]) ? ' (' . esc_html((string)(int)$counts[$source_cat_id]) . ')' : ''; ?></option>
                                                <?php endforeach; ?>
                                            </select><br><br>
                                            <p class="description"><?php echo esc_html__('Leave empty to use all topics currently ticked as Include under Topic Mappings.', 'source-distribution-partner'); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="source-dp-card">
                                    <h2><?php echo esc_html__('Frontend Custom CSS', 'source-distribution-partner'); ?></h2>
                                    <p class="description"><?php echo esc_html__('CSS entered here is printed on the frontend so you can style Source feeds, cards, tabs, imported posts and category pages without editing the plugin files.', 'source-distribution-partner'); ?></p>
                                    <textarea class="source-dp-custom-css" name="<?php echo esc_attr(self::OPT_CUSTOM_CSS); ?>" placeholder=".source-dp-feed { }"><?php echo esc_textarea($custom_css); ?></textarea>
                                    <p class="description"><?php echo esc_html__('Tip: target shortcode output with .source-dp-feed, .source-dp-tabs, .source-dp-card, and imported post pages with body classes/theme selectors.', 'source-distribution-partner'); ?></p>
                                    <?php submit_button(__('Save Cron & CSS Settings', 'source-distribution-partner'), 'secondary', 'submit', false); ?>
                                </div>

                                <div class="source-dp-card"><h2><?php echo esc_html__('Delete', 'source-distribution-partner'); ?></h2><p class="description"><?php echo esc_html__('Deletes imported posts from this WordPress site only.', 'source-distribution-partner'); ?></p><p><label><?php echo esc_html__('Source Category ID (optional):', 'source-distribution-partner'); ?> <input type="text" id="source-dp-delete-category-id" class="regular-text" style="max-width:200px;" placeholder="e.g. 50" /></label></p><p><label><input type="checkbox" id="source-dp-delete-created-terms" value="1" /> <?php echo esc_html__('Also delete WP terms created by plugin (unused only).', 'source-distribution-partner'); ?></label></p><p><button type="button" class="button button-secondary" id="source-dp-delete-imported"><?php echo esc_html__('Delete Imported Posts', 'source-distribution-partner'); ?></button><span id="source-dp-delete-status" style="margin-left:10px;"></span></p></div>
                            </section>

                            <section class="source-dp-panel" data-source-dp-panel="topics">
                                <div class="source-dp-card"><h2><?php echo esc_html__('Topic Mappings', 'source-distribution-partner'); ?></h2>
                                    <div class="source-dp-actions" style="margin-bottom:14px;"><button type="button" class="button button-primary" id="source-dp-fetch-approved"><?php echo esc_html__('Fetch Types, Topics & Post Counts', 'source-distribution-partner'); ?></button><span id="source-dp-fetch-status"></span></div>
                                    <div style="margin-bottom:14px;"><strong><?php echo esc_html__('Bundle:', 'source-distribution-partner'); ?></strong> <?php echo esc_html($bundle_name ?: '—'); ?> &nbsp; <strong><?php echo esc_html__('Approved Topic Matches:', 'source-distribution-partner'); ?></strong> <?php echo esc_html((string)$approved_total); ?> &nbsp; <strong><?php echo esc_html__('Unique Source Posts:', 'source-distribution-partner'); ?></strong> <?php echo esc_html((string)$source_unique_total); ?> &nbsp; <strong><?php echo esc_html__('Source Types:', 'source-distribution-partner'); ?></strong> <?php echo esc_html('Blog ' . (int)$content_type_counts['blog'] . ' / Podcast ' . (int)$content_type_counts['podcast'] . ' / Video ' . (int)$content_type_counts['video']); ?><?php $category_source_note = (string)($bundle_cache['category_source'] ?? ''); if ($category_source_note !== ''): ?> &nbsp; <strong><?php echo esc_html__('Category source:', 'source-distribution-partner'); ?></strong> <?php echo esc_html($category_source_note); ?><?php endif; ?></div>

                                    <?php if (empty($categories)): ?>
                                        <p><?php echo esc_html__('Fetch approved types & topics to populate mappings.', 'source-distribution-partner'); ?></p>
                                    <?php else: ?>
                                        <table class="widefat striped">
                                            <thead><tr><th><?php echo esc_html__('Include', 'source-distribution-partner'); ?></th><th><?php echo esc_html__('Source Topic', 'source-distribution-partner'); ?></th><th><?php echo esc_html__('# Posts', 'source-distribution-partner'); ?></th><th><?php echo esc_html__('WP Category', 'source-distribution-partner'); ?></th><th><?php echo esc_html__('Create New Category (name)', 'source-distribution-partner'); ?></th><th><?php echo esc_html__('Category Canonical URL', 'source-distribution-partner'); ?></th></tr></thead>
                                            <tbody>
                                            <?php foreach ($categories as $cat): ?>
                                                <?php $source_cat_id = (string) ($cat['id'] ?? ''); $source_cat_name = (string) ($cat['name'] ?? $source_cat_id); $row = isset($mappings[$source_cat_id]) && is_array($mappings[$source_cat_id]) ? $mappings[$source_cat_id] : []; $include = array_key_exists('include', $row) ? (bool) $row['include'] : true; $mapped_wp_cat = (int) ($row['wp_category'] ?? 0); $create_new = (string) ($row['create_new'] ?? ''); $canonical_url = (string) ($row['canonical_url'] ?? ''); $count = isset($counts[$source_cat_id]) ? (int)$counts[$source_cat_id] : 0; ?>
                                                <tr>
                                                    <td><input type="checkbox" name="<?php echo esc_attr(self::OPT_MAPPINGS); ?>[<?php echo esc_attr($source_cat_id); ?>][include]" value="1" <?php checked($include, true); ?> /></td>
                                                    <td><strong><?php echo esc_html($source_cat_name); ?></strong><br /><code><?php echo esc_html($source_cat_id); ?></code></td>
                                                    <td><span class="source-dp-count" data-source-cat-id="<?php echo esc_attr($source_cat_id); ?>"><?php echo esc_html((string)$count); ?></span></td>
                                                    <td><select name="<?php echo esc_attr(self::OPT_MAPPINGS); ?>[<?php echo esc_attr($source_cat_id); ?>][wp_category]"><option value="0"><?php echo esc_html__('— None —', 'source-distribution-partner'); ?></option><?php foreach ($wp_categories as $wp_cat): ?><option value="<?php echo esc_attr((string) $wp_cat->term_id); ?>" <?php selected($mapped_wp_cat, (int) $wp_cat->term_id); ?>><?php echo esc_html($wp_cat->name); ?></option><?php endforeach; ?></select></td>
                                                    <td><input type="text" name="<?php echo esc_attr(self::OPT_MAPPINGS); ?>[<?php echo esc_attr($source_cat_id); ?>][create_new]" value="<?php echo esc_attr($create_new); ?>" class="regular-text" /></td>
                                                    <td><input type="url" name="<?php echo esc_attr(self::OPT_MAPPINGS); ?>[<?php echo esc_attr($source_cat_id); ?>][canonical_url]" value="<?php echo esc_attr($canonical_url); ?>" class="regular-text" placeholder="https://" /></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                    <div class="source-dp-actions" style="margin-top:16px;">
                                        <?php submit_button(__('Save Settings & Mappings', 'source-distribution-partner'), 'primary', 'submit', false); ?>
                                        <button type="button" class="button button-primary" id="source-dp-sync-now"><?php echo esc_html__('Start Background Import', 'source-distribution-partner'); ?></button>
                                        <button type="button" class="button button-secondary source-dp-cancel-import" id="source-dp-cancel-import-main"><?php echo esc_html__('Stop / Cancel Import', 'source-distribution-partner'); ?></button>
                                        <span class="description"><?php echo esc_html__('Import selected categories and their posts.', 'source-distribution-partner'); ?></span>
                                    </div>
                                    <div id="source-dp-sync-result" class="source-dp-background-status" style="display:none;"></div>
                                </div>

                                <div class="source-dp-card none">
                                    <h2><?php echo esc_html__('Import Debug Log', 'source-distribution-partner'); ?></h2>
                                    <p class="description"><?php echo esc_html__('Shows background import progress, API calls, skipped pages and failed posts.', 'source-distribution-partner'); ?></p>
                                    <div class="source-dp-log-actions">
                                        <button type="button" class="button" id="source-dp-refresh-log"><?php echo esc_html__('Refresh Log', 'source-distribution-partner'); ?></button>
                                        <button type="button" class="button" id="source-dp-clear-log"><?php echo esc_html__('Clear Log', 'source-distribution-partner'); ?></button>
                                        <button type="button" class="button" id="source-dp-kick-import"><?php echo esc_html__('Kick / Continue Import', 'source-distribution-partner'); ?></button>
                                        <button type="button" class="button source-dp-cancel-import" id="source-dp-cancel-import-log"><?php echo esc_html__('Stop / Cancel Import', 'source-distribution-partner'); ?></button>
                                        <span id="source-dp-log-status"></span>
                                    </div>
                                    <pre id="source-dp-import-log" class="source-dp-log-box"><?php echo esc_html($this->format_import_log_text($import_log, 160)); ?></pre>
                                </div>
                            </section>
                        </form>

                        <section class="source-dp-panel" data-source-dp-panel="contact">
                            <div class="source-dp-card">
                                <h2><?php echo esc_html__('Contact Source Devs', 'source-distribution-partner'); ?></h2>
                                <p class="description"><?php echo esc_html__('Send us a message.', 'source-distribution-partner'); ?></p>
                                <div class="source-dp-contact-meta">
                                    <label style="display:none"><?php echo esc_html__('To', 'source-distribution-partner'); ?><input type="email" id="source-dp-contact-to" value="lee@wearesource.earth" /></label>
                                    <label><!--<?php echo esc_html__('Your email / reply-to', 'source-distribution-partner'); ?>--></label>
                                </div>
                                <table class="form-table" role="presentation">
                                    <tr><th><label>Your Email</label></th><td><input type="email" id="source-dp-contact-from" value="<?php $cu = wp_get_current_user(); echo esc_attr($cu && !empty($cu->user_email) ? $cu->user_email : get_option('admin_email')); ?>" /></td></tr>
                                    <tr><th><label for="source-dp-contact-subject"><?php echo esc_html__('Subject', 'source-distribution-partner'); ?></label></th><td><input type="text" id="source-dp-contact-subject" class="large-text" value="Source Distribution Partner Support" /></td></tr>
                                    <tr><th><label for="source-dp-contact-message"><?php echo esc_html__('Message', 'source-distribution-partner'); ?></label></th><td><textarea id="source-dp-contact-message" class="large-text" rows="7" placeholder="Describe the issue or request..."></textarea></td></tr>
                                </table>
                                <p><button type="button" class="button button-primary" id="source-dp-contact-send"><?php echo esc_html__('Send Message', 'source-distribution-partner'); ?></button><span id="source-dp-contact-status" style="margin-left:10px;"></span></p>
                                <p class="description none"><?php echo esc_html__('If WordPress mail is not configured, the message is still saved to Contact Log and the error will say to check SMTP/mail settings.', 'source-distribution-partner'); ?></p>
                            </div>
                            <div class="source-dp-card"  style="display:none">
                                <h2><?php echo esc_html__('Contact Log', 'source-distribution-partner'); ?></h2>
                                <div class="source-dp-contact-log" id="source-dp-contact-log">
                                    <?php if (empty($contact_log)): ?>
                                        <p><?php echo esc_html__('No messages logged yet.', 'source-distribution-partner'); ?></p>
                                    <?php else: ?>
                                        <?php foreach (array_reverse(array_slice($contact_log, -20)) as $entry): ?>
                                            <div class="source-dp-contact-log-entry"><strong><?php echo esc_html((string)($entry['subject'] ?? 'Message')); ?></strong><br><span><?php echo esc_html((string)($entry['date'] ?? '')); ?> · <?php echo esc_html((string)($entry['to'] ?? '')); ?> · <?php echo !empty($entry['sent']) ? esc_html__('Sent', 'source-distribution-partner') : esc_html__('Failed', 'source-distribution-partner'); ?></span><p><?php echo esc_html((string)($entry['message'] ?? '')); ?></p></div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_approved_category_breakdown(array $categories, array $counts, int $limit = 6): array
    {
        $rows = [];
        foreach ($categories as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $id = (string)($cat['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $count = isset($counts[$id]) ? (int)$counts[$id] : 0;
            if ($count <= 0) {
                continue;
            }
            $rows[] = [
                'id' => $id,
                'name' => (string)($cat['name'] ?? $id),
                'count' => $count,
            ];
        }
        usort($rows, static function ($a, $b) {
            return ((int)$b['count']) <=> ((int)$a['count']);
        });
        return array_slice($rows, 0, max(1, $limit));
    }

    private function build_source_embed_url(string $uuid, string $source_post_id = '', int $numeric_id = 0, string $fallback_url = ''): string
    {
        $fallback_url = trim($fallback_url);
        if ($fallback_url !== '' && stripos($fallback_url, 'app.wearesource.earth/embed') !== false) {
            return esc_url_raw($fallback_url);
        }

        $url = add_query_arg(['uuid' => $uuid], 'https://app.wearesource.earth/embed');
        if ($source_post_id !== '') {
            $url = add_query_arg(['postId' => $source_post_id], $url);
        } elseif ($numeric_id > 0) {
            $url = add_query_arg(['postId' => (string)$numeric_id], $url);
        }
        return esc_url_raw($url);
    }

    private function render_imported_posts_stats_table(string $uuid = ''): string
    {
        $post_type = (string)get_option(self::OPT_POST_TYPE, 'post');
        if (!post_type_exists($post_type)) {
            $post_type = 'post';
        }

        $search = isset($_GET['source_dp_post_search']) ? sanitize_text_field(wp_unslash((string)$_GET['source_dp_post_search'])) : '';
        $paged = isset($_GET['source_dp_posts_page']) ? max(1, (int)$_GET['source_dp_posts_page']) : 1;
        $per_page = 12;

        $meta_query = [
            ['key' => '_source_imported', 'value' => 1, 'compare' => '='],
        ];
        if ($uuid !== '') {
            $meta_query[] = ['key' => '_source_uuid', 'value' => $uuid, 'compare' => '='];
        }

        $qargs = [
            'post_type' => $post_type,
            'post_status' => 'any',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'meta_query' => $meta_query,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        if ($search !== '') {
            $qargs['s'] = $search;
        }

        $q = new WP_Query($qargs);
        ob_start();
        ?>
        <div class="source-dp-card source-dp-imported-stats">
            <h2><?php echo esc_html__('Imported Posts & Stats', 'source-distribution-partner'); ?></h2>
            <form method="get" class="source-dp-stats-tools">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>" />
                <input type="search" name="source_dp_post_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Search imported posts...', 'source-distribution-partner'); ?>" />
                <button type="submit" class="button"><?php echo esc_html__('Search', 'source-distribution-partner'); ?></button>
                <?php if ($search !== ''): ?><a class="button" href="<?php echo esc_url(admin_url('options-general.php?page=' . self::PAGE_SLUG)); ?>"><?php echo esc_html__('Clear', 'source-distribution-partner'); ?></a><?php endif; ?>
            </form>
            <table class="widefat striped source-dp-stats-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Post', 'source-distribution-partner'); ?></th>
                        <th><?php echo esc_html__('Type', 'source-distribution-partner'); ?></th>
                        <th><?php echo esc_html__('Source Topic', 'source-distribution-partner'); ?></th>
                        <th><?php echo esc_html__('Impressions', 'source-distribution-partner'); ?></th>
                        <th><?php echo esc_html__('Clicks', 'source-distribution-partner'); ?></th>
                        <th><?php echo esc_html__('Pageviews', 'source-distribution-partner'); ?></th>
                        <th><?php echo esc_html__('Video / Audio Plays', 'source-distribution-partner'); ?></th>
                        <th><?php echo esc_html__('Published', 'source-distribution-partner'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($q->have_posts()): ?>
                        <?php while ($q->have_posts()): $q->the_post(); $pid = get_the_ID(); ?>
                            <?php $type_label = $this->get_content_type_label_from_id((string)get_post_meta($pid, '_source_content_type_id', true)); ?>
                            <tr>
                                <?php
                                    $display_url = (string)get_post_meta($pid, '_source_embed_url', true);
                                    if ($display_url === '') {
                                        $display_url = $this->build_source_embed_url(
                                            (string)get_post_meta($pid, '_source_uuid', true),
                                            (string)get_post_meta($pid, '_source_post_id', true),
                                            (int)get_post_meta($pid, '_source_numeric_id', true),
                                            (string)get_post_meta($pid, '_source_url', true)
                                        );
                                    }
                                ?>
                                <td><strong><a href="<?php echo esc_url(get_edit_post_link($pid)); ?>"><?php echo esc_html(get_the_title($pid)); ?></a></strong><small><a href="<?php echo esc_url($display_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($display_url); ?></a></small></td>
                                <td><?php echo esc_html($type_label !== '' ? $type_label : '—'); ?></td>
                                <td><?php echo esc_html((string)get_post_meta($pid, '_source_topic', true) ?: '—'); ?></td>
                                <td><?php echo esc_html((string)(int)get_post_meta($pid, '_source_impressions', true)); ?></td>
                                <td><?php echo esc_html((string)(int)get_post_meta($pid, '_source_clicks', true)); ?></td>
                                <td><?php echo esc_html((string)(int)get_post_meta($pid, '_source_pageviews', true)); ?></td>
                                <td><?php echo esc_html((string)(int)get_post_meta($pid, '_source_video_views', true)); ?></td>
                                <td><?php echo esc_html(get_the_date('', $pid)); ?></td>
                            </tr>
                        <?php endwhile; wp_reset_postdata(); ?>
                    <?php else: ?>
                        <tr><td colspan="8"><?php echo esc_html__('No imported posts found.', 'source-distribution-partner'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($q->max_num_pages > 1): ?>
                <div class="source-dp-stats-pagination">
                    <?php
                    echo wp_kses_post(paginate_links([
                        'base' => add_query_arg('source_dp_posts_page', '%#%'),
                        'format' => '',
                        'current' => $paged,
                        'total' => (int)$q->max_num_pages,
                        'prev_text' => '‹',
                        'next_text' => '›',
                    ]));
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string)ob_get_clean();
    }

    private function get_imported_category_breakdown(string $uuid = '', array $source_categories = [], int $limit = 6): array
    {
        $meta_query = [
            ['key' => '_source_imported', 'value' => 1, 'compare' => '='],
        ];
        if ($uuid !== '') {
            $meta_query[] = ['key' => '_source_uuid', 'value' => $uuid, 'compare' => '='];
        }

        $ids = get_posts([
            'post_type' => 'any',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => $meta_query,
        ]);
        if (!is_array($ids) || empty($ids)) {
            return [];
        }

        $source_names = [];
        foreach ($source_categories as $cat) {
            if (is_array($cat) && isset($cat['id'])) {
                $source_names[(string)$cat['id']] = (string)($cat['name'] ?? $cat['id']);
            }
        }

        $counts = [];
        $names = [];
        foreach ($ids as $pid) {
            $pid = (int)$pid;
            $source_cat_ids = $this->get_source_category_ids_for_post($pid);
            if (empty($source_cat_ids)) {
                $source_topic = (string)get_post_meta($pid, '_source_topic', true);
                $source_cat_ids = [$source_topic !== '' ? sanitize_title($source_topic) : 'uncategorized'];
            }

            foreach ($source_cat_ids as $source_cat_id) {
                $source_cat_id = (string)$source_cat_id;
                $name = $source_names[$source_cat_id] ?? __('Unmapped', 'source-distribution-partner');
                if (!isset($counts[$source_cat_id])) {
                    $counts[$source_cat_id] = 0;
                    $names[$source_cat_id] = $name;
                }
                $counts[$source_cat_id]++;
            }
        }

        arsort($counts);
        $rows = [];
        foreach ($counts as $id => $count) {
            $rows[] = [
                'id' => (string)$id,
                'name' => (string)($names[$id] ?? $id),
                'count' => (int)$count,
            ];
            if (count($rows) >= max(1, $limit)) {
                break;
            }
        }
        return $rows;
    }

    private function get_imported_post_count(string $uuid = ''): int
    {
        $meta_query = [
            ['key' => '_source_imported', 'value' => 1, 'compare' => '='],
        ];
        if ($uuid !== '') {
            $meta_query[] = ['key' => '_source_uuid', 'value' => $uuid, 'compare' => '='];
        }
        $ids = get_posts([
            'post_type' => 'any',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => $meta_query,
        ]);
        return is_array($ids) ? count($ids) : 0;
    }

    private function get_source_category_ids_for_post(int $post_id): array
    {
        $ids = get_post_meta($post_id, '_source_category_ids', true);
        if (is_string($ids) && $ids !== '') {
            $decoded = json_decode($ids, true);
            if (is_array($decoded)) {
                $ids = $decoded;
            } elseif (strpos($ids, ',') !== false) {
                $ids = explode(',', $ids);
            }
        }
        if (!is_array($ids)) {
            $ids = [];
        }

        $legacy = (string)get_post_meta($post_id, '_source_category_id', true);
        if ($legacy !== '') {
            $ids[] = $legacy;
        }

        $clean = [];
        foreach ($ids as $id) {
            $id = $this->sanitize_text_field_relaxed((string)$id);
            if ($id !== '') {
                $clean[$id] = $id;
            }
        }
        return array_values($clean);
    }

    private function normalize_source_meta_list($value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } elseif (strpos($value, ',') !== false) {
                $value = explode(',', $value);
            }
        }
        if (!is_array($value)) {
            $value = [];
        }

        $clean = [];
        foreach ($value as $item) {
            $item = $this->sanitize_text_field_relaxed((string)$item);
            if ($item !== '') {
                $clean[$item] = $item;
            }
        }
        return array_values($clean);
    }

    private function merge_source_record_tracking(int $post_id, int $numeric_id, string $source_import_key): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        $changed = false;
        if ($numeric_id > 0) {
            $numeric_ids = $this->normalize_source_meta_list(get_post_meta($post_id, '_source_numeric_ids', true));
            $numeric_value = (string)$numeric_id;
            if (!in_array($numeric_value, $numeric_ids, true)) {
                $numeric_ids[] = $numeric_value;
                update_post_meta($post_id, '_source_numeric_ids', array_values($numeric_ids));
                $changed = true;
            }
            if ((int)get_post_meta($post_id, '_source_numeric_id', true) <= 0) {
                update_post_meta($post_id, '_source_numeric_id', $numeric_id);
                $changed = true;
            }
        }

        $source_import_key = $this->sanitize_text_field_relaxed($source_import_key);
        if ($source_import_key !== '') {
            $import_keys = $this->normalize_source_meta_list(get_post_meta($post_id, '_source_import_keys', true));
            if (!in_array($source_import_key, $import_keys, true)) {
                $import_keys[] = $source_import_key;
                update_post_meta($post_id, '_source_import_keys', array_values($import_keys));
                $changed = true;
            }
            if ((string)get_post_meta($post_id, '_source_import_key', true) === '') {
                update_post_meta($post_id, '_source_import_key', $source_import_key);
                $changed = true;
            }
        }

        return $changed;
    }

    private function merge_source_category_tracking(int $post_id, string $source_category_id, array $source_category_name_by_id): bool
    {
        $source_category_id = $this->sanitize_text_field_relaxed($source_category_id);
        if ($post_id <= 0 || $source_category_id === '') {
            return false;
        }

        $ids = $this->get_source_category_ids_for_post($post_id);
        $before = $ids;
        if (!in_array($source_category_id, $ids, true)) {
            $ids[] = $source_category_id;
        }
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));

        update_post_meta($post_id, '_source_category_ids', $ids);
        if ((string)get_post_meta($post_id, '_source_category_id', true) === '') {
            update_post_meta($post_id, '_source_category_id', $source_category_id);
        }

        $topics = [];
        foreach ($ids as $cid) {
            if (isset($source_category_name_by_id[$cid]) && (string)$source_category_name_by_id[$cid] !== '') {
                $topics[$cid] = (string)$source_category_name_by_id[$cid];
            }
        }
        if (!empty($topics)) {
            update_post_meta($post_id, '_source_topics', array_values($topics));
        }
        if ((string)get_post_meta($post_id, '_source_topic', true) === '' && isset($source_category_name_by_id[$source_category_id])) {
            update_post_meta($post_id, '_source_topic', (string)$source_category_name_by_id[$source_category_id]);
        }

        return array_values($before) !== $ids;
    }

    private function apply_mapped_source_category_to_post(int $post_id, string $source_category_id, array $mappings): bool
    {
        $source_category_id = $this->sanitize_text_field_relaxed($source_category_id);
        if ($post_id <= 0 || $source_category_id === '') {
            return false;
        }

        $row = isset($mappings[$source_category_id]) && is_array($mappings[$source_category_id]) ? $mappings[$source_category_id] : [];
        $mapped_cat = (int)($row['wp_category'] ?? 0);
        $create_new = $this->sanitize_text_field_relaxed((string)($row['create_new'] ?? ''));
        $category_canonical_url = esc_url_raw((string)($row['canonical_url'] ?? ''));

        $term_id = 0;
        if ($mapped_cat > 0) {
            $term_id = $mapped_cat;
        } elseif ($create_new !== '') {
            $created_term_id = $this->ensure_term('category', $create_new);
            if (!is_wp_error($created_term_id) && $created_term_id > 0) {
                $term_id = (int)$created_term_id;
            }
        }

        if ($term_id <= 0) {
            return false;
        }

        $existing_terms = wp_get_post_terms($post_id, 'category', ['fields' => 'ids']);
        $existing_terms = is_wp_error($existing_terms) ? [] : array_map('intval', (array)$existing_terms);
        wp_set_post_terms($post_id, [$term_id], 'category', true);

        if (!empty($category_canonical_url)) {
            update_term_meta($term_id, '_source_category_canonical_url', $category_canonical_url);
        }

        return !in_array($term_id, $existing_terms, true);
    }

    private function attach_source_category_to_post(int $post_id, string $source_category_id, array $source_category_name_by_id, array $mappings): bool
    {
        $meta_changed = $this->merge_source_category_tracking($post_id, $source_category_id, $source_category_name_by_id);
        $term_changed = $this->apply_mapped_source_category_to_post($post_id, $source_category_id, $mappings);
        return $meta_changed || $term_changed;
    }

    private function get_imported_post_count_for_source_category(string $uuid, string $source_category_id): int
    {
        if ($uuid === '' || $source_category_id === '') {
            return 0;
        }
        $ids = get_posts([
            'post_type' => 'any',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => '_source_imported', 'value' => 1, 'compare' => '='],
                ['key' => '_source_uuid', 'value' => $uuid, 'compare' => '='],
            ],
        ]);

        $count = 0;
        foreach ((array)$ids as $pid) {
            if (in_array($source_category_id, $this->get_source_category_ids_for_post((int)$pid), true)) {
                $count++;
            }
        }
        return $count;
    }

    private function get_import_reconciliation_rows(string $uuid, array $categories, array $counts): array
    {
        $rows = [];
        foreach ($categories as $cat) {
            if (!is_array($cat) || !isset($cat['id'])) {
                continue;
            }
            $cid = (string)$cat['id'];
            if ($cid === '') {
                continue;
            }
            $source_total = isset($counts[$cid]) && is_numeric($counts[$cid]) ? (int)$counts[$cid] : 0;
            $imported = $this->get_imported_post_count_for_source_category($uuid, $cid);
            $rows[] = [
                'id' => $cid,
                'name' => (string)($cat['name'] ?? $cid),
                'source_total' => $source_total,
                'imported' => $imported,
                'missing' => max(0, $source_total - $imported),
            ];
        }
        usort($rows, static function ($a, $b) {
            // Show categories that actually imported first. The previous missing-first sort could hide
            // the successfully imported category when only the first rows were rendered.
            $a_imported = (int)($a['imported'] ?? 0);
            $b_imported = (int)($b['imported'] ?? 0);
            if (($a_imported > 0) !== ($b_imported > 0)) {
                return $a_imported > 0 ? -1 : 1;
            }
            return ((int)$b['missing'] <=> (int)$a['missing']) ?: ((int)$b['source_total'] <=> (int)$a['source_total']);
        });
        return $rows;
    }

    private function get_local_analytics_totals(string $uuid = ''): array
    {
        $meta_query = [
            ['key' => '_source_imported', 'value' => 1, 'compare' => '='],
        ];
        if ($uuid !== '') {
            $meta_query[] = ['key' => '_source_uuid', 'value' => $uuid, 'compare' => '='];
        }
        $ids = get_posts([
            'post_type' => 'any',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => $meta_query,
        ]);

        $totals = ['impressions' => 0, 'clicks' => 0, 'pageviews' => 0, 'video_views' => 0];
        foreach ($ids as $pid) {
            $pid = (int)$pid;
            $totals['impressions'] += (int)get_post_meta($pid, '_source_impressions', true);
            $totals['clicks'] += (int)get_post_meta($pid, '_source_clicks', true);
            $totals['pageviews'] += (int)get_post_meta($pid, '_source_pageviews', true);
            $totals['video_views'] += (int)get_post_meta($pid, '_source_video_views', true);
        }
        return $totals;
    }

    private function get_local_analytics_series(string $uuid = ''): array
    {
        $daily = get_option(self::OPT_ANALYTICS_DAILY, []);
        $daily = is_array($daily) ? $daily : [];
        $uuid_daily = [];
        if ($uuid !== '' && isset($daily[$uuid]) && is_array($daily[$uuid])) {
            $uuid_daily = $daily[$uuid];
        }

        if (empty($uuid_daily)) {
            $today = current_time('Y-m-d');
            $totals = $this->get_local_analytics_totals($uuid);
            return [[
                'date' => $today,
                'impressions' => (int)$totals['impressions'],
                'clicks' => (int)$totals['clicks'],
                'views' => (int)$totals['pageviews'],
                'video_views' => (int)$totals['video_views'],
            ]];
        }

        ksort($uuid_daily);
        $series = [];
        foreach ($uuid_daily as $date => $row) {
            if (!is_array($row)) continue;
            $series[] = [
                'date' => (string)$date,
                'impressions' => (int)($row['impressions'] ?? 0),
                'clicks' => (int)($row['clicks'] ?? 0),
                'views' => (int)($row['views'] ?? 0),
                'video_views' => (int)($row['video_views'] ?? 0),
            ];
        }
        return $series;
    }

    private function get_analytics_kpi_tooltips(array $series, array $totals): array
    {
        $today = current_time('Y-m-d');
        $last_7 = gmdate('Y-m-d', strtotime($today . ' -6 days'));
        $last_30 = gmdate('Y-m-d', strtotime($today . ' -29 days'));
        $map = [
            'impressions' => ['label' => 'Impressions', 'series' => 'impressions', 'total' => 'impressions'],
            'clicks' => ['label' => 'Clicks', 'series' => 'clicks', 'total' => 'clicks'],
            'pageviews' => ['label' => 'Pageviews', 'series' => 'views', 'total' => 'pageviews'],
            'video_views' => ['label' => 'Video / audio plays', 'series' => 'video_views', 'total' => 'video_views'],
        ];
        $out = [];
        foreach ($map as $key => $info) {
            $today_total = 0;
            $week_total = 0;
            $month_total = 0;
            foreach ($series as $row) {
                if (!is_array($row)) continue;
                $date = (string)($row['date'] ?? '');
                $value = (int)($row[$info['series']] ?? 0);
                if ($date === $today) $today_total += $value;
                if ($date >= $last_7) $week_total += $value;
                if ($date >= $last_30) $month_total += $value;
            }
            $out[$key] = sprintf(
                "%s\nTotal: %d\nToday: %d\nLast 7 days: %d\nLast 30 days: %d",
                (string)$info['label'],
                (int)($totals[$info['total']] ?? 0),
                $today_total,
                $week_total,
                $month_total
            );
        }
        return $out;
    }

    private function increment_daily_metric(string $metric, string $uuid = ''): void
    {
        $key = $metric === 'views' ? 'views' : $metric;
        if (!in_array($key, ['impressions', 'clicks', 'views', 'video_views'], true)) return;
        if ($uuid === '') {
            $uuid = (string)get_option(self::OPT_UUID, '');
        }
        if ($uuid === '') return;

        $date = current_time('Y-m-d');
        $daily = get_option(self::OPT_ANALYTICS_DAILY, []);
        $daily = is_array($daily) ? $daily : [];
        if (!isset($daily[$uuid]) || !is_array($daily[$uuid])) {
            $daily[$uuid] = [];
        }
        if (!isset($daily[$uuid][$date]) || !is_array($daily[$uuid][$date])) {
            $daily[$uuid][$date] = ['impressions' => 0, 'clicks' => 0, 'views' => 0, 'video_views' => 0];
        }
        $daily[$uuid][$date][$key] = (int)($daily[$uuid][$date][$key] ?? 0) + 1;
        if (count($daily[$uuid]) > 120) {
            ksort($daily[$uuid]);
            $daily[$uuid] = array_slice($daily[$uuid], -120, null, true);
        }
        update_option(self::OPT_ANALYTICS_DAILY, $daily, false);
    }

    public function ajax_test_connection(): void
    {
        $this->assert_admin_ajax();
        $uuid = $this->get_uuid_from_request_or_option();
        if ($uuid === '') {
            wp_send_json_error(['message' => 'Missing Integration UUID.']);
        }
        $bundle = $this->api_get_bundle($uuid);
        if (is_wp_error($bundle)) {
            wp_send_json_error(['message' => $bundle->get_error_message()]);
        }

        $previous_uuid = (string)get_option(self::OPT_UUID, '');
        update_option(self::OPT_UUID, $uuid, false);
        $cache = ($previous_uuid !== $uuid) ? [] : (array)get_option(self::OPT_BUNDLE_CACHE, []);
        $cache['bundle'] = [
            'name' => (string)($bundle['name'] ?? ''),
            'uuid' => $uuid,
            'flags' => [
                'blog' => !empty($bundle['blog']) ? 1 : 0,
                'podcast' => !empty($bundle['podcast']) ? 1 : 0,
                'video' => !empty($bundle['video']) ? 1 : 0,
            ],
        ];
        $bundle_content_type_counts = $this->get_content_type_counts_from_bundle(is_array($bundle) ? $bundle : []);
        if (array_sum($bundle_content_type_counts) > 0) {
            $cache['content_type_counts'] = $bundle_content_type_counts;
        }
        update_option(self::OPT_BUNDLE_CACHE, $cache, false);

        wp_send_json_success(['message' => 'Connection OK.', 'bundle' => $bundle]);
    }

    public function ajax_fetch_approved(): void
    {
        $this->assert_admin_ajax();
        $uuid = $this->get_uuid_from_request_or_option();
        if ($uuid === '') {
            wp_send_json_error(['message' => 'Missing Integration UUID.']);
        }

        $previous_uuid = (string)get_option(self::OPT_UUID, '');
        if ($previous_uuid !== $uuid) {
            update_option(self::OPT_BUNDLE_CACHE, [], false);
        }
        update_option(self::OPT_UUID, $uuid, false);

        $bundle = $this->api_get_bundle($uuid);
        if (is_wp_error($bundle)) {
            wp_send_json_error(['message' => $bundle->get_error_message()]);
        }

        $category_source = '';
        $categories = $this->get_approved_categories_for_bundle($uuid, is_array($bundle) ? $bundle : [], $category_source);
        if (is_wp_error($categories)) {
            wp_send_json_error(['message' => $categories->get_error_message()]);
        }
        $counts = $this->get_counts_for_categories($uuid, $categories);
        if (is_wp_error($counts)) {
            wp_send_json_error(['message' => $counts->get_error_message()]);
        }
        $content_type_counts = $this->get_counts_by_content_type($uuid, $categories, is_array($bundle) ? $bundle : []);
        if (is_wp_error($content_type_counts)) {
            $content_type_counts = ['blog' => 0, 'podcast' => 0, 'video' => 0];
        }

        $cache = [
            'bundle' => [
                'name' => (string)($bundle['name'] ?? ''),
                'uuid' => $uuid,
                'flags' => [
                    'blog' => !empty($bundle['blog']) ? 1 : 0,
                    'podcast' => !empty($bundle['podcast']) ? 1 : 0,
                    'video' => !empty($bundle['video']) ? 1 : 0,
                ],
            ],
            'categories' => $categories,
            'counts' => $counts,
            'content_type_counts' => $content_type_counts,
            'category_source' => $category_source,
            'cache_uuid' => $uuid,
            'fetched_at' => time(),
        ];

        update_option(self::OPT_BUNDLE_CACHE, $cache, false);
        wp_send_json_success(['message' => 'Fetched approved types, topics and post counts.', 'counts' => $counts, 'category_source' => $category_source]);
    }

    public function ajax_fetch_counts(): void
    {
        $this->assert_admin_ajax();
        $uuid = $this->get_uuid_from_request_or_option();
        if ($uuid === '') {
            wp_send_json_error(['message' => 'Missing Integration UUID.']);
        }

        $bundle_cache = (array)get_option(self::OPT_BUNDLE_CACHE, []);
        $categories = is_array($bundle_cache['categories'] ?? null) ? $bundle_cache['categories'] : [];
        if (empty($categories)) {
            wp_send_json_error(['message' => 'Fetch approved topics first.']);
        }

        $counts = $this->get_counts_for_categories($uuid, $categories);
        if (is_wp_error($counts)) {
            wp_send_json_error(['message' => $counts->get_error_message()]);
        }

        $bundle = $this->api_get_bundle($uuid);
        if (!is_wp_error($bundle) && is_array($bundle)) {
            if (!isset($bundle_cache['bundle']) || !is_array($bundle_cache['bundle'])) {
                $bundle_cache['bundle'] = [];
            }
            $bundle_cache['bundle']['name'] = (string)($bundle['name'] ?? ($bundle_cache['bundle']['name'] ?? ''));
            $bundle_cache['bundle']['uuid'] = $uuid;
            $bundle_cache['bundle']['flags'] = [
                'blog' => !empty($bundle['blog']) ? 1 : 0,
                'podcast' => !empty($bundle['podcast']) ? 1 : 0,
                'video' => !empty($bundle['video']) ? 1 : 0,
            ];
        }
        $content_type_counts = $this->get_counts_by_content_type($uuid, $categories, is_wp_error($bundle) ? [] : (array)$bundle);
        if (!is_wp_error($content_type_counts)) {
            $bundle_cache['content_type_counts'] = $content_type_counts;
        }
        $bundle_cache['counts'] = $counts;
        update_option(self::OPT_BUNDLE_CACHE, $bundle_cache, false);

        wp_send_json_success(['message' => 'Counts updated.', 'counts' => $counts, 'content_type_counts' => is_wp_error($content_type_counts) ? null : $content_type_counts]);
    }

    private function get_counts_for_categories(string $uuid, array $categories)
    {
        $counts = [];
        foreach ($categories as $c) {
            $cid = $c['id'] ?? null;
            if (!is_numeric($cid)) {
                continue;
            }
            $cid = (int)$cid;
            if ($cid <= 0) {
                continue;
            }
            $payload = $this->api_get_posts_page($uuid, [$cid], 1, 0, null, false);
            if (is_wp_error($payload)) {
                return $payload;
            }
            $total = isset($payload['pagination']['totalSize']) && is_numeric($payload['pagination']['totalSize']) ? (int)$payload['pagination']['totalSize'] : 0;
            $counts[(string)$cid] = $total;
        }
        return $counts;
    }
    private function get_content_type_counts_from_bundle(array $bundle): array
    {
        return [
            'blog' => isset($bundle['blog']) && is_numeric($bundle['blog']) ? (int)$bundle['blog'] : 0,
            'podcast' => isset($bundle['podcast']) && is_numeric($bundle['podcast']) ? (int)$bundle['podcast'] : 0,
            'video' => isset($bundle['video']) && is_numeric($bundle['video']) ? (int)$bundle['video'] : 0,
        ];
    }

    private function get_content_type_label_from_id(string $content_type_id): string
    {
        if ($content_type_id === '1') {
            return 'Video';
        }
        if ($content_type_id === '2') {
            return 'Podcast';
        }
        if ($content_type_id === '3') {
            return 'Blog';
        }
        return '';
    }

    private function get_local_content_type_counts(string $uuid): array
    {
        $totals = ['blog' => 0, 'podcast' => 0, 'video' => 0];
        if ($uuid === '') {
            return $totals;
        }

        $q = new WP_Query([
            'post_type' => 'any',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                ['key' => '_source_imported', 'value' => '1', 'compare' => '='],
                ['key' => '_source_uuid', 'value' => $uuid, 'compare' => '='],
            ],
        ]);

        foreach ((array)$q->posts as $post_id) {
            $content_type_id = (string)get_post_meta((int)$post_id, '_source_content_type_id', true);
            if ($content_type_id === '1') {
                $totals['video']++;
            } elseif ($content_type_id === '2') {
                $totals['podcast']++;
            } elseif ($content_type_id === '3') {
                $totals['blog']++;
            } elseif ((int)get_post_meta((int)$post_id, '_source_has_youtube', true) === 1) {
                $totals['video']++;
            } else {
                $totals['blog']++;
            }
        }

        wp_reset_postdata();
        return $totals;
    }

    private function maybe_backfill_content_type_tags(string $uuid): void
    {
        if ($uuid === '') {
            return;
        }

        $marker = 'source_dp_content_type_tags_backfilled_' . md5($uuid);
        if ((string)get_option($marker, '') === '1') {
            return;
        }

        $q = new WP_Query([
            'post_type' => 'any',
            'post_status' => 'any',
            'posts_per_page' => 500,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                ['key' => '_source_imported', 'value' => '1', 'compare' => '='],
                ['key' => '_source_uuid', 'value' => $uuid, 'compare' => '='],
            ],
        ]);

        foreach ((array)$q->posts as $post_id) {
            $post_id = (int)$post_id;
            if ($post_id <= 0) {
                continue;
            }

            $content_type_id = (string)get_post_meta($post_id, '_source_content_type_id', true);
            if ($content_type_id === '' && (int)get_post_meta($post_id, '_source_has_youtube', true) === 1) {
                $content_type_id = '1';
            }

            $content_type_tag = $this->get_content_type_label_from_id($content_type_id);
            if ($content_type_tag === '') {
                continue;
            }

            $term_id = $this->ensure_term('post_tag', $content_type_tag);
            if (is_wp_error($term_id) || (int)$term_id <= 0) {
                continue;
            }

            wp_set_post_terms($post_id, [(int)$term_id], 'post_tag', true);
        }

        wp_reset_postdata();
        update_option($marker, '1', false);
    }

    private function get_counts_by_content_type(string $uuid, array $categories = [], array $bundle = [])
    {
        $bundle_counts = $this->get_content_type_counts_from_bundle($bundle);
        if (array_sum($bundle_counts) > 0) {
            return $bundle_counts;
        }

        $map = ['video' => 1, 'podcast' => 2, 'blog' => 3];
        $totals = ['blog' => 0, 'podcast' => 0, 'video' => 0];

        $category_ids = [];
        foreach ($categories as $c) {
            $cid = $c['id'] ?? null;
            if (is_numeric($cid) && (int)$cid > 0) {
                $category_ids[] = (int)$cid;
            }
        }

        foreach ($map as $key => $type_id) {
            if (!empty($category_ids)) {
                foreach ($category_ids as $cid) {
                    $payload = $this->api_get_posts_page($uuid, [$cid], 1, 0, $type_id, false);
                    if (is_wp_error($payload)) {
                        return $payload;
                    }
                    $totals[$key] += isset($payload['pagination']['totalSize']) && is_numeric($payload['pagination']['totalSize']) ? (int)$payload['pagination']['totalSize'] : 0;
                }
                continue;
            }

            $payload = $this->api_get_posts_page($uuid, [], 1, 0, $type_id, false);
            if (is_wp_error($payload)) {
                return $payload;
            }
            $totals[$key] = isset($payload['pagination']['totalSize']) && is_numeric($payload['pagination']['totalSize']) ? (int)$payload['pagination']['totalSize'] : 0;
        }

        if (array_sum($totals) > 0) {
            return $totals;
        }

        return $this->get_local_content_type_counts($uuid);
    }


    private function get_posted_mapping_snapshot(): array
    {
        // The import button can be clicked before the user presses "Save Settings & Mappings".
        // In that case, use the mappings currently visible in the admin form instead of the
        // older saved option so unchecked rows are not imported by accident.
        if (!isset($_POST['current_mappings']) || !is_array($_POST['current_mappings'])) {
            return [];
        }
        return $this->sanitize_mappings($_POST['current_mappings']);
    }

    private function get_posted_config_snapshot(): array
    {
        $out = [];
        if (isset($_POST['current_post_type'])) {
            $out['post_type'] = $this->sanitize_post_type($_POST['current_post_type']);
        }
        if (isset($_POST['current_global_tag'])) {
            $out['global_tag'] = $this->sanitize_text_field_relaxed((string)$_POST['current_global_tag']);
        }
        if (isset($_POST['current_overwrite'])) {
            $out['overwrite'] = !empty($_POST['current_overwrite']);
        }
        return $out;
    }

    public function ajax_sync_step(): void
    {
        $this->assert_admin_ajax();
        if (function_exists('set_time_limit')) {
            @set_time_limit(60);
        }

        $uuid = $this->get_uuid_from_request_or_option();
        if ($uuid === '') {
            wp_send_json_error(['message' => 'Missing Integration UUID.']);
        }

        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        // Keep the importer behaviour aligned with the last working build: one AJAX request
        // imports a small page of Source posts, then skip/overwrite is handled by find_existing_post_id().
        $limit = max(1, min(50, $limit));

        $state = isset($_POST['state']) && is_array($_POST['state']) ? $_POST['state'] : [];
        $category_index = isset($state['categoryIndex']) ? (int)$state['categoryIndex'] : 0;
        $start = isset($state['start']) ? (int)$state['start'] : 0;

        $bundle_cache = (array)get_option(self::OPT_BUNDLE_CACHE, []);
        $categories = is_array($bundle_cache['categories'] ?? null) ? $bundle_cache['categories'] : [];
        if (empty($categories)) {
            wp_send_json_error(['message' => 'Fetch approved topics first.']);
        }

        $posted_mappings = $this->get_posted_mapping_snapshot();
        $mappings = !empty($posted_mappings) ? $posted_mappings : (array)get_option(self::OPT_MAPPINGS, []);
        $posted_config = $this->get_posted_config_snapshot();
        $post_type = (string)($posted_config['post_type'] ?? get_option(self::OPT_POST_TYPE, 'post'));
        $global_tag = (string)($posted_config['global_tag'] ?? get_option(self::OPT_GLOBAL_TAG, ''));
        $overwrite = (bool)($posted_config['overwrite'] ?? get_option(self::OPT_OVERWRITE, false));

        $current_cat = null;
        while ($category_index < count($categories)) {
            $c = $categories[$category_index];
            $cid = (string)($c['id'] ?? '');
            $row = isset($mappings[$cid]) && is_array($mappings[$cid]) ? $mappings[$cid] : [];
            // Missing mapping rows are treated as excluded during import. This avoids importing
            // newly fetched/unmapped Source topics or rows that were never explicitly included.
            $include = array_key_exists('include', $row) ? (bool)$row['include'] : false;
            if ($include && is_numeric($cid)) {
                $current_cat = $c;
                break;
            }
            $category_index++;
            $start = 0;
        }

        if ($current_cat === null) {
            wp_send_json_success([
                'done' => true,
                'message' => 'Sync complete.',
                'state' => ['categoryIndex' => $category_index, 'start' => 0],
                'progress' => ['label' => 'Done', 'pct' => 100],
                'result' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'messages' => []],
            ]);
        }

        $cid_int = (int)$current_cat['id'];
        $payload = $this->api_get_posts_page($uuid, [$cid_int], $limit, $start, null, true);
        if (is_wp_error($payload)) {
            wp_send_json_error(['message' => $payload->get_error_message()]);
        }

        $total_size = isset($payload['pagination']['totalSize']) && is_numeric($payload['pagination']['totalSize']) ? (int)$payload['pagination']['totalSize'] : 0;
        $next_index = $payload['pagination']['nextIndex'] ?? null;
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];

        foreach ($data as &$p) {
            if (is_array($p)) {
                $p['_source_category_id'] = (string)$cid_int;
            }
        }
        unset($p);

        $import = $this->import_posts([
            'uuid' => $uuid,
            'post_type' => $post_type,
            'global_tag' => $global_tag,
            'mappings' => $mappings,
            'bundle_categories' => $categories,
            'source_posts' => $data,
            'overwrite' => $overwrite,
        ]);

        if (is_wp_error($import)) {
            wp_send_json_error(['message' => $import->get_error_message()]);
        }

        $processed = min($start + count($data), $total_size);
        $pct = $total_size > 0 ? (int)round(($processed / $total_size) * 100) : 0;

        $label = sprintf(
            'Category %s: %d/%d',
            (string)($current_cat['name'] ?? $cid_int),
            $processed,
            $total_size
        );

        $done_category = ($next_index === null || !is_numeric($next_index) || (int)$next_index === $start);

        if ($done_category) {
            $category_index++;
            $start = 0;
        } else {
            $start = (int)$next_index;
        }

        wp_send_json_success([
            'done' => false,
            'message' => 'Step complete.',
            'state' => [
                'categoryIndex' => $category_index,
                'start' => $start,
                'limit' => $limit,
            ],
            'progress' => [
                'label' => $label,
                'pct' => max(0, min(100, $pct)),
            ],
            'result' => $import,
        ]);
    }

    public function ajax_start_background_import(): void
    {
        $this->assert_admin_ajax();

        $uuid = $this->get_uuid_from_request_or_option();
        if ($uuid === '') {
            wp_send_json_error(['message' => 'Missing Integration UUID.']);
        }

        $existing_job = (array)get_option(self::OPT_IMPORT_JOB, []);
        if (!empty($existing_job['id']) && in_array((string)($existing_job['status'] ?? ''), ['queued', 'running'], true)) {
            $existing_job_id = (string)$existing_job['id'];
            $this->clear_pending_import_actions($existing_job_id);
            delete_transient('source_dp_import_lock_' . md5($existing_job_id));
            $this->add_import_log('warning', 'Previous active import was replaced by a new manual import', ['old_job_id' => $existing_job_id]);
        }

        $bundle_cache = (array)get_option(self::OPT_BUNDLE_CACHE, []);
        $categories = is_array($bundle_cache['categories'] ?? null) ? $bundle_cache['categories'] : [];
        if (empty($categories)) {
            wp_send_json_error(['message' => 'Fetch approved topics first.']);
        }

        if (isset($_POST['mappings']) && is_array($_POST['mappings'])) {
            $posted_mappings = $this->sanitize_mappings($_POST['mappings']);
            update_option(self::OPT_MAPPINGS, $posted_mappings, false);
        }

        $mappings = (array)get_option(self::OPT_MAPPINGS, []);
        $selected_categories = [];
        $counts = is_array($bundle_cache['counts'] ?? null) ? $bundle_cache['counts'] : [];
        $total_expected = 0;

        foreach ($categories as $cat) {
            if (!is_array($cat) || !isset($cat['id'])) {
                continue;
            }
            $cid = (string)$cat['id'];
            if (!is_numeric($cid)) {
                continue;
            }
            $row = isset($mappings[$cid]) && is_array($mappings[$cid]) ? $mappings[$cid] : [];
            $include = array_key_exists('include', $row) ? (bool)$row['include'] : false;
            if (!$include) {
                continue;
            }
            $count = isset($counts[$cid]) && is_numeric($counts[$cid]) ? (int)$counts[$cid] : 0;
            $selected_categories[] = [
                'id' => (int)$cid,
                'name' => (string)($cat['name'] ?? $cid),
                'count' => $count,
            ];
            $total_expected += max(0, $count);
        }

        if (empty($selected_categories)) {
            wp_send_json_error(['message' => 'No selected Source categories. Tick at least one category, then start import.']);
        }

        $job_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('source_dp_', true);
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;
        // Images are now deferred to the media queue, so post/text imports can safely run in larger batches.
        $limit = max(1, min(25, $limit));

        $current_post_type = isset($_POST['current_post_type']) ? $this->sanitize_post_type((string)$_POST['current_post_type']) : (string)get_option(self::OPT_POST_TYPE, 'post');
        if (!post_type_exists($current_post_type)) {
            $current_post_type = 'post';
        }
        $current_global_tag = isset($_POST['current_global_tag']) ? $this->sanitize_text_field_relaxed((string)$_POST['current_global_tag']) : (string)get_option(self::OPT_GLOBAL_TAG, '');
        $current_overwrite = isset($_POST['current_overwrite']) ? !empty($_POST['current_overwrite']) : (bool)get_option(self::OPT_OVERWRITE, false);

        $job = [
            'id' => $job_id,
            'status' => 'queued',
            'message' => 'Import queued.',
            'uuid' => $uuid,
            'post_type' => $current_post_type,
            'global_tag' => $current_global_tag,
            'overwrite' => $current_overwrite,
            'categories' => $selected_categories,
            'category_index' => 0,
            'start' => 0,
            'limit' => $limit,
            'total_expected' => $total_expected,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'media_skipped' => 0,
            'media_deferred' => 0,
            'category_linked' => 0,
            'content_reused' => 0,
            'errors' => 0,
            'failed_pages' => [],
            'messages' => [],
            'seen_source_ids' => [],
            'seen_content_keys' => [],
            'already_seen' => 0,
            'debug_log' => [],
            'current_step' => 'Queued',
            'page_retries' => 0,
            'last_error' => '',
            'progress_label' => 'Queued',
            'progress_pct' => 0,
            'started_at' => time(),
            'updated_at' => time(),
        ];

        update_option(self::OPT_IMPORT_LOG, [], false);
        update_option(self::OPT_IMPORT_JOB, $job, false);
        $this->add_import_log('info', 'Background import queued', [
            'job_id' => $job_id,
            'categories' => count($selected_categories),
            'total_expected' => $total_expected,
            'batch_limit' => $limit,
            'overwrite' => !empty($job['overwrite']) ? 'yes' : 'no',
        ]);
        $this->enqueue_background_import_batch($job_id, 2);

        wp_send_json_success([
            'message' => 'Background import queued.',
            'job' => $this->format_import_job_for_response($job),
        ]);
    }

    public function cron_fetch_new_posts(): void
    {
        if ($this->get_auto_import_interval() === 'manual' && !defined('SOURCE_DP_MANUAL_CRON_RUN')) {
            return;
        }
        $lock_key = 'source_dp_auto_import_lock';
        if (get_transient($lock_key)) {
            return;
        }
        set_transient($lock_key, 1, 300);

        try {
            $current_job = (array)get_option(self::OPT_IMPORT_JOB, []);
            if (!empty($current_job['id']) && in_array((string)($current_job['status'] ?? ''), ['queued', 'running'], true)) {
                delete_transient($lock_key);
                return;
            }

            $uuid = (string)get_option(self::OPT_UUID, '');
            $uuid = $this->sanitize_uuid($uuid);
            if ($uuid === '') {
                delete_transient($lock_key);
                return;
            }

            $bundle_cache = (array)get_option(self::OPT_BUNDLE_CACHE, []);
            $bundle = $this->api_get_bundle($uuid);
            if (!is_wp_error($bundle) && is_array($bundle)) {
                $category_source = '';
                $cron_categories = $this->get_approved_categories_for_bundle($uuid, $bundle, $category_source);
                if (!is_wp_error($cron_categories) && is_array($cron_categories) && !empty($cron_categories)) {
                    $bundle_cache['categories'] = $cron_categories;
                    $bundle_cache['category_source'] = $category_source;
                }
                $bundle_cache['bundle'] = [
                    'name' => (string)($bundle['name'] ?? ''),
                    'uuid' => $uuid,
                    'flags' => [
                        'blog' => !empty($bundle['blog']) ? 1 : 0,
                        'podcast' => !empty($bundle['podcast']) ? 1 : 0,
                        'video' => !empty($bundle['video']) ? 1 : 0,
                    ],
                ];
                $bundle_counts = $this->get_content_type_counts_from_bundle($bundle);
                if (array_sum($bundle_counts) > 0) {
                    $bundle_cache['content_type_counts'] = $bundle_counts;
                }
            }

            $categories = is_array($bundle_cache['categories'] ?? null) ? $bundle_cache['categories'] : [];
            if (empty($categories)) {
                $this->add_import_log('warning', 'Auto import skipped: no Source topics available. Fetch approved topics first.', []);
                update_option(self::OPT_BUNDLE_CACHE, $bundle_cache, false);
                delete_transient($lock_key);
                return;
            }

            $counts = $this->get_counts_for_categories($uuid, $categories);
            if (is_wp_error($counts)) {
                $this->add_import_log('warning', 'Auto import skipped: could not refresh Source counts', ['error' => $counts->get_error_message()]);
                delete_transient($lock_key);
                return;
            }
            $bundle_cache['counts'] = $counts;
            $bundle_cache['fetched_at'] = time();
            update_option(self::OPT_BUNDLE_CACHE, $bundle_cache, false);

            $mappings = (array)get_option(self::OPT_MAPPINGS, []);
            $cron_categories = $this->sanitize_auto_import_categories(get_option(self::OPT_AUTO_IMPORT_CATEGORIES, []));
            $selected_categories = [];
            $total_expected = 0;
            foreach ($categories as $cat) {
                if (!is_array($cat) || !isset($cat['id'])) {
                    continue;
                }
                $cid = (string)$cat['id'];
                if (!is_numeric($cid)) {
                    continue;
                }
                if (!empty($cron_categories) && !in_array($cid, $cron_categories, true)) {
                    continue;
                }
                $row = isset($mappings[$cid]) && is_array($mappings[$cid]) ? $mappings[$cid] : [];
                $include = array_key_exists('include', $row) ? (bool)$row['include'] : false;
                if (!$include) {
                    continue;
                }
                $source_count = isset($counts[$cid]) && is_numeric($counts[$cid]) ? (int)$counts[$cid] : 0;
                if ($source_count <= 0) {
                    continue;
                }
                $imported_count = $this->get_imported_post_count_for_source_category($uuid, $cid);
                if ($imported_count >= $source_count) {
                    continue;
                }
                $selected_categories[] = [
                    'id' => (int)$cid,
                    'name' => (string)($cat['name'] ?? $cid),
                    'count' => $source_count,
                    'imported_before' => $imported_count,
                ];
                $total_expected += $source_count;
            }

            if (empty($selected_categories)) {
                $this->add_import_log('debug', 'Auto import checked Source counts: no missing selected posts found', ['uuid' => $uuid]);
                delete_transient($lock_key);
                return;
            }

            $job_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('source_dp_', true);
            $post_type = (string)get_option(self::OPT_POST_TYPE, 'post');
            if (!post_type_exists($post_type)) {
                $post_type = 'post';
            }

            $job = [
                'id' => $job_id,
                'status' => 'queued',
                'message' => 'Auto import queued for new Source posts.',
                'uuid' => $uuid,
                'post_type' => $post_type,
                'global_tag' => (string)get_option(self::OPT_GLOBAL_TAG, ''),
                'overwrite' => false,
                'categories' => $selected_categories,
                'category_index' => 0,
                'start' => 0,
                'limit' => 20,
                'total_expected' => $total_expected,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'media_skipped' => 0,
                'media_deferred' => 0,
                'category_linked' => 0,
                'content_reused' => 0,
                'errors' => 0,
                'failed_pages' => [],
                'messages' => [],
                'seen_source_ids' => [],
                'seen_content_keys' => [],
                'already_seen' => 0,
                'debug_log' => [],
                'current_step' => 'Queued by cron',
                'page_retries' => 0,
                'last_error' => '',
                'progress_label' => 'Queued by cron',
                'progress_pct' => 0,
                'started_at' => time(),
                'updated_at' => time(),
                'source' => 'cron',
            ];

            update_option(self::OPT_IMPORT_LOG, [], false);
            update_option(self::OPT_IMPORT_JOB, $job, false);
            $this->add_import_log('info', 'Auto import queued for new Source posts', [
                'job_id' => $job_id,
                'categories' => count($selected_categories),
                'total_expected' => $total_expected,
            ]);
            $this->enqueue_background_import_batch($job_id, 1);
        } catch (Throwable $e) {
            $this->add_import_log('error', 'Auto import cron failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        delete_transient($lock_key);
    }

    public function ajax_run_auto_import_now(): void
    {
        $this->assert_admin_ajax();

        if (isset($_POST['cron_interval'])) {
            update_option(self::OPT_AUTO_IMPORT_INTERVAL, $this->sanitize_auto_import_interval((string)$_POST['cron_interval']), false);
            $this->ensure_auto_import_schedule(true);
        }
        if (isset($_POST['cron_categories']) && is_array($_POST['cron_categories'])) {
            update_option(self::OPT_AUTO_IMPORT_CATEGORIES, $this->sanitize_auto_import_categories($_POST['cron_categories']), false);
        }

        $current_job = (array)get_option(self::OPT_IMPORT_JOB, []);
        if (!empty($current_job['id']) && in_array((string)($current_job['status'] ?? ''), ['queued', 'running'], true)) {
            wp_send_json_success([
                'message' => 'An import is already running. Cron was not started again.',
                'job' => $this->format_import_job_for_response($current_job),
            ]);
        }

        if (!defined('SOURCE_DP_MANUAL_CRON_RUN')) {
            define('SOURCE_DP_MANUAL_CRON_RUN', true);
        }

        $before_id = (string)($current_job['id'] ?? '');
        $this->cron_fetch_new_posts();
        $after_job = (array)get_option(self::OPT_IMPORT_JOB, []);
        $after_id = (string)($after_job['id'] ?? '');

        if ($after_id !== '' && $after_id !== $before_id && in_array((string)($after_job['status'] ?? ''), ['queued', 'running'], true)) {
            wp_send_json_success([
                'message' => 'Cron check queued an import for missing/new Source posts.',
                'job' => $this->format_import_job_for_response($after_job),
            ]);
        }

        wp_send_json_success([
            'message' => 'Cron check complete. No missing/new posts were queued for the selected cron categories.',
            'job' => $after_id !== '' ? $this->format_import_job_for_response($after_job) : null,
        ]);
    }

    public function ajax_import_status(): void
    {
        $this->assert_admin_ajax();
        $job = (array)get_option(self::OPT_IMPORT_JOB, []);
        if (empty($job) || empty($job['id'])) {
            wp_send_json_success(['job' => null, 'message' => 'No import job found.']);
        }
        $this->maybe_run_import_status_fallback($job);
        $job = (array)get_option(self::OPT_IMPORT_JOB, []);
        $this->maybe_kick_stalled_import($job);
        $job = (array)get_option(self::OPT_IMPORT_JOB, []);
        wp_send_json_success(['job' => $this->format_import_job_for_response($job)]);
    }

    public function ajax_get_import_log(): void
    {
        $this->assert_admin_ajax();
        wp_send_json_success(['log' => $this->get_import_log(), 'text' => $this->format_import_log_text($this->get_import_log(), 200)]);
    }

    public function ajax_clear_import_log(): void
    {
        $this->assert_admin_ajax();
        update_option(self::OPT_IMPORT_LOG, [], false);
        wp_send_json_success(['message' => 'Import log cleared.', 'log' => []]);
    }

    public function ajax_cancel_import(): void
    {
        $this->assert_admin_ajax();
        $job = (array)get_option(self::OPT_IMPORT_JOB, []);
        if (empty($job) || empty($job['id'])) {
            wp_send_json_success(['message' => 'No import job found.']);
        }

        $job_id = (string)$job['id'];
        $job['status'] = 'cancelled';
        $job['message'] = 'Import cancelled by admin.';
        $job['current_step'] = 'Cancelled';
        $job['last_error'] = '';
        $job['updated_at'] = time();
        update_option(self::OPT_IMPORT_JOB, $job, false);

        $this->clear_pending_import_actions($job_id);
        delete_transient('source_dp_import_lock_' . md5($job_id));
        $this->add_import_log('warning', 'Import cancelled by admin', [
            'job_id' => $job_id,
            'processed' => (int)($job['processed'] ?? 0),
            'created' => (int)($job['created'] ?? 0),
            'updated' => (int)($job['updated'] ?? 0),
            'skipped' => (int)($job['skipped'] ?? 0),
            'media_skipped' => (int)($job['media_skipped'] ?? 0),
            'media_deferred' => (int)($job['media_deferred'] ?? 0),
            'category_linked' => (int)($job['category_linked'] ?? 0),
            'content_reused' => (int)($job['content_reused'] ?? 0),
            'errors' => (int)($job['errors'] ?? 0),
        ]);

        wp_send_json_success(['message' => 'Import cancelled. Any action already running may finish its current post/batch, but no new batches will be queued.', 'job' => $this->format_import_job_for_response($job)]);
    }

    public function run_background_import_batch($job_id): void
    {
        $job_id = is_array($job_id) ? (string)($job_id['job_id'] ?? '') : (string)$job_id;
        if ($job_id === '') {
            return;
        }

        $plugin = $this;
        register_shutdown_function(static function () use ($plugin, $job_id): void {
            $error = error_get_last();
            if (!is_array($error)) {
                return;
            }
            $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!in_array((int)($error['type'] ?? 0), $fatal_types, true)) {
                return;
            }
            delete_transient('source_dp_import_lock_' . md5($job_id));
            $current = (array)get_option(self::OPT_IMPORT_JOB, []);
            if (!empty($current) && (string)($current['id'] ?? '') === $job_id && !in_array((string)($current['status'] ?? ''), ['complete', 'cancelled'], true)) {
                $current['message'] = 'Import recovered from a PHP fatal/shutdown. The watchdog will continue it.';
                $current['last_error'] = (string)($error['message'] ?? 'PHP fatal/shutdown');
                $current['updated_at'] = time();
                update_option(self::OPT_IMPORT_JOB, $current, false);
            }
            $plugin->add_import_log('fatal', 'PHP fatal/shutdown during background import action', [
                'job_id' => $job_id,
                'message' => (string)($error['message'] ?? ''),
                'file' => (string)($error['file'] ?? ''),
                'line' => (int)($error['line'] ?? 0),
            ]);
        });

        $lock_key = 'source_dp_import_lock_' . md5($job_id);
        if (get_transient($lock_key)) {
            $this->add_import_log('warning', 'Batch action skipped because import lock is still active', ['job_id' => $job_id]);
            return;
        }
        set_transient($lock_key, 1, self::IMPORT_LOCK_TTL);

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(60);
            }

            $job = (array)get_option(self::OPT_IMPORT_JOB, []);
            if (empty($job) || (string)($job['id'] ?? '') !== $job_id) {
                $this->add_import_log('warning', 'Batch action stopped: job not found or job ID mismatch', ['job_id' => $job_id]);
                delete_transient($lock_key);
                return;
            }

            $status = (string)($job['status'] ?? '');
            if (in_array($status, ['complete', 'cancelled'], true)) {
                delete_transient($lock_key);
                return;
            }

            $categories = is_array($job['categories'] ?? null) ? $job['categories'] : [];
            $category_index = max(0, (int)($job['category_index'] ?? 0));
            $limit = max(1, min(25, (int)($job['limit'] ?? 20)));
            $start = max(0, (int)($job['start'] ?? 0));

            if ($category_index >= count($categories)) {
                $job['status'] = 'complete';
                $job['message'] = 'Import complete.';
                $job['progress_label'] = 'Done';
                $job['progress_pct'] = 100;
                $job['updated_at'] = time();
                update_option(self::OPT_IMPORT_JOB, $job, false);
                $this->add_import_log('info', 'Background import complete', [
                    'created' => (int)($job['created'] ?? 0),
                    'updated' => (int)($job['updated'] ?? 0),
                    'skipped' => (int)($job['skipped'] ?? 0),
                    'media_skipped' => (int)($job['media_skipped'] ?? 0),
                    'media_deferred' => (int)($job['media_deferred'] ?? 0),
                    'category_linked' => (int)($job['category_linked'] ?? 0),
                    'content_reused' => (int)($job['content_reused'] ?? 0),
                    'errors' => (int)($job['errors'] ?? 0),
                    'processed' => (int)($job['processed'] ?? 0),
                ]);
                delete_transient($lock_key);
                return;
            }

            $current_cat = is_array($categories[$category_index] ?? null) ? $categories[$category_index] : [];
            $cid = isset($current_cat['id']) ? (int)$current_cat['id'] : 0;
            if ($cid <= 0) {
                $job['category_index'] = $category_index + 1;
                $job['start'] = 0;
                $job['updated_at'] = time();
                update_option(self::OPT_IMPORT_JOB, $job, false);
                delete_transient($lock_key);
                $this->enqueue_background_import_batch($job_id, 2);
                return;
            }

            $uuid = (string)($job['uuid'] ?? '');
            $cat_name_for_log = (string)($current_cat['name'] ?? $cid);
            $job['status'] = 'running';
            $job['current_step'] = 'Fetching Source posts';
            $job['message'] = 'Import running in background.';
            $job['updated_at'] = time();
            $this->update_background_job_progress($job, $current_cat, (int)($current_cat['count'] ?? 0));
            update_option(self::OPT_IMPORT_JOB, $job, false);
            $this->add_import_log('info', 'Batch started: fetching Source posts', [
                'job_id' => $job_id,
                'category' => $cat_name_for_log,
                'category_id' => $cid,
                'start' => $start,
                'limit' => $limit,
                'processed' => (int)($job['processed'] ?? 0),
                'memory' => $this->format_bytes(memory_get_usage(true)),
            ]);

            $api_started = microtime(true);
            $payload = $this->api_get_posts_page($uuid, [$cid], $limit, $start, null, true);
            $api_ms = (int)round((microtime(true) - $api_started) * 1000);

            if (is_wp_error($payload)) {
                $retry = (int)($job['page_retries'] ?? 0) + 1;
                $cat_name = (string)($current_cat['name'] ?? $cid);
                $error_message = $payload->get_error_message();
                $this->add_import_log($retry <= 3 ? 'warning' : 'error', 'Source posts page request failed', [
                    'job_id' => $job_id,
                    'category' => (string)($current_cat['name'] ?? $cid),
                    'category_id' => $cid,
                    'start' => $start,
                    'limit' => $limit,
                    'attempt' => $retry,
                    'duration_ms' => $api_ms ?? 0,
                    'error' => $error_message,
                ]);
                $job['page_retries'] = $retry;
                $job['last_error'] = $error_message;
                $job['status'] = 'running';
                $job['message'] = 'Temporary Source/API timeout. Retrying in background (' . $retry . '/3).';
                $job['progress_label'] = 'Category ' . $cat_name . ': retrying from ' . $start;
                $job['updated_at'] = time();

                if ($retry <= 3) {
                    update_option(self::OPT_IMPORT_JOB, $job, false);
                    delete_transient($lock_key);
                    $this->enqueue_background_import_batch($job_id, 15 * $retry);
                    return;
                }

                $job['errors'] = (int)($job['errors'] ?? 0) + 1;
                $job['failed_pages'][] = [
                    'category_id' => $cid,
                    'category_name' => $cat_name,
                    'start' => $start,
                    'limit' => $limit,
                    'message' => $error_message,
                    'time' => time(),
                ];
                $messages = is_array($job['messages'] ?? null) ? $job['messages'] : [];
                $messages[] = 'Skipped Source page after 3 retries: ' . $cat_name . ' start ' . $start . ' — ' . $error_message;
                $job['messages'] = array_slice($messages, -50);
                $job['page_retries'] = 0;

                $cat_count = isset($current_cat['count']) ? (int)$current_cat['count'] : 0;
                $next_start = $start + $limit;
                $job['processed'] = (int)($job['processed'] ?? 0) + $limit;
                if ($cat_count <= 0 || $next_start >= $cat_count) {
                    $job['category_index'] = $category_index + 1;
                    $job['start'] = 0;
                } else {
                    $job['start'] = $next_start;
                }
                $job['updated_at'] = time();
                $this->update_background_job_progress($job, $current_cat, $cat_count);
                update_option(self::OPT_IMPORT_JOB, $job, false);
                delete_transient($lock_key);
                $this->enqueue_background_import_batch($job_id, 2);
                return;
            }

            if ($this->is_import_job_cancelled($job_id)) {
                $this->add_import_log('warning', 'Batch stopped after Source fetch because job was cancelled', ['job_id' => $job_id]);
                delete_transient($lock_key);
                return;
            }

            $total_size = isset($payload['pagination']['totalSize']) && is_numeric($payload['pagination']['totalSize']) ? (int)$payload['pagination']['totalSize'] : (int)($current_cat['count'] ?? 0);
            if ($total_size > 0 && empty($current_cat['count'])) {
                $categories[$category_index]['count'] = $total_size;
                $job['categories'] = $categories;
            }

            $next_index = $payload['pagination']['nextIndex'] ?? null;
            $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
            $raw_data_count = count($data);
            $this->add_import_log('info', 'Source posts page fetched', [
                'job_id' => $job_id,
                'category' => (string)($current_cat['name'] ?? $cid),
                'category_id' => $cid,
                'start' => $start,
                'limit' => $limit,
                'returned' => count($data),
                'next_index' => $next_index,
                'total_size' => $total_size,
                'duration_ms' => $api_ms ?? 0,
            ]);

            $bundle_cache = (array)get_option(self::OPT_BUNDLE_CACHE, []);
            $bundle_categories = is_array($bundle_cache['categories'] ?? null) ? $bundle_cache['categories'] : [];
            $mappings_for_links = (array)get_option(self::OPT_MAPPINGS, []);
            $source_category_name_by_id = [];
            foreach ($bundle_categories as $cat_for_name) {
                if (is_array($cat_for_name) && isset($cat_for_name['id'])) {
                    $source_category_name_by_id[(string)$cat_for_name['id']] = (string)($cat_for_name['name'] ?? $cat_for_name['id']);
                }
            }

            // A Source post can belong to multiple Source categories. The numeric bundle-post `id`
            // identifies a category/member row, while `postId` identifies the actual Source content item.
            // Track content keys across the job so a second category appearance links categories only instead
            // of being counted as a WordPress post update.
            $seen_records = is_array($job['seen_source_ids'] ?? null) ? $job['seen_source_ids'] : [];
            $seen_content = is_array($job['seen_content_keys'] ?? null) ? $job['seen_content_keys'] : [];
            $already_seen_in_batch = 0;
            foreach ($data as &$candidate) {
                if (!is_array($candidate)) {
                    continue;
                }

                $import_key = $this->get_source_import_key($candidate);
                if ($import_key !== '') {
                    $record_key = md5($import_key);
                    if (!isset($seen_records[$record_key])) {
                        $seen_records[$record_key] = 1;
                    }
                }

                $content_key = $this->get_source_content_key($candidate);
                $candidate['_source_category_id'] = (string)$cid;
                $candidate['_source_content_key'] = $content_key;
                $candidate['_source_content_seen_in_run'] = 0;
                if ($content_key === '') {
                    continue;
                }

                $seen_key = md5($content_key);
                if (isset($seen_content[$seen_key])) {
                    $already_seen_in_batch++;
                    $candidate['_source_content_seen_in_run'] = 1;
                    continue;
                }
                $seen_content[$seen_key] = 1;
            }
            unset($candidate);

            if ($already_seen_in_batch > 0) {
                $job['already_seen'] = (int)($job['already_seen'] ?? 0) + $already_seen_in_batch;
                $this->add_import_log('info', 'Source content already appeared in another selected category; only category membership will be linked', [
                    'job_id' => $job_id,
                    'category' => (string)($current_cat['name'] ?? $cid),
                    'category_overlap_rows' => $already_seen_in_batch,
                ]);
            }
            $job['seen_source_ids'] = $seen_records;
            $job['seen_content_keys'] = $seen_content;

            $job['current_step'] = 'Importing WordPress post(s)';
            $job['updated_at'] = time();
            update_option(self::OPT_IMPORT_JOB, $job, false);

            $import = $this->import_posts([
                'job_id' => $job_id,
                'log_category' => (string)($current_cat['name'] ?? $cid),
                'uuid' => $uuid,
                'post_type' => (string)($job['post_type'] ?? get_option(self::OPT_POST_TYPE, 'post')),
                'global_tag' => (string)($job['global_tag'] ?? get_option(self::OPT_GLOBAL_TAG, '')),
                'mappings' => (array)get_option(self::OPT_MAPPINGS, []),
                'bundle_categories' => $bundle_categories,
                'source_posts' => $data,
                'overwrite' => (bool)($job['overwrite'] ?? false),
            ]);

            if ($this->is_import_job_cancelled($job_id)) {
                $this->add_import_log('warning', 'Batch stopped after current post import because job was cancelled', ['job_id' => $job_id]);
                delete_transient($lock_key);
                return;
            }

            if (is_wp_error($import)) {
                $job['errors'] = (int)($job['errors'] ?? 0) + 1;
                $messages = is_array($job['messages'] ?? null) ? $job['messages'] : [];
                $messages[] = 'Import batch failed: ' . $import->get_error_message();
                $job['messages'] = array_slice($messages, -50);
                $this->add_import_log('error', 'WordPress import batch failed', ['job_id' => $job_id, 'error' => $import->get_error_message()]);
            } else {
                $this->add_import_log('info', 'WordPress import batch finished', [
                    'job_id' => $job_id,
                    'created' => (int)($import['created'] ?? 0),
                    'updated' => (int)($import['updated'] ?? 0),
                    'skipped' => (int)($import['skipped'] ?? 0),
                    'media_skipped' => (int)($import['media_skipped'] ?? 0),
                    'media_deferred' => (int)($import['media_deferred'] ?? 0),
                    'category_linked' => (int)($import['category_linked'] ?? 0),
                    'content_reused' => (int)($import['content_reused'] ?? 0),
                    'errors' => (int)($import['errors'] ?? 0),
                ]);
                $job['created'] = (int)($job['created'] ?? 0) + (int)($import['created'] ?? 0);
                $job['updated'] = (int)($job['updated'] ?? 0) + (int)($import['updated'] ?? 0);
                $job['skipped'] = (int)($job['skipped'] ?? 0) + (int)($import['skipped'] ?? 0);
                $job['media_skipped'] = (int)($job['media_skipped'] ?? 0) + (int)($import['media_skipped'] ?? 0);
                $job['media_deferred'] = (int)($job['media_deferred'] ?? 0) + (int)($import['media_deferred'] ?? 0);
                $job['category_linked'] = (int)($job['category_linked'] ?? 0) + (int)($import['category_linked'] ?? 0);
                $job['content_reused'] = (int)($job['content_reused'] ?? 0) + (int)($import['content_reused'] ?? 0);
                $job['errors'] = (int)($job['errors'] ?? 0) + (int)($import['errors'] ?? 0);
                if (!empty($import['messages']) && is_array($import['messages'])) {
                    $messages = is_array($job['messages'] ?? null) ? $job['messages'] : [];
                    $job['messages'] = array_slice(array_merge($messages, $import['messages']), -50);
                }
            }

            $processed_in_category = min($start + $raw_data_count, $total_size);
            $job['processed'] = (int)($job['processed'] ?? 0) + $raw_data_count;
            $done_category = $raw_data_count === 0 || $next_index === null || !is_numeric($next_index) || (int)$next_index === $start || ($total_size > 0 && $processed_in_category >= $total_size);

            if ($done_category) {
                $job['category_index'] = $category_index + 1;
                $job['start'] = 0;
            } else {
                $job['category_index'] = $category_index;
                $job['start'] = (int)$next_index;
            }

            if ($this->is_import_job_cancelled($job_id)) {
                $this->add_import_log('warning', 'Next batch not queued because job was cancelled', ['job_id' => $job_id]);
                delete_transient($lock_key);
                return;
            }

            $job['page_retries'] = 0;
            $job['status'] = 'running';
            $job['message'] = 'Import running in background.';
            $job['updated_at'] = time();
            $this->update_background_job_progress($job, $current_cat, $total_size);
            update_option(self::OPT_IMPORT_JOB, $job, false);
            $this->add_import_log('debug', 'Batch saved and next action queued', [
                'job_id' => $job_id,
                'next_category_index' => (int)($job['category_index'] ?? 0),
                'next_start' => (int)($job['start'] ?? 0),
                'processed' => (int)($job['processed'] ?? 0),
            ]);

            delete_transient($lock_key);
            $this->enqueue_background_import_batch($job_id, 2);
        } catch (Throwable $e) {
            $job = (array)get_option(self::OPT_IMPORT_JOB, []);
            if (!empty($job) && (string)($job['id'] ?? '') === $job_id) {
                $job['errors'] = (int)($job['errors'] ?? 0) + 1;
                $job['last_error'] = $e->getMessage();
                $job['message'] = 'Background import recovered from an error and will continue.';
                $this->add_import_log('error', 'Throwable caught in background import batch', [
                    'job_id' => $job_id,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $job['updated_at'] = time();
                update_option(self::OPT_IMPORT_JOB, $job, false);
                $this->enqueue_background_import_batch($job_id, 2);
            }
            delete_transient($lock_key);
        }
    }

    private function is_import_job_cancelled(string $job_id): bool
    {
        $current = (array)get_option(self::OPT_IMPORT_JOB, []);
        return !empty($current)
            && (string)($current['id'] ?? '') === $job_id
            && (string)($current['status'] ?? '') === 'cancelled';
    }

    private function clear_pending_import_actions(string $job_id): void
    {
        if ($job_id === '') {
            return;
        }

        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::AS_HOOK_IMPORT_BATCH, ['job_id' => $job_id], self::IMPORT_GROUP);
        }

        wp_clear_scheduled_hook(self::CRON_HOOK_IMPORT_BATCH, [$job_id]);
    }

    private function enqueue_background_import_batch(string $job_id, int $delay = 10): void
    {
        $delay = max(1, $delay);

        if ($this->is_import_job_cancelled($job_id)) {
            return;
        }

        if (function_exists('as_has_scheduled_action') && as_has_scheduled_action(self::AS_HOOK_IMPORT_BATCH, ['job_id' => $job_id], self::IMPORT_GROUP)) {
            return;
        }

        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(time() + $delay, self::AS_HOOK_IMPORT_BATCH, ['job_id' => $job_id], self::IMPORT_GROUP);
            return;
        }

        if (!wp_next_scheduled(self::CRON_HOOK_IMPORT_BATCH, [$job_id])) {
            wp_schedule_single_event(time() + $delay, self::CRON_HOOK_IMPORT_BATCH, [$job_id]);
        }
    }

    public function ajax_kick_import(): void
    {
        $this->assert_admin_ajax();
        $job = (array)get_option(self::OPT_IMPORT_JOB, []);
        if (empty($job) || empty($job['id'])) {
            wp_send_json_error(['message' => 'No import job found.']);
        }
        if (in_array((string)($job['status'] ?? ''), ['complete', 'cancelled'], true)) {
            wp_send_json_success(['message' => 'Import is not running.', 'job' => $this->format_import_job_for_response($job)]);
        }
        $active_lock_key = 'source_dp_import_lock_' . md5((string)$job['id']);
        if (get_transient($active_lock_key)) {
            $job['message'] = 'Import is already processing a batch. No duplicate kick was queued.';
            $job['updated_at'] = time();
            update_option(self::OPT_IMPORT_JOB, $job, false);
            $this->add_import_log('debug', 'Manual kick ignored because a batch lock is active', ['job_id' => (string)$job['id']]);
            wp_send_json_success(['message' => 'Import is already processing a batch.', 'job' => $this->format_import_job_for_response($job)]);
        }

        $job['status'] = 'running';
        $job['message'] = 'Import kicked/continued.';
        $job['last_error'] = '';
        $job['updated_at'] = time();
        update_option(self::OPT_IMPORT_JOB, $job, false);
        $this->add_import_log('info', 'Manual kick requested from admin', ['job_id' => (string)$job['id']]);
        $this->enqueue_background_import_batch((string)$job['id'], 1);
        wp_send_json_success(['message' => 'Import kicked.', 'job' => $this->format_import_job_for_response($job)]);
    }

    private function maybe_run_import_status_fallback(array $job): void
    {
        if (empty($job['id'])) {
            return;
        }
        $status = (string)($job['status'] ?? '');
        if (in_array($status, ['complete', 'cancelled'], true)) {
            return;
        }
        $job_id = (string)$job['id'];
        $lock_key = 'source_dp_import_lock_' . md5($job_id);
        if (get_transient($lock_key)) {
            return;
        }

        // The admin status poller is only a safety net. It must not race the real
        // Action Scheduler/WP-Cron worker, otherwise the same category/start page
        // can be processed twice and show artificial "updated" / repeated counts.
        $updated = (int)($job['updated_at'] ?? 0);
        if ($updated > 0 && (time() - $updated) < self::IMPORT_FALLBACK_IDLE_SECONDS) {
            return;
        }

        $last_inline = (int)($job['inline_tick_at'] ?? 0);
        if ($last_inline > 0 && (time() - $last_inline) < self::IMPORT_FALLBACK_IDLE_SECONDS) {
            return;
        }

        $job['inline_tick_at'] = time();
        $job['message'] = 'Import running. Admin monitor is advancing safe batches while Action Scheduler catches up.';
        $job['updated_at'] = time();
        update_option(self::OPT_IMPORT_JOB, $job, false);

        $this->add_import_log('debug', 'Admin monitor advanced one safe import batch', [
            'job_id' => $job_id,
            'category_index' => (int)($job['category_index'] ?? 0),
            'start' => (int)($job['start'] ?? 0),
        ]);

        $this->run_background_import_batch($job_id);
    }

    private function maybe_kick_stalled_import(array &$job): void
    {
        if (empty($job['id'])) {
            return;
        }
        if (in_array((string)($job['status'] ?? ''), ['complete', 'cancelled'], true)) {
            return;
        }
        $updated = (int)($job['updated_at'] ?? 0);
        if ($updated > 0 && (time() - $updated) < (self::IMPORT_LOCK_TTL + 60)) {
            return;
        }
        $job['message'] = 'Import appeared stalled; queue was kicked automatically.';
        $job['updated_at'] = time();
        update_option(self::OPT_IMPORT_JOB, $job, false);
        delete_transient('source_dp_import_lock_' . md5((string)$job['id']));
        $this->add_import_log('warning', 'Watchdog kicked stalled background import', [
            'job_id' => (string)$job['id'],
            'category_index' => (int)($job['category_index'] ?? 0),
            'start' => (int)($job['start'] ?? 0),
        ]);
        $this->enqueue_background_import_batch((string)$job['id'], 2);
    }

    private function update_background_job_progress(array &$job, array $current_cat, int $current_total): void
    {
        $cat_name = (string)($current_cat['name'] ?? ($current_cat['id'] ?? 'Category'));
        $start = (int)($job['start'] ?? 0);
        $category_index = (int)($job['category_index'] ?? 0);
        $categories = is_array($job['categories'] ?? null) ? $job['categories'] : [];
        $total_categories = max(1, count($categories));
        $processed = (int)($job['processed'] ?? 0);
        $total_expected = max(0, (int)($job['total_expected'] ?? 0));

        if ($total_expected > 0) {
            $pct = (int)round(min(100, ($processed / max(1, $total_expected)) * 100));
        } else {
            $pct = (int)round(min(100, ($category_index / $total_categories) * 100));
        }

        $label_total = $current_total > 0 ? $current_total : (int)($current_cat['count'] ?? 0);
        $job['progress_label'] = 'Category ' . $cat_name . ': ' . min($start, max($label_total, $start)) . '/' . $label_total;
        $job['progress_pct'] = max(0, min(100, $pct));
    }

    private function format_import_job_for_response(array $job): array
    {
        return [
            'id' => (string)($job['id'] ?? ''),
            'status' => (string)($job['status'] ?? ''),
            'message' => (string)($job['message'] ?? ''),
            'progress' => [
                'label' => (string)($job['progress_label'] ?? ''),
                'pct' => (int)($job['progress_pct'] ?? 0),
            ],
            'created' => (int)($job['created'] ?? 0),
            'updated' => (int)($job['updated'] ?? 0),
            'skipped' => (int)($job['skipped'] ?? 0),
            'media_skipped' => (int)($job['media_skipped'] ?? 0),
            'media_deferred' => (int)($job['media_deferred'] ?? 0),
            'category_linked' => (int)($job['category_linked'] ?? 0),
            'content_reused' => (int)($job['content_reused'] ?? 0),
            'errors' => (int)($job['errors'] ?? 0),
            'processed' => (int)($job['processed'] ?? 0),
            'unique_seen' => is_array($job['seen_content_keys'] ?? null) ? count($job['seen_content_keys']) : (is_array($job['seen_source_ids'] ?? null) ? count($job['seen_source_ids']) : 0),
            'total_expected' => (int)($job['total_expected'] ?? 0),
            'last_error' => (string)($job['last_error'] ?? ''),
            'current_step' => (string)($job['current_step'] ?? ''),
            'log' => array_slice($this->get_import_log(), -80),
            'messages' => array_slice(is_array($job['messages'] ?? null) ? $job['messages'] : [], -20),
            'failed_pages' => array_slice(is_array($job['failed_pages'] ?? null) ? $job['failed_pages'] : [], -20),
            'already_seen' => (int)($job['already_seen'] ?? 0),
            'actual_imported_total' => $this->get_imported_post_count((string)($job['uuid'] ?? '')),
            'actual_imported_types' => $this->get_local_content_type_counts((string)($job['uuid'] ?? '')),
            'started_at' => (int)($job['started_at'] ?? 0),
            'updated_at' => (int)($job['updated_at'] ?? 0),
        ];
    }

    public function ajax_delete_imported(): void
    {
        $this->assert_admin_ajax();

        // A running import can immediately recreate posts while the admin is deleting them.
        // Stop the active job first so the delete count and the next fresh import are reliable.
        $active_job = (array)get_option(self::OPT_IMPORT_JOB, []);
        if (!empty($active_job['id']) && in_array((string)($active_job['status'] ?? ''), ['queued', 'running'], true)) {
            $active_job_id = (string)$active_job['id'];
            $active_job['status'] = 'cancelled';
            $active_job['message'] = 'Import cancelled automatically before deleting imported posts.';
            $active_job['current_step'] = 'Cancelled before delete';
            $active_job['updated_at'] = time();
            update_option(self::OPT_IMPORT_JOB, $active_job, false);
            $this->clear_pending_import_actions($active_job_id);
            delete_transient('source_dp_import_lock_' . md5($active_job_id));
            $this->add_import_log('warning', 'Active import cancelled before deleting imported posts', ['job_id' => $active_job_id]);
        }

        $category_id = isset($_POST['category_id']) ? $this->sanitize_text_field_relaxed((string)$_POST['category_id']) : '';
        $delete_terms = !empty($_POST['delete_terms']);

        $meta_query = [
            ['key' => '_source_imported', 'value' => 1, 'compare' => '='],
        ];
        if ($category_id !== '') {
            $meta_query[] = ['key' => '_source_category_id', 'value' => $category_id, 'compare' => '='];
        }

        $ids = get_posts([
            'post_type' => 'any',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => $meta_query,
        ]);

        $deleted = 0;
        foreach ($ids as $pid) {
            $pid = (int)$pid;
            if ($pid > 0) {
                wp_delete_post($pid, true);
                $deleted++;
            }
        }

        $term_deleted = ['category' => 0, 'post_tag' => 0];
        if ($delete_terms) {
            $term_deleted = $this->maybe_delete_created_terms_if_unused();
        }

        if ($category_id === '') {
            delete_option(self::OPT_MEDIA_QUEUE);
            wp_clear_scheduled_hook(self::CRON_HOOK_MEDIA_BATCH);
        }

        wp_send_json_success([
            'message' => 'Deleted imported posts.',
            'deleted' => $deleted,
            'deleted_terms' => $term_deleted,
        ]);
    }

    private function add_import_log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);
        $allowed = ['debug', 'info', 'warning', 'error', 'fatal'];
        if (!in_array($level, $allowed, true)) {
            $level = 'info';
        }

        $entry = [
            'time' => time(),
            'time_human' => function_exists('wp_date') ? wp_date('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $this->truncate_for_log($message, 240),
            'context' => $this->sanitize_log_context($context),
        ];

        $log = $this->get_import_log();
        $log[] = $entry;
        $log = array_slice($log, -300);
        update_option(self::OPT_IMPORT_LOG, $log, false);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SourceDP Import][' . strtoupper($level) . '] ' . $entry['message'] . ' ' . wp_json_encode($entry['context']));
        }
    }

    private function get_import_log(): array
    {
        $log = get_option(self::OPT_IMPORT_LOG, []);
        return is_array($log) ? $log : [];
    }

    private function sanitize_log_context(array $context): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            $key = is_string($key) ? sanitize_key($key) : (string)$key;
            if (is_scalar($value) || $value === null) {
                $clean[$key] = is_bool($value) ? ($value ? 'true' : 'false') : $this->truncate_for_log((string)$value, 260);
            } elseif (is_array($value)) {
                $clean[$key] = $this->truncate_for_log(wp_json_encode($value), 260);
            } else {
                $clean[$key] = gettype($value);
            }
        }
        return $clean;
    }

    private function format_import_log_text(array $log, int $limit = 120): string
    {
        if (empty($log)) {
            return 'No import log yet.';
        }
        $rows = array_slice($log, -max(1, $limit));
        $lines = [];
        foreach ($rows as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $ctx = isset($entry['context']) && is_array($entry['context']) && !empty($entry['context']) ? ' ' . wp_json_encode($entry['context']) : '';
            $lines[] = '[' . (string)($entry['time_human'] ?? '') . '] ' . strtoupper((string)($entry['level'] ?? 'info')) . ' - ' . (string)($entry['message'] ?? '') . $ctx;
        }
        return implode("\n", $lines);
    }

    private function truncate_for_log(string $value, int $limit = 180): string
    {
        $value = trim(wp_strip_all_tags($value));
        if ($value === '') {
            return '';
        }
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length <= $limit) {
            return $value;
        }
        $part = function_exists('mb_substr') ? mb_substr($value, 0, max(0, $limit - 1)) : substr($value, 0, max(0, $limit - 1));
        return $part . '…';
    }

    private function format_bytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . 'MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . 'KB';
        }
        return $bytes . 'B';
    }

    private function maybe_delete_created_terms_if_unused(): array
    {
        $created = (array)get_option(self::OPT_CREATED_TERMS, ['category' => [], 'post_tag' => []]);

        $out = ['category' => 0, 'post_tag' => 0];
        foreach (['category', 'post_tag'] as $tax) {
            $ids = isset($created[$tax]) && is_array($created[$tax]) ? $created[$tax] : [];
            $keep = [];
            foreach ($ids as $tid) {
                $tid = (int)$tid;
                if ($tid <= 0) continue;

                $term = get_term($tid, $tax);
                if (!$term || is_wp_error($term)) continue;

                $count = isset($term->count) ? (int)$term->count : 0;
                if ($count > 0) {
                    $keep[] = $tid;
                    continue;
                }

                $deleted = wp_delete_term($tid, $tax);
                if (!is_wp_error($deleted) && $deleted) {
                    $out[$tax]++;
                }
            }
            $created[$tax] = $keep;
        }

        update_option(self::OPT_CREATED_TERMS, $created, false);
        return $out;
    }

    public function ajax_contact_source(): void
    {
        $this->assert_admin_ajax();
        $to = isset($_POST['to']) ? sanitize_email((string)$_POST['to']) : '';
        $from = isset($_POST['from']) ? sanitize_email((string)$_POST['from']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field((string)$_POST['subject']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field((string)$_POST['message']) : '';
        $uuid = (string)get_option(self::OPT_UUID, '');

        if ($to === '' || !is_email($to)) {
            wp_send_json_error(['message' => 'Please enter a valid recipient email address.']);
        }
        if ($from === '' || !is_email($from)) {
            $from = sanitize_email((string)get_option('admin_email'));
        }
        if ($subject === '') {
            wp_send_json_error(['message' => 'Please enter a subject.']);
        }
        if ($message === '') {
            wp_send_json_error(['message' => 'Please enter a message.']);
        }

        $body = "Source Distribution Partner message\n\n";
        $body .= "Site: " . home_url('/') . "\n";
        $body .= "Integration UUID: " . $uuid . "\n";
        $body .= "Reply-to: " . $from . "\n\n";
        $body .= $message;

        $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $from_header = $from !== '' && is_email($from) ? $from : (string)get_option('admin_email');
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $from_header,
        ];
        if (is_string($site_host) && $site_host !== '') {
            $headers[] = 'From: Source Distribution Partner <wordpress@' . sanitize_text_field($site_host) . '>';
        }

        $sent = wp_mail($to, $subject, $body, $headers);
        $log = (array)get_option(self::OPT_CONTACT_LOG, []);
        $log[] = [
            'date' => current_time('mysql'),
            'to' => $to,
            'from' => $from,
            'subject' => $subject,
            'message' => $message,
            'sent' => (bool)$sent,
        ];
        if (count($log) > 50) {
            $log = array_slice($log, -50);
        }
        update_option(self::OPT_CONTACT_LOG, $log, false);

        if (!$sent) {
            wp_send_json_error(['message' => 'Message logged, but WordPress mail did not confirm sending. Check SMTP/mail settings.']);
        }
        wp_send_json_success(['message' => 'Message sent and logged.']);
    }

    private function assert_admin_ajax(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden.'], 403);
        }
        $nonce = isset($_POST['nonce']) ? (string)$_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => 'Invalid nonce.'], 403);
        }
    }

    private function get_uuid_from_request_or_option(): string
    {
        $uuid = isset($_POST['uuid']) ? $this->sanitize_uuid((string)$_POST['uuid']) : '';
        return $uuid !== '' ? $uuid : (string)get_option(self::OPT_UUID, '');
    }

    private function api_get_bundle(string $uuid)
    {
        $url = add_query_arg(['uuid' => $uuid], self::API_BASE . '/bundle');
        return $this->api_request_json('GET', $url, null);
    }

    private function api_get_categories(string $uuid)
    {
        $url = add_query_arg(['uuid' => $uuid], self::API_BASE . '/bundle/categories');
        return $this->api_request_json('GET', $url, null);
    }

    private function api_get_posts_page(string $uuid, array $category_ids, int $limit, int $start, ?int $type, bool $truncate_false)
    {
        $query = [
            'uuid' => $uuid,
            'limit' => $limit,
            'start' => $start,
            'truncate' => $truncate_false ? 'false' : 'true',
        ];
        if ($type !== null) {
            $query['type'] = $type;
            $query['contentTypeId'] = $type;
            $query['contentType'] = $type;
        }

        $url = add_query_arg($query, self::API_BASE . '/bundle/posts');
        return $this->api_request_json('POST', $url, ['categoryIds' => array_values($category_ids)]);
    }

    private function get_primary_source_numeric_id_for_post(int $post_id): int
    {
        $numeric_id = (int)get_post_meta($post_id, '_source_numeric_id', true);
        if ($numeric_id > 0) {
            return $numeric_id;
        }
        $numeric_ids = $this->normalize_source_meta_list(get_post_meta($post_id, '_source_numeric_ids', true));
        foreach ($numeric_ids as $id) {
            if (is_numeric($id) && (int)$id > 0) {
                return (int)$id;
            }
        }
        return 0;
    }

    private function get_source_analytics_ids_for_post(int $post_id): array
    {
        $ids = [];

        // Source analytics endpoints have been more reliable with the numeric bundle/post row ids.
        // Keep Source postId as a fallback, but do not try it first because a 2xx/no-op response can
        // make the plugin think an impression/click synced when Source did not count it.
        $numeric_id = (int)get_post_meta($post_id, '_source_numeric_id', true);
        if ($numeric_id > 0) {
            $ids[] = (string)$numeric_id;
        }

        $numeric_ids = $this->normalize_source_meta_list(get_post_meta($post_id, '_source_numeric_ids', true));
        foreach ($numeric_ids as $id) {
            $id = $this->sanitize_text_field_relaxed((string)$id);
            if ($id !== '' && is_numeric($id) && (int)$id > 0) {
                $ids[] = (string)((int)$id);
            }
        }

        $import_keys = $this->normalize_source_meta_list(get_post_meta($post_id, '_source_import_keys', true));
        foreach ($import_keys as $key) {
            $key = $this->sanitize_text_field_relaxed((string)$key);
            if (preg_match('/^source-id:(\d+)$/', $key, $m)) {
                $ids[] = (string)$m[1];
            }
        }

        $source_post_id = $this->sanitize_text_field_relaxed((string)get_post_meta($post_id, '_source_post_id', true));
        if ($source_post_id !== '') {
            $ids[] = $source_post_id;
        }

        $ids = array_values(array_unique(array_filter(array_map(static function ($id) {
            $id = trim((string)$id);
            return $id !== '' ? $id : null;
        }, $ids))));

        return $ids;
    }

    private function get_source_analytics_event_attempts(string $event): array
    {
        $event = sanitize_key($event);

        // The public Source statistics dashboard confirms views through the plural endpoint.
        // Keep impressions/clicks plural-first as well; some deployments return 2xx for singular
        // aliases without incrementing the distributor statistics.
        if ($event === 'impressions') {
            return ['impressions', 'impression'];
        }
        if ($event === 'clicks') {
            return ['clicks', 'click'];
        }
        if ($event === 'views') {
            return ['views', 'view'];
        }

        return [$event];
    }

    private function api_request_analytics_post(string $url, ?array $body = null)
    {
        $args = [
            'method' => 'POST',
            'timeout' => 8,
            'headers' => ['Accept' => 'application/json'],
        ];

        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }

        $resp = wp_remote_request($url, $args);
        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = (int)wp_remote_retrieve_response_code($resp);
        $raw = (string)wp_remote_retrieve_body($resp);

        if ($code >= 200 && $code < 300) {
            $decoded = null;
            if (trim($raw) !== '') {
                $json = json_decode($raw, true);
                $decoded = is_array($json) ? $json : $raw;
            }
            return [
                'ok' => true,
                'code' => $code,
                'body' => $decoded,
            ];
        }

        $msg = 'Analytics API request failed (' . $code . ').';
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
            $msg .= ' ' . $decoded['message'];
        } elseif (trim($raw) !== '') {
            $msg .= ' ' . $this->truncate_for_log(wp_strip_all_tags($raw), 220);
        }

        return new WP_Error('source_dp_analytics_http', $msg, ['code' => $code, 'body' => $raw]);
    }

    private function api_post_analytics_impression_event(string $uuid, string $source_identifier, int $wp_post_id = 0)
    {
        $source_identifier = trim((string)$source_identifier);
        if ($source_identifier === '') {
            return new WP_Error('source_dp_analytics_identifier', 'Missing Source analytics identifier.');
        }

        $url = add_query_arg(['uuid' => $uuid], self::API_BASE . '/analytics/bundle/posts/impressions');
        $numeric = is_numeric($source_identifier) ? (int)$source_identifier : $source_identifier;

        // Source's current docs define impressions as a bulk endpoint:
        // POST /analytics/bundle/posts/impressions, with a list of post IDs.
        // Try the documented list shape first, then common named wrappers so this remains
        // compatible if the Swagger schema expects an object wrapper on some deployments.
        $bodies = [
            [$numeric],
            ['ids' => [$numeric]],
            ['postIds' => [$numeric]],
            ['post_ids' => [$numeric]],
            ['posts' => [$numeric]],
            ['postId' => $numeric],
        ];

        $last_error = null;
        foreach ($bodies as $body) {
            $resp = $this->api_request_analytics_post($url, $body);
            if (!is_wp_error($resp)) {
                $resp['source_identifier'] = $source_identifier;
                $resp['event_endpoint'] = 'impressions';
                $resp['request_body'] = is_array($body) && array_keys($body) === range(0, count($body) - 1) ? 'json-list' : 'json-object';
                return $resp;
            }
            $last_error = $resp;
        }

        return $last_error ?: new WP_Error('source_dp_analytics_impression_failed', 'Source impression API request failed.');
    }

    private function api_post_analytics_event(string $uuid, string $source_identifier, string $event, int $wp_post_id = 0)
    {
        $event = sanitize_key($event);
        $allowed = ['impressions', 'clicks', 'views'];
        if (!in_array($event, $allowed, true)) {
            return new WP_Error('source_dp_analytics_event', 'Unsupported analytics event.');
        }

        $source_identifier = trim((string)$source_identifier);
        if ($source_identifier === '') {
            return new WP_Error('source_dp_analytics_identifier', 'Missing Source analytics identifier.');
        }

        if ($event === 'impressions') {
            return $this->api_post_analytics_impression_event($uuid, $source_identifier, $wp_post_id);
        }

        $last_error = null;
        foreach ($this->get_source_analytics_event_attempts($event) as $event_name) {
            $url = add_query_arg(['uuid' => $uuid], self::API_BASE . '/analytics/bundle/posts/' . rawurlencode($source_identifier) . '/' . $event_name);

            // The analytics endpoints may return 204/empty-body success, so this deliberately does not require JSON.
            $resp = $this->api_request_analytics_post($url, null);
            if (!is_wp_error($resp)) {
                $resp['source_identifier'] = $source_identifier;
                $resp['event_endpoint'] = $event_name;
                $resp['request_body'] = 'none';
                return $resp;
            }
            $last_error = $resp;

            // Fallback for API deployments that require a JSON object body instead of a body-less POST.
            $body = [
                'uuid' => $uuid,
                'postId' => $source_identifier,
                'id' => $source_identifier,
                'metric' => $event,
            ];
            if ($wp_post_id > 0) {
                $body['wordpressPostId'] = $wp_post_id;
            }
            $resp = $this->api_request_analytics_post($url, $body);
            if (!is_wp_error($resp)) {
                $resp['source_identifier'] = $source_identifier;
                $resp['event_endpoint'] = $event_name;
                $resp['request_body'] = 'json';
                return $resp;
            }
            $last_error = $resp;
        }

        return $last_error ?: new WP_Error('source_dp_analytics_failed', 'Analytics API request failed.');
    }

    private function sync_source_metric_for_post(int $post_id, string $uuid, string $metric)
    {
        $ids = $this->get_source_analytics_ids_for_post($post_id);
        if ($uuid === '' || empty($ids)) {
            return new WP_Error('source_dp_metric_identifiers', 'Missing Source identifiers.');
        }

        $errors = [];
        foreach ($ids as $id) {
            $resp = $this->api_post_analytics_event($uuid, (string)$id, $metric, $post_id);
            if (!is_wp_error($resp)) {
                update_post_meta($post_id, '_source_last_' . $metric . '_sync_identifier', (string)$id);
                update_post_meta($post_id, '_source_last_' . $metric . '_sync_endpoint', (string)($resp['event_endpoint'] ?? $metric));
                update_post_meta($post_id, '_source_last_' . $metric . '_sync_body_mode', (string)($resp['request_body'] ?? ''));
                update_post_meta($post_id, '_source_last_' . $metric . '_sync_code', (string)($resp['code'] ?? ''));
                return $resp;
            }
            $errors[] = (string)$id . ': ' . $resp->get_error_message();
        }

        return new WP_Error('source_dp_metric_sync_failed', implode(' | ', array_slice($errors, 0, 5)));
    }

    private function api_post_analytics_view(string $uuid, int $numeric_id)
    {
        return $this->api_post_analytics_event($uuid, (string)$numeric_id, 'views');
    }

    private function record_source_metric(int $post_id, string $metric, string $meta_key, bool $sync_source = true)
    {
        if ($post_id <= 0) {
            return new WP_Error('source_dp_metric_post', 'Missing postId.');
        }
        if ((int)get_post_meta($post_id, '_source_imported', true) !== 1) {
            return new WP_Error('source_dp_metric_imported', 'Not a Source post.');
        }

        $uuid = (string)get_post_meta($post_id, '_source_uuid', true);

        $local = (int)get_post_meta($post_id, $meta_key, true);
        update_post_meta($post_id, $meta_key, $local + 1);
        $this->increment_daily_metric($metric, $uuid);
        if (!$sync_source) {
            update_post_meta($post_id, '_source_last_' . $metric . '_recorded_at', current_time('mysql'));
            return true;
        }

        $resp = $this->sync_source_metric_for_post($post_id, $uuid, $metric);
        if (is_wp_error($resp)) {
            update_post_meta($post_id, '_source_last_' . $metric . '_sync_error', $resp->get_error_message());
            $this->add_import_log('warning', 'Source analytics sync failed', [
                'wp_post_id' => $post_id,
                'metric' => $metric,
                'error' => $resp->get_error_message(),
            ]);
            return $resp;
        }

        delete_post_meta($post_id, '_source_last_' . $metric . '_sync_error');
        update_post_meta($post_id, '_source_last_' . $metric . '_synced_at', current_time('mysql'));
        return $resp;
    }

    private function api_request_json(string $method, string $url, ?array $body)
    {
        $method = strtoupper($method);
        $args = [
            'method' => $method,
            'timeout' => 12,
            'headers' => ['Accept' => 'application/json'],
        ];

        if ($method !== 'GET' && $body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }

        $resp = wp_remote_request($url, $args);
        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = (int)wp_remote_retrieve_response_code($resp);
        $raw = (string)wp_remote_retrieve_body($resp);

        if ($code < 200 || $code >= 300) {
            $msg = 'API request failed (' . $code . ').';
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
                $msg .= ' ' . $decoded['message'];
            }
            return new WP_Error('source_dp_http', $msg);
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return new WP_Error('source_dp_json', 'Invalid JSON response from API.');
        }

        return $json;
    }

    private function extract_source_stream_url(array $sp): string
    {
        $keys = [
            'streamUrl', 'streamURL', 'audioUrl', 'audioURL', 'podcastUrl', 'podcastURL',
            'mediaUrl', 'mediaURL', 'fileUrl', 'fileURL', 'enclosureUrl', 'enclosureURL',
            'episodeUrl', 'episodeURL', 'downloadUrl', 'downloadURL'
        ];
        foreach ($keys as $key) {
            if (!isset($sp[$key])) {
                continue;
            }
            $value = $sp[$key];
            if (is_string($value) && trim($value) !== '') {
                return esc_url_raw(trim($value));
            }
            if (is_array($value)) {
                foreach (['url', 'href', 'src', 'link'] as $nested_key) {
                    if (isset($value[$nested_key]) && is_string($value[$nested_key]) && trim($value[$nested_key]) !== '') {
                        return esc_url_raw(trim($value[$nested_key]));
                    }
                }
            }
        }
        return '';
    }

    private function import_posts(array $args)
    {
        $uuid = (string)($args['uuid'] ?? '');
        $post_type = (string)($args['post_type'] ?? 'post');
        $global_tag = (string)($args['global_tag'] ?? '');
        $mappings = is_array($args['mappings'] ?? null) ? $args['mappings'] : [];
        $bundle_categories = is_array($args['bundle_categories'] ?? null) ? $args['bundle_categories'] : [];
        $source_posts = is_array($args['source_posts'] ?? null) ? $args['source_posts'] : [];
        $overwrite = (bool)($args['overwrite'] ?? false);
        $job_id = (string)($args['job_id'] ?? '');
        $log_category = (string)($args['log_category'] ?? '');

        if ($uuid === '') {
            return new WP_Error('source_dp_import', 'Missing UUID.');
        }
        if (!post_type_exists($post_type)) {
            $post_type = 'post';
        }

        $source_category_name_by_id = [];
        foreach ($bundle_categories as $cat) {
            if (is_array($cat) && isset($cat['id'])) {
                $source_category_name_by_id[(string)$cat['id']] = (string)($cat['name'] ?? $cat['id']);
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $media_skipped = 0;
        $media_deferred = 0;
        $category_linked = 0;
        $content_reused = 0;
        $errors = 0;
        $messages = [];

        foreach ($source_posts as $sp) {
            if (!is_array($sp)) {
                $skipped++;
                continue;
            }

            $numeric_id = isset($sp['id']) && is_numeric($sp['id']) ? (int)$sp['id'] : 0;
            $source_post_id = $this->sanitize_text_field_relaxed((string)($sp['postId'] ?? ''));
            if ($source_post_id === '' && $numeric_id > 0) {
                $source_post_id = (string)$numeric_id;
            }
            $source_import_key = $this->get_source_import_key($sp);
            $source_content_key = $this->get_source_content_key($sp);
            if ($source_content_key === '') {
                $source_content_key = $source_import_key;
            }
            if ($source_import_key === '' && $source_content_key === '') {
                $skipped++;
                continue;
            }

            try {
                $title_for_log = (string)($sp['title'] ?? '');
                if ($job_id !== '') {
                    $this->add_import_log('debug', 'Processing Source post', [
                        'job_id' => $job_id,
                        'category' => $log_category,
                        'source_import_key' => $source_import_key,
                        'source_content_key' => $source_content_key,
                        'source_post_id' => $source_post_id,
                        'numeric_id' => $numeric_id,
                        'title' => $this->truncate_for_log($title_for_log, 120),
                    ]);
                }

                $source_category_id = $this->sanitize_text_field_relaxed((string)($sp['_source_category_id'] ?? ''));
                $content_seen_in_run = !empty($sp['_source_content_seen_in_run']);

                $existing_post_id = $this->find_existing_post_id($uuid, $source_post_id, $post_type, $numeric_id, $source_import_key, $source_content_key);
                if ($existing_post_id > 0 && ($content_seen_in_run || !$overwrite)) {
                    $record_changed = $this->merge_source_record_tracking($existing_post_id, $numeric_id, $source_import_key);
                    if ($source_content_key !== '' && (string)get_post_meta($existing_post_id, '_source_content_key', true) === '') {
                        update_post_meta($existing_post_id, '_source_content_key', $source_content_key);
                        $record_changed = true;
                    }
                    $linked = $this->attach_source_category_to_post($existing_post_id, $source_category_id, $source_category_name_by_id, $mappings);
                    update_post_meta($existing_post_id, '_source_last_synced_at', time());

                    if ($content_seen_in_run) {
                        $content_reused++;
                        if ($linked) {
                            $category_linked++;
                        }
                        if ($job_id !== '') {
                            $this->add_import_log('info', 'Source content already imported in this run; linked additional category instead of updating post', [
                                'job_id' => $job_id,
                                'source_post_id' => $source_post_id,
                                'wp_post_id' => $existing_post_id,
                                'source_category_id' => $source_category_id,
                                'record_tracking_changed' => $record_changed ? 'yes' : 'no',
                            ]);
                        }
                    } elseif ($linked || $record_changed) {
                        $category_linked++;
                        if ($job_id !== '') {
                            $this->add_import_log('info', 'Linked existing Source post to topic/category without overwriting content', [
                                'job_id' => $job_id,
                                'source_post_id' => $source_post_id,
                                'wp_post_id' => $existing_post_id,
                                'source_category_id' => $source_category_id,
                            ]);
                        }
                    } else {
                        if ($job_id !== '') {
                            $this->add_import_log('debug', 'Skipped existing imported post because overwrite is off and category was already linked', [
                                'job_id' => $job_id,
                                'source_post_id' => $source_post_id,
                                'wp_post_id' => $existing_post_id,
                            ]);
                        }
                        $skipped++;
                    }
                    continue;
                }

                $title = (string)($sp['title'] ?? '');
                $info = (string)($sp['info'] ?? '');
                $url = (string)($sp['url'] ?? '');
                $stream_url = $this->extract_source_stream_url($sp);
                $thumb = (string)($sp['thumbnail'] ?? '');
                $feed_title = (string)($sp['feedTitle'] ?? '');
                $posted_at = (string)($sp['postedAt'] ?? '');
                $content_type_id = isset($sp['contentTypeId']) ? (string)$sp['contentTypeId'] : '';

                $youtube_id = $this->extract_youtube_id($stream_url) ?: $this->extract_youtube_id($url);
                $has_youtube = $youtube_id !== '';

                $content = $this->build_post_content($info, $feed_title, $posted_at, $content_type_id, $youtube_id, $stream_url, $url);

                $postarr = [
                    'post_type' => $post_type,
                    'post_author' => $this->get_source_author_id($feed_title),
                    'post_title' => wp_strip_all_tags($title),
                    'post_content' => $content,
                    'post_excerpt' => $this->safe_excerpt($info),
                    'post_status' => 'publish',
                ];

                $source_date_fields = $this->source_post_date_fields($posted_at);
                if (!empty($source_date_fields)) {
                    $postarr = array_merge($postarr, $source_date_fields);
                }

                $new_id = $existing_post_id > 0
                    ? wp_update_post(wp_slash($postarr + ['ID' => $existing_post_id]), true)
                    : wp_insert_post(wp_slash($postarr), true);

                if (is_wp_error($new_id)) {
                    $errors++;
                    $messages[] = 'Post ' . $source_post_id . ': ' . $new_id->get_error_message();
                    if ($job_id !== '') {
                        $this->add_import_log('error', 'WordPress post create/update failed', [
                            'job_id' => $job_id,
                            'source_post_id' => $source_post_id,
                            'title' => $this->truncate_for_log($title, 120),
                            'error' => $new_id->get_error_message(),
                        ]);
                    }
                    continue;
                }

                $post_id = (int)$new_id;

                update_post_meta($post_id, '_source_uuid', $uuid);
                update_post_meta($post_id, '_source_content_key', $source_content_key);
                update_post_meta($post_id, '_source_import_key', $source_import_key);
                update_post_meta($post_id, '_source_post_id', $source_post_id);
                if ((int)get_post_meta($post_id, '_source_numeric_id', true) <= 0) {
                    update_post_meta($post_id, '_source_numeric_id', $numeric_id);
                }
                $this->merge_source_record_tracking($post_id, $numeric_id, $source_import_key);
                update_post_meta($post_id, '_source_imported', 1);
                update_post_meta($post_id, '_source_url', esc_url_raw($url));
                update_post_meta($post_id, '_source_embed_url', $this->build_source_embed_url($uuid, $source_post_id, $numeric_id, $url));
                update_post_meta($post_id, '_source_stream_url', esc_url_raw($stream_url));
                update_post_meta($post_id, '_source_feed_title', $feed_title);
                update_post_meta($post_id, '_source_posted_at', $posted_at);
                update_post_meta($post_id, '_source_content_type_id', $content_type_id);
                update_post_meta($post_id, '_source_has_youtube', $has_youtube ? 1 : 0);
                update_post_meta($post_id, '_source_youtube_id', $youtube_id);
                update_post_meta($post_id, '_source_last_synced_at', time());

                $term_ids_category = [];
                $term_ids_tag = [];
                $category_canonical_url = '';

                if ($global_tag !== '') {
                    $tid = $this->ensure_term('post_tag', $global_tag);
                    if (!is_wp_error($tid) && $tid > 0) {
                        $term_ids_tag[] = (int)$tid;
                    }
                }

                $content_type_tag = $this->get_content_type_label_from_id($content_type_id);
                if ($content_type_tag !== '') {
                    $content_type_tag_id = $this->ensure_term('post_tag', $content_type_tag);
                    if (!is_wp_error($content_type_tag_id) && $content_type_tag_id > 0) {
                        $term_ids_tag[] = (int)$content_type_tag_id;
                    }
                }

                $term_ids_category = array_values(array_unique(array_filter(array_map('intval', $term_ids_category))));
                $term_ids_tag = array_values(array_unique(array_filter(array_map('intval', $term_ids_tag))));

                if ($source_category_id !== '') {
                    $this->attach_source_category_to_post($post_id, $source_category_id, $source_category_name_by_id, $mappings);
                }
                if (!empty($term_ids_tag)) {
                    wp_set_post_terms($post_id, $term_ids_tag, 'post_tag', true);
                }

                if ($thumb !== '') {
                    update_post_meta($post_id, '_source_thumbnail_url', esc_url_raw($thumb));
                    if (!get_post_thumbnail_id($post_id)) {
                        $this->queue_featured_image($post_id, $thumb, $source_post_id, $job_id);
                        $media_deferred++;
                        if ($job_id !== '') {
                            $this->add_import_log('debug', 'Featured image deferred to media queue', [
                                'job_id' => $job_id,
                                'source_post_id' => $source_post_id,
                                'wp_post_id' => $post_id,
                                'image' => $this->truncate_for_log($thumb, 180),
                            ]);
                        }
                    }
                }

                if ($job_id !== '') {
                    $this->add_import_log('info', $existing_post_id > 0 ? 'Updated imported post' : 'Created imported post', [
                        'job_id' => $job_id,
                        'source_post_id' => $source_post_id,
                        'wp_post_id' => $post_id,
                        'title' => $this->truncate_for_log($title, 120),
                    ]);
                }

                $existing_post_id > 0 ? $updated++ : $created++;
            } catch (\Throwable $e) {
                $errors++;
                if (count($messages) < 25) {
                    $messages[] = 'Post ' . $source_post_id . ' failed: ' . $e->getMessage();
                }
                if ($job_id !== '') {
                    $this->add_import_log('error', 'Throwable while importing single Source post', [
                        'job_id' => $job_id,
                        'source_post_id' => $source_post_id,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
                continue;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'media_skipped' => $media_skipped,
            'media_deferred' => $media_deferred,
            'category_linked' => $category_linked,
            'content_reused' => $content_reused,
            'errors' => $errors,
            'messages' => $messages,
        ];
    }

    private function source_post_date_fields(string $posted_at): array
    {
        $posted_at = trim($posted_at);
        if ($posted_at === '') {
            return [];
        }

        $timestamp = strtotime($posted_at);
        if ($timestamp === false || $timestamp <= 0) {
            return [];
        }

        $post_date_gmt = gmdate('Y-m-d H:i:s', $timestamp);
        $post_date = function_exists('get_date_from_gmt') ? get_date_from_gmt($post_date_gmt) : $post_date_gmt;

        return [
            'post_date' => $post_date,
            'post_date_gmt' => $post_date_gmt,
        ];
    }

    private function build_post_content(string $info, string $feed_title, string $posted_at, string $content_type_id, string $youtube_id, string $stream_url = '', string $url = ''): string
    {
        $parts = [];

        if ($content_type_id === '1') {
            if ($youtube_id !== '') {
                $parts[] = '<div class="source-dp-video" data-youtube-id="' . esc_attr($youtube_id) . '"><button type="button" class="source-dp-play-btn" aria-label="Play video"><span class="source-dp-play-icon" aria-hidden="true">▶</span><span class="source-dp-play-text">Play video</span></button></div>';
            } elseif ($stream_url !== '') {
                $parts[] = '<div class="source-dp-video" data-video-url="' . esc_url($stream_url) . '"><button type="button" class="source-dp-play-btn" aria-label="Play video"><span class="source-dp-play-icon" aria-hidden="true">▶</span><span class="source-dp-play-text">Play video</span></button></div>';
            } elseif ($url !== '') {
                $parts[] = '<div class="source-dp-video" data-external-url="' . esc_url($url) . '"><button type="button" class="source-dp-play-btn" aria-label="Play video"><span class="source-dp-play-icon" aria-hidden="true">▶</span><span class="source-dp-play-text">Play video</span></button></div>';
            }
        }

        if ($content_type_id === '2') {
            $podcast_url = $stream_url !== '' ? $stream_url : $url;
            if ($podcast_url !== '') {
                $data_attr = $stream_url !== '' ? 'data-video-url' : 'data-external-url';
                $parts[] = '<div class="source-dp-video source-dp-podcast" ' . $data_attr . '="' . esc_url($podcast_url) . '"><button type="button" class="source-dp-play-btn source-dp-podcast-play" aria-label="Listen to podcast"><span class="source-dp-play-icon" aria-hidden="true">▶</span><span class="source-dp-play-text">Listen to podcast</span></button></div>';
            }
        }

        if ((bool)get_option(self::OPT_SHOW_SOURCE_META, true)) {
            if ($feed_title !== '') {
                $parts[] = '<p><strong>Source Feed:</strong> ' . esc_html($feed_title) . '</p>';
            }
            if ($posted_at !== '') {
                $parts[] = '<p><strong>Published:</strong> ' . esc_html($posted_at) . '</p>';
            }
            if ($content_type_id !== '') {
                $parts[] = '<p><strong>Content Type ID:</strong> ' . esc_html($content_type_id) . '</p>';
            }
        }
        if ($info !== '') {
            $body = make_clickable($info);
            $parts[] = wp_kses_post(wpautop($body));
        }

        return implode("\n", $parts);
    }

    private function safe_excerpt(string $text): string
    {
        $text = wp_strip_all_tags($text);
        $text = trim($text);
        if ($text === '') return '';
        $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($len > 180) {
            $sub = function_exists('mb_substr') ? mb_substr($text, 0, 177) : substr($text, 0, 177);
            return $sub . '…';
        }
        return $text;
    }

    private function extract_youtube_id(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~', $url, $m)) return $m[1];
        if (preg_match('~[?&]v=([A-Za-z0-9_-]{6,})~', $url, $m)) return $m[1];
        if (preg_match('~/embed/([A-Za-z0-9_-]{6,})~', $url, $m)) return $m[1];
        return '';
    }

    private function get_default_source_author_id(): int
    {
        $existing = get_user_by('login', 'source-distribution');
        if ($existing && isset($existing->ID)) {
            return (int)$existing->ID;
        }

        $created = wp_insert_user([
            'user_login' => 'source-distribution',
            'user_pass' => wp_generate_password(24, true, true),
            'user_email' => 'source-distribution-' . substr(md5((string)home_url()), 0, 10) . '@example.invalid',
            'display_name' => 'Source',
            'nickname' => 'Source',
            'role' => 'author',
        ]);

        if (is_wp_error($created) || (int)$created <= 0) {
            // Last-resort WordPress fallback only. Normal imports should never be attributed to the admin user.
            return 1;
        }
        return (int)$created;
    }

    private function get_source_author_id(string $feed_title): int
    {
        $feed_title = $this->sanitize_text_field_relaxed($feed_title);
        if ($feed_title === '') {
            return $this->get_default_source_author_id();
        }

        $slug = 'source-' . sanitize_title($feed_title);
        $existing = get_user_by('login', $slug);
        if ($existing && isset($existing->ID)) {
            wp_update_user(['ID' => (int)$existing->ID, 'display_name' => $feed_title, 'nickname' => $feed_title]);
            return (int)$existing->ID;
        }

        $created = wp_insert_user([
            'user_login' => $slug,
            'user_pass' => wp_generate_password(24, true, true),
            'user_email' => 'source-' . substr(md5($feed_title), 0, 10) . '@example.invalid',
            'display_name' => $feed_title,
            'nickname' => $feed_title,
            'role' => 'author',
        ]);

        if (is_wp_error($created) || (int)$created <= 0) {
            return $this->get_default_source_author_id();
        }
        return (int)$created;
    }

    private function get_source_content_key(array $sp): string
    {
        // `postId` is the Source content identity. The numeric `id` is the bundle/category row id
        // used for analytics and can repeat the same content under different Source categories.
        $post_id = $this->sanitize_text_field_relaxed((string)($sp['postId'] ?? ''));
        if ($post_id !== '') {
            return 'source-post-id:' . $post_id;
        }

        foreach (['contentId', 'assetId', 'articleId', 'originalPostId'] as $field) {
            if (isset($sp[$field]) && (string)$sp[$field] !== '') {
                return 'source-' . strtolower($field) . ':' . $this->sanitize_text_field_relaxed((string)$sp[$field]);
            }
        }

        $url = esc_url_raw((string)($sp['url'] ?? ''));
        if ($url !== '') {
            return 'source-url:' . md5($url);
        }

        $title = $this->sanitize_text_field_relaxed((string)($sp['title'] ?? ''));
        $posted_at = $this->sanitize_text_field_relaxed((string)($sp['postedAt'] ?? ''));
        if ($title !== '' || $posted_at !== '') {
            return 'source-fallback:' . md5($title . '|' . $posted_at);
        }

        if (isset($sp['id']) && is_numeric($sp['id']) && (int)$sp['id'] > 0) {
            return 'source-id:' . (string)(int)$sp['id'];
        }

        return '';
    }

    private function get_source_import_key(array $sp): string
    {
        // The Source API exposes a numeric bundle-post id as `id` and often also exposes `postId`.
        // The numeric id is the record-level identity used by analytics and is the safest import key.
        // Earlier builds keyed imports by postId first, which collapsed legitimate records when postId was reused.
        if (isset($sp['id']) && is_numeric($sp['id']) && (int)$sp['id'] > 0) {
            return 'source-id:' . (string)(int)$sp['id'];
        }

        $post_id = $this->sanitize_text_field_relaxed((string)($sp['postId'] ?? ''));
        if ($post_id !== '') {
            return 'source-post-id:' . $post_id;
        }

        $url = esc_url_raw((string)($sp['url'] ?? ''));
        $title = $this->sanitize_text_field_relaxed((string)($sp['title'] ?? ''));
        if ($url !== '' || $title !== '') {
            return 'source-fallback:' . md5($title . '|' . $url);
        }

        return '';
    }

    private function find_existing_post_id(string $uuid, string $source_post_id, string $post_type, int $numeric_id = 0, string $source_import_key = '', string $source_content_key = ''): int
    {
        $source_content_key = $this->sanitize_text_field_relaxed($source_content_key);
        if ($source_content_key !== '') {
            $q = new WP_Query([
                'post_type' => $post_type,
                'post_status' => 'any',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'meta_query' => [
                    ['key' => '_source_uuid', 'value' => $uuid, 'compare' => '='],
                    ['key' => '_source_content_key', 'value' => $source_content_key, 'compare' => '='],
                ],
            ]);
            if (!empty($q->posts)) {
                return (int)$q->posts[0];
            }
        }

        // Backward compatibility: older versions stored postId but not _source_content_key.
        // When postId exists it is the best content-level identity and must be checked before numeric id.
        if ($source_post_id !== '') {
            $q = new WP_Query([
                'post_type' => $post_type,
                'post_status' => 'any',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'meta_query' => [
                    ['key' => '_source_uuid', 'value' => $uuid, 'compare' => '='],
                    ['key' => '_source_post_id', 'value' => $source_post_id, 'compare' => '='],
                ],
            ]);
            if (!empty($q->posts)) {
                return (int)$q->posts[0];
            }
        }

        // Only use record-level keys as a fallback when no content-level postId/content key exists.
        $has_content_identity = ($source_content_key !== '' && strpos($source_content_key, 'source-id:') !== 0) || $source_post_id !== '';
        $source_import_key = $this->sanitize_text_field_relaxed($source_import_key);
        if (!$has_content_identity && $source_import_key !== '') {
            $q = new WP_Query([
                'post_type' => $post_type,
                'post_status' => 'any',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'meta_query' => [
                    ['key' => '_source_uuid', 'value' => $uuid, 'compare' => '='],
                    ['key' => '_source_import_key', 'value' => $source_import_key, 'compare' => '='],
                ],
            ]);
            if (!empty($q->posts)) {
                return (int)$q->posts[0];
            }
        }

        if (!$has_content_identity && $numeric_id > 0) {
            $q = new WP_Query([
                'post_type' => $post_type,
                'post_status' => 'any',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'meta_query' => [
                    ['key' => '_source_uuid', 'value' => $uuid, 'compare' => '='],
                    ['key' => '_source_numeric_id', 'value' => $numeric_id, 'compare' => '='],
                ],
            ]);
            if (!empty($q->posts)) {
                return (int)$q->posts[0];
            }
        }

        return 0;
    }

    private function ensure_term(string $taxonomy, string $name)
    {
        $taxonomy = sanitize_key($taxonomy);
        $name = $this->sanitize_text_field_relaxed($name);

        if ($name === '' || !taxonomy_exists($taxonomy)) {
            return new WP_Error('source_dp_term', 'Invalid taxonomy/term.');
        }

        $existing = term_exists($name, $taxonomy);
        if (is_array($existing) && isset($existing['term_id'])) return (int)$existing['term_id'];
        if (is_int($existing)) return $existing;

        $created = wp_insert_term($name, $taxonomy);
        if (is_wp_error($created)) return $created;

        $tid = (int)($created['term_id'] ?? 0);
        if ($tid > 0) $this->record_created_term($taxonomy, $tid);
        return $tid;
    }

    private function record_created_term(string $taxonomy, int $term_id): void
    {
        $created = (array)get_option(self::OPT_CREATED_TERMS, ['category' => [], 'post_tag' => []]);
        if (!isset($created[$taxonomy]) || !is_array($created[$taxonomy])) $created[$taxonomy] = [];
        if (!in_array($term_id, $created[$taxonomy], true)) $created[$taxonomy][] = $term_id;
        update_option(self::OPT_CREATED_TERMS, $created, false);
    }

    private function queue_featured_image(int $post_id, string $image_url, string $source_post_id = '', string $job_id = ''): void
    {
        $post_id = (int)$post_id;
        $image_url = esc_url_raw($image_url);
        if ($post_id <= 0 || $image_url === '' || get_post_thumbnail_id($post_id)) {
            return;
        }

        $queue = (array)get_option(self::OPT_MEDIA_QUEUE, []);
        $existing = isset($queue[(string)$post_id]) && is_array($queue[(string)$post_id]) ? $queue[(string)$post_id] : [];
        $queue[(string)$post_id] = [
            'post_id' => $post_id,
            'image_url' => $image_url,
            'source_post_id' => $source_post_id,
            'job_id' => $job_id,
            'attempts' => (int)($existing['attempts'] ?? 0),
            'queued_at' => (int)($existing['queued_at'] ?? time()),
            'updated_at' => time(),
            'last_error' => (string)($existing['last_error'] ?? ''),
        ];
        update_option(self::OPT_MEDIA_QUEUE, $queue, false);
        $this->schedule_media_queue(20);
    }

    private function schedule_media_queue(int $delay = 20): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK_MEDIA_BATCH)) {
            wp_schedule_single_event(time() + max(5, $delay), self::CRON_HOOK_MEDIA_BATCH);
        }
    }

    public function process_media_queue(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(45);
        }

        $queue = (array)get_option(self::OPT_MEDIA_QUEUE, []);
        if (empty($queue)) {
            return;
        }

        $processed = 0;
        $max = 8;
        foreach ($queue as $key => $item) {
            if ($processed >= $max) {
                break;
            }
            if (!is_array($item)) {
                unset($queue[$key]);
                continue;
            }

            $post_id = (int)($item['post_id'] ?? $key);
            $image_url = esc_url_raw((string)($item['image_url'] ?? ''));
            if ($post_id <= 0 || $image_url === '' || get_post_status($post_id) === false) {
                unset($queue[$key]);
                continue;
            }
            if (get_post_thumbnail_id($post_id)) {
                unset($queue[$key]);
                continue;
            }

            $processed++;
            $result = $this->maybe_set_featured_image($post_id, $image_url);
            if (is_wp_error($result)) {
                $attempts = (int)($item['attempts'] ?? 0) + 1;
                if ($attempts >= 3) {
                    update_post_meta($post_id, '_source_image_last_error', $result->get_error_message());
                    unset($queue[$key]);
                    $this->add_import_log('warning', 'Deferred featured image failed after retries', [
                        'post_id' => $post_id,
                        'source_post_id' => (string)($item['source_post_id'] ?? ''),
                        'error' => $result->get_error_message(),
                    ]);
                    continue;
                }
                $item['attempts'] = $attempts;
                $item['updated_at'] = time();
                $item['last_error'] = $result->get_error_message();
                $queue[$key] = $item;
                continue;
            }

            delete_post_meta($post_id, '_source_image_last_error');
            unset($queue[$key]);
        }

        update_option(self::OPT_MEDIA_QUEUE, $queue, false);
        if (!empty($queue)) {
            $this->schedule_media_queue(60);
        }
    }

    private function maybe_set_featured_image(int $post_id, string $image_url)
    {
        $image_url = esc_url_raw($image_url);
        if ($image_url === '' || get_post_thumbnail_id($post_id)) return false;
        if (!preg_match('#^https?://#i', $image_url)) return false;

        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Do not let a slow/broken remote image kill the whole import step.
        // The post is already saved before this runs; if the image fails, the post remains imported.
        $filter = static function (array $args, string $url) use ($image_url): array {
            if ($url === $image_url) {
                $headers = isset($args['headers']) && is_array($args['headers']) ? $args['headers'] : [];
                $headers['Accept-Encoding'] = 'identity';
                $headers['Accept'] = 'image/*,*/*;q=0.8';
                $args['headers'] = $headers;
                $args['timeout'] = min(3, (int)($args['timeout'] ?? 3));
                $args['redirection'] = 3;
            }
            return $args;
        };
        add_filter('http_request_args', $filter, 10, 2);
        $tmp = download_url($image_url, 3);
        remove_filter('http_request_args', $filter, 10);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $path = (string)parse_url($image_url, PHP_URL_PATH);
        $name = $path !== '' ? wp_basename($path) : '';
        if ($name === '' || strpos($name, '.') === false) {
            $name = 'source-image-' . $post_id . '.jpg';
        }

        $file = [
            'name' => sanitize_file_name($name),
            'tmp_name' => $tmp,
        ];

        $att_id = media_handle_sideload($file, $post_id);
        if (is_wp_error($att_id)) {
            @unlink($tmp);
            return $att_id;
        }

        set_post_thumbnail($post_id, (int)$att_id);
        return true;
    }

    public function output_seo_meta(): void
    {
        $mode = (string)get_option(self::OPT_SEO_MODE, 'canonical_source');

        if (is_category()) {
            $term = get_queried_object();
            if (!$term || empty($term->term_id)) return;

            if ($mode === 'canonical_self') {
                $link = get_term_link((int)$term->term_id, 'category');
                if (!is_wp_error($link)) echo '<link rel="canonical" href="' . esc_url($link) . "\" />\n";
                return;
            }

            if ($mode === 'canonical_source') {
                $url = $this->get_category_canonical_url((int)$term->term_id);
                if ($url !== '') echo '<link rel="canonical" href="' . esc_url($url) . "\" />\n";
            }
            return;
        }

        if (!is_singular()) return;
        $post_id = get_queried_object_id();
        if ($post_id <= 0) return;
        if ((int)get_post_meta($post_id, '_source_imported', true) !== 1) return;

        if ($mode === 'noindex') {
            echo "<meta name=\"robots\" content=\"noindex,follow\" />\n";
            return;
        }

        if ($mode === 'canonical_source') {
            $source_url = (string)get_post_meta($post_id, '_source_url', true);
            if ($source_url !== '') echo '<link rel="canonical" href="' . esc_url($source_url) . "\" />\n";
            return;
        }

        if ($mode === 'canonical_self') {
            echo '<link rel="canonical" href="' . esc_url(get_permalink($post_id)) . "\" />\n";
        }
    }

    private function get_category_canonical_url(int $term_id): string
    {
        $term_meta_url = esc_url_raw((string)get_term_meta($term_id, '_source_category_canonical_url', true));
        if ($term_meta_url !== '') {
            return $term_meta_url;
        }

        $mappings = (array)get_option(self::OPT_MAPPINGS, []);
        foreach ($mappings as $row) {
            if (!is_array($row)) continue;
            $mapped = (int)($row['wp_category'] ?? 0);
            $url = esc_url_raw((string)($row['canonical_url'] ?? ''));
            if ($mapped === $term_id && $url !== '') {
                return $url;
            }
        }
        return '';
    }

    public function add_source_tracking_post_class(array $classes, $class = '', $post_id = 0): array
    {
        $post_id = (int)$post_id;
        if ($post_id > 0 && (int)get_post_meta($post_id, '_source_imported', true) === 1) {
            $classes[] = 'source-dp-item';
            $classes[] = 'source-dp-native-item';
            $classes[] = 'source-dp-post-id-' . $post_id;
        }
        return array_values(array_unique(array_filter($classes)));
    }

    public function output_custom_css(): void
    {
        $css = (string)get_option(self::OPT_CUSTOM_CSS, '');
        $css = trim($css);
        if ($css === '') {
            return;
        }
        echo "\n<style id=\"source-dp-custom-css\">\n" . $css . "\n</style>\n";
    }

    public function enqueue_front_assets(): void
    {
        if (is_admin()) return;

        $post_id = 0;
        if (is_singular()) {
            $queried_id = (int)get_queried_object_id();
            if ($queried_id > 0 && (int)get_post_meta($queried_id, '_source_imported', true) === 1) {
                $post_id = $queried_id;
            }
        }

        $archive_posts = $this->get_visible_source_posts_for_tracking();

        $needs_script = $post_id > 0 || !empty($archive_posts) || (bool)get_option(self::OPT_ENABLE_IMPRESSION_TRACK, true) || (bool)get_option(self::OPT_ENABLE_CLICK_TRACK, true);
        if (!$needs_script) return;

        wp_enqueue_script(
            'source-dp-frontend',
            plugin_dir_url(__FILE__) . 'frontend.js',
            [],
            self::VERSION,
            true
        );

        wp_localize_script('source-dp-frontend', 'SourceDPFront', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'postId' => $post_id,
            'archivePosts' => $archive_posts,
            'enableTrack' => (bool)get_option(self::OPT_ENABLE_VIDEO_TRACK, true),
            'externalLinkBehavior' => (string)get_option(self::OPT_EXTERNAL_LINK_BEHAVIOR, 'modal'),
            'enableImpressions' => (bool)get_option(self::OPT_ENABLE_IMPRESSION_TRACK, true),
            'enableClicks' => (bool)get_option(self::OPT_ENABLE_CLICK_TRACK, true),
        ]);

        if ($post_id <= 0) return;
        if (!(bool)get_option(self::OPT_ENABLE_VIDEO_TRACK, true)) return;
        if ((int)get_post_meta($post_id, '_source_has_youtube', true) !== 1) return;

        wp_enqueue_script(
            'source-dp-video',
            plugin_dir_url(__FILE__) . 'video-tracker.js',
            [],
            self::VERSION,
            true
        );

        wp_localize_script('source-dp-video', 'SourceDPVideo', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'postId' => $post_id,
        ]);
    }

    private function get_visible_source_posts_for_tracking(): array
    {
        global $wp_query;
        $items = [];
        if (!($wp_query instanceof WP_Query) || empty($wp_query->posts) || !is_array($wp_query->posts)) {
            return $items;
        }

        foreach ($wp_query->posts as $post) {
            $pid = is_object($post) && isset($post->ID) ? (int)$post->ID : (int)$post;
            if ($pid <= 0 || (int)get_post_meta($pid, '_source_imported', true) !== 1) {
                continue;
            }
            $items[] = [
                'id' => $pid,
                'url' => get_permalink($pid),
            ];
            if (count($items) >= 50) {
                break;
            }
        }
        return $items;
    }

    public function ajax_resolve_links(): void
    {
        $nonce = isset($_POST['nonce']) ? (string)$_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => 'Invalid nonce.'], 403);
        }

        if (!(bool)get_option(self::OPT_ENABLE_IMPRESSION_TRACK, true) && !(bool)get_option(self::OPT_ENABLE_CLICK_TRACK, true)) {
            wp_send_json_success(['items' => []]);
        }

        $raw = isset($_POST['urls']) ? wp_unslash($_POST['urls']) : '[]';
        if (is_array($raw)) {
            $urls = $raw;
        } else {
            $decoded = json_decode((string)$raw, true);
            $urls = is_array($decoded) ? $decoded : [];
        }

        $urls = array_values(array_unique(array_filter(array_map(static function ($url) {
            $url = esc_url_raw((string)$url);
            return $url !== '' ? $url : null;
        }, $urls))));

        $items = [];
        foreach (array_slice($urls, 0, 120) as $url) {
            $pid = url_to_postid($url);
            if ($pid <= 0) {
                $path = wp_parse_url($url, PHP_URL_PATH);
                if (is_string($path) && $path !== '') {
                    $pid = url_to_postid(home_url($path));
                }
            }
            $pid = (int)$pid;
            if ($pid <= 0 || (int)get_post_meta($pid, '_source_imported', true) !== 1) {
                continue;
            }
            $items[] = [
                'id' => $pid,
                'url' => get_permalink($pid),
                'requestedUrl' => $url,
            ];
        }

        wp_send_json_success(['items' => $items]);
    }

    public function ajax_track_impression(): void
    {
        $nonce = isset($_POST['nonce']) ? (string)$_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) wp_send_json_error(['message' => 'Invalid nonce.'], 403);
        if (!(bool)get_option(self::OPT_ENABLE_IMPRESSION_TRACK, true)) wp_send_json_error(['message' => 'Impression tracking disabled.']);

        $post_id = isset($_POST['postId']) ? (int)$_POST['postId'] : 0;
        $resp = $this->record_source_metric($post_id, 'impressions', '_source_impressions');
        if (is_wp_error($resp)) {
            wp_send_json_success(['message' => 'Impression recorded locally. Source sync failed: ' . $resp->get_error_message(), 'source_synced' => false]);
        }
        wp_send_json_success(['message' => 'Impression recorded.', 'source_synced' => true]);
    }

    public function ajax_track_click(): void
    {
        $nonce = isset($_POST['nonce']) ? (string)$_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) wp_send_json_error(['message' => 'Invalid nonce.'], 403);
        if (!(bool)get_option(self::OPT_ENABLE_CLICK_TRACK, true)) wp_send_json_error(['message' => 'Click tracking disabled.']);

        $post_id = isset($_POST['postId']) ? (int)$_POST['postId'] : 0;
        $resp = $this->record_source_metric($post_id, 'clicks', '_source_clicks');
        if (is_wp_error($resp)) {
            wp_send_json_success(['message' => 'Click recorded locally. Source sync failed: ' . $resp->get_error_message(), 'source_synced' => false]);
        }
        wp_send_json_success(['message' => 'Click recorded.', 'source_synced' => true]);
    }

    public function ajax_pageview(): void
    {
        $nonce = isset($_POST['nonce']) ? (string)$_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) wp_send_json_error(['message' => 'Invalid nonce.'], 403);

        $post_id = isset($_POST['postId']) ? (int)$_POST['postId'] : 0;
        $resp = $this->record_source_metric($post_id, 'views', '_source_pageviews', true);
        if (is_wp_error($resp)) {
            wp_send_json_success(['message' => 'Pageview recorded locally. Source sync failed: ' . $resp->get_error_message(), 'source_synced' => false]);
        }
        wp_send_json_success(['message' => 'Pageview recorded.', 'source_synced' => true]);
    }

    public function ajax_video_view(): void
    {
        $nonce = isset($_POST['nonce']) ? (string)$_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) wp_send_json_error(['message' => 'Invalid nonce.'], 403);

        $post_id = isset($_POST['postId']) ? (int)$_POST['postId'] : 0;
        if ($post_id <= 0) wp_send_json_error(['message' => 'Missing postId.']);

        if ((int)get_post_meta($post_id, '_source_imported', true) !== 1) wp_send_json_error(['message' => 'Not a Source post.']);

        $resp = $this->record_source_metric($post_id, 'video_views', '_source_video_views', false);
        if (is_wp_error($resp)) {
            wp_send_json_error(['message' => $resp->get_error_message()]);
        }

        wp_send_json_success(['message' => 'Media play recorded locally.', 'source_synced' => false]);
    }

    public function shortcode_tabs(array $atts): string
    {
        $atts = shortcode_atts(['per_page' => 12], $atts, 'source_dp_tabs');
        $per_page = max(1, min(50, (int)$atts['per_page']));
        $id = 'source-dp-tabs-' . wp_generate_uuid4();

        ob_start(); ?>
        <div class="source-dp-tabs" id="<?php echo esc_attr($id); ?>">
            <div class="source-dp-tab-buttons" style="display:flex;gap:10px;flex-wrap:wrap;margin:0 0 16px 0;">
                <button type="button" class="source-dp-tab-btn" data-tab="all">ALL</button>
                <button type="button" class="source-dp-tab-btn" data-tab="podcast">PODCASTS</button>
                <button type="button" class="source-dp-tab-btn" data-tab="video">VIDEOS</button>
                <button type="button" class="source-dp-tab-btn" data-tab="articles">ARTICLES</button>
            </div>
            <div class="source-dp-tab-panels">
                <div class="source-dp-tab-panel" data-tab="all"><?php echo $this->shortcode_feed(['type' => 'all', 'per_page' => $per_page]); ?></div>
                <div class="source-dp-tab-panel" data-tab="podcast" style="display:none;"><?php echo $this->shortcode_feed(['type' => 'podcast', 'per_page' => $per_page]); ?></div>
                <div class="source-dp-tab-panel" data-tab="video" style="display:none;"><?php echo $this->shortcode_feed(['type' => 'video', 'per_page' => $per_page]); ?></div>
                <div class="source-dp-tab-panel" data-tab="articles" style="display:none;"><?php echo $this->shortcode_feed(['type' => 'articles', 'per_page' => $per_page]); ?></div>
            </div>
        </div>
        <script>
        (function(){
            var root = document.getElementById(<?php echo json_encode($id); ?>);
            if(!root) return;
            var btns = root.querySelectorAll('.source-dp-tab-btn');
            var panels = root.querySelectorAll('.source-dp-tab-panel');
            function show(tab){
                panels.forEach(function(p){ p.style.display = (p.getAttribute('data-tab')===tab) ? '' : 'none'; });
                btns.forEach(function(b){
                    var active = b.getAttribute('data-tab')===tab;
                    b.style.fontWeight = active ? '700' : '400';
                });
            }
            btns.forEach(function(b){ b.addEventListener('click', function(){ show(b.getAttribute('data-tab')); }); });
            show('all');
        })();
        </script>
        <?php
        return (string)ob_get_clean();
    }

    public function shortcode_feed(array $atts): string
    {
        $atts = shortcode_atts([
            'per_page' => 10,
            'post_type' => '',
            'type' => 'all',
            'latest' => 0,
            'category_id' => '',
            'show_image' => 1,
            'show_excerpt' => 1,
        ], $atts, 'source_dp_feed');

        wp_enqueue_script(
            'source-dp-frontend',
            plugin_dir_url(__FILE__) . 'frontend.js',
            [],
            self::VERSION,
            true
        );
        wp_localize_script('source-dp-frontend', 'SourceDPFront', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'postId' => 0,
            'enableTrack' => (bool)get_option(self::OPT_ENABLE_VIDEO_TRACK, true),
            'externalLinkBehavior' => (string)get_option(self::OPT_EXTERNAL_LINK_BEHAVIOR, 'modal'),
            'enableImpressions' => (bool)get_option(self::OPT_ENABLE_IMPRESSION_TRACK, true),
            'enableClicks' => (bool)get_option(self::OPT_ENABLE_CLICK_TRACK, true),
        ]);

        $per_page = max(1, min(50, (int)$atts['per_page']));
        $post_type = $atts['post_type'] !== '' ? sanitize_key((string)$atts['post_type']) : (string)get_option(self::OPT_POST_TYPE, 'post');

        $meta_query = [
            ['key' => '_source_imported', 'value' => 1, 'compare' => '='],
        ];

        if ($atts['category_id'] !== '') {
            $meta_query[] = ['key' => '_source_category_id', 'value' => $this->sanitize_text_field_relaxed((string)$atts['category_id']), 'compare' => '='];
        }

        $type = sanitize_key((string)$atts['type']);
        $type_id = null;
        if ($type === 'video') $type_id = '1';
        elseif ($type === 'podcast') $type_id = '2';
        elseif ($type === 'articles' || $type === 'article') $type_id = '3';

        if ($type_id !== null) {
            $meta_query[] = ['key' => '_source_content_type_id', 'value' => $type_id, 'compare' => '='];
        }

        $orderby = 'date';
        $order = 'DESC';
        $meta_key = '';

        if ((int)$atts['latest'] === 1) {
            $orderby = 'meta_value';
            $order = 'DESC';
            $meta_key = '_source_posted_at';
        }

        $paged = max(1, (int)get_query_var('paged', 1));

        $qargs = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'meta_query' => $meta_query,
            'orderby' => $orderby,
            'order' => $order,
        ];
        if ($meta_key !== '') $qargs['meta_key'] = $meta_key;

        $q = new WP_Query($qargs);
        if (!$q->have_posts()) {
            return '<p>No Source content found.</p>';
        }

        ob_start();
        echo '<div class="source-dp-feed">';
        while ($q->have_posts()) {
            $q->the_post();
            $pid = get_the_ID();
            $feed_title = (string)get_post_meta($pid, '_source_feed_title', true);

            echo '<article class="source-dp-item" data-source-post-id="' . esc_attr((string)$pid) . '" style="margin:0 0 18px 0;">';

            if ((int)$atts['show_image'] === 1 && has_post_thumbnail()) {
                echo '<div style="margin:0 0 8px 0;">';
                echo get_the_post_thumbnail($pid, 'medium');
                echo '</div>';
            }

            echo '<h3 style="margin:0 0 6px 0;">' . esc_html(get_the_title()) . '</h3>';

            if ($feed_title !== '') {
                echo '<div style="font-size:0.9em;opacity:0.8;margin:0 0 6px 0;">' . esc_html($feed_title) . '</div>';
            }

            if ((int)$atts['show_excerpt'] === 1) {
                echo '<div style="margin:0 0 6px 0;">' . esc_html(wp_strip_all_tags(get_the_excerpt())) . '</div>';
            }

            echo '<div><a data-source-dp-click-post="' . esc_attr((string)$pid) . '" href="' . esc_url(get_permalink()) . '">Read</a></div>';
            echo '</article>';
        }
        wp_reset_postdata();
        echo '</div>';

        if ($q->max_num_pages > 1) {
            echo '<div class="source-dp-pagination">';
            echo paginate_links(['total' => $q->max_num_pages, 'current' => $paged]);
            echo '</div>';
        }

        return (string)ob_get_clean();
    }

    private function get_approved_categories_for_bundle(string $uuid, array $bundle, ?string &$source = null)
    {
        $source = '';

        $bundle_candidates = $this->extract_category_candidates_from_bundle($bundle);
        if (!empty($bundle_candidates)) {
            $normalized = $this->normalize_categories($bundle_candidates, true);
            if (!empty($normalized)) {
                $source = 'bundle payload approved category fields';
                return $normalized;
            }
        }

        $cats = $this->api_get_categories($uuid);
        if (is_wp_error($cats)) {
            return $cats;
        }

        $normalized = $this->normalize_categories(is_array($cats) ? $cats : [], true);
        $source = $this->category_list_has_approval_markers(is_array($cats) ? $cats : [])
            ? 'bundle/categories filtered by approved/enabled fields'
            : 'bundle/categories endpoint';

        return $normalized;
    }

    private function extract_category_candidates_from_bundle(array $bundle, int $depth = 0): array
    {
        if ($depth > 3) {
            return [];
        }

        $preferred_keys = [
            'approvedCategories', 'approved_categories', 'approvedTopics', 'approved_topics',
            'selectedCategories', 'selected_categories', 'selectedTopics', 'selected_topics',
            'categories', 'topics', 'categoryMatches', 'category_matches', 'topicMatches', 'topic_matches',
        ];

        foreach ($preferred_keys as $key) {
            if (!isset($bundle[$key]) || !is_array($bundle[$key])) {
                continue;
            }
            if ($this->looks_like_category_list($bundle[$key])) {
                return $bundle[$key];
            }
            $nested = $this->extract_category_candidates_from_bundle($bundle[$key], $depth + 1);
            if (!empty($nested)) {
                return $nested;
            }
        }

        foreach ($bundle as $key => $value) {
            if (!is_array($value) || !is_string($key)) {
                continue;
            }
            $lower = strtolower($key);
            if (strpos($lower, 'categor') === false && strpos($lower, 'topic') === false && strpos($lower, 'integration') === false && strpos($lower, 'bundle') === false) {
                continue;
            }
            if ($this->looks_like_category_list($value)) {
                return $value;
            }
            $nested = $this->extract_category_candidates_from_bundle($value, $depth + 1);
            if (!empty($nested)) {
                return $nested;
            }
        }

        return [];
    }

    private function looks_like_category_list(array $items): bool
    {
        $checked = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $checked++;
            if (isset($item['id']) && (isset($item['name']) || isset($item['title']) || isset($item['label']))) {
                return true;
            }
            if ($checked >= 5) {
                break;
            }
        }
        return false;
    }

    private function category_list_has_approval_markers(array $cats): bool
    {
        foreach ($cats as $cat) {
            if (is_array($cat) && $this->category_has_approval_marker($cat)) {
                return true;
            }
        }
        return false;
    }

    private function category_has_approval_marker(array $cat): bool
    {
        foreach (['approved', 'isApproved', 'is_approved', 'selected', 'isSelected', 'is_selected', 'included', 'isIncluded', 'is_included', 'enabled', 'active', 'visible', 'status', 'state'] as $key) {
            if (array_key_exists($key, $cat)) {
                return true;
            }
        }
        return false;
    }

    private function category_is_approved(array $cat): bool
    {
        foreach (['approved', 'isApproved', 'is_approved', 'selected', 'isSelected', 'is_selected', 'included', 'isIncluded', 'is_included', 'enabled', 'active', 'visible'] as $key) {
            if (array_key_exists($key, $cat)) {
                $value = $cat[$key];
                if (is_bool($value)) {
                    return $value;
                }
                if (is_numeric($value)) {
                    return ((int)$value) === 1;
                }
                $text = strtolower(trim((string)$value));
                return in_array($text, ['1', 'true', 'yes', 'approved', 'selected', 'enabled', 'active', 'included'], true);
            }
        }

        foreach (['status', 'state'] as $key) {
            if (array_key_exists($key, $cat)) {
                $text = strtolower(trim((string)$cat[$key]));
                return in_array($text, ['approved', 'selected', 'enabled', 'active', 'included', 'published'], true);
            }
        }

        return true;
    }

    private function normalize_categories(array $cats, bool $filter_approved_if_possible = false): array
    {
        $out = [];
        $has_approval_markers = $filter_approved_if_possible && $this->category_list_has_approval_markers($cats);

        foreach ($cats as $cat) {
            if (!is_array($cat)) continue;
            if ($has_approval_markers && !$this->category_is_approved($cat)) continue;

            $id = $this->sanitize_text_field_relaxed((string)($cat['id'] ?? ($cat['categoryId'] ?? ($cat['topicId'] ?? ''))));
            $name = $this->sanitize_text_field_relaxed((string)($cat['name'] ?? ($cat['title'] ?? ($cat['label'] ?? ''))));
            if ($id === '' && $name === '') continue;
            $out[] = ['id' => $id !== '' ? $id : $name, 'name' => $name !== '' ? $name : $id];
        }
        return $out;
    }

    public function sanitize_uuid($value): string
    {
        $value = is_string($value) ? trim($value) : '';
        $value = strtolower($value);
        $value = preg_replace('/[^a-f0-9\-]/', '', $value) ?? '';
        return substr($value, 0, 64);
    }

    public function sanitize_post_type($value): string
    {
        $value = is_string($value) ? sanitize_key($value) : 'post';
        $pts = get_post_types(['public' => true], 'names');
        return in_array($value, $pts, true) ? $value : 'post';
    }

    public function sanitize_text_field_relaxed($value): string
    {
        $value = is_string($value) ? wp_strip_all_tags($value) : '';
        $value = trim($value);
        return substr($value, 0, 120);
    }

    public function sanitize_auto_import_interval($value): string
    {
        $value = is_string($value) ? sanitize_key($value) : 'daily';
        $allowed = ['manual', 'hourly', 'twicedaily', 'daily', 'source_dp_weekly'];
        return in_array($value, $allowed, true) ? $value : 'daily';
    }

    public function sanitize_auto_import_categories($value): array
    {
        if (!is_array($value)) return [];
        $out = [];
        foreach ($value as $id) {
            $id = preg_replace('/[^0-9]/', '', (string)$id);
            if ($id !== '') $out[] = $id;
        }
        return array_values(array_unique($out));
    }

    public function sanitize_custom_css($value): string
    {
        $value = is_string($value) ? $value : '';
        $value = wp_kses_no_null($value);
        $value = preg_replace('#</?style[^>]*>#i', '', $value);
        $value = wp_strip_all_tags($value);
        return trim(substr($value, 0, 50000));
    }

    public function sanitize_mappings($value): array
    {
        if (!is_array($value)) return [];
        $out = [];
        foreach ($value as $source_cat_id => $row) {
            $source_cat_id = $this->sanitize_text_field_relaxed((string)$source_cat_id);
            if ($source_cat_id === '' || !is_array($row)) continue;
            $out[$source_cat_id] = [
                'include' => !empty($row['include']),
                'wp_category' => max(0, (int)($row['wp_category'] ?? 0)),
                'create_new' => $this->sanitize_text_field_relaxed((string)($row['create_new'] ?? '')),
                'canonical_url' => esc_url_raw((string)($row['canonical_url'] ?? '')), 
            ];
        }
        return $out;
    }

    public function sanitize_bundle_cache($value): array
    {
        if (!is_array($value)) return [];
        $bundle = isset($value['bundle']) && is_array($value['bundle']) ? $value['bundle'] : [];
        $categories = isset($value['categories']) && is_array($value['categories']) ? $value['categories'] : [];
        $counts = isset($value['counts']) && is_array($value['counts']) ? $value['counts'] : [];
        $content_type_counts = isset($value['content_type_counts']) && is_array($value['content_type_counts']) ? $value['content_type_counts'] : [];
        $flags = isset($bundle['flags']) && is_array($bundle['flags']) ? $bundle['flags'] : [];
        return [
            'bundle' => [
                'name' => $this->sanitize_text_field_relaxed((string)($bundle['name'] ?? '')),
                'uuid' => $this->sanitize_uuid((string)($bundle['uuid'] ?? '')),
                'flags' => [
                    'blog' => !empty($flags['blog']) ? 1 : 0,
                    'podcast' => !empty($flags['podcast']) ? 1 : 0,
                    'video' => !empty($flags['video']) ? 1 : 0,
                ],
            ],
            'categories' => $this->normalize_categories($categories),
            'counts' => $counts,
            'content_type_counts' => [
                'blog' => isset($content_type_counts['blog']) && is_numeric($content_type_counts['blog']) ? (int)$content_type_counts['blog'] : 0,
                'podcast' => isset($content_type_counts['podcast']) && is_numeric($content_type_counts['podcast']) ? (int)$content_type_counts['podcast'] : 0,
                'video' => isset($content_type_counts['video']) && is_numeric($content_type_counts['video']) ? (int)$content_type_counts['video'] : 0,
            ],
            'fetched_at' => (int)($value['fetched_at'] ?? time()),
        ];
    }
}


/**
 * Lightweight GitHub Releases updater for the private/public Source Content Distribution plugin.
 *
 * Production release workflow:
 * 1. Push the plugin source to https://github.com/Syncrony-Magento/source-distribution-partner
 * 2. Create a GitHub release/tag with a version higher than the installed plugin, for example v1.4.74.
 * 3. Attach an installable plugin ZIP asset to that release. Recommended asset name: source-distribution-partner.zip
 * 4. WordPress will show the normal Plugins > Update now link when the release version is newer.
 */
final class Source_Distribution_Partner_GitHub_Updater
{
    private const REPO_OWNER = 'Syncrony-Magento';
    private const REPO_NAME = 'source-distribution-partner';
    private const CACHE_KEY = 'source_dp_github_latest_release';
    private const CACHE_TTL = 1800;

    private string $plugin_file;
    private string $plugin_basename;
    private string $slug;
    private string $version;
    private string $repo_owner;
    private string $repo_name;

    public function __construct(string $plugin_file, string $version)
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->slug = dirname($this->plugin_basename);
        $this->version = $version;
        $this->repo_owner = (string)apply_filters('source_dp_github_repo_owner', self::REPO_OWNER);
        $this->repo_name = (string)apply_filters('source_dp_github_repo_name', self::REPO_NAME);
    }

    public static function init(string $plugin_file, string $version): void
    {
        $updater = new self($plugin_file, $version);
        add_filter('pre_set_site_transient_update_plugins', [$updater, 'inject_update']);
        add_filter('plugins_api', [$updater, 'plugin_information'], 20, 3);
        add_filter('upgrader_source_selection', [$updater, 'normalize_package_folder'], 10, 4);
    }

    public function inject_update($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }

        if (empty($transient->checked) || !isset($transient->checked[$this->plugin_basename])) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if (!$release) {
            return $transient;
        }

        $update = $this->build_update_object($release);
        if (!$update) {
            return $transient;
        }

        if (version_compare($this->version, $release['version'], '<')) {
            $transient->response[$this->plugin_basename] = $update;
            unset($transient->no_update[$this->plugin_basename]);
        } else {
            $transient->no_update[$this->plugin_basename] = $update;
            unset($transient->response[$this->plugin_basename]);
        }

        return $transient;
    }

    public function plugin_information($result, string $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }

        $release = $this->get_latest_release();
        if (!$release) {
            return $result;
        }

        return (object)[
            'name' => 'Source Content Distribution',
            'slug' => $this->slug,
            'version' => $release['version'],
            'author' => '<a href="https://syncrony.com">Syncrony Digital</a>',
            'author_profile' => 'https://syncrony.com',
            'homepage' => $release['html_url'],
            'download_link' => $release['package'],
            'requires' => '6.0',
            'tested' => $release['tested'],
            'requires_php' => '7.4',
            'last_updated' => $release['published_at'],
            'sections' => [
                'description' => 'Pull approved content from Source bundles and publish to WordPress with taxonomy mapping.',
                'changelog' => $release['body'] !== '' ? wp_kses_post(wpautop($release['body'])) : 'See the GitHub release notes for details.',
            ],
        ];
    }

    public function normalize_package_folder($source, $remote_source, $upgrader, $hook_extra)
    {
        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $source;
        }

        $source = untrailingslashit((string)$source);
        $remote_source = untrailingslashit((string)$remote_source);
        $target = $remote_source . '/' . $this->slug;

        if ($source === $target || !is_dir($source)) {
            return $source;
        }

        global $wp_filesystem;
        if ($wp_filesystem && method_exists($wp_filesystem, 'move')) {
            if (is_dir($target)) {
                $wp_filesystem->delete($target, true);
            }
            if ($wp_filesystem->move($source, $target, true)) {
                return $target;
            }
        }

        return $source;
    }

    private function get_latest_release(bool $force = false): ?array
    {
        if (!$force) {
            $cached = get_site_transient(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', rawurlencode($this->repo_owner), rawurlencode($this->repo_name));
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; Source Content Distribution',
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = (int)wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $data = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['tag_name'])) {
            return null;
        }

        if (!empty($data['draft']) || !empty($data['prerelease'])) {
            return null;
        }

        $version = ltrim((string)$data['tag_name'], 'vV');
        if ($version === '') {
            return null;
        }

        $package = $this->find_release_package($data);
        if ($package === '') {
            return null;
        }

        $release = [
            'version' => $version,
            'package' => $package,
            'html_url' => esc_url_raw((string)($data['html_url'] ?? sprintf('https://github.com/%s/%s/releases/latest', $this->repo_owner, $this->repo_name))),
            'published_at' => sanitize_text_field((string)($data['published_at'] ?? '')),
            'body' => (string)($data['body'] ?? ''),
            'tested' => '6.8',
        ];

        set_site_transient(self::CACHE_KEY, $release, self::CACHE_TTL);
        return $release;
    }

    private function find_release_package(array $release): string
    {
        $preferred_names = (array)apply_filters('source_dp_github_release_asset_names', [
            $this->slug . '.zip',
            $this->slug . '-latest.zip',
        ]);

        $assets = isset($release['assets']) && is_array($release['assets']) ? $release['assets'] : [];

        foreach ($preferred_names as $preferred) {
            foreach ($assets as $asset) {
                $name = isset($asset['name']) ? (string)$asset['name'] : '';
                if ($name === (string)$preferred && !empty($asset['browser_download_url'])) {
                    return esc_url_raw((string)$asset['browser_download_url']);
                }
            }
        }

        foreach ($assets as $asset) {
            $name = isset($asset['name']) ? (string)$asset['name'] : '';
            if (preg_match('/^' . preg_quote($this->slug, '/') . '(?:[-_].*)?\.zip$/i', $name) && !empty($asset['browser_download_url'])) {
                return esc_url_raw((string)$asset['browser_download_url']);
            }
        }

        // Fallback to GitHub's generated source ZIP for public repositories.
        // The upgrader_source_selection filter above renames the extracted folder back to the plugin slug.
        return !empty($release['zipball_url']) ? esc_url_raw((string)$release['zipball_url']) : '';
    }

    private function build_update_object(array $release): ?object
    {
        if (empty($release['version']) || empty($release['package'])) {
            return null;
        }

        return (object)[
            'id' => 'github.com/' . $this->repo_owner . '/' . $this->repo_name,
            'slug' => $this->slug,
            'plugin' => $this->plugin_basename,
            'new_version' => $release['version'],
            'url' => $release['html_url'],
            'package' => $release['package'],
            'tested' => $release['tested'],
            'requires' => '6.0',
            'requires_php' => '7.4',
            'icons' => [],
            'banners' => [],
        ];
    }
}

register_activation_hook(__FILE__, [Source_Distribution_Partner_Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Source_Distribution_Partner_Plugin::class, 'deactivate']);
Source_Distribution_Partner_GitHub_Updater::init(__FILE__, '1.4.72');
Source_Distribution_Partner_Plugin::init();
