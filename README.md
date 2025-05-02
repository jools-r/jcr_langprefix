# jcr_langprefix

## Custom language URL scheme handler

This plugin detects and inserts a language prefix to Textpattern URLs
for use in multilingual sites, for example:

    /fr/section-name
    /de/section-name/article-url-title
    /en/category/category-name

will be treated by Textpattern as follows:

    /section-name                     -> lang:fr
    /section-name/article-url-title   -> lang:de
    /category/category-name           -> lang:en

You do not need to create sections for each language. The plugin
includes public tags to output and detect the currently set language
prefix, to create language switchers and to output matching text
strings.

#### Incoming URLs

jcr_langprefix works invisibly in the background, storing the language
prefix for later use in your page code while passing the remainder of
the URL to textpattern for handling as normal.

[If no language prefix is provided]{.underline}, the default language
(see settings) is set in the background (but *not* added to the url) and
the url works as normal.

[If an unknown language prefix is provided]{.underline}, i.e. one not in
the list of permitted languages (see settings), Textpattern will treat
it as a regular section name and return a "404 Not Found" if it does not
exist.

#### Outgoing links

The plugin can automatically prepend a language prefix to txp:tags with
a `link="1"` attribute and to `txp:permlink`. You can optionally disable
this in the plugin settings. You must then construct your own
language-specific links.

### Please note

[The plugin only handles the language prefix in the url. It has no
concept of whether the actual page is in a particular
language.]{.underline} That means `/en/vegetables/` and `/de/vegetables`
and `/vegetables/` are all identical, i.e. the "vegetables" section in
Textpattern. It is up to you to define what information should be shown
for a page's current language prefix.

For example, define article custom_fields to denote different language
fields, or denote an article's language. Similarly, use
[jcr_section_custom](https://github.com/jools-r/jcr_section_custom) or
[jcr_category_custom](https://github.com/jools-r/jcr_category_custom) to
define language specific fields for sections and categories. You can
then use this plugin's public tags to output specific fields.

Also, make sure to provide correct canonical urls to avoid url
duplication in search machine results.

## Installation & settings

Paste the code into the Admin › Plugins tab, install and enable the
plugin.

To configure the plugin, visit the plugin options on the *Admin ›
Preferences › Language URL prefixes* panel. You have options to set:

- the permitted language prefixes. Specify these as a comma-separated
  list of prefixes, e.g `de, it, fr`.
- the default language prefix.
- whether the plugin should automatically add language-prefixes to
  permlinks.

### Language strings

To use the language-aware `jcr_text` tag to output language strings
stored in Textpattern, make sure the relevant languages have been
installed on the *Admin › Languages* panel. The language code must match
the language prefix, e.g. to use US English (TXP language code: en-us),
your language prefix will need to be `en-us`.

## Tag: jcr_lang

Outputs the language prefix from the current page url.

    <html lang="<txp:jcr_lang />">

## Tag: jcr_if_lang

Conditionally show output when it matches the current language prefix.

    <txp:jcr_if_lang lang="en">Your English-language text</txp:jcr_if_lang>

You can additionally check if the language matches the preset default
language:

    <txp:jcr_if_lang lang="default">
        <txp:body />
    <txp:else />
        <txp:custom_field name='body_<txp:jcr_lang />' escape="textile" />
    </txp:jcr_if_lang>

## Tag: jcr_langswitch

Output a link to the current page -- or the site root -- with a
different language prefix.

#### Attributes

**lang**\
The target language\
Example: `<txp:jcr_langswitch lang="de" />` will return
`domain.com/de/section-name/my-current-page`.

**root**\
The site root with the specified language\
Example: `<txp:jcr_langswitch lang="de" root />` will return
`domain.com/de/`.

## Tag: jcr_text

A drop-in language-aware replacement for
[txp:text](https://docs.textpattern.com/tags/text) that shows a
pre-translated language string stored in Textpattern in the current
page's language -- or in a specified language.

    <txp:jcr_text item="categories" />

will show:

- "Categories" on an `/en/` page,
- "Kategorien" on a `/de/` page, and
- "Catégories" on a `/fr/` page.

**lang**\
Specify a specific language\
Example: `<txp:jcr_text item="categories" lang="fr" />` will return
"Catégories" regardless of the current page's language prefix

### Tip

To add language strings of your own in multiple languages, use
[smd_babel](https://github.com/bloke/smd_babel).

## Usage examples

Use the public tag directly with txp:evaluate or store it in a variable
for use in your template.

### A very simple example

    <txp:jcr_if_lang lang="de">
        <h1>Willkommen!</h1>
    <txp:else />
        <h1>Welcome!</h1>
    </txp:jcr_if_lang>

### Per-language text snippets

    <!-- predefined variables holding a text snippet in each language -->
    <txp:variable name="rewelcome_en">Welcome back!</txp:variable>
    <txp:variable name="rewelcome_de">Willkommen zurück!</txp:variable>
    <txp:variable name="rewelcome_fr">Bon retour!</txp:variable>
    <!-- use language variable as tag-in-tag (with single quotes) -->
    <h1><txp:variable name='rewelcome_<txp:jcr_lang />' /></h1>

### Per-language article fields

You can expand on this to show an article's body text in different
languages. First install
[glz_custom_fields](https://github.com/jools-r/glz_custom_fields).
Create new custom fields and define then as textareas. Suffix their name
with the language code, e.g. `body_de`, `body_fr` etc. In your article
write tab, enter your translations in the respective body fields.

To show the language you want in your page template, combine the
principles of the above two examples:

    <txp:jcr_if_lang lang="en">
        <txp:body />
    <txp:else />
        <txp:custom_field name='body_<txp:jcr_lang />' escape="textile" />
    </txp:jcr_if_lang>

### Per-language article fields with warning for missing content

You'll probably want to add some safeguards to show something else if a
translation is missing or to only allow defined languages. For example:

    <txp:jcr_if_lang lang="default">
        <txp:body />
    <txp:else />
        <txp:evaluate test="custom_field">
            <txp:custom_field name='body_<txp:jcr_lang />' escape="textile" />
        <txp:else />
            <p class="alert warning">This article is not yet available in your language.</p>
            <txp:body />
        </txp:evaluate>
    </txp:jcr_if_lang>

### Language switcher principle

An example of a simple language switcher that switches between different
language versions of the current page:

    <nav>
        <ul>
            <li<txp:jcr_if_lang lang="en"> class="active"</txp:jcr_if_lang>>
                <a href="<txp:jcr_langswitch lang="en" />">EN</a>
            </li>
            <li<txp:jcr_if_lang lang="de"> class="active"</txp:jcr_if_lang>>
                <a href="<txp:jcr_langswitch lang="de" />">DE</a>
            </li>
            <li<txp:jcr_if_lang lang="fr"> class="active"</txp:jcr_if_lang>>
                <a href="<txp:jcr_langswitch lang="fr" />">FR</a>
            </li>
        </ul>
    </nav>

## Changelog

### v 0.2 (jcr)

- Renamed plugin and main tag:
  - jcr_langprefix_url -\> jcr_lang
- New tags:
  - jcr_if_lang
  - jcr_langswitch
  - jcr_text
- Added plugin prefs (no longer necessary to edit plugin code)
- Updated help accordingly

### v 0.1.1 (jcr)

- Support installations in subdirectories (e.g. when using localhost)
- Silence error on base page with no subsequent path (thanks gil)

### v 0.1 (jcr)

- Custom URL handling via callback
- Custom permlink function
- Custom pagelink function
- Public-side language prefix tag
