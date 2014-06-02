<?php
/**
 * The Header for our theme.
 */
$theme_url = get_template_directory_uri();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>" />
        <meta name="viewport" content="width=device-width" />

        <title><?php echo gcgc_getWindowTitle(); ?></title>

        <link rel="profile" href="http://gmpg.org/xfn/11" />
        <link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>" />

        <!--
        <link rel="stylesheet" href="<?php echo $theme_url ?>/includes/fontAwesome/css/font-awesome.min.css" />
        -->
        <link rel="stylesheet" href="//netdna.bootstrapcdn.com/font-awesome/3.2.0/css/font-awesome.css" />
        <link rel="stylesheet" href="<?php echo $theme_url ?>/includes/shadowbox/shadowbox.css" />
        <link rel="stylesheet" href="<?php echo $theme_url ?>/style.css" />

        <?php echo gcgc_pageMetaTags(); ?>

        <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
        <script src="<?php echo $theme_url ?>/js/plugins.js"></script>

        <?php wp_head(); ?>
    </head>

    <body>
        <div class="container">

            <div class="page-spinner"></div>
        
            <header>
                <h1><?php echo gcgc_getSiteTitle(); ?></h1>
                <h2><?php echo get_bloginfo( 'description' ); ?></h2>
            </header>