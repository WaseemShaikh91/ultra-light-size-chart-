<?php
/**
 * Plugin Name: Ultra-Light Size Chart for Woo
 * Plugin URI: https://youngbazar.com
 * Description: The complete solution: Inheritance logic, CSV management, flexible positioning, and Tab/Popup modes.
 * Version: 2.2.2
 * Author: Waseem Shaikh
 * Text Domain: scfw-size-chart
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants
define( 'SCFW_CAT_META', '_scfw_category_chart' );
define( 'SCFW_PROD_META', '_scfw_show_chart' );

/**
 * --------------------------------------------------
 * 1. Initialize & Admin Assets
 * --------------------------------------------------
 */
add_action( 'admin_enqueue_scripts', 'scfw_admin_scripts' );
function scfw_admin_scripts( $hook ) {
    if ( 'toplevel_page_scfw-size-chart' === $hook || 'term.php' === $hook || 'edit-tags.php' === $hook ) {
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'scfw-admin-js', false, array( 'wp-color-picker' ), false, true );
        wp_add_inline_script( 'scfw-admin-js', 'jQuery(document).ready(function($){ $(".scfw-color-field").wpColorPicker(); });' );
    }
}

/**
 * --------------------------------------------------
 * 2. Data Logic Layer (The "Brain")
 * --------------------------------------------------
 */
function scfw_parse_csv( $raw_text ) {
    if ( empty( $raw_text ) ) return false;
    $lines = preg_split( '/\r\n|\r|\n/', trim( $raw_text ) );
    $lines = array_filter( $lines );
    if ( empty( $lines ) ) return false;

    $first_line = array_shift( $lines );
    $headers = array_map( 'trim', explode( ',', $first_line ) );

    $rows = [];
    foreach ( $lines as $line ) {
        if ( empty( trim( $line ) ) ) continue;
        $cols = array_map( 'trim', explode( ',', $line ) );
        $rows[] = $cols;
    }

    if ( empty( $headers ) || empty( $rows ) ) return false;
    return [ 'headers' => $headers, 'rows' => $rows ];
}

function scfw_get_product_chart_data( $product_id ) {
    // 1. Check Product Veto
    $product_meta = get_post_meta( $product_id, SCFW_PROD_META, true );
    if ( 'no' === $product_meta ) return false;

    // 2. Check Category Inheritance
    $terms = get_the_terms( $product_id, 'product_cat' );
    if ( $terms && ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            $cat_raw = get_term_meta( $term->term_id, SCFW_CAT_META, true );
            if ( ! empty( $cat_raw ) ) {
                $data = scfw_parse_csv( $cat_raw );
                if ( $data ) return $data;
            }
        }
    }

    // 3. Check Global Fallback
    $global_on = get_option( 'scfw_global_enable', 'yes' ) === 'yes';
    if ( ! $global_on && $product_meta !== 'yes' ) return false;

    $h_str = get_option( 'scfw_chart_headers', '' );
    $r_str = get_option( 'scfw_chart_rows', '' );
    return scfw_parse_csv( $h_str . "\n" . $r_str );
}

/**
 * --------------------------------------------------
 * 3. Menu & Settings (Added Position Options)
 * --------------------------------------------------
 */
add_action( 'admin_menu', 'scfw_add_admin_menu' );
function scfw_add_admin_menu() {
    add_menu_page( 'Size Chart', 'Size Chart', 'manage_options', 'scfw-size-chart', 'scfw_render_admin_page', 'dashicons-editor-table', 56 );
}

add_action( 'admin_init', 'scfw_register_settings' );
function scfw_register_settings() {
    register_setting( 'scfw_settings_group', 'scfw_chart_headers' );
    register_setting( 'scfw_settings_group', 'scfw_chart_rows' );
    register_setting( 'scfw_settings_group', 'scfw_display_mode' ); 
    register_setting( 'scfw_settings_group', 'scfw_button_location' ); // NEW: Hook Position
    register_setting( 'scfw_settings_group', 'scfw_btn_text' );
    register_setting( 'scfw_settings_group', 'scfw_brand_color' );
    register_setting( 'scfw_settings_group', 'scfw_text_color' );
    register_setting( 'scfw_settings_group', 'scfw_global_enable' );
}

function scfw_render_admin_page() {
    $headers = get_option( 'scfw_chart_headers', 'Size, Chest (in), Length (in)' );
    $rows    = get_option( 'scfw_chart_rows', "S, 38, 27\nM, 40, 28\nL, 42, 29\nXL, 44, 30" );
    $mode    = get_option( 'scfw_display_mode', 'popup' );
    $loc     = get_option( 'scfw_button_location', 'woocommerce_single_product_summary' );
    $btn_txt = get_option( 'scfw_btn_text', '📏 Size Guide' );
    $bg_col  = get_option( 'scfw_brand_color', '#000000' );
    $txt_col = get_option( 'scfw_text_color', '#ffffff' );
    $global  = get_option( 'scfw_global_enable', 'yes' );
    ?>
    <div class="wrap">
        <h1 style="font-weight:800;">📏 Global Size Chart</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'scfw_settings_group' ); ?>
            
            <div style="background:#fff; padding:15px; border-left:4px solid #0073aa; margin-bottom:20px;">
                <h3 style="margin-top:0;">Global Visibility</h3>
                <label>
                    <input type="checkbox" name="scfw_global_enable" value="yes" <?php checked( $global, 'yes' ); ?>>
                    Enable Default Chart?
                </label>
            </div>

            <div style="display:flex; gap:30px; flex-wrap:wrap;">
                <div style="flex:2; min-width:300px;">
                    <h3>📊 Default Data</h3>
                    <label><strong>Headers:</strong></label>
                    <input type="text" name="scfw_chart_headers" value="<?php echo esc_attr( $headers ); ?>" class="regular-text" style="width:100%;">
                    <br><br>
                    <label><strong>Rows:</strong></label>
                    <textarea name="scfw_chart_rows" rows="10" style="width:100%; font-family:monospace;"><?php echo esc_textarea( $rows ); ?></textarea>
                </div>
                <div style="flex:1; min-width:250px; background:#f9f9f9; padding:20px; border-radius:8px;">
                    <h3>🎨 Settings</h3>
                    <p>
                        <label><b>Display Mode</b></label><br>
                        <select name="scfw_display_mode">
                            <option value="popup" <?php selected($mode, 'popup'); ?>>Button + Popup</option>
                            <option value="tab" <?php selected($mode, 'tab'); ?>>Product Tab</option>
                        </select>
                    </p>
                    <p>
                        <label><b>Button Position</b> (Popup Mode Only)</label><br>
                        <select name="scfw_button_location">
                            <option value="woocommerce_single_product_summary" <?php selected($loc, 'woocommerce_single_product_summary'); ?>>Summary (Standard)</option>
                            <option value="woocommerce_before_add_to_cart_form" <?php selected($loc, 'woocommerce_before_add_to_cart_form'); ?>>Before Add to Cart Form</option>
                            <option value="woocommerce_after_add_to_cart_button" <?php selected($loc, 'woocommerce_after_add_to_cart_button'); ?>>After Add to Cart Button</option>
                            <option value="woocommerce_product_meta_end" <?php selected($loc, 'woocommerce_product_meta_end'); ?>>After Meta (SKU/Cats)</option>
                        </select>
                    </p>
                    <p><label>Button/Tab Text</label><br><input type="text" name="scfw_btn_text" value="<?php echo esc_attr( $btn_txt ); ?>" class="regular-text"></p>
                    <p><label>Brand Color</label><br><input type="text" name="scfw_brand_color" value="<?php echo esc_attr( $bg_col ); ?>" class="scfw-color-field"></p>
                    <p><label>Text Color</label><br><input type="text" name="scfw_text_color" value="<?php echo esc_attr( $txt_col ); ?>" class="scfw-color-field"></p>
                </div>
            </div>
            <p class="submit"><button type="submit" class="button button-primary button-large">Save Settings</button></p>
        </form>
    </div>
    <?php
}

/**
 * --------------------------------------------------
 * 4. Category & Product Settings (Inheritance)
 * --------------------------------------------------
 */
add_action( 'product_cat_add_form_fields', 'scfw_add_category_fields' );
add_action( 'product_cat_edit_form_fields', 'scfw_edit_category_fields' );
add_action( 'created_product_cat', 'scfw_save_category_fields' );
add_action( 'edited_product_cat', 'scfw_save_category_fields' );

function scfw_add_category_fields() { echo '<div class="form-field"><label>📏 Size Chart CSV</label><textarea name="scfw_cat_chart" rows="5"></textarea></div>'; }
function scfw_edit_category_fields( $term ) { $val = get_term_meta( $term->term_id, SCFW_CAT_META, true ); echo '<tr class="form-field"><th><label>📏 Size Chart CSV</label></th><td><textarea name="scfw_cat_chart" rows="6" style="width:100%">'.esc_textarea($val).'</textarea></td></tr>'; }
function scfw_save_category_fields( $term_id ) { if ( isset( $_POST['scfw_cat_chart'] ) ) update_term_meta( $term_id, SCFW_CAT_META, sanitize_textarea_field( $_POST['scfw_cat_chart'] ) ); }

add_action( 'woocommerce_product_options_general_product_data', 'scfw_add_product_meta' );
function scfw_add_product_meta() {
    echo '<div class="options_group">';
    $val = get_post_meta( get_the_ID(), SCFW_PROD_META, true );
    if ( '' === $val ) $val = get_option( 'scfw_global_enable', 'yes' ) === 'yes' ? 'yes' : 'no';
    woocommerce_wp_checkbox( array( 'id' => SCFW_PROD_META, 'label' => 'Show Size Chart', 'value' => $val ) );
    echo '</div>';
}
add_action( 'woocommerce_process_product_meta', function( $id ) { update_post_meta( $id, SCFW_PROD_META, isset($_POST[SCFW_PROD_META])?'yes':'no' ); } );
add_action( 'save_post_product', function( $id ) { if(!metadata_exists('post',$id,SCFW_PROD_META)) update_post_meta($id,SCFW_PROD_META,get_option('scfw_global_enable','yes')==='yes'?'yes':'no'); }, 10, 3 );


/**
 * --------------------------------------------------
 * 5. Frontend Logic (Decoupled Renderer & Hooks)
 * --------------------------------------------------
 */

// A. The Pure Renderer (Returns HTML, doesn't echo)
function scfw_generate_chart_html( $product_id, $mode = 'popup' ) {
    $data = scfw_get_product_chart_data( $product_id );
    if ( ! $data ) return '';

    $btn_txt = get_option( 'scfw_btn_text', 'Size Guide' );
    $unique_id = 'scfw-' . $product_id;

    ob_start();
    
    // Only render button if mode is popup
    if ( $mode === 'popup' ) {
        echo '<div class="scfw-wrapper" style="margin: 10px 0; clear:both;">';
        echo '<a href="#" class="scfw-btn scfw-trigger" data-modal="'.esc_attr($unique_id).'">'.esc_html( $btn_txt ).'</a>';
        echo '</div>';
    }

    // Modal Wrapper (Only for popup)
    if ( $mode === 'popup' ) echo '<div id="'.esc_attr($unique_id).'" class="scfw-modal"><div class="scfw-modal-content"><span class="scfw-close">&times;</span><h3>'.esc_html($btn_txt).'</h3>';
    
    // The Table (Used in both modes)
    echo '<div class="scfw-table-responsive"><table class="scfw-table">';
    echo '<thead><tr>';
    foreach($data['headers'] as $th) echo '<th>'.esc_html($th).'</th>';
    echo '</tr></thead><tbody>';
    foreach($data['rows'] as $row) {
        echo '<tr>';
        foreach($row as $td) echo '<td>'.esc_html($td).'</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';

    // Close Modal Wrapper
    if ( $mode === 'popup' ) echo '</div></div>';
    
    return ob_get_clean();
}

// B. The Hook Logic (Decides WHERE to show)
add_action( 'wp', 'scfw_init_frontend_hooks' );
function scfw_init_frontend_hooks() {
    if ( is_admin() ) return;
    if ( ! is_product() ) return; // Safety Guard: Only run on single product pages

    $mode = get_option('scfw_display_mode', 'popup');

    if ( $mode === 'popup' ) {
        // Dynamic Hook Location
        $hook_name = get_option( 'scfw_button_location', 'woocommerce_single_product_summary' );
        
        // Priority adjustment (summary needs late priority, buttons need standard)
        $priority = ($hook_name === 'woocommerce_single_product_summary') ? 25 : 10;
        
        add_action( $hook_name, function() {
            global $product;
            if ( $product ) {
                echo scfw_generate_chart_html( $product->get_id(), 'popup' );
            }
        }, $priority );
    }
}

// C. Product Tabs (Tab Mode)
add_filter( 'woocommerce_product_tabs', function( $tabs ) {
    if ( get_option('scfw_display_mode') === 'tab' ) {
        global $post;
        if ( scfw_get_product_chart_data( $post->ID ) ) {
            $tabs['scfw_tab'] = [
                'title' => get_option( 'scfw_btn_text', 'Size Guide' ),
                'priority' => 50,
                'callback' => function() use ($post) { echo scfw_generate_chart_html( $post->ID, 'tab' ); }
            ];
        }
    }
    return $tabs;
} );

// D. Shortcode (Universal Fallback)
add_shortcode( 'scfw_size_chart', function() {
    global $post;
    if ( ! $post ) return '';
    return scfw_generate_chart_html( $post->ID, 'tab' );
} );

// CSS & JS
add_action( 'wp_footer', 'scfw_frontend_styles' );
function scfw_frontend_styles() {
    $bg_col = get_option( 'scfw_brand_color', '#000000' );
    $txt_col = get_option( 'scfw_text_color', '#ffffff' );
    ?>
    <style>
        .scfw-btn { background-color: <?php echo $bg_col; ?>; color: <?php echo $txt_col; ?>; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block; }
        .scfw-btn:hover { opacity: 0.9; color: <?php echo $txt_col; ?>; }
        .scfw-modal { display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .scfw-modal-content { background-color: #fefefe; margin: 10% auto; padding: 25px; border: 1px solid #888; width: 90%; max-width: 600px; border-radius: 8px; position: relative; }
        .scfw-close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .scfw-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        .scfw-table th, .scfw-table td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        .scfw-table th { background-color: #f8f8f8; font-weight: 700; }
        .scfw-table-responsive { overflow-x: auto; }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.body.addEventListener('click', function(e) {
            if (e.target.matches('.scfw-trigger') || e.target.closest('.scfw-trigger')) {
                e.preventDefault();
                let trigger = e.target.matches('.scfw-trigger') ? e.target : e.target.closest('.scfw-trigger');
                let modal = document.getElementById(trigger.getAttribute('data-modal'));
                if(modal) modal.style.display = 'block';
            }
            if (e.target.matches('.scfw-close') || e.target.matches('.scfw-modal')) {
                let modal = e.target.closest('.scfw-modal') || e.target;
                if(modal) modal.style.display = 'none';
            }
        });
    });
    </script>
    <?php
}
