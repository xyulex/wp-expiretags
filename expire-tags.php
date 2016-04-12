<?php
/*
Plugin Name: Expire Tags
Text Domain: expire-tags
Domain Path: /languages
Author:      xyulex
Author URI:  https://profiles.wordpress.org/xyulex/
Description: Expire tags allows you to add a date to a tag to expire it.  When the date is reached the tag is no longer associated with the post, but the tag is not removed and the post is not deleted. 
This could be used to display a custom query by tag of important issues or upcoming events.
Version: 1.1
License:     GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) or die( 'No direct access permitted!' );

// Create a helper function for easy SDK access.
function et_fs() {
    global $et_fs;

    if ( ! isset( $et_fs ) ) {
        // Include Freemius SDK.
        require_once dirname(__FILE__) . '/freemius/start.php';

        $et_fs = fs_dynamic_init( array(
            'id'                => '249',
            'slug'              => 'expire-tags',
            'public_key'        => 'pk_53e85f2d7ace61b16dac9a54d13ea',
            'is_premium'        => false,
            'has_addons'        => false,
            'has_paid_plans'    => false,
            'menu'              => array(
                'slug'       => 'expire-tags',
                'first-path' => 'plugins.php',
                'account'    => false,
                'contact'    => false,
                'support'    => false,
            ),
        ) );
    }

    return $et_fs;
}

// Init Freemius.
et_fs();

register_activation_hook( __FILE__, 'expiretags_install' );

// Actions
add_action( 'admin_menu', 'expiretags_menu' );
add_action( 'init', 'expiretags_init' );

// Cronable
if ( ! wp_next_scheduled( 'expiretags_checkexpirations' ) ) {
  wp_schedule_event( time(), 'hourly', 'expiretags_check' );
}

add_action( 'expiretags_check', 'expiretags_expire' );

function expiretags_init() {
    wp_register_script( 'expirejs', plugins_url().'/expire-tags/js/functions.js' );
    $delete_message = __('Remove the expiration date for this tag?','expire-tags');
    wp_localize_script(
        'expirejs', 'ET',
        array('plugins_url'     => plugins_url().'/expire-tags/',
             'delete_confirm'   => $delete_message
        ));
    wp_enqueue_script( 'expirejs' );
    expiretags_save();
    expiretags_expire();
    load_plugin_textdomain('expire-tags', FALSE, dirname(plugin_basename(__FILE__)).'/languages/');
}

function expiretags_menu() {
    add_options_page( 'Options', __('Expire tags','expire-tags'), 'manage_options', 'expiretags_options', 'expiretags_options' );
}


function expiretags_install() {
    require_once( '../wp-admin/includes/upgrade.php' );
    global $wpdb;
    global $charset_collate;

    $table_name = $wpdb->prefix . 'expiretags';

    $sql = "CREATE TABLE $table_name (
        id int(11) unsigned NOT NULL AUTO_INCREMENT,
        term_id int(11) unsigned NOT NULL,
        expiration_datetime date NOT NULL DEFAULT '0000-00-00',
        UNIQUE KEY id (id)
        ) $charset_collate;";

    dbDelta( $sql );
}

function expiretags_options() {
    global $wpdb;
    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_enqueue_style( 'expiretags-styles', plugins_url( '/css/style.css', __FILE__ ) );
    wp_enqueue_script( 'expiretags-scripts', plugins_url('/js/functions.js', __FILE__));

    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __('nopermissions','expire-tags') );
    }

    echo '<div class="wrap">';
    $sql = "SELECT t.name,t.slug,ta.taxonomy, ta.term_id
            from " .$wpdb->prefix. "terms t,".$wpdb->prefix. "term_taxonomy ta
            where
            t.term_id = ta.term_id
            and ta.taxonomy = 'post_tag'
            order by t.name";

    $total_query = "SELECT COUNT(1) FROM (${sql}) AS combined_table";
    $total = $wpdb->get_var( $total_query );
    $items_per_page = 10;
    $page = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : 1;
    $offset = ( $page * $items_per_page ) - $items_per_page;
    $posts = $wpdb->get_results( $sql . " LIMIT ${offset}, ${items_per_page}" );

    if ($posts) {
        echo
        "<form name='expiretags' action='' method='post' id='expiretags2'>".
        "<table>".
        "<th>".__('tagname', 'expire-tags')."</th><th>".__('tagexpirationdate', 'expire-tags')."</th>";
        foreach ($posts as $post) {
            $expirationsql = "SELECT * from ".$wpdb->prefix."expiretags WHERE term_id = ".$post->term_id;

            $expirations = $wpdb->get_results($expirationsql);

            $expiration = '';
            if ($expirations){
                $expiration = $expirations[0]->expiration_datetime;
            }

            echo('<tr><td>'.$post->name.'</td><td class="expire-tags-calendar"><input type="text" class="datepicker" id='.$post->term_id.' name='.$post->term_id.' value='.$expiration.'></td><td><input type="button" class="expire-btn" value="" data-name="'.$post->name.'" data-id="' . $post->term_id . '"></td></tr>');
        }
        echo '<tr><td><input name="submit" id="submit" class="button button-primary" value="'.__('Save changes','expire-tags').'"" type="submit"></td></tr>';
        echo '</form>';

    } else {
        wp_die( __('notags', 'expire-tags'));
    }

    $paginate_links = paginate_links( array(
        'base'              => add_query_arg( 'cpage', '%#%' ),
        'format'            => '',
        'prev_text'         => '« '. __('Previous', 'expire-tags'),
        'next_text'         => __('Next', 'expire-tags').' »',
        'total'             => ceil($total / $items_per_page),
        'current'           => $page,
        'type'              => 'array'
    ));

    if ($paginate_links) {
        foreach ($paginate_links as $paginate_link) {
            echo '<span style="margin-right:20px;">'.$paginate_link.'</span>';
        }
    }

    echo '</div>';
}

function expiretags_expire() {
    global $wpdb;

    $date = date("Y-m-d");
    $tags = $wpdb->get_results("SELECT * from ". $wpdb->prefix."expiretags where expiration_datetime <'". $date ."'");

    if ($tags){
        foreach($tags as $tag) {
            $tt = $wpdb->get_results("SELECT term_taxonomy_id from ". $wpdb->prefix."term_taxonomy where term_id =" .$tag->term_id);
            if ($tt) {
                $trs = $wpdb->get_results("select * from ". $wpdb->prefix."term_relationships where term_taxonomy_id=".$tt[0]->term_taxonomy_id);
                foreach($trs as $tr) {
                    $wpdb->delete( $wpdb->prefix."term_relationships", array( 'object_id' => $tr->object_id ) );
                }

            }
            $wpdb->delete( $wpdb->prefix."expiretags", array( 'term_id' => $tag->term_id ) );
        }
    }
}

function expiretags_save() {
    global $wpdb;

    $table = $wpdb->prefix."expiretags";

    if ($_POST) {
        // Save data
        foreach($_POST as $key=>$value) {
            if ($value && $key != 'submit' && $key > 0) {
                $data = array(
                        'term_id'               => $key,
                        'expiration_datetime'   => $value
                        );

                $exists = $wpdb->get_results (
                        "
                        SELECT *
                        FROM  ". $wpdb->prefix."expiretags
                        WHERE term_id = " . $key
                        );
                if (!$exists) {
                    $wpdb->insert($table, $data);
                } else {
                    $where = array ('term_id' => $key);
                    $wpdb->update($table, $data, $where);
                }
            }
        }
    }
}
?>