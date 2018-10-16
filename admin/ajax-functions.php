<?php
defined( 'ABSPATH' ) or die( 'Nope, not accessing this' );

if (  is_admin() ) {
	add_action( 'wp_ajax_import_csv_products', 'import_csv_products' );
	add_action( 'wp_ajax_nopriv_import_csv_products', 'import_csv_products' );
}


function import_csv_products(){
    ini_set('html_errors', 0);

    global $option;
    // $folder_name = $option['images-folder'];

    $test_mode = ($_REQUEST['debug'] === 'true')? true : false;

    $fullsize_path =  $_REQUEST['data']['file'];
    $media_dir     =  $_REQUEST['data']['dir'];
    $current_item  = (int) $_REQUEST['current_item'];

	$csvFile = file( $fullsize_path );
    $csv = array_map( 'str_getcsv', file( $fullsize_path ) );
    if($test_mode){
        $items = 20;//count($csv); // count imtes in CSV
    }else{
        $items = count($csv); // count imtes in CSV
    }
    $step = 20; // import items per step

    $outut_html = '';
    // start table
    if ($current_item <= 1) {
    	$outut_html .= '<tr>';
    	$exp_keys = [ 'ID', 'Obrazek', 'Produkt', 'SKU', 'Cena', 'Kategorie','Type'];
    	foreach ($exp_keys as $value) {
    		$outut_html .= '<th>'.$value.'</th>';
    	}
    	$outut_html .= '</tr>';
    }

    // //first is CSV KEY
    $excluded = 0;
    $product_ibnsert = [];
    for ($i=1; $i <= $step ; $i++) {
    	if( $current_item > $items || !isset( $csv[$current_item] ) ) break;
        $product_data = isu_ma2_product_woo_data( $csv[$current_item], $media_dir );

        $test = ob_get_clean();
        $current_item++;
        // We want without any lang
        if( $product_data['store_view_code'] !== '' ) {
            $excluded = $excluded +1;
            continue;
        }
		$insert_type['type'] = 'debug';
    	if( !$test_mode ){ 
            $product_ibnsert[] = $insert_type = input_product_exported( $product_data );
        }

        //debug LOG
    	$outut_html .= '<tr><td>'.$current_item.'</td>';
    	$outut_html .= '<td><img style="height: 80px; width: auto;" src="'.$product_data['thumbnail_image'].'"/></td>';
		$outut_html .= '<td>'.$product_data['name'].'</td>';
		$outut_html .= '<td>'.$product_data['sku'].'</td>';
    	$outut_html .= '<td>'.$product_data['price'].'</td>';
        $outut_html .= '<td>'.$product_data['categories'].'</td>';
        $outut_html .= '<td><code>'.$insert_type['type'].'</code></td>';
        $outut_html .= '</tr>';
    }
    
	wp_send_json( [
        'type'	=>  'log',
        'items' => $items,
        'excluded' => $excluded,
        'current_item' => $current_item,
        'html'  => $outut_html,
        'actions'  =>  $product_ibnsert,
	    'percent'  =>  round( ( (($current_item-1)/$items)*100), 2), //we doing -1 because 1st is csv index
	    //'max'	   =>  2,
    ]);


    die();
}



function isu_ma2_product_woo_data( $csv_data, $dir ){
	$url_conten =   ISU_IMP_DIR_URL.$dir.'/catalog/product';
	$images_keys = [21,23,25,27,74]; // there are small, medium etc images

	$keys = [
        'sku'			  => 0,
        'store_view_code' => 1,
		'product_type'	  => 3,
		'categories'	  => 4,
		'name'			  => 6,
		'url_key'		  => 17,
		'description'	  => 7,
		'short_description'	=> 8,
		'product_online'	=> 10,
		'price'				=> 13,
		'base_image'		=> 21,
		'small_image'		=> 23,
		'thumbnail_image' 	=> 25,
		'additional_images'	=> 74,
		'qty'				=> 47,
		'out_of_stock_qty'  => 48,
		'is_in_stock'		=> 57,
    ];

	$wanted_data = [];
	foreach ($keys as $key => $key_id) {
		if( in_array($key_id, $images_keys) ){
			$wanted_data[$key] = isset($csv_data[$key_id])? $url_conten.$csv_data[$key_id] : null;
		}else{
            if(isset($csv_data[$key_id])){}
			$wanted_data[$key] = isset($csv_data[$key_id])? $csv_data[$key_id] : null;	
		}
	}
	return $wanted_data;
}


/*
* https://www.skyverge.com/blog/find-product-sku-woocommerce/
*/
function get_product_by_sku_isu( $sku ) {
	global $wpdb;
	$product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );
	return $product_id;
}

function input_product_exported( $wanted_data ){
	$user = wp_get_current_user();
    $user_id = $user->ID;
    $type = 'existed';
    $force_update = false;
    $post_id = (int) get_product_by_sku_isu($wanted_data['sku']);

    $wanted_data['price'] = (string) $wanted_data['price'];
    $wanted_data['price'] = substr( $wanted_data['price'], 0, -5);

    if( !$post_id  && $wanted_data['name'] ){
        $type = 'insert';
        $post_id = wp_insert_post( [
        'post_author'  => $user_id,
        'post_title'   => $wanted_data['name'],
        'post_content' => $wanted_data['description'],
        'post_status'  => 'publish',
        'post_type'    => 'product',
        ]);
    }elseif($force_update && $wanted_data['name']){
        $type = 'force_update';
		wp_update_post([
			'id' => $post_id,
			'post_title'   => $wanted_data['name'],
			'post_content' => $wanted_data['description'],
		]);
    }
    if(!$wanted_data['name'] || !(is_numeric ( (float) $wanted_data['price'] ) ) ){
        $type = '<span style="display: block; padding: 4px; border-radius: 3px; background-color:red;">error</span>' ;
    }
    
    
    if( $type === 'insert' || $type === 'force_update' ){

        //create array of categories - every next is next in hierarhy.
        $cat_arr = explode(",", $wanted_data['categories']);
        if( !empty($cat_arr) && is_array($cat_arr) ){
            foreach ($cat_arr as $key => $cats) {
                $cat_arr[$key] = explode("/", $cats);
            }
        }else{
            $cat_arr[0] = explode("/", $wanted_data['categories']);
        }

        //remove 'root' cat
            foreach ($cat_arr as $key => $cat) {
                foreach ($cat as $key2 => $value) {
                    if( $value === 'root' ){
                        unset($cat_arr[$key][$key2]);
                    }
                }
            }
        
            $taxonomy = 'product_cat';
            $parent_term = 0;//$option['child-cat-id'];

            //make empty array of ids for taxonomies, we need ID of taxonomy to proper assigns it.
            $taxes_ids = [];
            foreach ($cat_arr as $key => $cat) {
                $parent_term = 0;
                $hierarhy_size = count( $cat );
                //make sure all cats exist that product required
                for ( $i=1; $i <= $hierarhy_size ; $i++ ) {
                    $temp_parent = null;
                    $data = null;
                    $temp_parent = term_exists( $cat[$i], $taxonomy, $parent_term );
                    if(is_wp_error( $temp_parent )){
                       //var_dump($temp_parent );
                    }else{
                        if ( !$temp_parent && !is_array($temp_parent) ) {
                            $data = wp_insert_term( $cat[$i], $taxonomy, array ( 'parent' => $parent_term ) );
                            // $data = wp_insert_term( $cat[$i], $taxonomy );
                            if(is_wp_error( $data )){
                               // var_dump($data );
                            }else{
                                $taxes_ids[] = $parent_term = (int) $data['term_id'];
                            }
                        }else{
                            $taxes_ids[]= $parent_term = (int) $temp_parent['term_id'];
                        }
                    }
                }
            }
        //set product taxonomies
        wp_set_object_terms( $post_id, $taxes_ids, 'product_cat' );
        update_post_meta( $post_id, '_visibility', 'visible' );
        if ( $wanted_data['is_in_stock'] === '1' ) {
                update_post_meta( $post_id, '_stock_status', 'instock');
        }else{
                update_post_meta( $post_id, '_stock_status', 'outofstock');
        }
            
            // Adding media
            $images[] = $wanted_data['thumbnail_image'];
            $images[] = $wanted_data['additional_images'];
            $images[] = $wanted_data['small_image'];
            $images[] = $wanted_data['base_image'];

            //remove diplications from array
            $images = array_unique($images);
            foreach ($images as $key => $image_url) {
                if ( !UR_exists_import($image_url) ) {
                    unset( $images[$key] );
                }
            }

            //set featured image
            if ( isset( $images[0] ) ){
                $image_src = media_sideload_image( $images[0], $post_id, '', 'src' );
                $featured_img = get_attachment_id_from_src($image_src);
                set_post_thumbnail($post_id, $featured_img);
                unset( $images[0] );
            }
            //set product gallery if exist
            $gallery_img = '';
            if ( !empty( $images ) ){
                foreach ($images as $key => $image_url) {
                    $img_id = null;
                    $image_src = media_sideload_image( $image_url, $post_id, '', 'src' );
                    $img_id = get_attachment_id_from_src($image_src);
                    $gallery_img .= $img_id.',';
                    $gallery_img = rtrim($gallery_img, ',');
                }
                //update gallery meta
                update_post_meta( $post_id, '_product_image_gallery' ,$gallery_img );
            }



        update_post_meta( $post_id, 'total_sales', '0' );
        update_post_meta( $post_id, '_downloadable', 'no' );
        update_post_meta( $post_id, '_virtual', 'no' );
        update_post_meta( $post_id, '_regular_price', $wanted_data['price'] );
        update_post_meta( $post_id, '_sale_price', '' );
        update_post_meta( $post_id, '_purchase_note', '' );
        update_post_meta( $post_id, '_featured', 'no' );
        update_post_meta( $post_id, '_weight', '' );
        update_post_meta( $post_id, '_length', '' );
        update_post_meta( $post_id, '_width', '' );
        update_post_meta( $post_id, '_height', '' );
        update_post_meta( $post_id, '_sku', $wanted_data['sku'] );
        update_post_meta( $post_id, '_product_attributes', array() );
        update_post_meta( $post_id, '_sale_price_dates_from', '' );
        update_post_meta( $post_id, '_sale_price_dates_to', '' );
        update_post_meta( $post_id, '_price', $wanted_data['price'] );
        update_post_meta( $post_id, '_sold_individually', '' );
        update_post_meta( $post_id, '_manage_stock', 'no' );
        update_post_meta( $post_id, '_backorders', 'no' );
        update_post_meta( $post_id, '_stock', '' );
    }
   return [
    'id' => $post_id,
    'type' => $type,
    ];
}

function UR_exists_import($url){
   $headers=get_headers($url);
   return stripos($headers[0],"200 OK")?true:false;
}


function get_attachment_id_from_src ($image_src) {
  global $wpdb;
  $query = "SELECT ID FROM {$wpdb->posts} WHERE guid='$image_src'";
  $id = $wpdb->get_var($query);
  return $id;
}