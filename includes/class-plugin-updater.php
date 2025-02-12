<?php
/**
 * Plugin Updater Class
 * Enables automatic updates from GitHub repository
 */

if (!defined('ABSPATH')) {
    exit;
}

class PMAT_Plugin_Updater {
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $github_response;
    private $github_url = 'https://api.github.com/repos/KariukiDave/PMaaS-Assessment/releases/latest';
    private $access_token;

    public function __construct($file) {
        $this->file = $file;
        add_action('admin_init', array($this, 'set_plugin_properties'));
        
        // Check for updates
        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
        
        // Plugin details popup
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        
        // After update tasks
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
    }

    public function set_plugin_properties() {
        $this->plugin   = get_plugin_data($this->file);
        $this->basename = plugin_basename($this->file);
        $this->active   = is_plugin_active($this->basename);
    }

    private function get_repository_info() {
        if (is_null($this->github_response)) {
            $request_uri = $this->github_url;
            
            $response = wp_remote_get($request_uri, array(
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json'
                )
            ));

            if (is_wp_error($response)) {
                return false;
            }

            $response = json_decode(wp_remote_retrieve_body($response));

            if ($response) {
                $this->github_response = $response;
            }
        }
    }

    public function modify_transient($transient) {
        if (property_exists($transient, 'checked')) {
            if ($checked = $transient->checked) {
                $this->get_repository_info();

                if (!$this->github_response) {
                    return $transient;
                }

                $github_version = ltrim($this->github_response->tag_name, 'v');
                $plugin_version = $this->plugin['Version'];

                if (version_compare($github_version, $plugin_version, '>')) {
                    $package = $this->github_response->zipball_url;

                    $obj = new stdClass();
                    $obj->slug = dirname($this->basename);
                    $obj->new_version = $github_version;
                    $obj->url = $this->plugin['PluginURI'];
                    $obj->package = $package;
                    $obj->tested = '6.4.3'; // Update this regularly
                    $obj->requires = '5.0';
                    $obj->requires_php = '7.4';

                    // Add upgrade notice from release body
                    if (!empty($this->github_response->body)) {
                        $obj->upgrade_notice = $this->github_response->body;
                    }

                    $transient->response[$this->basename] = $obj;
                }
            }
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!empty($args->slug)) {
            if ($args->slug == dirname($this->basename)) {
                $this->get_repository_info();

                $plugin = new stdClass();
                $plugin->name = $this->plugin['Name'];
                $plugin->slug = dirname($this->basename);
                $plugin->version = ltrim($this->github_response->tag_name, 'v');
                $plugin->author = $this->plugin['Author'];
                $plugin->author_profile = $this->plugin['AuthorURI'];
                $plugin->requires = '5.0';
                $plugin->tested = '6.4.3';
                $plugin->requires_php = '7.4';
                $plugin->downloaded = 0;
                $plugin->last_updated = $this->github_response->published_at;
                $plugin->sections = array(
                    'description' => $this->plugin['Description'],
                    'changelog' => $this->github_response->body
                );
                $plugin->download_link = $this->github_response->zipball_url;

                return $plugin;
            }
        }

        return $result;
    }

    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        $install_directory = plugin_dir_path($this->file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        if ($this->active) {
            activate_plugin($this->basename);
        }

        return $result;
    }
}