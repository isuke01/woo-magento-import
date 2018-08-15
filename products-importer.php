<?php
/*
Plugin Name: Magento import 2.x
Plugin URI: 
Description: Simple plugin to import magento csv, place dump files from magento in <code>wp-uploads/isu-magento-import</code> directory
Version:     1.0.0
Author:      Łukasz Biedroń
Author URI:  isuke.pl
Text Domain: isu_ma2
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
defined( 'ABSPATH' ) or die( 'Nope, not accessing this' );


register_activation_hook( __FILE__,  'isu_m2_plugin_activate'); //activate hook
// register_deactivation_hook(ISU_IMP_ABSP,  'plugin_deactivate'); //deactivate hook

function isu_m2_plugin_activate(){
    if ( !is_dir( WP_CONTENT_DIR.'/isu-magento-import/') ){
        mkdir(  WP_CONTENT_DIR.'/isu-magento-import/', 0777, true);
     }
}

function isu_ma2_init_plugin() {
    $plugin_data = get_plugin_data(  __FILE__ , true,  true );

    define( 'ISU_IMP_ABSP', plugin_dir_path(  __FILE__ ) );
    define( 'ISU_IMP_URL', plugin_dir_url(  __FILE__ ) );
    define( 'ISU_IMP_VER', $plugin_data['Version'] );
    define( 'ISU_IMP_DIR', WP_CONTENT_DIR.'/isu-magento-import/' );
    define( 'ISU_IMP_DIR_URL', WP_CONTENT_URL.'/isu-magento-import/' );
    
    load_plugin_textdomain( 'isu_ma2', false, ISU_IMP_ABSP.'languages/' );
   

    

    add_action('admin_enqueue_scripts', 'isu_m2_register_scripts_and_styles', 10);
    
    if (is_admin() ){
        require_once( ISU_IMP_ABSP . 'admin/options-views.php');
        require_once( ISU_IMP_ABSP . 'admin/ajax-functions.php');

    }
}
add_action('init', 'isu_ma2_init_plugin', 20);

function isu_m2_register_scripts_and_styles(){
    $screen = get_current_screen();

    if($screen->id === 'tools_page_isu-import-magento' ){

        wp_enqueue_style( 'isu_ma2', ISU_IMP_URL.'admin/style.min.css', null, ISU_IMP_VER, 'all' );

        $isu_ma2 = [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
        ];
        // https://vuejs.org/v2/guide/
        if(SCRIPT_DEBUG){
            wp_register_script( 'vue', ISU_IMP_URL . 'vue/vue.dev.js' , null, '2.5.17', true );
        }else{
            wp_register_script( 'vue', ISU_IMP_URL . 'vue/vue.js' , null, '2.5.17', true );
        }


        wp_register_script( 'isu_ma2', ISU_IMP_URL . 'admin/scripts.min.js' , ['vue', 'jquery'], ISU_IMP_VER, true );
        wp_localize_script( 'isu_ma2', 'isu_ma2', $isu_ma2);


        wp_enqueue_script( 'vue' );
        wp_enqueue_script( 'isu_ma2' );


    }

}