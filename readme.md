# RepoMan

Install public repos to WordPress

## Description

RepoMan adds selected WordPress plugins hosted in public GitHub repositories to the native **Plugins > Add New** search results.

When a search matches an entry in the bundled `plugin-repos.json` index, RepoMan displays the matching repositories ahead of WordPress.org results using standard WordPress plugin cards. Plugins are installed directly from their GitHub branch archives.

RepoMan automatically detects each repository’s default branch, with `master` and `main` used as fallbacks when necessary. During installation, the extracted GitHub archive directory is renamed to the configured plugin slug before WordPress selects the final installation destination.

Hosts, agencies, and developers can replace the bundled repository index by defining the `REPOMAN_PLUGIN_INDEX_PATH` constant with the path to a readable custom `plugin-repos.json` file. This allows a privately maintained plugin catalog to be used without modifying or forking RepoMan.

RepoMan also prevents WordPress.org update packages from being offered for plugins whose main file contains a `GitHub Plugin URI` or `Update URI` header, along with several explicitly protected plugin slugs. This protection applies whether those plugins are active or inactive.

RepoMan only exposes repositories listed in the bundled or configured index. It does not search all of GitHub, access private repositories, assess third-party code, or provide ongoing GitHub updates by itself.

## Changelog

### 3.0.1
- removes unused placeholder rating and rating-count values from the bundled plugin index while retaining zero-value defaults required by WordPress plugin cards
- skips and logs plugin entries whose slugs are missing or sanitize to an empty value
- keeps the first plugin entry for each sanitized slug and skips and logs later duplicates
- `Tested up to:` bumped to 7.0

### 3.0.0
- removes the shared `repoman_installing_plugin` transient and `upgrader_post_install` folder rename used by earlier releases
- maps each GitHub package URL to its configured plugin slug for the current PHP request and passes that slug through `upgrader_package_options` only for new plugin installs
- validates the extracted plugin first, then renames its temporary GitHub archive folder during `upgrader_source_selection` at priority `20` before WordPress chooses the final install directory
- uses the WordPress filesystem API for the folder move and returns explicit errors when the filesystem is unavailable, the target folder already exists, or the move fails
- prevents overlapping installs from sharing the wrong slug while leaving plugin updates, themes, WordPress.org packages, and unrelated installs unchanged

### 2.1.3
- prevents RepoMan plugins from repeating on later search pages and preserves WordPress.org result totals

### 2.1.2
- limits GitHub ZIP probe response bodies to one byte to reduce memory use during availability checks

### 2.1.1
- handles `WP_Error` and non-200 GitHub API or ZIP responses before parsing metadata or accepting download URLs

### 2.1.0
- rejects non-array top-level JSON values, skips malformed non-array entries, and returns an error when no valid plugin entries remain
- requires repository values to contain exactly one GitHub `owner/repo` pair using supported owner and repository characters
- prevents punctuation-only or otherwise empty sanitized search terms from matching plugin slugs
- normalizes `plugin-repos.json` formatting and alphabetical ordering

### 2.0.1
- added `littlebizzy/verified-customers`

### 2.0.0
- added support for overriding the bundled plugin index using the `REPOMAN_PLUGIN_INDEX_PATH` PHP constant
- allows hosts and agencies to supply a custom `plugin-repos.json` file without forking RepoMan
- default bundled index remains unchanged and is used automatically when no override is defined
- bumped `Tested up to:` to 6.9

### 1.9.0
- added `littlebizzy/export-database`

### 1.8.9
- added `littlebizzy/anti-spam`

### 1.8.8
- tested up to WordPress 6.8
- removed `load_textdomain` block (we are not bundling translation files for now)
- added `littlebizzy/secure-file-access`

### 1.8.7
- removed the `repoman-` slug prefix which caused installation errors
- now hiding star ratings and active installs using modern `:has` CSS pseudo-class
- now linking author names to author homepage with fallback to GitHub repos
- uncommented and sanitized `active_installs` lines after confirming no conflicts
- `scan_plugin_main_file_for_` now returns immediately if the file is missing or unreadable
- various minor code optimization formatting improvements

### 1.8.6
- prefixed plugin slugs with `repoman-` to enable targeted CSS
- scoped CSS better to hide star ratings and active install counts on RepoMan tiles only

### 1.8.5
- experimented with commenting some `active_installs` lines in plugin data
- added basic CSS to hide the install count `.column-downloaded` in plugin tiles
- linked author names to GitHub repos in `repoman_prepare_plugin_for_display()`

### 1.8.4
- added `littlebizzy/contact-form`

### 1.8.3
- added `littlebizzy/disable-search`
- removed `littlebizzy/disable-gutenberg` (not ready yet)
- added `Tested up to` plugin header
- added `Update URI` plugin header

### 1.8.2
- added `codersaiful/woo-product-table`

### 1.8.1
- added `littlebizzy/random-post-ids`

### 1.8.0
- now blocks plugins with `Update URI` string from WP.org (same as `GitHub Plugin URI`)
- `plugin-update-checker` also hardcode protected from WP.org overwrites

### 1.7.6
- added `littlebizzy/metadata`

### 1.7.5
- added `littlebizzy/disable-cart-fragments`

### 1.7.4
- added `robertdevore/benchpress`
- added `robertdevore/email-validator-for-wordpress`
- added `robertdevore/frontend-post-order`
- added `robertdevore/gift-cards-for-woocommerce`

### 1.7.3
- added `robertdevore/custom-update-request-modifier` (first plugin without Git Updater support)
- Note: plugins without Git Updater headers can't be protected from WP.org overwrites

### 1.7.2
- added `wp-privacy/wp-api-privacy`

### 1.7.1
- added `repoman` to skipped plugin namespace array
- added `git-updater` to skipped plugin namespace array
- added `wpe-secure-updater` to skipped plugin namespace array

### 1.7.0
- new functionality to block wordpress.org updates from specified array of plugin slugs
- `advanced-custom-fields` is the first plugin added to this array
- does not conflict with `wpe-secure-updater` plugin

### 1.6.6
- added `littlebizzy/disable-emojis`

### 1.6.5
- added `littlebizzy/404-to-homepage`

### 1.6.4
- fixed broken php tag

### 1.6.3
- added `rhubarbgroup/redis-cache`

### 1.6.2
- added `discourse/wp-discourse`

### 1.6.1
- added `Requires PHP: 7.0` header
- added `wp-graphql/wp-graphql`
- added `reduxframework/redux-framework`

### 1.6.0
- changed name from "Repo Man" to "RepoMan" for better branding
- all `repo-man` and `repo_man` instances in the code changed to simply `repoman`
- textdomain is also now simply `repoman` for translation support

### 1.5.0
- automatically blocks all updates/notices from wordpress.org to any plugin with `GitHub Plugin URI` in the main file
- both activated and deactivated plugins that support Git Updater will be "blocked" from wordpress.org
- optimized loading order of all functions and filters

### 1.4.4
- added `boogah/biscotti`
- added `zouloux/bowl`

### 1.4.3
- added `MisoAI/miso-wordpress-plugin`
- added `pods-framework/pods`
- added `thecodezone/dt-home`

### 1.4.2
- changed from 2-space to 4-space json indentation
- added `pressbooks/pressbooks`
- added `mihdan/recrawler`
- added `WPCloudDeploy/wp-cloud-deploy`
- added `wp-sms/wp-sms`

### 1.4.1
- added `littlebizzy/multisite-billing-manager`
- tweaked dummy data in the json file for consistency

### 1.4.0
- installing plugins from GitHub now supported based on the `repo` field in the `plugin-repos.json` file
- GitHub repos will be automatically scanned for default, `master` and `main` fallback branches
- `url` field in json data changed to `repo` field with owner/repo syntax
- refined error handling if json file has parsing issues
- plugin folders will be force renamed during installation to match `repo` field (if folder not exists)
- various other code refactoring and cleanup

### 1.3.0
- simplified approach focused on plugin search results only (removed Public Repos tab)
- greatly improved search query matching rules with new scoring function
- tweaked logic for plugin data normalization and sanitizing
- added textdomain `repo-man` for translation support

### 1.2.4
- added error handling in case of empty `plugin-repos.json` file
- added/changed to `wp_kses_post()` from `esc_html()` for admin notices

### 1.2.3
- added `urldecode()` inside the `repo_man_extend_search_results` function

### 1.2.2 
- Public Repos tab position is now dynamic depending on Search Results tab being active or not
- various minor security enhancements
- minor translation enhancements
- transitional release to prepare for 1.3.0 changes

### 1.2.1
- changed 3 actions/filters to use priority `12`

### 1.2.0
- added LittleBizzy icon from GitHub to appropriate plugins in `plugin-repos.json`
- integrated json list into the native plugin search results (json list plugins should appear first)

### 1.1.1
- added `littlebizzy/disable-feeds`
- sorted `plugin-repos.json` in alphabetical order

### 1.1.0
- enhanced json file location security using `realpath()`
- added error handing for json file and admin notices for clear user feedback
- more efficient rendering of top/bottom pagination
- display 36 plugins instead of 10 per page
- added fallback values for missing keys in the plugin data (e.g., slug, name, icon_url, author) to ensure that all plugins display properly even if some data is missing
- improved structure and display of plugin cards, including star ratings, action buttons, and compatibility information
- removed forced redirect to  "Repos" tab as it was unnecessary and caused redirect loop on Multisite

### 1.0.0
- adds new tab under Add Plugins page for "Public Repos" (default tab)
- displays a few hand-picked plugins from GitHub
- hardcoded list of plugins using local `plugin-repos.json`
- public repo suggestions are more than welcome!
- supports PHP 7.0 to PHP 8.3
- supports Git Updater
- supports Multisite