=== RRZE FAUbox ===
Contributors: rrze
Tags: files, cloud, fau, faubox, directory, list, table, preview
Tested up to: 6.5
Requires at least: 6.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A WordPress plugin providing a file browser for FAUbox public share links. Includes table, list and image preview display, filtering options, and server-side rendering.

== Description ==

RRZE FAUbox integrates publicly shared FAUbox folders into WordPress.

The plugin allows editors to embed FAUbox content via a Gutenberg block or shortcode.  
Files are fetched via the FAUbox WAPI endpoint of a public share link.  

**Features:**
- Display FAUbox folders as *list* or *table*
- Preview image files directly in the browser
- all file are download files
- Filetype filtering (e.g. pdf, png, zip)
- Custom folder title override
- Shortcode support
- Works entirely server-side (SSR), no client-side API keys required
- No authentication needed for public shares

The block is designed for users of the FAU (Friedrich-Alexander-Universität Erlangen-Nürnberg).

== Installation ==

1. Upload the plugin folder `rrze-faubox` to `/wp-content/plugins/`.
2. Activate the plugin via **Plugins → Installed Plugins**.
3. In the WordPress editor, add the **FAUbox** block under the category **RRZE**.
4. Insert a public FAUbox share link (example:  
   `https://faubox.rrze.uni-erlangen.de/getlink/fi12345ABCDEF/`).
5. Select a folder and configure the display options.

== Usage ==

### Block Editor

After adding the FAUbox block:

1. Paste the public FAUbox link.
2. Choose list or table
3. Configure:
   - Sorting  
   - Visible file information (`name`, `type`)
   - Filetype filter
   - Optional custom folder title  
4. The block renders server-side.

### Shortcode

The plugin provides a shortcode that displays files from a public FAUbox link.
The shortcode is [faubox] and requires at least the sharelink attribute.
All other attributes are optional and control display mode, filters, and sorting.

Basic usage:
[faubox sharelink="https://faubox.rrze.uni-erlangen.de/getlink/…"]

Display a specific subfolder:
[faubox sharelink="…" index="Images"]

Combine multiple folders:
[faubox sharelink="…" selectedFolders="Images,Documents"]

Change the view mode (list, table):
[faubox sharelink="…" view="table"]

Show additional file information (name, type):
[faubox sharelink="…" show="name,type"]

Filter by file types:
[faubox sharelink="…" filetype="pdf,jpg,png"]

Sorting options:
[faubox sharelink="…" sort="desc"]

Custom folder title:
[faubox sharelink="…" show_title="true" changeTitle="Project Files"]



