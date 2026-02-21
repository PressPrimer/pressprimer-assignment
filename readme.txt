=== PressPrimer Assignment – Document-Based Assignment Submission & Grading for WordPress ===
Contributors: pressprimer
Tags: assignment, grading, submission, lms, education
Requires at least: 6.4
Tested up to: 6.9.1
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Comprehensive assignment management for WordPress educators. Create assignments, collect submissions, and grade student work.

== Description ==

**PressPrimer Assignment** is a document-based assignment submission and grading plugin for WordPress. It is part of the PressPrimer plugin suite, designed to complement PressPrimer Quiz.

= Features =

* Create and manage assignments with due dates and point values
* Collect document submissions from students
* Grade submissions with feedback
* Category organization for assignments
* File type validation and security
* Multisite compatible

= Source Code & Development =

The full uncompressed source code for all JavaScript and CSS files is available in our public GitHub repository:

* [GitHub Repository](https://github.com/PressPrimer/pressprimer-assignment)

The `/src` directory contains all unminified source files. The plugin uses webpack for building production assets. To rebuild from source:

1. Clone the repository
2. Run `npm install` to install dependencies
3. Run `npm run build` to compile assets

== Installation ==

= Automatic Installation =

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "PressPrimer Assignment"
3. Click **Install Now** and then **Activate**
4. Navigate to **PressPrimer Assignment** in your admin menu to get started

= Manual Installation =

1. Download the plugin zip file
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the zip file and click **Install Now**
4. Activate the plugin

== Frequently Asked Questions ==

= Does this work with PressPrimer Quiz? =

Yes. PressPrimer Assignment is designed as a sibling plugin to PressPrimer Quiz. They share the Groups infrastructure and Teacher role when both are active.

= What file types can students upload? =

By default, students can upload PDF, DOCX, TXT, RTF, JPG, JPEG, PNG, and GIF files. Administrators can configure allowed file types in the plugin settings.

== Screenshots ==

1. Assignment management dashboard
2. Assignment editor
3. Submission grading interface

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release of PressPrimer Assignment.
