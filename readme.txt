=== RRZE FAUbox ===
Contributors: rrze
Tags: files, cloud, fau, faubox, directory, list, table
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Display files from FAUbox public share links as lists or tables in WordPress.

== Description ==

RRZE FAUbox integrates publicly shared FAUbox folders into WordPress.

The plugin allows editors to embed files from FAUbox public share links using
either a Gutenberg block or a shortcode. Files are fetched server-side via the
FAUbox WAPI endpoint and rendered directly in WordPress.

No authentication or API keys are required. The plugin works exclusively with
public FAUbox share links.

**Features:**
- Display files from FAUbox public shares
- List and table view modes
- Optional display of file name and file extension
- File type filtering (e.g. pdf, jpg, png)
- Optional custom folder title
- Gutenberg block and shortcode support
- Server-side rendering (SSR)
- No authentication required for public shares


== Installation ==

1. Upload the plugin folder `rrze-faubox` to `/wp-content/plugins/`.
2. Activate the plugin via **Plugins → Installed Plugins**.
3. In the WordPress editor, add the **FAUbox** block from the **RRZE** category.
4. Paste a public FAUbox share link, for example:
   `https://faubox.rrze.uni-erlangen.de/getlink/fi12345ABCDEF/`
5. Select folders and configure the display options.


=== Shortcode ===

The plugin provides the shortcode `[faubox]`.

The attribute `sharelink` is required. All other attributes are optional.

Basic usage (root folder only):

[faubox sharelink="https://faubox.rrze.uni-erlangen.de/getlink/…"]

Display a specific subfolder:

[faubox sharelink="…" selectedfolders="Images"]

Display multiple subfolders:

[faubox sharelink="…" selectedfolders="Images,Documents"]

Display a nested subfolder:

[faubox sharelink="…" selectedfolders="folder/subfolder"]

Change the view mode:

[faubox sharelink="…" view="table"]

Show file name and file type:

[faubox sharelink="…" show="name,type"]

Filter by file types:

[faubox sharelink="…" filetype="pdf,jpg,png"]

Sorting order:

[faubox sharelink="…" sort="desc"]

Custom folder title:

[faubox sharelink="…" show_title="true" changetitle="Project Files"]


== Shortcode Attributes ==

= sharelink =
Public FAUbox share link (required).

= selectedfolders =
One or more FAUbox folder paths.
Use exact folder names as shown in FAUbox.
Nested folders must be separated by `/`.

Examples:
- `Images`
- `Images,Documents`
- `Projects/2024`

= view =
Display mode.
Possible values: `list`, `table`
Default: `list`

= show =
Controls which file information is displayed.
Possible values:
- `name` (file name)
- `type` (file extension, e.g. PDF, JPG)

Example:
`show="name,type"`

= filetype =
Limits the displayed files to specific file extensions.

Example:
`filetype="pdf,jpg,png"`

= sort =
Sorting order by file name.
Possible values:
- `asc` (ascending)
- `desc` (descending)

Default: `asc`

= show_title =
Displays a title above the file list.
Possible values: `true`, `false`
Default: `false`

= changetitle =
Overrides the displayed folder title when `show_title` is enabled.



