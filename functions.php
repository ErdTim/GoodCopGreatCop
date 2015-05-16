<?php
/**
 * GCGC functions and definitions.
 */

$GCGC_TRANSIENTS_ID = 'gcgc_transients';
$GCGC_OPTIONS_ID = 'gcgc_options';
$GCGC_YOUTUBE_API_KEY = 'AIzaSyCTUKBDvz2w4iPzN5osad8OMzLvfHSVkys';
$GCGC_YOUTUBE_UPLOADS_CHANNEL_ID = 'UUu6_Ypm5e8WEvBRKop6oblw';


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
    $output .= '<!-- Facebook & Google+ -->' . "\n\t";
    $output .= '<meta property="og:site_name" content="'. $site_name .'"/>' . "\n\t";
    $output .= '<meta property="og:title" content="'. $title .'"/>' . "\n\t";
    $output .= '<meta property="og:image" content="'. $image . '"/>' . "\n\t";
    $output .= '<meta property="og:description" content="'. $description .'"/>' . "\n\t";
    $output .= '<meta property="og:url" content="'. trailingslashit($url) .'"/>' . "\n\n\t";

    // Twitter
    $output .= '<!-- Twitter -->' . "\n\t";
    $output .= '<meta name="twitter:site" content="@goodcopgreatcop">' . "\n\t";
    $output .= '<meta name="twitter:creator" content="@goodcopgreatcop">' . "\n\t";
    $output .= '<meta name="twitter:card" content="summary_large_image">' . "\n\t";
    $output .= '<meta name="twitter:title" content="'. $title .'"/>' . "\n\t";
    $output .= '<meta name="twitter:image" content="'. $image . '"/>' . "\n\t";
    $output .= '<meta name="twitter:description" content="'. $description .'"/>' . "\n\n";

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


function gcgc_getYouTubeUploadData( $startIndex = '' ) {
    global $GCGC_YOUTUBE_API_KEY;
    global $GCGC_YOUTUBE_UPLOADS_CHANNEL_ID;

    $url = 'https://www.googleapis.com/youtube/v3/playlistItems
                ?part=contentDetails
                &maxResults=50
                &playlistId='. $GCGC_YOUTUBE_UPLOADS_CHANNEL_ID .'
                &key=' . $GCGC_YOUTUBE_API_KEY;

    if ( !empty($startIndex) ) {
        $url = $url . '&pageToken=' . $startIndex;
    }

    $url = preg_replace( '/\s+/', '', $url );
    $response = wp_remote_get( $url );

    return $response;
}


function gcgc_getYouTubeVideosData( $playlist ) {
    global $GCGC_YOUTUBE_API_KEY;

    $ids = array();
    foreach ($playlist->items as $item) {
        array_push( $ids, $item->contentDetails->videoId );
    }
    $ids = implode( ",", $ids );

    $url = 'https://www.googleapis.com/youtube/v3/videos
                ?part=snippet,contentDetails
                &id='. $ids .'
                &key=' . $GCGC_YOUTUBE_API_KEY;

    $url = preg_replace( '/\s+/', '', $url );
    $response = wp_remote_get( $url );

    return $response;
}


function gcgc_parseYouTubeResponse( $data, $skipChildParse = false ) {
    if (!$skipChildParse) {
        $data = json_decode( $data['body'] );
        $data = $data->items;
    }

    $response = array();

    foreach ( $data as $video ) {
        $response[$video->id] = array(
            'id' => $video->id,
            'title' => $video->snippet->localized->title,
            'description' => $video->snippet->localized->description,
            'duration' => gcgc_prettyDuration($video->contentDetails->duration),
            'thumbnail' => $video->snippet->thumbnails->high->url,
            'youtube_url' => '//www.youtube.com/watch?v=' . $video->id . '',
            'embed_url' => 'http://www.youtube.com/embed/' . $video->id . '?version=3&f=videos&enablejsapi=0&iv_load_policy=3&showinfo=0',
            'wordpress_url' => gcgc_makeWpUrl( $video->id, $video->snippet->localized->title )
        );
    }

    return $response;
}


function gcgc_refreshData() {
    global $GCGC_OPTIONS_ID;
    global $GCGC_TRANSIENTS_ID;

    $startIndex = '';
    $total_data = array();

    while( $startIndex !== false ) {
        $uploads_data = gcgc_getYouTubeUploadData($startIndex);

        // if YouTube doesn't respond at any point, bail on the flush
        $uploads_response_code = wp_remote_retrieve_response_code( $uploads_data );
        if ( $uploads_response_code != 200 ) {
            return get_option( $GCGC_OPTIONS_ID );
        }

        $uploads_data = json_decode( $uploads_data['body'] );

        $videos_data = gcgc_getYouTubeVideosData( $uploads_data );

        $videos_response_code = wp_remote_retrieve_response_code( $videos_data );

        if ( $videos_response_code != 200 ) {
            return get_option( $GCGC_OPTIONS_ID );
        }

        $videos_data = json_decode( $videos_data['body'] );
        $startIndex = gcgc_hasMore( $uploads_data );
        $total_data = array_merge( $total_data, $videos_data->items );
    }

    $db_data = gcgc_parseYouTubeResponse( $total_data, true );

    delete_option( $GCGC_OPTIONS_ID );
    delete_transient( $GCGC_TRANSIENTS_ID );

    update_option( $GCGC_OPTIONS_ID, $db_data );
    set_transient( $GCGC_TRANSIENTS_ID, $db_data, 60*15 );

    return $db_data;
}


function gcgc_hasMore( $data ) {
    if ( !isset( $data->nextPageToken ) ) {
        return false;
    }

    return $data->nextPageToken;
}


function gcgc_prettyDuration($seconds) {
    $date = new DateInterval($seconds);
    return $date->format('%i:%S');
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
        // WordPress using default permalink structure like www.site.com/wordpress/?p=123
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
add_filter( 'query_vars', 'query_vars' );


function gcgc_load_scripts() {
    wp_register_script( 'gcgc-shadowbox', get_template_directory_uri() . '/includes/shadowbox/shadowbox.js', array(), '1.0.0', true);
    wp_register_script( 'gcgc-plugins', get_template_directory_uri() . '/js/plugins.js', array('jquery', 'gcgc-shadowbox'), '1.0.0', true);
    wp_register_script( 'gcgc-main', get_template_directory_uri() . '/js/main.js', array('jquery', 'gcgc-plugins', 'gcgc-shadowbox'), '1.0.0', true);
    wp_localize_script( 'gcgc-main', 'gcgc_php_data', array( 'site_name' => get_bloginfo('name') ) );
    wp_enqueue_script ( 'gcgc-main' );
}

add_action( 'wp_enqueue_scripts', 'gcgc_load_scripts' );


// ------------
// Dashboard widget and Social Buttons
// ------------

add_action( 'wp_dashboard_setup', array('GCGCSocial', 'setup') );
add_action( 'wp_ajax_gcgc_dashboard_social_update', array('GCGCSocial', 'update') );

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