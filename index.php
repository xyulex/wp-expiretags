<?php
/*  Copyright 2015 Raúl Martínez

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
/*
Plugin Name: Expire Tags
Description: Expire tags allows you to add a date to a tag to expire it.  When the date is reached the tag is no longer associated with the post, but the tag is not removed and the post is not deleted. 
This could be used to display a custom query by tag of important issues or upcoming events.
Version: 0.1.1
Author: Raúl Martínez
License: GPL2
*/

defined( 'ABSPATH' ) or die( 'No direct access permitted!' );

register_activation_hook( __FILE__, 'expiretags_install' );

// Actions
add_action( 'admin_menu', 'expiretags_menu' );
add_action( 'init', 'expiretags_save' );
add_action( 'init', 'expiretags_expire' );
add_action( 'init', 'expiretags_translation' );

// Cronable
if ( ! wp_next_scheduled( 'expiretags_checkexpirations' ) ) {
  wp_schedule_event( time(), 'hourly', 'expiretags_check' );
}

add_action( 'expiretags_check', 'expiretags_expire' );

function expiretags_translation()
{
    load_plugin_textdomain('expiretags', FALSE, dirname(plugin_basename(__FILE__)).'/languages/');
}

function expiretags_menu() {
    add_options_page( 'Options', 'Expire tags', 'manage_options', 'expiretags_options', 'expiretags_options' );
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
    $pluginsUrl = plugins_url('assets/calendar.gif', __FILE__ );

    echo( "<script>jQuery(document).ready(function(){    
        jQuery( '.datepicker' ).datepicker({
            showOn: 'button',
            buttonImage: '". $pluginsUrl ."',
            buttonImageOnly: true,
            buttonText: '',
            dateFormat: 'yy-mm-dd'
        });
    });</script>");    
    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_enqueue_style( 'jquery-style', plugins_url( '/css/jquery-ui.css', __FILE__ ) );

    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __('nopermissions','expiretags') );
    }
    
    echo '<div class="wrap">';
                    $sql = "SELECT t.name,t.slug,ta.taxonomy, ta.term_id 
                            from " .$wpdb->prefix. "terms t,".$wpdb->prefix. "term_taxonomy ta 
                            where
                            t.term_id = ta.term_id
                            and ta.taxonomy = 'post_tag'
                            order by t.name";                            

                    $posts = $wpdb->get_results($sql);

                    if ($posts) {                        
                        echo "<form name='expiretags' action='' method='post'>";
                        echo "<table>";
                        echo "<th>".__('tagname', 'expiretags')."</th><th>".__('tagexpirationdate', 'expiretags')."</th>";
                        foreach ($posts as $post) {
                            $expirationsql = "SELECT * from ".$wpdb->prefix."expiretags 
                                    WHERE term_id = ".$post->term_id;                           

                            $expirations = $wpdb->get_results($expirationsql);                            

                            $expiration = '';
                            if ($expirations){
                                $expiration = $expirations[0]->expiration_datetime;
                            } 
                            echo('<tr><td>'.$post->name.'</td><td class="expire-tags-calendar"><input type="text" class="datepicker" id='.$post->term_id.' name='.$post->term_id.' value='.$expiration.'></td></tr>');
                        }
                        echo '<tr><td><input name="submit" id="submit" class="button button-primary" value="'.__('Save Changes').'"" type="submit"></td></tr>';
                      
                        echo("</form>");
                    } else {
                        wp_die( __('notags', 'expiretags'));
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