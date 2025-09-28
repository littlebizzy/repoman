<?php
/*
Plugin Name: RepoMan
Plugin URI: https://www.littlebizzy.com/plugins/repoman
Description: Install public repos to WordPress
Version: 1.8.8
Requires PHP: 7.0
Tested up to: 6.8
Author: LittleBizzy
Author URI: https://www.littlebizzy.com
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Update URI: false
GitHub Plugin URI: littlebizzy/repoman
Primary Branch: master
Text Domain: repoman
*/

// prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// override wordpress.org with git updater
add_filter( 'gu_override_dot_org', function( $overrides ) {
    $overrides[] = 'repoman/repoman.php';
    return $overrides;
}, 999 );

// scan main plugin file for 'GitHub Plugin URI' string
function scan_plugin_main_file_for_github_uri( $plugin_file ) {
    $plugin_file_path = WP_PLUGIN_DIR . '/' . $plugin_file;

    // check if file exists and is readable
    if ( ! file_exists( $plugin_file_path ) || ! is_readable( $plugin_file_path ) ) {
        error_log( 'Plugin file does not exist or is not readable: ' . $plugin_file_path );
        return false;
    }

    // read file contents
    $file_content = @file_get_contents( $plugin_file_path );

    // check if reading failed
    if ( $file_content === false ) {
        error_log( 'Failed to read plugin file: ' . $plugin_file_path );
        return false;
    }

    // look for expected header in file contents
    return strpos( $file_content, 'GitHub Plugin URI' ) !== false;
}

// scan main plugin file for 'Update URI' string
function scan_plugin_main_file_for_update_uri( $plugin_file ) {
    $plugin_file_path = WP_PLUGIN_DIR . '/' . $plugin_file;

    // check if file exists and is readable
    if ( ! file_exists( $plugin_file_path ) || ! is_readable( $plugin_file_path ) ) {
        error_log( 'Plugin file does not exist or is not readable: ' . $plugin_file_path );
        return false;
    }

    // read file contents
    $file_content = @file_get_contents( $plugin_file_path );

    // check if reading failed
    if ( $file_content === false ) {
        error_log( 'Failed to read plugin file: ' . $plugin_file_path );
        return false;
    }

    // look for expected header in file contents
    return strpos( $file_content, 'Update URI' ) !== false;
}

// array of specific plugin slugs to block updates
function get_blocked_plugin_slugs() {
    return array(
        'repoman',
        'git-updater',
        'wpe-secure-updater',
        'advanced-custom-fields',
        'plugin-update-checker', // add more slugs as needed
    );
}

// disable WordPress.org for plugins with 'GitHub Plugin URI', 'Update URI', and specified slugs
function dynamic_block_plugin_updates( $overrides ) {
    // get all installed plugins
    $all_plugins = get_plugins();

    // get array of blocked slugs
    $blocked_slugs = get_blocked_plugin_slugs();

    // loop through each plugin
    foreach ( $all_plugins as $plugin_file => $plugin_data ) {
        // get plugin slug from its path
        $slug = dirname( $plugin_file );

        // check if file has relevant header or matches a blocked slug
        if ( scan_plugin_main_file_for_github_uri( $plugin_file ) || scan_plugin_main_file_for_update_uri( $plugin_file ) || in_array( $slug, $blocked_slugs, true ) ) {
            $overrides[] = $plugin_file;
        }
    }

    return $overrides;
}
add_filter( 'gu_override_dot_org', 'dynamic_block_plugin_updates', 999 );

// apply blocklist even if plugins are deactivated
function dynamic_block_deactivated_plugin_updates( $transient ) {
    // get override list from filter
    $overrides = apply_filters( 'gu_override_dot_org', [] );

    // remove matching plugins from update response
    foreach ( $overrides as $plugin ) {
        if ( isset( $transient->response[ $plugin ] ) ) {
            unset( $transient->response[ $plugin ] );
        }
    }

    return $transient;
}
add_filter( 'site_transient_update_plugins', 'dynamic_block_deactivated_plugin_updates' );

// fetch plugin data from json file with safe handling and fallback values
function repoman_get_plugins_data() {
    // get plugin directory path
    $plugin_dir = plugin_dir_path( __FILE__ );

    // get resolved path of the json file
    $file = realpath( $plugin_dir . 'plugin-repos.json' );

    // check if file exists and is inside the plugin directory
    if ( ! $file || strpos( $file, realpath( $plugin_dir ) ) !== 0 ) {
        return new WP_Error( 'file_missing', __( 'Error: the plugin-repos.json file is missing or outside the plugin directory', 'repoman' ) );
    }

    // try reading json file contents
    $content = @file_get_contents( $file );

    // check if json reading failed
    if ( $content === false ) {
        return new WP_Error( 'file_unreadable', __( 'Error: the plugin-repos.json file could not be read', 'repoman' ) );
    }

    // decode json contents
    $plugins = json_decode( $content, true );

    // check for json decode errors
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return new WP_Error( 'file_malformed', sprintf( __( 'Error: the plugin-repos.json file is malformed (%s)', 'repoman' ), json_last_error_msg() ) );
    }

    // check if decoded array is empty
    if ( empty( $plugins ) ) {
        return new WP_Error( 'file_empty', __( 'Error: the plugin-repos.json file is empty or contains no plugins', 'repoman' ) );
    }

    // sanitize and fill missing fields
    foreach ( $plugins as &$plugin ) {
        $plugin['slug'] = isset( $plugin['slug'] ) ? sanitize_title( $plugin['slug'] ) : 'unknown-slug';
        $plugin['repo'] = isset( $plugin['repo'] ) ? sanitize_text_field( $plugin['repo'] ) : '';
        $plugin['name'] = isset( $plugin['name'] ) ? sanitize_text_field( $plugin['name'] ) : __( 'unknown plugin', 'repoman' );
        $plugin['description'] = isset( $plugin['description'] ) ? wp_kses_post( $plugin['description'] ) : __( 'no description available', 'repoman' );
        $plugin['author'] = isset( $plugin['author'] ) ? sanitize_text_field( $plugin['author'] ) : __( 'unknown author', 'repoman' );
        $plugin['author_url'] = isset( $plugin['author_url'] ) ? esc_url_raw( $plugin['author_url'] ) : '#';
        $plugin['icon_url'] = isset( $plugin['icon_url'] ) ? esc_url_raw( $plugin['icon_url'] ) : '';
        $plugin['rating'] = isset( $plugin['rating'] ) ? intval( $plugin['rating'] ) : 0;
        $plugin['num_ratings'] = isset( $plugin['num_ratings'] ) ? intval( $plugin['num_ratings'] ) : 0;
        $plugin['active_installs'] = isset( $plugin['active_installs'] ) ? intval( $plugin['active_installs'] ) : 0;
        $plugin['compatible'] = isset( $plugin['compatible'] ) ? (bool) $plugin['compatible'] : false;
        $plugin['last_updated'] = isset( $plugin['last_updated'] ) ? sanitize_text_field( $plugin['last_updated'] ) : __( 'unknown', 'repoman' );
    }

    // return the plugin array
    return $plugins;
}

// fetch plugin data with caching using transients
function repoman_get_plugins_data_with_cache() {
    // get cached plugin data
    $plugins = get_transient( 'repoman_plugins' );

    // fetch fresh data if cache is missing
    if ( $plugins === false ) {
        $plugins = repoman_get_plugins_data();

        // set transient if no error
        if ( ! is_wp_error( $plugins ) ) {
            set_transient( 'repoman_plugins', $plugins, HOUR_IN_SECONDS );
        } else {
            // log error message
            error_log( 'RepoMan Error: ' . $plugins->get_error_message() );
        }
    }

    return $plugins;
}

// handle plugin information requests
function repoman_plugins_api_handler( $result, $action, $args ) {
    // check if action is for plugin information
    if ( $action !== 'plugin_information' ) {
        return $result;
    }

    // ensure args is an object and has a slug
    if ( ! is_object( $args ) || ! isset( $args->slug ) ) {
        return $result;
    }

    // fetch plugin data from cache
    $plugins = repoman_get_plugins_data_with_cache();

    // return original result if data is missing or invalid
    if ( is_wp_error( $plugins ) || empty( $plugins ) ) {
        return $result;
    }

    // look for matching plugin slug
    foreach ( $plugins as $plugin ) {
        if ( $plugin['slug'] === $args->slug ) {
            // prepare plugin response
            $plugin_info = repoman_prepare_plugin_information( $plugin );

            // cache slug temporarily
            set_transient( 'repoman_installing_plugin', $plugin['slug'], 15 * MINUTE_IN_SECONDS );

            return (object) $plugin_info;
        }
    }

    // return original result if no match found
    return $result;
}
add_filter( 'plugins_api', 'repoman_plugins_api_handler', 99, 3 );

// prepare plugin information for installer response
function repoman_prepare_plugin_information( $plugin ) {
    // get plugin version or fallback
    $version = isset( $plugin['version'] ) ? sanitize_text_field( $plugin['version'] ) : '1.0.0';

    // get plugin download link
    $download_link = repoman_get_plugin_download_link( $plugin );

    // build plugin data array
    $plugin_data = array(
        'id' => $plugin['slug'],
        'type' => 'plugin',
        'name' => sanitize_text_field( $plugin['name'] ),
        'slug' => sanitize_title( $plugin['slug'] ),
        'version' => $version,
        'author' => esc_html( $plugin['author'] ),
        'author_profile' => esc_url( $plugin['author_url'] ),
        'requires' => '5.0',
        'tested' => get_bloginfo( 'version' ),
        'requires_php' => '7.0',
        'sections' => array(
            'description' => wp_kses_post( $plugin['description'] ),
        ),
        'download_link' => $download_link,
        'package' => $download_link,
        'last_updated' => sanitize_text_field( $plugin['last_updated'] ),
        'homepage' => ! empty( $plugin['author_url'] ) ? esc_url( $plugin['author_url'] ) : '',
        'short_description' => wp_kses_post( $plugin['description'] ),
        'icons' => array(
            'default' => ! empty( $plugin['icon_url'] ) ? esc_url( $plugin['icon_url'] ) : '',
        ),
        'external' => false,
        'plugin' => $plugin['slug'] . '/' . $plugin['slug'] . '.php',
    );

    // return plugin data as object
    return (object) $plugin_data;
}

// get download link for plugin using github with automatic branch detection
function repoman_get_plugin_download_link( $plugin ) {

    // check if repo field is empty
    if ( empty( $plugin['repo'] ) ) {
        error_log( 'RepoMan Error: Repository owner or repo is empty for plugin ' . $plugin['slug'] );
        return '';
    }

    // extract owner and repo from repo string
    $parts = explode( '/', $plugin['repo'] );
    if ( count( $parts ) < 2 ) {
        error_log( 'RepoMan Error: Invalid repository format for plugin ' . $plugin['slug'] );
        return '';
    }

    $owner = $parts[0];
    $repo = $parts[1];

    // check for cached default branch
    $cache_key = 'repoman_default_branch_' . $owner . '_' . $repo;
    $default_branch = get_transient( $cache_key );

    // fetch default branch if not cached
    if ( $default_branch === false ) {
        $api_url = "https://api.github.com/repos/{$owner}/{$repo}";
        $response = wp_remote_get( $api_url, array(
            'headers' => array( 'user-agent' => 'RepoMan' ),
            'timeout' => 30,
        ) );

        // handle api error
        if ( is_wp_error( $response ) ) {
            error_log( 'RepoMan Error: Unable to connect to github api for plugin ' . $plugin['slug'] . '. error: ' . $response->get_error_message() );
            $default_branch = 'master';
        } else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( json_last_error() === JSON_ERROR_NONE && isset( $data['default_branch'] ) ) {
                $default_branch = sanitize_text_field( $data['default_branch'] );
            } else {
                error_log( 'RepoMan Error: Unable to detect default branch for plugin ' . $plugin['slug'] . '. json error: ' . json_last_error_msg() );
                $default_branch = 'master';
            }
        }

        // cache the result for 12 hours
        set_transient( $cache_key, $default_branch, 12 * HOUR_IN_SECONDS );
    }

    // build initial download link
    $download_link = "https://github.com/{$owner}/{$repo}/archive/refs/heads/{$default_branch}.zip";

    // test download link
    $get_response = wp_remote_get( $download_link, array(
        'headers' => array( 'user-agent' => 'RepoMan' ),
        'timeout' => 30,
    ) );

    // handle error or fallback if zip not accessible
    if ( is_wp_error( $get_response ) || wp_remote_retrieve_response_code( $get_response ) !== 200 ) {
        $error_message = is_wp_error( $get_response ) ? $get_response->get_error_message() : wp_remote_retrieve_response_message( $get_response );
        error_log( 'RepoMan Error: unable to access zip file at ' . $download_link . ' for plugin ' . $plugin['slug'] . '. response: ' . print_r( $error_message, true ) );

        // fallback to master if not already used
        if ( $default_branch !== 'master' ) {
            $fallback_branch = 'master';
            $fallback_download_link = "https://github.com/{$owner}/{$repo}/archive/refs/heads/{$fallback_branch}.zip";

            $fallback_response = wp_remote_get( $fallback_download_link, array(
                'headers' => array( 'user-agent' => 'RepoMan' ),
                'timeout' => 30,
            ) );

            if ( ! is_wp_error( $fallback_response ) && wp_remote_retrieve_response_code( $fallback_response ) === 200 ) {
                $download_link = $fallback_download_link;
                $default_branch = $fallback_branch;
                set_transient( $cache_key, $default_branch, 12 * HOUR_IN_SECONDS );
            } else {
                // fallback to main
                $fallback_branch = 'main';
                $fallback_download_link = "https://github.com/{$owner}/{$repo}/archive/refs/heads/{$fallback_branch}.zip";

                $main_response = wp_remote_get( $fallback_download_link, array(
                    'headers' => array( 'user-agent' => 'RepoMan' ),
                    'timeout' => 30,
                ) );

                if ( ! is_wp_error( $main_response ) && wp_remote_retrieve_response_code( $main_response ) === 200 ) {
                    $download_link = $fallback_download_link;
                    $default_branch = $fallback_branch;
                    set_transient( $cache_key, $default_branch, 12 * HOUR_IN_SECONDS );
                } else {
                    error_log( 'RepoMan Error: Unable to access zip file at ' . $fallback_download_link . ' for plugin ' . $plugin['slug'] );
                    return '';
                }
            }
        } else {
            // fallback to main if master failed
            $fallback_branch = 'main';
            $fallback_download_link = "https://github.com/{$owner}/{$repo}/archive/refs/heads/{$fallback_branch}.zip";

            $main_response = wp_remote_get( $fallback_download_link, array(
                'headers' => array( 'user-agent' => 'RepoMan' ),
                'timeout' => 30,
            ) );

            if ( ! is_wp_error( $main_response ) && wp_remote_retrieve_response_code( $main_response ) === 200 ) {
                $download_link = $fallback_download_link;
                $default_branch = $fallback_branch;
                set_transient( $cache_key, $default_branch, 12 * HOUR_IN_SECONDS );
            } else {
                error_log( 'RepoMan Error: Unable to access zip file at ' . $fallback_download_link . ' for plugin ' . $plugin['slug'] );
                return '';
            }
        }
    }

    return esc_url_raw( $download_link );
}

// normalize plugin data
function repoman_normalize_plugin_data( $plugin ) {
    // set default values for plugin data
    $defaults = array(
        'name' => __( 'Unknown Plugin', 'repoman' ),
        'slug' => 'unknown-slug',
        'author' => __( 'Unknown Author', 'repoman' ),
        'author_url' => '',
        'version' => '1.0.0',
        'repo' => '',
        'description' => __( 'No description available.', 'repoman' ),
        'icon_url' => '',
        'last_updated' => __( 'Unknown', 'repoman' ),
        'active_installs' => 0,
        'rating' => 0,
        'num_ratings' => 0,
    );

    // merge plugin data with defaults
    return wp_parse_args( $plugin, $defaults );
}

// calculate match score based on search query
function repoman_calculate_match_score( $plugin, $search_query ) {
    $score = 0;
    $plugin_name = strtolower( $plugin['name'] );
    $plugin_slug = strtolower( $plugin['slug'] );
    $plugin_description = strtolower( $plugin['description'] );
    $search_query = strtolower( $search_query );
    $search_terms = array_filter( explode( ' ', $search_query ) );

    // exact match on plugin name
    if ( $plugin_name === $search_query ) {
        $score += 100;
    }

    // partial match in plugin name
    if ( false !== strpos( $plugin_name, $search_query ) ) {
        $score += 50;
    }

    // exact match on plugin slug
    if ( $plugin_slug === sanitize_title( $search_query ) ) {
        $score += 80;
    }

    // partial match in plugin slug
    if ( false !== strpos( $plugin_slug, sanitize_title( $search_query ) ) ) {
        $score += 40;
    }

    // match terms in slug
    foreach ( $search_terms as $term ) {
        $sanitized_term = sanitize_title( $term );
        if ( false !== strpos( $plugin_slug, $sanitized_term ) ) {
            $score += 15;
        }
    }

    // match terms in name
    foreach ( $search_terms as $term ) {
        if ( false !== strpos( $plugin_name, $term ) ) {
            $score += 10;
        }
    }

    // match terms in description
    foreach ( $search_terms as $term ) {
        if ( false !== strpos( $plugin_description, $term ) ) {
            $score += 5;
        }
    }

    return $score;
}

// prepare plugin data for display in plugin tiles
function repoman_prepare_plugin_for_display( $plugin ) {
    // normalize plugin data
    $plugin = repoman_normalize_plugin_data( $plugin );

    // get the download link
    $download_link = repoman_get_plugin_download_link( $plugin );

    // return array with plugin information
    return array(
        'id' => $plugin['slug'],
        'type' => 'plugin',
        'name' => sanitize_text_field( $plugin['name'] ),
        'slug' => sanitize_title( $plugin['slug'] ),
        'version' => sanitize_text_field( $plugin['version'] ),
        'author' => ! empty( $plugin['author_url'] )
            ? '<a href="' . esc_url( $plugin['author_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $plugin['author'] ) . '</a>'
            : ( ! empty( $plugin['repo'] )
                ? '<a href="' . esc_url( 'https://github.com/' . $plugin['repo'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $plugin['author'] ) . '</a>'
                : esc_html( $plugin['author'] )
            ),
        'author_profile' => ! empty( $plugin['author_url'] ) ? esc_url( $plugin['author_url'] ) : '',
        'contributors' => array(),
        'requires' => '',
        'tested' => '',
        'requires_php' => '',
        'rating' => intval( $plugin['rating'] ) * 20,
        'num_ratings' => intval( $plugin['num_ratings'] ),
        'support_threads' => 0,
        'support_threads_resolved' => 0,
        'active_installs' => intval( $plugin['active_installs'] ),
        'short_description' => wp_kses_post( $plugin['description'] ),
        'sections' => array(
            'description' => wp_kses_post( $plugin['description'] ),
        ),
        'download_link' => $download_link,
        'downloaded' => true,
        'homepage' => ! empty( $plugin['author_url'] ) ? esc_url( $plugin['author_url'] ) : '',
        'tags' => array(),
        'donate_link' => '',
        'icons' => array(
            'default' => ! empty( $plugin['icon_url'] ) ? esc_url( $plugin['icon_url'] ) : '',
        ),
        'banners' => array(),
        'banners_rtl' => array(),
        'last_updated' => sanitize_text_field( $plugin['last_updated'] ),
        'added' => '',
        'external' => false,
        'package' => $download_link,
        'plugin' => $plugin['slug'] . '/' . $plugin['slug'] . '.php',
    );
}

// handle renaming of plugin folder after installation
function repoman_rename_plugin_folder( $response, $hook_extra, $result ) {
    // only run for plugin installs
    if ( isset( $hook_extra['type'] ) && $hook_extra['type'] === 'plugin' ) {

        // get plugin slug from transient
        $plugin_slug = get_transient( 'repoman_installing_plugin' );

        if ( ! $plugin_slug ) {
            return $response;
        }

        // get destination path from result
        if ( is_array( $result ) && isset( $result['destination'] ) ) {
            $plugin_path = $result['destination'];
        } else {
            error_log( 'RepoMan Error: Invalid result format for plugin installation' );
            return $response;
        }

        // build new path using plugin slug
        $new_plugin_path = trailingslashit( dirname( $plugin_path ) ) . $plugin_slug;

        // rename only if paths do not match
        if ( basename( $plugin_path ) !== $plugin_slug ) {
            if ( rename( $plugin_path, $new_plugin_path ) ) {
                error_log( 'Renamed plugin folder from ' . $plugin_path . ' to ' . $new_plugin_path );
                $response = $new_plugin_path;
            } else {
                error_log( 'Failed to rename plugin folder from ' . $plugin_path . ' to ' . $new_plugin_path );
                return new WP_Error( 'rename_failed', __( 'Could not rename plugin directory', 'repoman' ) );
            }
        }
    }

    // clear transient after install
    delete_transient( 'repoman_installing_plugin' );

    return $response;
}
add_filter( 'upgrader_post_install', 'repoman_rename_plugin_folder', 10, 3 );

// extend search results to include plugins from the json file and prioritize them when relevant
function repoman_extend_search_results( $res, $action, $args ) {
    // return early if not a query_plugins action or search query is empty
    if ( 'query_plugins' !== $action || empty( $args->search ) ) {
        return $res;
    }

    // sanitize the search query
    $search_query = sanitize_text_field( urldecode( $args->search ) );
    $plugins = repoman_get_plugins_data_with_cache();

    // return original results if there was an error or no plugins found
    if ( is_wp_error( $plugins ) || empty( $plugins ) ) {
        return $res;
    }

    // normalize plugin data and prepare matching plugins array
    $plugins = array_map( 'repoman_normalize_plugin_data', $plugins );
    $matching_plugins = array();

    // loop through plugins to calculate match score
    foreach ( $plugins as $plugin ) {
        $score = repoman_calculate_match_score( $plugin, $search_query );
        if ( $score > 0 ) {
            $plugin['match_score'] = $score;
            $matching_plugins[] = $plugin;
        }
    }

    // return original results if no matching plugins found
    if ( empty( $matching_plugins ) ) {
        return $res;
    }

    // sort matching plugins by score in descending order
    usort( $matching_plugins, function( $a, $b ) {
        return $b['match_score'] - $a['match_score'];
    } );

    // prepare formatted plugins for display
    $formatted_plugins = array_map( 'repoman_prepare_plugin_for_display', $matching_plugins );

    // filter out original plugins that match the slugs of the formatted plugins
    $original_plugins = $res->plugins;
    $original_plugins = array_filter( $original_plugins, function( $plugin ) use ( $formatted_plugins ) {
        return ! in_array( $plugin['slug'], wp_list_pluck( $formatted_plugins, 'slug' ), true );
    } );

    // merge formatted plugins with the original ones
    $res->plugins = array_merge( $formatted_plugins, $original_plugins );
    $res->info['results'] = count( $res->plugins );

    return $res;
}
add_filter( 'plugins_api_result', 'repoman_extend_search_results', 12, 3 );

// hide active installs and ratings for repoman plugin cards only
add_action( 'admin_head', function() {
    echo '<style>
      /* target plugin cards with github avatar icons */
      .plugin-card:has(.plugin-icon[src*="avatars.githubusercontent.com"]) .column-downloaded,
      .plugin-card:has(.plugin-icon[src*="avatars.githubusercontent.com"]) .vers.column-rating .star-rating,
      .plugin-card:has(.plugin-icon[src*="avatars.githubusercontent.com"]) .vers.column-rating .num-ratings {
        visibility: hidden;
      }
    </style>';
});

// Ref: ChatGPT
// Ref: https://make.wordpress.org/core/2021/06/29/introducing-update-uri-plugin-header-in-wordpress-5-8/
// Ref: https://github.com/YahnisElsts/plugin-update-checker/issues/581
