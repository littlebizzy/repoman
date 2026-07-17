<?php
/*
Plugin Name: RepoMan
Plugin URI: https://www.littlebizzy.com/plugins/repoman
Description: Install public repos to WordPress
Version: 3.0.5
Requires PHP: 7.0
Tested up to: 7.0
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

// parse supported update headers from main plugin file
function repoman_scan_plugin_main_file_for_update_headers( $plugin_file ) {
    $plugin_file_path = WP_PLUGIN_DIR . '/' . $plugin_file;

    // plugin files may temporarily disappear during installs, updates, or removals
    if ( ! file_exists( $plugin_file_path ) || ! is_readable( $plugin_file_path ) ) {
        return false;
    }

    // parse actual plugin headers using the WordPress header reader
    $plugin_headers = @get_file_data(
        $plugin_file_path,
        array(
            'GitHubPluginURI' => 'GitHub Plugin URI',
            'UpdateURI' => 'Update URI',
        )
    );

    return ! empty( $plugin_headers['GitHubPluginURI'] ) || ! empty( $plugin_headers['UpdateURI'] );
}

// array of specific plugin slugs to block updates
function repoman_get_blocked_plugin_slugs() {
    return array(
        'repoman',
        'git-updater',
        'wpe-secure-updater',
        'advanced-custom-fields',
        'plugin-update-checker', // add more slugs as needed
    );
}

// disable WordPress.org for plugins with 'GitHub Plugin URI', 'Update URI', and specified slugs
function repoman_dynamic_block_plugin_updates( $overrides ) {
    // get all installed plugins
    $all_plugins = get_plugins();

    // get array of blocked slugs
    $blocked_slugs = repoman_get_blocked_plugin_slugs();

    // loop through each plugin
    foreach ( $all_plugins as $plugin_file => $plugin_data ) {
        // get plugin slug from its path
        $slug = dirname( $plugin_file );

        // check blocked slugs before scanning the plugin file
        if ( in_array( $slug, $blocked_slugs, true ) || repoman_scan_plugin_main_file_for_update_headers( $plugin_file ) ) {
            $overrides[] = $plugin_file;
        }
    }

    return $overrides;
}
add_filter( 'gu_override_dot_org', 'repoman_dynamic_block_plugin_updates', 999 );

// apply blocklist even if plugins are deactivated
function repoman_dynamic_block_deactivated_plugin_updates( $transient ) {
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
add_filter( 'site_transient_update_plugins', 'repoman_dynamic_block_deactivated_plugin_updates' );

// get plugin index path
function repoman_get_plugin_index_path() {
    $default_path = plugin_dir_path( __FILE__ ) . 'plugin-repos.json';

    if ( defined( 'REPOMAN_PLUGIN_INDEX_PATH' ) && is_readable( REPOMAN_PLUGIN_INDEX_PATH ) ) {
        return realpath( REPOMAN_PLUGIN_INDEX_PATH );
    }

    return realpath( $default_path );
}

// fetch plugin data from json file with safe handling and fallback values
function repoman_get_plugins_data() {
    // get resolved path of the json file
    $file = repoman_get_plugin_index_path();

    // check if file exists and is readable
    if ( ! $file || ! file_exists( $file ) || ! is_readable( $file ) ) {
        return new WP_Error( 'file_missing', __( 'Error: the plugin-repos.json file is missing or unreadable', 'repoman' ) );
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

    // require a non-empty top-level array
    if ( ! is_array( $plugins ) || empty( $plugins ) ) {
        return new WP_Error( 'file_empty', __( 'Error: the plugin-repos.json file is empty or contains no plugins', 'repoman' ) );
    }

    // sanitize valid plugin entries and fill missing fields
    $valid_plugins = array();
    $seen_slugs = array();

    foreach ( $plugins as $plugin ) {
        if ( ! is_array( $plugin ) ) {
            continue;
        }

        $plugin['slug'] = isset( $plugin['slug'] ) ? sanitize_title( $plugin['slug'] ) : '';

        if ( $plugin['slug'] === '' ) {
            error_log( 'RepoMan Error: Plugin entry with an empty slug skipped' );
            continue;
        }

        if ( isset( $seen_slugs[ $plugin['slug'] ] ) ) {
            error_log( 'RepoMan Error: Duplicate plugin slug skipped: ' . $plugin['slug'] );
            continue;
        }

        $seen_slugs[ $plugin['slug'] ] = true;
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
        $valid_plugins[] = $plugin;
    }

    // return an error if no valid entries remain
    if ( empty( $valid_plugins ) ) {
        return new WP_Error( 'file_no_valid_plugins', __( 'Error: the plugin-repos.json file contains no valid plugin entries', 'repoman' ) );
    }

    return $valid_plugins;
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

            // associate this package with its slug for the current install request
            if ( ! empty( $plugin_info->download_link ) ) {
                repoman_register_install_package( $plugin_info->download_link, $plugin['slug'] );
            }

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

// check whether a remote response completed successfully
function repoman_is_successful_http_response( $response ) {
    return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
}

// get download link for plugin using github with automatic branch detection
function repoman_get_plugin_download_link( $plugin ) {

    // check if repo field is empty
    if ( empty( $plugin['repo'] ) ) {
        error_log( 'RepoMan Error: Repository owner or repo is empty for plugin ' . $plugin['slug'] );
        return '';
    }

    // extract and validate the owner/repo string
    $parts = explode( '/', $plugin['repo'] );
    if (
        count( $parts ) !== 2 ||
        ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9-]*$/', $parts[0] ) ||
        ! preg_match( '/^[A-Za-z0-9._-]+$/', $parts[1] )
    ) {
        error_log( 'RepoMan Error: Invalid repository format for plugin ' . $plugin['slug'] );
        return '';
    }

    $owner = $parts[0];
    $repo = $parts[1];

    // check for cached default branch
    $cache_key = 'repoman_default_branch_' . $owner . '_' . $repo;
    $default_branch = get_transient( $cache_key );
    $cache_branch = false;

    // fetch default branch if not cached
    if ( $default_branch === false ) {
        $cache_branch = true;
        $api_url = "https://api.github.com/repos/{$owner}/{$repo}";
        $response = wp_remote_get( $api_url, array(
            'headers' => array( 'user-agent' => 'RepoMan' ),
            'timeout' => 30,
        ) );

        // handle api error
        if ( is_wp_error( $response ) ) {
            error_log( 'RepoMan Error: Unable to connect to github api for plugin ' . $plugin['slug'] . '. error: ' . $response->get_error_message() );
            $default_branch = 'master';
        } elseif ( ! repoman_is_successful_http_response( $response ) ) {
            $response_code = wp_remote_retrieve_response_code( $response );
            $response_message = wp_remote_retrieve_response_message( $response );
            error_log( 'RepoMan Error: GitHub API request failed for plugin ' . $plugin['slug'] . '. response: ' . $response_code . ' ' . $response_message );
            $default_branch = 'master';
        } else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( json_last_error() !== JSON_ERROR_NONE ) {
                error_log( 'RepoMan Error: Unable to detect default branch for plugin ' . $plugin['slug'] . '. json error: ' . json_last_error_msg() );
                $default_branch = 'master';
            } elseif ( ! isset( $data['default_branch'] ) || ! is_string( $data['default_branch'] ) ) {
                error_log( 'RepoMan Error: GitHub API response did not include a usable default branch for plugin ' . $plugin['slug'] );
                $default_branch = 'master';
            } else {
                $default_branch = sanitize_text_field( $data['default_branch'] );

                if ( $default_branch === '' ) {
                    error_log( 'RepoMan Error: GitHub API response did not include a usable default branch for plugin ' . $plugin['slug'] );
                    $default_branch = 'master';
                }
            }
        }
    }

    // build initial download link
    $download_link = "https://github.com/{$owner}/{$repo}/archive/refs/heads/{$default_branch}.zip";

    // test download link
    $get_response = wp_remote_get( $download_link, array(
        'headers' => array( 'user-agent' => 'RepoMan' ),
        'timeout' => 30,
        'limit_response_size' => 1,
    ) );

    // cache only branches whose zip files are accessible
    if ( repoman_is_successful_http_response( $get_response ) ) {
        if ( $cache_branch ) {
            set_transient( $cache_key, $default_branch, 12 * HOUR_IN_SECONDS );
        }
    } else {
        $error_message = is_wp_error( $get_response ) ? $get_response->get_error_message() : wp_remote_retrieve_response_message( $get_response );
        error_log( 'RepoMan Error: unable to access zip file at ' . $download_link . ' for plugin ' . $plugin['slug'] . '. response: ' . print_r( $error_message, true ) );

        // fallback to master if not already used
        if ( $default_branch !== 'master' ) {
            $fallback_branch = 'master';
            $fallback_download_link = "https://github.com/{$owner}/{$repo}/archive/refs/heads/{$fallback_branch}.zip";

            $fallback_response = wp_remote_get( $fallback_download_link, array(
                'headers' => array( 'user-agent' => 'RepoMan' ),
                'timeout' => 30,
                'limit_response_size' => 1,
            ) );

            if ( repoman_is_successful_http_response( $fallback_response ) ) {
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
                    'limit_response_size' => 1,
                ) );

                if ( repoman_is_successful_http_response( $main_response ) ) {
                    $download_link = $fallback_download_link;
                    $default_branch = $fallback_branch;
                    set_transient( $cache_key, $default_branch, 12 * HOUR_IN_SECONDS );
                } else {
                    delete_transient( $cache_key );
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
                'limit_response_size' => 1,
            ) );

            if ( repoman_is_successful_http_response( $main_response ) ) {
                $download_link = $fallback_download_link;
                $default_branch = $fallback_branch;
                set_transient( $cache_key, $default_branch, 12 * HOUR_IN_SECONDS );
            } else {
                delete_transient( $cache_key );
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
    $search_slug = sanitize_title( $search_query );
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
    if ( $search_slug !== '' && $plugin_slug === $search_slug ) {
        $score += 80;
    }

    // partial match in plugin slug
    if ( $search_slug !== '' && false !== strpos( $plugin_slug, $search_slug ) ) {
        $score += 40;
    }

    // match terms in slug
    foreach ( $search_terms as $term ) {
        $sanitized_term = sanitize_title( $term );
        if ( $sanitized_term === '' ) {
            continue;
        }

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

// associate repoman package urls with plugin slugs for the current request
function repoman_register_install_package( $package, $slug = '' ) {
    static $packages = array();

    $package = esc_url_raw( $package );

    if ( $package === '' ) {
        return '';
    }

    if ( $slug !== '' ) {
        $slug = sanitize_title( $slug );

        if ( $slug !== '' ) {
            $packages[ $package ] = $slug;
        }
    }

    return isset( $packages[ $package ] ) ? $packages[ $package ] : '';
}

// attach the intended repoman slug to the matching plugin install request
function repoman_add_install_package_options( $options ) {
    if (
        ! is_array( $options ) ||
        empty( $options['package'] ) ||
        empty( $options['hook_extra']['type'] ) ||
        empty( $options['hook_extra']['action'] ) ||
        $options['hook_extra']['type'] !== 'plugin' ||
        $options['hook_extra']['action'] !== 'install'
    ) {
        return $options;
    }

    $plugin_slug = repoman_register_install_package( $options['package'] );

    if ( $plugin_slug !== '' ) {
        $options['hook_extra']['repoman_slug'] = $plugin_slug;
    }

    return $options;
}
add_filter( 'upgrader_package_options', 'repoman_add_install_package_options' );

// rename the extracted github folder before wordpress chooses the install destination
function repoman_select_plugin_source( $source, $remote_source, $upgrader, $hook_extra ) {
    if ( empty( $hook_extra['repoman_slug'] ) ) {
        return $source;
    }

    $plugin_slug = sanitize_title( $hook_extra['repoman_slug'] );

    if ( $plugin_slug === '' || ! is_string( $source ) || $source === '' ) {
        return $source;
    }

    $source = untrailingslashit( $source );

    if ( basename( $source ) === $plugin_slug ) {
        return trailingslashit( $source );
    }

    global $wp_filesystem;

    if ( ! is_object( $wp_filesystem ) ) {
        return new WP_Error( 'repoman_filesystem_unavailable', __( 'Could not access the WordPress filesystem', 'repoman' ) );
    }

    $new_source = trailingslashit( dirname( $source ) ) . $plugin_slug;

    if ( $wp_filesystem->exists( $new_source ) ) {
        return new WP_Error( 'repoman_folder_exists', __( 'The target plugin directory already exists', 'repoman' ) );
    }

    if ( ! $wp_filesystem->move( $source, $new_source ) ) {
        return new WP_Error( 'repoman_rename_failed', __( 'Could not prepare the plugin directory', 'repoman' ) );
    }

    return trailingslashit( $new_source );
}
add_filter( 'upgrader_source_selection', 'repoman_select_plugin_source', 20, 4 );

// extend search results to include plugins from the json file and prioritize them when relevant
function repoman_extend_search_results( $res, $action, $args ) {
    // return early if not a query_plugins action or search query is empty
    if ( 'query_plugins' !== $action || empty( $args->search ) ) {
        return $res;
    }

    // only add repoman matches to the first search results page
    if ( isset( $args->page ) && intval( $args->page ) > 1 ) {
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
