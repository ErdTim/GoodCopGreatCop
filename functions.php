<?php
/**
 * GCGC functions and definitions.
 */

$GCGC_TRANSIENTS_ID = 'gcgc_transients';
$GCGC_OPTIONS_ID = 'gcgc_options';


function gcgc_pageMetaTags() {
    $video_id = gcgc_getPermalinkVideo();
    $output = '';

    if ( $video_id ) {
        $videos = gcgc_getData();
        $video = $videos[$video_id];
    }

    $site_name   = get_bloginfo('name');
    $title       = (!$video_id ? get_bloginfo('name') : $video['title']);
    $image       = (!$video_id ? get_template_directory_uri() . '/images/matt_and_charlie.png' : $video['thumbnail']);
    $description = (!$video_id ? get_bloginfo('description') : preg_replace("/[\r\n]+/", " ", $video['description']));
    $url         = (!$video_id ? get_bloginfo('url') : $video['wordpress_url']);

    // Facebook & Google+
    $output .= '<!-- Facebook & Google+ -->';
    $output .= '<meta property="og:site_name" content="'. $site_name .'"/>';
    $output .= '<meta property="og:title" content="'. $title .'"/>';
    $output .= '<meta property="og:image" content="'. $image . '"/>';
    $output .= '<meta property="og:description" content="'. $description .'"/>';
    $output .= '<meta property="og:url" content="'. trailingslashit($url) .'"/>';

    // Twitter
    $output .= '<!-- Twitter -->';
    $output .= '<meta name="twitter:site" content="@goodcopgreatcop">';
    $output .= '<meta name="twitter:creator" content="@goodcopgreatcop">';
    $output .= '<meta name="twitter:card" content="summary_large_image">';
    $output .= '<meta name="twitter:title" content="'. $title .'"/>';
    $output .= '<meta name="twitter:image" content="'. $image . '"/>';
    $output .= '<meta name="twitter:description" content="'. $description .'"/>';

    return $output;
}


function gcgc_getSiteTitle() {
    // {Good Cop Great Cop} --> {<span>Good Cop Great Cop</span>}
    $title = get_bloginfo('name');
    $pattern = '/([\d\w\s]+)/';
    // removes any whitespace between the "{" and the text
    preg_match($pattern, $title, $matches);
    return preg_replace($pattern, '<span>'.trim($matches[0]).'</span>', $title);
}


function gcgc_getWindowTitle() {
    $site_title = get_bloginfo('name');
    $video_title = '';
    $video_id = gcgc_getPermalinkVideo();


    if ( $video_id ) {
        $videos = gcgc_getData();
        $video_title = ' - ' . $videos[$video_id]['title'];
    }

    return $site_title . $video_title;
}


function gcgc_getAboutContent() {
    $page = get_page_by_path( 'about' );
    return apply_filters( 'the_content', $page->post_content );
}


function gcgc_getSocialButtons() {
    $page = get_page_by_path( 'social-buttons' );
    $fields = explode('-- ', $page->post_content);
    array_shift($fields); // $page->post_content begins with "-- "
    foreach ($fields as $key => $val) {
        $fields[$key] = explode(' | ', $val);
    }
    return $fields;
}

// --------------------

function gcgc_getData() {
    global $GCGC_TRANSIENTS_ID;

    // refresh all saved YoutTube data
    if ( isset($_GET['flush']) ) {
        $data = gcgc_refreshData();
        return $data;
    }

    $data = get_transient( $GCGC_TRANSIENTS_ID );

    // transient has expired, time to look for new data
    if ( $data === false ) {
        $data = gcgc_updateData();
    }

    return $data;
}


function gcgc_updateData() {
    global $GCGC_OPTIONS_ID;
    global $GCGC_TRANSIENTS_ID;

    $db_data = get_option( $GCGC_OPTIONS_ID );
    $yt_data = gcgc_getYouTubeData();

    $response_code = wp_remote_retrieve_response_code( $yt_data );
    if ( $response_code != 200 ) {
        set_transient( $GCGC_TRANSIENTS_ID, $db_data, 60*15 );
        return $db_data;
    }

    $yt_data = gcgc_parseYouTubeResponse( $yt_data );

    if ( empty($db_data) ) {
        $db_data = $yt_data;
        update_option( $GCGC_OPTIONS_ID, $db_data );
    } else {
        $initial_db_length = sizeof( $db_data );
        
        foreach ( $yt_data as $video ) {
            if ( !array_key_exists( $video['id'], $db_data ) ) {
                // array_unshift($db_data, $video); doesn't let you specify a key for the new item
                $db_data = array( $video['id'] => $video ) + $db_data;
            } else {
                break;
            }
        }

        if ( sizeof( $db_data ) > $initial_db_length ) {
            update_option( $GCGC_OPTIONS_ID, $db_data );
        }
    }

    set_transient( $GCGC_TRANSIENTS_ID, $db_data, 60*15 );

    return $db_data;
}


function gcgc_getYouTubeData( $startIndex = '1' ) {
    $url = 'http://gdata.youtube.com/feeds/api/users/goodcopgreatcop/uploads
                ?v=2
                &start-index='. $startIndex . '
                &alt=jsonc';
                // &max-results=3';
    $url = preg_replace( '/\s+/', '', $url );
    $response = wp_remote_get( $url );
    return $response;
}


function gcgc_parseYouTubeResponse( $data, $skipChildParse = false ) {
    if (!$skipChildParse) {
        $data = json_decode( $data['body'] );
        $data = $data->data->items;
    }

    $response = array();

    foreach ( $data as $video ) {
        $response[$video->id] = array(
            'id' => $video->id,
            'title' => $video->title,
            'description' => $video->description,
            'duration' => gcgc_prettyDuration($video->duration),
            'thumbnail' => $video->thumbnail->hqDefault,
            'youtube_url' => '//www.youtube.com/watch?v=' . $video->id . '',
            'embed_url' => preg_replace('/\/v\//', '/embed/', $video->content->{'5'}) . '&enablejsapi=0&iv_load_policy=3&showinfo=0',
            'wordpress_url' => gcgc_makeWpUrl( $video->id, $video->title )
        );
    }

    return $response;
}


function gcgc_refreshData() {
    global $GCGC_OPTIONS_ID;
    global $GCGC_TRANSIENTS_ID;

    $startIndex = '1';
    $total_data = array();

    while( $startIndex ) {
        $yt_data = gcgc_getYouTubeData($startIndex);

        // if YouTube doesn't respond at any point, bail on the flush
        $response_code = wp_remote_retrieve_response_code( $yt_data );
        if ( $response_code != 200 ) {
            return get_option( $GCGC_OPTIONS_ID );;
        }

        $data = json_decode( $yt_data['body'] );
        $data = $data->data;
        $startIndex = gcgc_hasMore($data);
        $total_data = array_merge($total_data, $data->items);
    }

    $db_data = gcgc_parseYouTubeResponse( $total_data, true );

    delete_option( $GCGC_OPTIONS_ID );
    delete_transient( $GCGC_TRANSIENTS_ID );

    update_option( $GCGC_OPTIONS_ID, $db_data );
    set_transient( $GCGC_TRANSIENTS_ID, $db_data, 60*15 );

    return $db_data;
}


function gcgc_hasMore($data) {
    $startIndex = $data->startIndex;
    $itemsPerPage = $data->itemsPerPage;
    $totalItems = $data->totalItems;
    $lastItemIndex = ($startIndex - 1) + $itemsPerPage;

    if ($lastItemIndex >= $totalItems) {
        return false;
    }

    return ($lastItemIndex + 1);
}


function gcgc_prettyDuration($seconds) {
    $h = $seconds / 3600 % 24;
    $m = $seconds / 60 % 60; 
    $s = $seconds % 60;

    $output = array();

    // only print hours when present
    if ($h >= 1) $output[] = "{$h}";

    // always print minutes (even 0)
    // only add the leading 0 when there are hours
    if ( $h >= 1 && strlen(strval("{$m}")) < 2 ) {
        $output[] = "0{$m}";
    } else {
        $output[] = "{$m}";
    }

    // always print seconds
    // always be 2 digits long (leading 0)
    if ( strlen(strval("{$s}")) < 2 ) {
        $output[] = "0{$s}";
    } else {
        $output[] = "{$s}";
    }

    return implode(':', $output);
}


function gcgc_makeWpUrl($id, $title) {
    $slug = sanitize_title_with_dashes( remove_accents( $title ) );
    return get_bloginfo('url') . "/video/" . $id . "/" . $slug;
}


function gcgc_getPermalinkVideo() {
    global $wp_query;
    global $wp_rewrite;
 
    $id = 0;

    // WordPress using Pretty Permalink structure
    if ( $wp_rewrite->using_permalinks() ) {
        if ( isset($wp_query->query_vars['video']) ) {
            $id = $wp_query->query_vars['video'];
        }
    } else { 
        // WordPress using default pwrmalink structure like www.site.com/wordpress/?p=123
        $id = $_GET['video'];
    }

    return $id;
}


function gcgc_addUrlRules() {
    add_rewrite_tag('%video%','([^&]+)', 'video=');
    add_rewrite_rule(  
        '^video/([a-zA-Z0-9_-]{11})/?',
        'index.php?video=$matches[1]',  
        'top'
    );
}


function query_vars($public_query_vars) {
    $public_query_vars[] = "video";
    return $public_query_vars;
}

// ------------
// WP_Rewrite info:
// http://www.prodeveloper.org/create-your-own-rewrite-rules-in-wordpress.html
// http://www.hongkiat.com/blog/wordpress-url-rewrite/
// ------------
add_action( 'init', 'gcgc_addUrlRules' );
add_filter('query_vars', 'query_vars');


// ------------
// Dashboard widget and Social Buttons
// ------------

add_action('wp_dashboard_setup', array('GCGCSocial', 'setup'));
add_action("wp_ajax_gcgc_dashboard_social_update", array('GCGCSocial', 'update'));

class GCGCSocial {

    public static function init() {
?>
    <form id="gcgc_dashboard_social" action="gcgc_dashboard_social_update">
        <?php
            $fields = gcgc_getSocialButtons();
            foreach ($fields as $field) {
        ?>
            <div class="set">
                <h4><?php echo $field[0] ?></h4>
                <p>
                    <label for="<?php echo 'gcgc_title_'.$field[1] ?>">Title</label>  
                    <input type="text" name="<?php echo 'gcgc_title_'.$field[1]; ?>" id="<?php echo 'gcgc_title_'.$field[1]; ?>" value="<?php echo trim($field[0]); ?>" />
                </p>
                <p>
                    <label for="<?php echo 'gcgc_class_'.$field[1] ?>">CSS Class</label>  
                    <input type="text" name="<?php echo 'gcgc_class_'.$field[1]; ?>" id="<?php echo 'gcgc_class_'.$field[1]; ?>" value="<?php echo trim($field[1]); ?>" />
                </p>
                <p>
                    <label for="<?php echo 'gcgc_icon_'.$field[1] ?>">Icon</label>  
                    <input type="text" name="<?php echo 'gcgc_icon_'.$field[1]; ?>" id="<?php echo 'gcgc_icon_'.$field[1]; ?>" value="<?php echo trim($field[2]); ?>" />
                </p>
                <p>
                    <label for="<?php echo 'gcgc_url_'.$field[1] ?>">URL</label>  
                    <input type="text" name="<?php echo 'gcgc_url_'.$field[1]; ?>" id="<?php echo 'gcgc_url_'.$field[1]; ?>" value="<?php echo trim($field[3]); ?>" />
                </p>
            </div>
        <?php
            }

            if ( function_exists('wp_nonce_field') ) {
                wp_nonce_field('gcgc_dashboard_social_update_nonce');
            }
        ?>
        <div class="set">
            <p class="footnote">
                *** Thinking about a different icon? <a href="http://fortawesome.github.io/Font-Awesome/icons/">Font Awesome reference</a>.
            </p>
            <p class="footnote">
                *** The colors for any new icons will need to be added to <a href="<?php echo get_bloginfo('url'); ?>/wp-admin/theme-editor.php">the Stylesheet</a> (search for "SOCIAL BUTTONS" and just follow the pattern; you'll be fine).
            </p>
        </div>
        <p class="form-buttons">
            <input type="submit" name="save" id="update-field" class="button" value="Update">
        </p>
    </form>
<?php
    } // GCGCSocial :: init

    public static function setup() {
        $social_page = get_page_by_path( 'social-buttons' );

        wp_add_dashboard_widget('gcgc_social_widget', 'GCGC Social Buttons', array('GCGCSocial', 'init'));
        
        wp_enqueue_style('gcgc_social_css', get_template_directory_uri() . '/css/social.css');
        
        wp_register_script('gcgc_social_js', get_template_directory_uri() . '/js/social.js', array('jquery') );
        wp_localize_script( 'gcgc_social_js', 'GCGC_SOCIAL', array(
            'admin_ajax' => admin_url('admin-ajax.php'),
            'post_id' => $social_page->ID
        ));
        wp_enqueue_script('gcgc_social_js');
    } // GCGCSocial :: setup

    public static function update() {
        if ( !wp_verify_nonce( $_REQUEST['_wpnonce'], "gcgc_dashboard_social_update_nonce")) {
            exit("Unauthorized");
        }

        $post_id = $_REQUEST['post_id']; // set in JS
        $post_content = $_REQUEST['content'];

        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $post_content
        ));

        $result = array(
            'type' => 'success',
            'response' => '200'
        );

        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            $result = json_encode($result);
            echo $result;
        } else {
            header("Location: ".$_SERVER["HTTP_REFERER"]);
        }

        die();
    } // GCGCSocial :: update
}