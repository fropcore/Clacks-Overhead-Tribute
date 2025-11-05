<?php
/**
 * Plugin Name: X Clacks Overhead Tribute
 * Description: Sends an "X-Clacks-Overhead" header as a quiet tribute. Defaults to "Ozzy Osbourne (The Prince of Darkness)".
 * Version: 1.0.0
 * Author: Sudo Seagull
 * Author URI: https://www.sudoseagull.com/
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Requires PHP: 7.2
 * Requires at least: 4.9
 * Tested up to: 6.6
 * Text Domain: x-clacks-overhead-tribute
 */

if (!defined('ABSPATH')) { exit; }

class XClacksOverheadTribute {
    const OPTION_KEY = 'xclacks_overhead_options';
    const DEFAULT_TEXT = 'Ozzy "The Prince of Darkness" Osbourne';
    const HEADER_NAME = 'X-Clacks-Overhead';
    const HEADER_NAME_HEX = 'X-Clacks-Overhead-Encoded';

    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('send_headers', [$this, 'send_header']);
        add_filter('rest_post_dispatch', [$this, 'add_rest_headers'], 10, 3);
        // Also add headers during login page requests
        add_action('login_init', [$this, 'maybe_buffer_login_headers']);
    }

    public static function get_options() {
        $defaults = [
            'enabled' => 1,
            'message' => self::DEFAULT_TEXT,
            'send_hex' => 0,
        ];
        $opts = get_option(self::OPTION_KEY, []);
        if (!is_array($opts)) { $opts = []; }
        return wp_parse_args($opts, $defaults);
    }

    public function register_settings() {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => function($raw) {
                $out = [];
                $out['enabled'] = isset($raw['enabled']) ? 1 : 0;
                $out['message'] = isset($raw['message']) ? sanitize_text_field($raw['message']) : self::DEFAULT_TEXT;
                $out['send_hex'] = isset($raw['send_hex']) ? 1 : 0;
                return $out;
            },
            'default' => [
                'enabled' => 1,
                'message' => self::DEFAULT_TEXT,
                'send_hex' => 0,
            ]
        ]);

        add_settings_section('xclacks_main', __('Settings', 'x-clacks-overhead-tribute'), function(){}, self::OPTION_KEY);

        add_settings_field('enabled', __('Enable tribute header', 'x-clacks-overhead-tribute'), function() {
            $o = self::get_options();
            echo '<input type="checkbox" name="'.esc_attr(self::OPTION_KEY).'[enabled]" value="1" '.checked(1, $o['enabled'], false).' />';
        }, self::OPTION_KEY, 'xclacks_main');

        add_settings_field('message', __('Tribute text', 'x-clacks-overhead-tribute'), function() {
            $o = self::get_options();
            echo '<input type="text" class="regular-text" name="'.esc_attr(self::OPTION_KEY).'[message]" value="'.esc_attr($o['message']).'" placeholder="'.esc_attr(self::DEFAULT_TEXT).'" />';
            echo '<p class="description">'.esc_html__('Example: Ozzy "The Prince of Darkness" Osbourne', 'x-clacks-overhead-tribute').'</p>';
        }, self::OPTION_KEY, 'xclacks_main');

        add_settings_field('send_hex', __('Also send hex-encoded header', 'x-clacks-overhead-tribute'), function() {
            $o = self::get_options();
            echo '<input type="checkbox" name="'.esc_attr(self::OPTION_KEY).'[send_hex]" value="1" '.checked(1, $o['send_hex'], false).' />';
            echo '<p class="description">'.esc_html__('Sends an additional "X-Clacks-Overhead-Encoded" header with ASCII hex bytes.', 'x-clacks-overhead-tribute').'</p>';
        }, self::OPTION_KEY, 'xclacks_main');
    }

    public function add_settings_page() {
        add_options_page(
            __('Clacks Tribute', 'x-clacks-overhead-tribute'),
            __('Clacks Tribute', 'x-clacks-overhead-tribute'),
            'manage_options',
            self::OPTION_KEY,
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() {
        echo '<div class="wrap">';
        echo '<h1>'.esc_html__('X-Clacks-Overhead Tribute', 'x-clacks-overhead-tribute').'</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPTION_KEY);
        do_settings_sections(self::OPTION_KEY);
        submit_button();
        echo '</form>';
        echo '<hr/>';
        echo '<p>'.esc_html__('This plugin adds a memorial header to all responses, in the spirit of GNU Terry Pratchett, but customizable (default: Ozzy Osbourne).', 'x-clacks-overhead-tribute').'</p>';
        echo '</div>';
    }

    public function send_header() {
        $o = self::get_options();
        if (!$o['enabled']) return;
        if (!headers_sent()) {
            header(self::HEADER_NAME . ': ' . $o['message'], false);
            if (!empty($o['send_hex'])) {
                header(self::HEADER_NAME_HEX . ': ' . $this->to_hex($o['message']), false);
            }
        }
    }

    public function add_rest_headers($response, $server, $request) {
        $o = self::get_options();
        if ($o['enabled'] && $response instanceof WP_REST_Response) {
            $response->header(self::HEADER_NAME, $o['message']);
            if (!empty($o['send_hex'])) {
                $response->header(self::HEADER_NAME_HEX, $this->to_hex($o['message']));
            }
        }
        return $response;
    }

    // During login flows, WordPress can send headers before 'send_headers'.
    // We ensure our header is present by hooking early and forcing it when possible.
    public function maybe_buffer_login_headers() {
        add_action('login_head', function() {
            $this->send_header();
        }, 1);
    }

    private function to_hex($str) {
        $bytes = unpack('C*', $str);
        $hex = array_map(function($b){ return strtoupper(str_pad(dechex($b), 2, '0', STR_PAD_LEFT)); }, $bytes);
        return implode(' ', $hex);
    }
}

new XClacksOverheadTribute();
