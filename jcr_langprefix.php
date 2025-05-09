<?php
if (txpinterface === "admin") {
    new jcr_langprefix();
}

if (defined('txpinterface') && txpinterface == 'public') {
    // incoming: callback for custom url handling
    register_callback('jcr_langprefix_url', 'pretext', '', 1);

    // outgoing: custom url function for generating permlinks and pagelinks
    if (get_pref('jcr_langprefix_auto_permlinks', true)) {
        $prefs['custom_url_func'] = 'jcr_langprefix_permlinkhandler';
    }

    if (class_exists('\Textpattern\Tag\Registry')) {
        Txp::get('\Textpattern\Tag\Registry')
            ->register('jcr_lang')
            ->register('jcr_if_lang')
            ->register('jcr_langswitch')
            ->register('jcr_text');
    }
}

class jcr_langprefix
{
    /**
     * Initialise.
     */
    public function __construct()
    {
        // Hook into the system's callbacks
        register_callback(array(__CLASS__, "lifecycle"), "plugin_lifecycle.jcr_langprefix");

        // Prefs pane for custom fields
        add_privs("prefs.jcr_langprefix", "1");

        // Redirect 'Options' link on plugins panel to preferences pane
        add_privs("plugin_prefs.jcr_langprefix", "1");
        register_callback(array(__CLASS__, "options_prefs_redirect"), "plugin_prefs.jcr_langprefix");
    }

    /**
     * Add and remove custom fields from txp_file table.
     *
     * @param $event string
     * @param $step string  The lifecycle phase of this plugin
     */
    public static function lifecycle($event, $step)
    {
        switch ($step) {
            case "enabled":
                add_privs("prefs.jcr_langprefix", "1");
                break;
            case "disabled":
                break;
            case "installed":
                // Add prefs for langprefix settings panel
                create_pref("jcr_langprefix_set", "en, de, fr", "jcr_langprefix", PREF_PLUGIN, "text_input", 5);
                create_pref("jcr_langprefix_default", "en", "jcr_langprefix", PREF_PLUGIN, "text_input", 10);
                create_pref("jcr_langprefix_auto_permlinks", 1, "jcr_langprefix", PREF_PLUGIN, "onoffRadio", 15);
                break;
            case "deleted":
                // Remove all prefs from event 'jcr_langprefix'.
                remove_pref(null, "jcr_langprefix");

                // Remove all associated lang strings
                safe_delete(
                    "txp_lang",
                    "owner = 'jcr_langprefix'"
                );
                break;
        }
        return;
    }

    /**
     * Re-route 'Options' link on Plugins panel to Admin › Preferences panel
     *
     */
    public static function options_prefs_redirect()
    {
        header("Location: index.php?event=prefs#prefs_group_jcr_langprefix");
    }
}


/**
 * Custom url handler for use as $pretext callback.
 *
 * Overrides permlink_mode and converts
 * an url with /langprefix/ into a regular url
 * and saves the langprefix to $pretext
 *
 */

function jcr_langprefix_url()
{
    global $pretext;

    // Set to true for debug output
    $debug = false;

    $langprefix_set = get_pref('jcr_langprefix_set');
    $permitted_langprefixes = array_map('trim', explode(',', $langprefix_set)); // ["en", "de", "fr"];
    $default_langprefix = get_pref('jcr_langprefix_default', 'en');

    // Get server request_uri and strip off http(s):// if present
    $req_uri = preg_replace("|^https?://[^/]+|i", "", serverSet('REQUEST_URI'));

    // Define the usable url, minus any subdirectories in the site_url.
    $subpath = preg_quote(preg_replace("/https?:\/\/.*(\/.*)/Ui", "$1", hu), "/");
    $req = preg_replace("/^$subpath/i", "/", $req_uri);

    if ($debug) {
        dmp("req_uri: " . $req_uri);
        dmp("site_url / hu: " . hu);
        dmp("subpath: ". $subpath);
        dmp("req: " . $req);
    }

    // split url into first slashed part and everything thereafter
    $uri_parts = explode('/', $req, 3);

    // is first part a permitted langprefix
    if (in_array($uri_parts[1], $permitted_langprefixes)) {
        // yes: use langprefix and strip it from url
        $langprefix = $uri_parts[1];
        $req = isset($uri_parts[2]) ? "/".$uri_parts[2] : "/";
    } else {
        // no: use default language
        $langprefix = $default_langprefix;
    }

    if ($debug) {
        dmp("lang: " . $langprefix);
        dmp("uri_parts 1: " . $uri_parts[1]);
        if (isset($uri_parts[2])) {
            dmp("uri_parts 2: " . $uri_parts[2]);
        }
    }

    // save langprefix to $pretext for later use
    $pretext["lang_prefix"] = $langprefix;

    if ($debug) {
        dmp("req: " . $req);
    }

    // pass on url without langprefix for handling via TXP
    $_SERVER['REQUEST_URI'] = $req;
}

/**
 * Custom permlinkhandler for use with $prefs['custom_url_func'].
 * Redirects to jcr_langprefix_permlinkurl() or jcr_langprefix_pagelinkurl()
 * depending on the function it was called from.
 *
 * @param   array $article_array An array consisting of keys 'thisid', 'section', 'title', 'url_title', 'posted', 'expires'
 * @return  string The URL
 * @see     jcr_langprefix_permlinkurl()
 * @see     jcr_langprefix_pagelinkurl()
 * @package URL
 *
 */

function jcr_langprefix_permlinkhandler($article_array, $type)
{
    if ($type == PAGELINKURL) {
        // Link to page
        return jcr_langprefix_pagelinkurl($article_array);
    } else {
        // Article permlink
        return jcr_langprefix_permlinkurl($article_array);
    }
}

/**
 * Generates a language-prefixed format article URL from the given data array.
 *
 * Uses $prefs['custom_url_func'] to override standard permlinkurl.
 * Called from jcr_langprefix_permlinkhandler
 *
 * @param   array $article_array An array consisting of keys 'thisid', 'section', 'title', 'url_title', 'posted', 'expires'
 * @return  string The URL
 * @see     jcr_langprefix_pagelinkurl()
 * @package URL
 *
 */

function jcr_langprefix_permlinkurl($article_array)
{
    global $permlink_mode, $prefs, $permlinks, $production_status, $pretext;

    extract(
        lAtts(
            [
                'thisid' => null,
                'id' => null,
                'title' => null,
                'url_title' => null,
                'section' => null,
                'category1' => null,
                'category2' => null,
                'posted' => null,
                'expires' => null,
            ],
            array_change_key_case($article_array, CASE_LOWER),
            false
        )
    );

    $language = $pretext['lang_prefix'];

    if (empty($thisid)) {
        $thisid = $id;
    }
    $thisid = (int) $thisid;
    if (isset($permlinks[$thisid])) {
        return $permlinks[$thisid];
    }
    if (empty($prefs['publish_expired_articles']) && !empty($expires) && $expires < time() && $production_status != 'live' && txpinterface == 'public') {
        trigger_error(gTxt('permlink_to_expired_article', ['{id}' => $thisid]), E_USER_NOTICE);
    }
    if (empty($url_title)) {
        $url_title = stripSpace($title);
    }
    $section = urlencode($section);
    $url_title = urlencode($url_title);

    if (empty($language)) {
        $language = get_pref('jcr_langprefix_default', 'en'); // DEFAULT LANGUAGE (if not specified in url)
    }

    switch ($permlink_mode) {
        case 'section_title':
            $out = "$section/$url_title";
            break;
        case 'section_category_title':
            $out = $section . '/' . (empty($category1) ? '' : urlencode($category1) . '/') . (empty($category2) ? '' : urlencode($category2) . '/') . $url_title;
            break;
    }

    $out = hu . $language . '/' . $out;

    return $out;
}

/**
 * Generates a language-prefixed format page URL from the given data array of type:
 * section or category
 *
 * Uses $prefs['custom_url_func'] to override standard pagelinkurl.
 * Called from jcr_langprefix_permlinkhandler
 *
 * Cannot be used to link to an article. See jcr_permlinkurl() instead.
 *
 * @param   array $parts   The parts used to construct the URL
 * @param   array $inherit Can be used to add parameters to an existing url
 * @return  string
 * @see     jcr_langprefix_permlinkurl()
 * @package URL
 */

function jcr_langprefix_pagelinkurl($parts, $inherit = [])
{
    global $permlink_mode, $prefs, $pretext;
    $keys = array_merge($inherit, $parts);

    $language = $pretext['lang_prefix'];

    // Can't use this to link to an article.
    if (isset($keys['id'])) {
        unset($keys['id']);
    }
    if (isset($keys['s']) && $keys['s'] == 'default') {
        unset($keys['s']);
        $section_default = true;
    }
    // 'article' context is implicit, no need to add it to the page URL.
    if (isset($keys['context']) && $keys['context'] == 'article') {
        unset($keys['context']);
    }

    // All clean URL modes use the same schemes for list pages.
    $url = '';
    if (!empty($keys['rss'])) {
        $url = hu . 'rss/';
        unset($keys['rss']);
        return $url . join_qs($keys);
    } elseif (!empty($keys['atom'])) {
        $url = hu . 'atom/';
        unset($keys['atom']);
        return $url . join_qs($keys);
    } elseif (!empty($keys['author'])) {
        $ct = empty($keys['context']) ? '' : strtolower(urlencode(gTxt($keys['context'] . '_context'))) . '/';
        $url = hu . strtolower(urlencode(gTxt('author'))) . '/' . $ct . urlencode($keys['author']) . '/';
        unset($keys['author'], $keys['context'], $keys['s'], $keys['c']);
        return $url . (count($keys) > 0 ? substr_replace(join_qs($keys), '&', 0, 1) : '');
    } elseif (!empty($keys['s'])) {
        if (!empty($keys['context'])) {
            $keys['context'] = gTxt($keys['context'] . '_context');
        }
        $url = hu . $language . '/' . urlencode($keys['s']) . '/';
        unset($keys['s'], $keys['author'], $keys['context']);
        return $url . (count($keys) > 0 ? join_qs($keys) : '');
    } elseif (!empty($keys['c'])) {
        $ct = empty($keys['context']) ? '' : strtolower(urlencode(gTxt($keys['context'] . '_context'))) . '/';
        $url = hu . $language . '/' . strtolower(urlencode(gTxt('category'))) . '/' . $ct . urlencode($keys['c']);
        unset($keys['c'], $keys['context'], $keys['s']);
        return $url . (count($keys) > 0 ? join_qs($keys) : '');
    } elseif (isset($section_default)) {
        $url = hu . $language . '/';
        return $url . (count($keys) > 0 ? join_qs($keys) : '');
    }

    return hu . join_qs($keys);
}

/**
 * Public-side tag to retrieve or set current page's language prefix.
 * Can also return a comma-separated list of all permitted languages.
 */

function jcr_lang()
{
    global $pretext;

    extract($atts = lAtts(array(
        'set'        => '',
        'permitted'  => '0',
    ), $atts));

    if (!empty($set)) {
        $pretext['lang_prefix'] = $set;
        return;
    }

    if ($permitted) {
        $out = get_pref('jcr_langprefix_set');
    }

    return isset($out) ? $out : $pretext['lang_prefix'];
}

/**
 * Public-side tag to check the current page's language prefix.
 */

function jcr_if_lang($atts, $thing = null)
{
    global $pretext;

    extract($atts = lAtts(array(
        'lang'      => '',
    ), $atts));

    if (empty($lang)) {
        trigger_error(gTxt('jcr_lang_empty'));
        return '';
    } elseif ($lang == 'default') {
        $x = get_pref('jcr_langprefix_default') === $pretext['lang_prefix'] ? true : false;
    } else {
        $x = $lang === $pretext['lang_prefix'] ? true : false;
    }

    return isset($thing) ? parse($thing, $x) : $x;
}

/**
 * Public-side tag to output a language switch permlink.
 */

function jcr_langswitch($atts)
{
    global $pretext;

    extract(lAtts(array(
        'lang' => $pretext['lang_prefix'],
        'root' => '0'
    ), $atts));

    return (empty($lang) ? rtrim(hu, '/') : hu) . $lang . ($root ? '/' : $pretext['req']);
}

/**
 * Public-side tag to output a text string in a specific language.
 */

function jcr_text($atts)
{
    global $pretext;

    extract(lAtts(array(
        'item'   => '',
        'lang'   => isset($pretext['lang_prefix']) ? $pretext['lang_prefix'] : '',
        'escape' => null,
    ), $atts, false));

    if (!$item) {
        return '';
    }

    unset(
        $atts['item'],
        $atts['lang'],
        $atts['escape']
    );

    $tags = array();
    foreach ($atts as $name => $value) {
        $tags['{'.$name.'}'] = $value;
    }

    // switch language
    if (!empty($lang)) {
        $currLang = LANG;
        $txpLang = Txp::get('\Textpattern\L10n\Lang');
        $txpLang->load($lang, 'public,common');
    }

    $out = gTxt($item, $tags, isset($escape) ? '' : 'html');

    // revert language
    if (!empty($lang)) {
        $txpLang->load($currLang, 'public,common');
    }

    return $out;
}
