=== gdgt Databox ===
Contributors: gdgt, niallkennedy
Requires at least: 3.2.1
Tested up to: 3.5
Stable tag: 1.31
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: gdgt, gadgets, consumer electronics, reviews

Display product specs, reviews, price data, and other useful, contextual data alongside your post content. Powered by gdgt.

== Description ==

The [gdgt](http://gdgt.com/) Databox makes it incredibly easy to augment your site's content with highly structured product data, specs, reviews, and prices, enhancing the value of your stories and keeping users engaged on your site longer.

Built to be nearly invisible to a busy editorial workflow, the gdgt Databox takes full advantage of the WordPress publishing system and automatically returns relevant products without adding additional steps before posting. Writers can also manually add products with an inline search field, or disable individual products or the entire Databox with a single click.

The Databox is highly optimized for WordPress. Minimal, intelligently-timed API requests and built-in caching mean your pages will load just as fast as ever.

Powered by the gdgt API, the Databox displays product specs, review data, pricing, and is officially developed by [gdgt](http://gdgt.com/). The gdgt Databox requires a valid and active [gdgt API key](http://gdgt.com/api/).

Customizations include:

* Choose the maximum number of products displayed in a Databox
* Define stop tags to exclude the gdgt Databox from multiple posts
* Explicitly disable the gdgt Databox for individual posts
* Define which user capability may disable the Databox for individual posts
* Fully expand all products on initial pageload
* Include rich semantic markup describing the content of your post to improve the relevancy of your webpages to search engines and social sharing sites
* Customize display order alongside other plugins
* Include Databox content in syndicated RSS and Atom feeds

== Installation ==

1. Obtain an API key from gdgt.com. API keys are currently only provided to select publishing partners, [apply here](http://gdgt.com/api/ "Apply for a gdgt API key").
1. Search for and install the plugin from the Plugins page in your WordPress administrative interface or upload the plugin files to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress at the site or network level; yes, the Databox is WordPress multisite network compatible!
1. Enter your API key through each site's `gdgt Databox` Settings page.

== Frequently Asked Questions ==

= I installed the plugin but it does nothing =

The gdgt Databox plugin requires a valid API key issued by gdgt.com. API keys are currently only provided to select publishing partners: [apply here](http://gdgt.com/api/ "Apply for a gdgt API key"). Once you have your gdgt API key, enter it through the `gdgt Databox` Settings page.

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

1. Display specifications, reviews, and real-time pricing data for products related to your post.
2. Customize Databox and administrative controls with stop tags, tab options, maximum number of products, content priority and HTML markup options.
3. Manually add products you would like to display alongside each post. The gdgt Databox editor makes managing products incredibly easy.
4. Rearrange the order in which products appear in a Databox, or easily remove products that you don't want to appear.

== Changelog ==

= 1.31 =

* Add support for on-contract cellphone offers.

= 1.3 =

* Load JS and CSS only when Databox available for a given post
* Navigate to a specific product or tab within a Databox from feed view
* Do not display product selector on post edit screens when post contains one or more stop tags
* Support Google Analytics noscript image beacons on HTTPS pages

= 1.23 =

* Bundle jQuery UI autocomplete for WordPress 3.2 installations
* Contextual help added to settings page and add/edit post screens
* Display company name in collapsed product view when Schema.org enabled

= 1.22 =

* Fix issue with an oEmbed on the last line of the post when Databox priority 9 or earlier

= 1.21 =

* Improve caching for dynamic theme switchers

= 1.2 =

* Fresh new design
* Add a prices tab to browse product prices by merchant and by product configuration
* Display the lowest available price for a product
* Improve compatibility with `wpautop` and other WordPress content filters
* Improved product search in the Databox editor
* Remove answers and discussions tabs
* Various bug fixes

= 1.11 =

* Remove content filter helper from Settings page when no plugins data found

= 1.1 =

* Include a stand-alone version of the Databox in web feeds (the_content_feed)
* Add custom priority to setting page. Position gdgt Databox above or below other content plugins
* Display content_width in Settings page alongside its impact on gdgt Databox settings

= 1.03 =

* Additional CSS rules to maintain expected layout across a variety of themes

= 1.02 =

* Featured image fallback for themes without post thumbnail support

= 1.01 =

* PHP 5.2.4+ compatibility
* Increase priority of gdgt Databox compared to other content filters

= 1.0 =

* Initial release. Display specifications, reviews and ratings, discussions, and answers related to a post

== Upgrade Notice ==

= 1.31 =

On contract cellphone offers are now supported.

= 1.3 =

Faster load times. Clicks from feed view open specific products and tabs inside your post

= 1.23 =

WordPress 3.2 compatibility improvements. Contextual help. Display company name in collapsed product view

= 1.22 =

Improve compatibility with WordPress Core content filters searching for content on its own line at the end of a post

= 1.21 =

Improve caching for dynamic theme switchers

= 1.2 =

All new design. New price comparison tab. Fluid layout auto-resizes Databox to content_width. Product search fixes

= 1.11 =

Remove content filter helper from Settings page when no plugins data found

= 1.1 =

Databox appears in feed view. Set custom filter priority

= 1.03 =

Additional CSS rules to maintain expected layout across a variety of themes

= 1.02 =

Featured image fallback for themes without post thumbnail support

= 1.01 =

PHP 5.2 compatibility. Higher priority for gdgt Databox when used with other content plugins
