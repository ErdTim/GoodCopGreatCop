<?php
/**
 * The main template file.
 * Learn more: http://codex.wordpress.org/Template_Hierarchy
 */

get_header(); ?>

    <?php if ( current_user_can( 'edit_posts' ) ) : ?>

        <div class="admin-banner">
            <a href="<?php echo get_bloginfo('url') . '?flush=1' ?>">Refresh Data</a>
        </div>

    <?php endif; // end current_user_can() check ?>


    <?php
        $videos = gcgc_getData();
        $current_video_id = gcgc_getPermalinkVideo();
    ?>

    <?php if ( $current_video_id ) : ?>

        <?php $current_video = $videos[$current_video_id]; ?>
        <section class="featured">
            <div class="seo-item">
                <h2><?php echo $current_video['title']; ?></h2>
                <a href="<?php echo $current_video['youtube_url']; ?>">
                    <img src="<?php echo $current_video['thumbnail']; ?>" />
                </a>
                <p><?php echo nl2br( $current_video['description'], true ); ?></p>
            </div>
        </section>

    <?php endif; ?>

    <section class="main">

        <?php foreach ($videos as $video) : ?>

            <div class="item" id="<?php echo $video['id']; ?>">
                <div class="video">
                    <img data-src="<?php echo $video['thumbnail']; ?>" />
                    <a href="<?php echo $video['wordpress_url']; ?>" data-url="<?php echo $video['embed_url']; ?>">
                        <span class="title"><?php echo $video['title']; ?></span>
                        <span class="duration"><?php echo $video['duration']; ?></span>
                    </a>
                </div>
            </div>

        <?php endforeach ;?>

    </section><!-- .main -->

<?php get_footer(); ?>