<?php
/*
Plugin Name: Quick Edit
Description: <strong>Quick Edit</strong> is a Dashboard Widget. It provides the flexibiltiy to add any post or page to this dashboard widget with a metabox. The added posts or pages can be edited easily from this dashboard widget.
Author: Zakaria Binsaifullah
Author URI: https://wpquerist.com
Version: 1.6.1
Text Domain: quick-edit
License: GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Domain Path:  /languages
*/

// security check
defined( 'ABSPATH' ) or die( 'Stop tryig this wrong way. Go Back.' );

// appsero tracker
if( !class_exists('Appsero\Client') ) {
    require __DIR__ . '/appsero/src/Client.php';
}

function appsero_init_tracker_quick_edit() {
    $client = new Appsero\Client( '3eacb302-2053-4d04-84f1-911062f2b209', 'quick-edit', __FILE__ );
    // Active insights
    $client->insights()->init();
}

appsero_init_tracker_quick_edit();

class QUICK_EDIT {
    public function __construct() {
        // text-domain load
        add_action( 'plugins_loaded', array( $this, 'que_text_domain_load' ) );

        // add metaxbox for quick edit
        add_action( 'admin_menu', array( $this, 'que_quick_edit_metabox' ) );

        // Metabox data save
        add_action( 'save_post', array( $this, 'que_metabox_data_save' ) );

        // Dashboard widget
        add_action( 'wp_dashboard_setup', array( $this, 'que_quick_edit_dashboard' ) );
    }
    /*
     * Plugin text domain load function
     * */
    public function que_text_domain_load() {
        load_plugin_textdomain( 'quick-edit', false, dirname( plugin_basename( __FILE__ ) . '/languages' ) );
    }

    /*
     * Quick Edit Metabox
     * */
    public function que_quick_edit_metabox() {
        add_meta_box( 'que_metabox', __( 'Quick Edit', 'quick-edit' ), array( $this, 'que_metabox_markup' ), array( 'post', 'page' ), 'side', 'default' );
    }

    /*
     * Nonce Security Check function
     * */

    private function is_secured( $name, $action, $post_id ) {
        $que_nonce = isset( $_POST[ $name ] ) ? sanitize_text_field( $_POST[ $name ] ) : '';

        if ( $que_nonce == '' ) {
            return false;
        }

        if ( !wp_verify_nonce( $que_nonce, $action ) ) {
            return false;
        }

        if ( !current_user_can( 'edit_post', $post_id ) ) {
            return false;
        }

        if ( wp_is_post_autosave( $post_id ) ) {
            return false;
        }

        if ( wp_is_post_revision( $post_id ) ) {
            return false;
        }

        return true;
    }

    /*
     * Metabox Data Save
     * */
    public function que_metabox_data_save( $id ) {

        // security check
        if ( !$this->is_secured( 'que_metabox_name', 'que_metabox_action', $id ) ) {
            return $id;
        }

        $que_data = isset( $_POST[ 'que_metabox' ] ) ? sanitize_text_field( $_POST[ 'que_metabox' ] ) : '';
        if ( !isset( $que_data ) && $que_data == '' ) {
            return $id;
        }

        update_post_meta( $id, 'que_metabox', $que_data );
    }

    // Metabox Markup
    public function que_metabox_markup( $post_obj ) {
        $que_meta_label = __( 'Add to Quick Edit', 'quick-edit' );

        // nonce for security
        wp_nonce_field( 'que_metabox_action', 'que_metabox_name' );

        $que_checked   = '';
        $que_user_data = get_post_meta( $post_obj->ID, 'que_metabox', true );
        if ( $que_user_data == true ) {
            $que_checked = 'checked';
        }

        $que_markup = <<<EOD
        <p>
            <input type="checkbox" name="que_metabox" id="que_metabox" {$que_checked}> {$que_meta_label}
        </p>
EOD;

        echo $que_markup;

    }

    /*
     * Dashbaord Widget Setup
     * */
    public function que_quick_edit_dashboard() {
        if ( current_user_can( 'edit_dashboard' ) ) {
            wp_add_dashboard_widget( 'que_quick_edit_dashboard_id', __( 'Quick Edit', 'quick-edit' ), array( $this, 'que_dashboard_output' ), array( $this, 'que_dashboard_settings' ) );
        } else {
            wp_add_dashboard_widget( 'que_quick_edit_dashboard_id', __( 'Quick Edit', 'quick-edit' ), array( $this, 'que_dashboard_output' ) );
        }
    }

    // Dashboard control settings 
    public function que_dashboard_settings() {
        $que_item_number = get_option( 'que_setting_item_number', -1 );

        $que_user_item_number = isset( $_POST[ 'que_setting_item' ] ) ? sanitize_text_field( $_POST[ 'que_setting_item' ] ) : -1;
        if ( $que_user_item_number >= -1 ) {
            update_option( 'que_setting_item_number', $que_user_item_number );
        }

        ?>
        <p>
            <label for="que_setting_item"><?php esc_html_e( 'Items Show ( Value -1 shows all items )', 'quick-edit' ); ?></label>
            <input class="widefat" type="number" name="que_setting_item" id="que_setting_item"
                   value="<?php echo esc_attr( $que_item_number ); ?>">
        </p>
        <?php
    }

    // Dashboard output
    public function que_dashboard_output() {
        // number of items show
        $que_item_number = get_option( 'que_setting_item_number', 5 );

        $que_posts = null;
        $que_posts = new WP_Query( array(
            'post_type'      => array( 'post', 'page' ),
            'meta_key'       => 'que_metabox',
            'meta_value'     => 'on',
            'posts_per_page' => $que_item_number,
            'order'          => 'ASC',
            'orderby'        => 'title'
        ) );

        while ( $que_posts->have_posts() ) {
            $que_posts->the_post();

            // Post information
            $que_post_id        = get_the_ID();
            $que_post_edit_link = site_url() . "/wp-admin/post.php?post={$que_post_id}&action=edit";
            $que_post_title     = get_the_title( get_the_ID() );

            // post structure
            if ( get_post_type( get_the_ID() ) == 'post' ) {
                printf( "<p><b>Post: </b><a href='%s'>%s</a></p>", esc_url( $que_post_edit_link ), esc_html( $que_post_title ) );
            } elseif ( get_post_type( get_the_ID() ) == 'page' ) {
                printf( "<p><b>Page: </b><a href='%s'>%s</a></p>", esc_url( $que_post_edit_link ), esc_html( $que_post_title ) );
            }
        }

        wp_reset_query();

    }


}

if ( class_exists( 'QUICK_EDIT' ) ) {
    $que_class = new QUICK_EDIT();
}