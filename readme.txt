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

Files uploaded with the Contact Form 7 "[file NAME]" fields are saved
to the Media Library. Only image files are saved. The type is checked
from the file content, and the file extension must also be an allowed
image type. Other files are skipped, and the error is reported in a
popup. File names are reduced to the characters a-z, A-Z, 0-9, ".",
"_" and "-", and existing files are never overwritten: "_1", "_2", and
so on is added to the name.

The images URLs added to the created post, and the first uploaded
image is used for the Featured Image. If an uploaded file could not be
saved, the error message is reported with a popup.

== Installation ==

Install these plugins in this order.

1. Install contact-form-7 plugin.
1. Install flamingo plugin.
1. Install this plugin turtle-template-form-knitter

If the save-cf7-file-uploads plugin is installed, delete it. That
functionallity is handled by this plugin.

In the "Additional Settings" tab for a contact form make sure
"do_not_store: true" is not defined.

== Frequently Asked Questions ==

= Where can I get more documentation =

See: https://turtleengr.github.io/WP-turtle-template-form-knitter/

= How do I configure Contact Form 7 to use this? ==

See: https://turtleengr.github.io/WP-turtle-template-form-knitter/#contact-form-settings

= How do I define a template for the posts?

See: https://turtleengr.github.io/WP-turtle-template-form-knitter/#template-page

= Where can I report bugs or feature requests? =

See: https://github.com/TurtleEngr/WP-turtle-template-form-knitter/issues

= Where can I find newer versions? =

The latest "stable version" can be found at
https://moria.whyayh.com/rel/released/software/own/WP-turtle-template-form-knitter/
(or if accepted, at wordpress.org.)

For development versions see:
https://turtleengr.github.io/WP-turtle-template-form-knitter/#development-installs

== Changelog ==

= 1.4.6 =

- Updated documentation.

= 1.4.2 =

- Report errors with a popup.

- Identified problem and a workaround with the "date" type in
  contact-form-7. (See Issue 1)

= 1.1.0 =

- Changed to follow the wordpress.org plugin guidelines.

- Merged in the save-cf7-file-uploads plugin functionallity.
  That plugin should not be installed.

- Fixed: uploaded images were not attached to the post (hook name
  mismatch).

= 0.1.0 =

Initial version.
