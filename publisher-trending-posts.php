<?php
/*
 Plugin Name: Web Publisher PRO - Trending Posts
 Plugin URI: https://webpublisherpro.com
 Description: Addons plugin for web publisher pro powered site
 Author: webpublisherpro
 Version: 1.0
 Author URI: https://webpublisherpro.com
 Text Domain: pro-addons
 */

defined( 'ABSPATH' ) || exit();

class Publisher_Trending_Posts {
    /**
     * The single instance of the class.
     *
     * @var self
     * @since  1.0.0
     */
    private static $_instance = null;

    /**
     * Publisher_Trending_Posts constructor.
     */
    public function __construct() {
        // Activation - works with symlinks.
        register_activation_hook( __FILE__, array( $this, 'install' ) );

        add_action( 'template_redirect', array( $this, 'count_post_views' ) );
    }

    /**
     * Install Database Tables
     */
    public function install() {
        global $wpdb;
        $wpdb->hide_errors();
        $collate = '';
        if ( $wpdb->has_cap( 'collation' ) ) {
            if ( ! empty( $wpdb->charset ) ) {
                $collate .= "DEFAULT CHARACTER SET $wpdb->charset";
            }
            if ( ! empty( $wpdb->collate ) ) {
                $collate .= " COLLATE $wpdb->collate";
            }
        }

        $table_schema = [
            "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wpp_post_view_logs` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `post_id` int(11) DEFAULT NULL,
                `ip` varchar(50) NOT NULL DEFAULT '',
                `hash` varchar(50) NOT NULL DEFAULT '',
                `date` datetime DEFAULT current_timestamp,
                PRIMARY KEY (`id`)
            ) $collate;",
        ];
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        foreach ( $table_schema as $table ) {
            dbDelta( $table );
        }
    }

    /**
     *
     */
    public function count_post_views() {
        if ( is_singular( 'post' ) ) {
            global $post;
            if ( $this->is_view_countable( $post->ID ) ) {
                $this->log_view( $post->ID );
            }
        }
    }

    /**
     * @param $post_id
     */
    private function log_view( $post_id ) {
        global $wpdb;
        $result = $wpdb->insert(
            "{$wpdb->prefix}wpp_post_view_logs",
            array(
                'post_id' => $post_id,
                'ip'      => $this->get_user_ip(),
                'hash'    => $this->get_hash( $post_id ),
                'date'    => current_time( 'mysql' ),
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%s',
            )
        );
    }

    /**
     * @param $post_id
     *
     * @return int
     */
    private function is_view_countable( $post_id ) {
        global $wpdb;
        $hash          = $this->get_hash( $post_id );
        $time_to_check = date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );

        return ! $wpdb->get_var( "SELECT count(id) from {$wpdb->prefix}wpp_post_view_logs WHERE hash='{$hash}' AND `date` > '{$time_to_check}'" );
    }

    /**
     * @param $post_id
     *
     * @return string
     */
    private function get_hash( $post_id ) {
        return md5( $post_id . $this->get_user_agent() . $this->get_user_ip() );
    }

    /**
     * get user ip
     *
     * @return mixed
     */
    private function get_user_ip() {
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            //ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            //ip pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    /**
     * @return mixed|string
     */
    private function get_user_agent() {
        return empty( $_SERVER['HTTP_USER_AGENT'] ) ? '' : $_SERVER['HTTP_USER_AGENT'];
    }

    /**
     * @param string $count
     * @param null $last_time
     */
    public function get_trending_posts( $count = '5', $last_time = null ) {
        if ( empty( $last_time ) ) {
            $last_time = date( 'Y-m-d H:i:s', strtotime( '-15 days' ) );
        }

        global $wpdb;
        $trending_post_column = $wpdb->get_results( $wpdb->prepare( "select post_id, count(*) total_view from {$wpdb->prefix}wpp_post_view_logs where `date`>=%s group by post_id order by total_view desc limit %d", $last_time, $count ) );
        $wpdb->query($wpdb->prepare("DELETE from {$wpdb->prefix}wpp_post_view_logs where `date`<=%s", $last_time));
        $posts_query_args = array();

        if ( ! empty( $trending_post_column ) ) {
            $trending_post_ids           = wp_list_pluck( $trending_post_column, 'post_id' );

            $posts_query_args['post__in'] = $trending_post_ids;
            $posts_query_args['orderby'] = 'post__in';
        }

        return  get_posts( apply_filters('publisher_trending_posts_args', $posts_query_args) );
    }


    /**
     * @return Publisher_Trending_Posts
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

}

function publisher_trending_posts() {
    return Publisher_Trending_Posts::instance();
}

//do magic
publisher_trending_posts();