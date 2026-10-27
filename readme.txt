=== Turtle Template Form Knitter ===
Contributors: TurtleEngr
Tags: contact, form, post, flamingo, upload
Requires at least: 6.0
Requires PHP: 8.0
Tested up to: 7.1
Stable tag: VERSION
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin knits form fields into templates to generate posts that are almost publication-ready.

== Description ==

With this plugin a contact-form-7 form can be used to create a post.
The created post will have a "Pending" status. See the documentation
for how to configure this plugin, contact-form-7, and flamingo.

Required plugins: contact-form-7, flamingo.

Optional plugin: ninja-auto-post-expire - If this plugin is installed
the "expiry date" field for a post can be set with this plugin.

This plugin assumes you are using Gutenberg Blocks for editing.

Image files uploaded with the Contact Form 7 "[file NAME]" fields are
saved to the Media Library. The file type and the file extension must
be an allowed image type. Non-image files are skipped, and the error
is reported in a popup. File names are converted to the characters a-z,
A-Z, 0-9, ".", "_" and "-", and existing files are never overwritten:
"_1", "_2", and so on is added to the name.

The first uploaded image is used for the Featured Image.

== Installation ==

Install these plugins in this order.

1. Install contact-form-7 plugin.
1. Install flamingo plugin.
1. Optional: Install ninja-auto-post-expire plugin
1. If installed, remove the conflicting  save-cf7-file-uploads plugin.
1. Install this plugin turtle-template-form-knitter.

== Frequently Asked Questions ==

= Where can I find the documentation =

See: https://turtleengr.github.io/WP-turtle-template-form-knitter/

= How do I configure Contact Form 7 to use this? ==

See: https://turtleengr.github.io/WP-turtle-template-form-knitter/#contact-form-settings

= How do I define a template for the posts?

See: https://turtleengr.github.io/WP-turtle-template-form-knitter/#template-page

= Where can I report bugs or feature requests? =

See: https://turtleengr.github.io/WP-turtle-template-form-knitter/#support

= Where can I find development versions? =

For "unstable" development versions see:
https://turtleengr.github.io/WP-turtle-template-form-knitter/#development-installs

== Changelog ==

= 1.0.6 =

- Renamed this plugin to comply with WordPress.org naming convention.

- Identified a "date" type problem with contact-form-7. See Issue 1,
  for the problem and a workaround.

- Updated documentation.

- Report errors with a popup.

- Changed to follow the wordpress.org plugin guidelines.

- save-cf7-file-uploads plugin functionality has been added to this
  plugin. That plugin should not be installed.

- Fixed: uploaded images were not attached to the post (hook name
  mismatch).

= 0.1.0 =

Initial version.
