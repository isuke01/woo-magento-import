<?php
defined( 'ABSPATH' ) or die( 'Nope, not accessing this' );

function isu_ma2_add_submenu() {

    add_management_page(
        __('Isu import magento', 'isu_ma2'), 
        __('Import magento 2.x', 'isu_ma2'),
        'administrator', 
        'isu-import-magento', 
        'isu_ma2_settings_views'
    );


}
add_action('admin_menu', 'isu_ma2_add_submenu');


function isu_ma2_settings_views() {
    ?>
    <div  class="wrap">
        <h1>Import from Magento 2.x</h1>
        <div id="isu_ma2_app">
            <div class="app-main" style="opacity: 0; visibility: hidden" :class="{ loaded : loaded }" >
            <?php 
                if(!is_dir( ISU_IMP_DIR )){
                    printf(__('Make sure directort exist<code>%1s</code>', 'isu_ma2'), ISU_IMP_DIR );                
                    echo '<br/>';
                    _e('If it not, create it manually, and put your import files into this directory', 'isu_ma2');
                }else{
                    printf(__('Make sure to upload directly  <code>%1s</code> into folder yor .csv file and directory where are extracted images', 'isu_ma2'), ISU_IMP_DIR );                
                    _e('Select CSV file and related directory that contain import images', 'isu_ma2');
                    $directory = ISU_IMP_DIR;
                    //get all files in specified directory
                    $items = glob($directory . "*");
                    //print each file name
                    $files= [];
                    $dirs= [];
                    foreach($items as $key=> $item)
                    {
                        if(is_dir($item)){
                            $dirs[]= $item;
                        }else{
                            $files[]= $item;
                        }
                    }

                    echo '<div class="select-items">';
                    echo '<label for="select-dir">'.__('Select Directory', 'isu_ma2').'</label>';
                    echo '<select name="select-dir" id="select-dir" class="select-base" v-model="data.dir">';
                    foreach ($dirs as $key_dirs => $dir) {
                        echo '<option value="'.basename($dir).'">'.basename($dir).'</option>';
                    }
                    echo '<select></div>';

                    echo '<div class="select-items">';
                    echo '<label for="select-file">'.__('Select File', 'isu_ma2').'</label>';
                    echo '<select name="select-file" id="select-file" class="select-base" v-model="data.file">';
                    foreach ($files as $key_files => $file) {
                        echo '<option value="'. $file.'">'.basename($file).'</option>';
                    }
                    echo '<select></div>';
                }
            ?>
            <div class="error-info" v-if="error">{{error}}</div>
            <button ref="button" @click="startImport('false')" class="button button-primary" :disabled="doingImport"><?php _e('Import', 'isu_ma2') ?></button>
            <button ref="button_test" @click="startImport('true')" class="button button-secondary" :disabled="doingImport"><?php _e('Test import', 'isu_ma2') ?></button>
                <div id="import-log">
                <div id="progress"><div class="progress-bar" :style="{ 'width' : progressWidth }"></div></div>
                    <table id="outtable" style="overfollow: scroll" v-html="debug">
                    </table>
                </div>
            </div>
            <div class="loading" v-if="!loaded"><?php _e( 'LOADING IMPORTER... ,<br/> Javascript is required and modern browser!', 'isu_ma2' ) ?></div>
        </div>        
    
    </div>
    <?php 
}