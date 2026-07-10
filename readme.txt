=== RRZE FAUbox ===
Contributors: rrze
Tags: files, cloud, fau, faubox, webdav, directory, list, table
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.0.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Display files from FAUbox folders as lists or tables in WordPress.

== Description ==

RRZE FAUbox integrates files from FAUbox into WordPress via WebDAV.

Editors can embed files from any FAUbox folder using a Gutenberg block.
Files are fetched server-side via WebDAV and rendered directly in WordPress.
Downloads are proxied through WordPress — the WebDAV token is never exposed
to visitors.

**Features:**
- Connect to FAUbox via WebDAV (username + token)
- Browse and select folders directly in the block editor
- Display files as a list or table
- Show file name, type, size and last modified date
- Filter by file type (e.g. pdf, jpg, png)
- Sort by name, size, type or date
- Optional folder title with configurable heading level
- Server-side rendering (SSR)
- Secure file download proxy — no public FAUbox access required
- Folder index with configurable cache duration


== Requirements ==

- A FAUbox account at https://faubox.rrze.uni-erlangen.de
- A WebDAV token generated in your FAUbox account
- WordPress 6.8 or higher
- PHP 8.2 or higher


== Installation ==

1. Upload the plugin folder `rrze-faubox` to `/wp-content/plugins/`.
2. Activate the plugin via **Plugins → Installed Plugins**.
3. Go to **Settings → RRZE FAUbox**.
4. Enter your FAUbox username and WebDAV token.
   You can generate a token in FAUbox under: **My Account → Devices → Add WebDAV connection → Create**
5. Enter the name of your FAUbox root folder (the folder whose subfolders should be available in the block editor).
6. Save the settings. The folder index is built automatically.
7. In the WordPress editor, add the **FAUbox** block from the **RRZE** category.
8. Select a folder and configure the display options.


== Block Attributes ==

The FAUbox block is configured entirely through the block editor sidebar.
The following options are available:

= Folder =
The FAUbox folder path to display files from.
Selected via the folder tree in the block editor.

= View =
Display mode.
Possible values: `list`, `table`
Default: `list`

= Show columns =
Controls which file information is displayed.
Possible values: `name`, `type`, `size`, `modified`
Default: `name`

= Filter by file type =
Limits the displayed files to specific file extensions.
Example: `pdf`, `jpg`, `png`

= Sort by =
Field to sort by.
Possible values: `name`, `size`, `type`, `modified`
Default: `name`

= Sort order =
Possible values: `asc` (ascending), `desc` (descending)
Default: `asc`

= Show title =
Displays a heading above the file list.
Default: off

= Heading level =
HTML heading tag for the title.
Possible values: `h2`, `h3`, `h4`, `h5`
Default: `h3`

= Custom title =
Overrides the displayed folder name when "Show title" is enabled.


== Folder Index ==

The plugin maintains a cached index of all subfolders under the configured
root folder. This index is used by the block editor to display the folder tree
without making live WebDAV requests on every editor load.

The index is rebuilt automatically when:
- Credentials or the root folder are changed in settings
- The daily cron job runs

You can also refresh the index manually on the settings page under
**Settings → RRZE FAUbox → Folder Index → Refresh index now**.

The cache duration can be configured (12h, 24h). Default: 24h.


== File Downloads ==

Files in FAUbox are not publicly accessible. The plugin acts as a download
proxy: when a visitor clicks a file link, WordPress fetches the file from
FAUbox using the stored WebDAV token and streams it to the visitor.
The token and the FAUbox URL are never visible in the browser.

Each download link is protected with an HMAC signature and restricted to the
configured root folder.

