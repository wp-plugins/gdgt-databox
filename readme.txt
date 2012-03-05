=== gdgt Databox ===
Contributors: gdgt, niallkennedy
Requires at least: 3.2
Tested up to: 3.3.1
Stable tag: 1.0
License: GPLv2 or later
Tags: gdgt, gadgets, consumer electronics, reviews

Display gadget specifications, reviews, discussions, and answers alongside your post content. Powered by gdgt.com.

== Description ==

The [gdgt](http://gdgt.com/) Databox adds relative product information to the end of a post based on post tags as well as products you manually include or exclude through a meta box in your post editor.

The Databox displays product specifications, reviews, support answers, and user discussions for matching products.

Based on the gdgt API and officially developed by gdgt.com. This plugin requires a valid and active gdgt API key.

Customizations include:

* Choose the maximum number of products displayed in a Databox
* Define stop tags to exclude the gdgt Databox from multiple posts
* Explicitly disable the gdgt Databox for individual posts
* Define which user capability may disable the Databox for individual posts
* Choose to exclude up-to-date product support questions and discussions from gdgt.com
* Fully expand all products on initial pageload
* Include rich semantic markup describing the content of your post to improve the relevancy of your webpages to search engines and social sharing sites.

The gdgt Databox plugin requires PHP version 5.3 (released in June 2009) or later.

== Installation ==

1. Obtain an API key from gdgt.com. API keys are currently only provided to select publishing partners.
1. Upload files to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress at the site or network level
1. Define your API key through the gdgt settings page

or

1. Search for the plugin from your administrative interface
1. Install
1. Define your API key through the gdgt settings page

== Frequently Asked Questions ==

= I installed the plugin but it does nothing =

The gdgt Databox plugin requires a valid API key issued by gdgt.com. You may enter your gdgt API key through the gdgt Databox settings pane.

= The Acme Anvil appears next to my posts. How can I remove it? =

Edit the post. Find the gdgt meta box in your post editing interface. Click "Delete" next to the product name.

= Is my theme compatible with the gdgt Databox? =

The gdgt Databox requires at least 550 available pixels in your theme's main content column. Your theme(s) should define a `content_width` value for use by gdgt Databox and other plugins.

= Does the gdgt Databox meet accessibility requirements? =

The gdgt Databox plugin includes [WAI-ARIA](http://www.w3.org/WAI/intro/aria) markup for compatibility with screen readers and other assistive software. These extra rules should help sites meet Section 508 compliance needs in the United States.

= May I customize display of the Databox? =

Databox content outputs alongside your site's posts, inheriting the styles of your theme and custom CSS. Refer to the gdgt Terms of Service related to your API agreement for branding requirements.

= Is the gdgt Databox plugin available in languages other than English? =

The gdgt Databox plugin is currently United States English only. Product specifications are currently provided in imperial units (inches, ounces, pounds).

= Does gdgt track my users in any way? =

The gdgt Databox uses [Google Analytics](http://www.google.com/analytics/) to track Databox views and interactions.

== Screenshots ==

1. Display product reviews, specifications, discussions, and support questions and answers related to your post.
2. Customize databoxes and administrative controls with stop tags, tab options, maximum number of products, and HTML markup options.
3. Manually add products you would like to display alongside each post. The gdgt Databox meta box in your post editor makes managing products easy.
4. Explicitly remove products that may be returned by a tag search or rearrange the order in which products appear in your Databox.

== Changelog ==

= 1.0 =

* Initial release. Display specifications, reviews and ratings, discussions, and answers related to a post.
