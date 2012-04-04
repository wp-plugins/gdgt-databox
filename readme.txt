=== gdgt Databox ===
Contributors: gdgt, niallkennedy
Requires at least: 3.2
Tested up to: 3.4
Stable tag: 1.11
License: GPLv2 or later
Tags: gdgt, gadgets, consumer electronics, reviews

Display product specs, reviews, and other useful, contextual data alongside your post content. Powered by gdgt.

== Description ==

The [gdgt](http://gdgt.com/) Databox makes it incredibly easy to augment your site's content with highly structured product data, specs, and other timely information, enhancing the value of your stories, increasing engagement, and keeping users engaged on your site longer.

Built to be nearly invisible to a busy editorial workflow, the gdgt Databox takes full advantage of the WordPress publishing system and automatically returns relevant products without adding additional steps before posting. Writers can also manually add products with an inline search field, or disable individual products or the entire Databox with a single click.

The Databox is highly optimized for WordPress. Minimal, intelligently-timed API requests and built-in caching mean your pages will load just as fast as ever.

Powered by the gdgt API, the Databox displays product specs, review data, support answers, and discussions, and is officially developed by [gdgt](http://gdgt.com/). The gdgt Databox requires a valid and active gdgt API key.

Customizations include:

* Choose the maximum number of products displayed in a Databox
* Define stop tags to exclude the gdgt Databox from multiple posts
* Explicitly disable the gdgt Databox for individual posts
* Define which user capability may disable the Databox for individual posts
* Choose to exclude up-to-date product support questions and discussions from gdgt.com
* Fully expand all products on initial pageload
* Include rich semantic markup describing the content of your post to improve the relevancy of your webpages to search engines and social sharing sites.
* Customize display order alongside other plugins
* Include Databox content in syndicated RSS and Atom feeds

== Installation ==

1. Obtain an API key from gdgt.com. API keys are currently only provided to select publishing partners, please [contact gdgt for more information](http://gdgt.com/contact/).
1. Search for and install the plugin from the Plugins page in your WordPress administrative interface or upload the plugin files to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress at the site or network level; yes, the Databox is WordPress multisite network compatible!
1. Enter your API key through each site's `gdgt Databox` Settings page.

== Frequently Asked Questions ==

= I installed the plugin but it does nothing =

The gdgt Databox plugin requires a valid API key issued by gdgt.com. API keys are currently only provided to select publishing partners, please [contact gdgt for more information](http://gdgt.com/contact/). Once you have your gdgt API key, enter it through the `gdgt Databox` Settings page.

= A wrong or unwanted product appears next to my posts. How can I remove it? =

Easy! First, go edit the post. Find the gdgt Databox editing box in your post editing interface, and click "Delete" next to the product name. Save or update your post. That's it!

= Is my theme compatible with the gdgt Databox? =

The gdgt Databox requires at least 550 horizontal pixels in your theme's main content column. Ideally your theme(s) should define a `content_width` value for use by gdgt Databox and other plugins.

= May I customize display of the Databox? =

Databox content outputs alongside your site's posts, inheriting the styles of your theme and custom CSS. Refer to the gdgt Terms of Service related to your API agreement for additional branding requirements.

= The Databox appears above / below another plugin. How do I change it? =

Set a custom content filter priority from the `gdgt Databox` Settings page.

= Does gdgt track my users in any way? =

The gdgt Databox uses [Google Analytics](http://www.google.com/analytics/) to track aggregate Databox views and interactions, but does not add any kind of tracking to identify individual users.

= Is the gdgt Databox plugin available in languages other than English? =

The gdgt Databox plugin is currently United States English only. Product spec are currently provided in imperial units (inches, ounces, pounds).

= Does the gdgt Databox meet accessibility requirements? =

The gdgt Databox plugin includes [WAI-ARIA](http://www.w3.org/WAI/intro/aria) markup for compatibility with screen readers and other assistive software. These extra rules should help sites meet Section 508 compliance needs in the United States.


== Screenshots ==

1. Display product specs, review data, support questions, and discussions related to your post.
2. Customize Databoxes and administrative controls with stop tags, tab options, maximum number of products, and HTML markup options.
3. Manually add products you would like to display alongside each post. The gdgt Databox editor makes managing products incredibly easy.
4. Rearrange the order in which products appear in a Databox, or easily remove products that you don't want to appear.

== Changelog ==

= 1.11 =

* Remove content filter helper from Settings page when no plugins data found

= 1.1 =

* Include a stand-alone version of the Databox in web feeds (the_content_feed).
* Add custom priority to setting page. Position gdgt Databox above or below other content plugins.
* Display content_width in Settings page alongside its impact on gdgt Databox settings

= 1.03 =

* Additional CSS rules to maintain expected layout across a variety of themes

= 1.02 =

* Featured image fallback for themes without post thumbnail support

= 1.01 =

* PHP 5.2.4+ compatibility
* Increase priority of gdgt Databox compared to other content filters.

= 1.0 =

* Initial release. Display specifications, reviews and ratings, discussions, and answers related to a post.

== Upgrade Notice ==

= 1.11 =

Remove content filter helper from Settings page when no plugins data found.

= 1.1 =

Databox appears in feed view. Set custom filter priority.

= 1.03 =

Additional CSS rules to maintain expected layout across a variety of themes.

= 1.02 =

Featured image fallback for themes without post thumbnail support.

= 1.01 =

PHP 5.2 compatibility. Higher priority for gdgt Databox when used with other content plugins.