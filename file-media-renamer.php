<?php
/**
 * Plugin Name:       File Media Renamer
 * Plugin URI:        http://wordpress.org/plugins/media-filename-renamer/
 * Description:       This plugin allows you rename uploaded files available in wordpress media and change the postname or slug name.
 * Version:           1.0
 * Requires at least: 4.6
 * Tested up to:      5.3
 * Stable tag:        1.0
 * Requires PHP:      5.2.4
 * Author:            Bradley B. Dalina
 * Author URI:        https://www.bradley-dalina.tk/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       file-media-renamer
 */

/**
 * Restrict Direct Access
 */
defined( 'ABSPATH' ) or die( 'You\'re in the wrong way of access...' );
/**
 * Inlcudes Required Files
 */
if(!function_exists('get_plugin_data')) require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
require_once( ABSPATH . "wp-includes/pluggable.php" );
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

class FRM_12132019{
    /**
    * Plugin main class
    * @since 1.0
    */
    public function __construct(){
        /**
        * Call plugin initialization
        */
        $this->init();
    }
    public static function get($string='', $id=0, $file=false){
        /**
        * Plugin initialization
        * @param  string url part string
        * @param  integer $attachment_id
        * @param boolean file
        * @return object
        */
        $string = trim($string,'/');
        $defination = (object) array();
        /**
        * Define Plugin Domain
        */
        $domain = pathinfo(__FILE__)['filename'];
        $defination->domain = $domain.(!empty(trim($string))) ? '_'.$string : '';
        $defination->prefix = function(){
            foreach(explode('-', 'media-filename-renamer') as $index){
			    $prefix.= substr($index, 0, 1);
                return $prefix.'_';
            }
        };
        /**
        * Define Constant Directory Paths
        */
        $realpath = trailingslashit(realpath(plugin_dir_path(__FILE__)));
        $abspath = trailingslashit( plugin_dir_url(__FILE__) );
        $defination->realpath = $realpath.$string;
        $defination->abspath = $abspath.$string;
        /**
        * Plugin Info
        */
        $info = get_plugin_data( realpath($realpath)."/{$domain}.php" );
        $defination->name = $info['Name'];
        $defination->uri = $info['PluginURI'];
        $defination->version = $info['Version'];
        $defination->description = $info['Description'];

        if($id > 0){
            $path = get_post_meta( $id , '_wp_attached_file', true );
            $basedir =trailingslashit(  pathinfo($path)['dirname']);
            $defination->extension = pathinfo($path)['extension'];
            $defination->filename = pathinfo($path)['filename'];
            $defination->basename = pathinfo($path)['basename'];

            if($file){
                $string = $defination->basename;
            }

            $defination->basedir = $basedir.$string;
            $defination->baseurl = wp_upload_dir()['baseurl'].'/'.$basedir.$string;
            $defination->basepath = realpath(wp_upload_dir()['basedir'].'/'.$basedir).DIRECTORY_SEPARATOR.$string;
        }else{
            $defination->extension = '';
            $defination->filename = '';
            $defination->basename = '';
            $defination->basedir = '';
            $defination->baseurl = '';
            $defination->basepath = '';
        }
        return $defination;
    }
    public function init(){
        /**
        * Plugin initialization
        * @return void
        */
        add_action( 'admin_footer', array($this,"jscripts"), 100, true );
        add_filter( 'attachment_fields_to_edit', array($this,'fields'), null, 2 );
        add_action( 'edit_attachment', array($this,'save_no_ajax') );
        add_action( 'wp_ajax_save-attachment-compat', array($this, 'save_with_ajax'), 0, 1 );
        add_filter( 'attachment_fields_to_save', array($this,'prepare'), 20, 2 );
        add_action( 'admin_head', array($this, 'csstyle') );
        register_activation_hook(  __FILE__, [__CLASS__,'activate'] );
    	register_uninstall_hook(  __FILE__ , [__CLASS__,'uninstall'] );
    }
    public function jscripts(){
        /*
        * Javascript registration
        * @param string $page
        * @return void
        */
        global $pagenow;
    	if ($pagenow === 'upload.php' || $pagenow ==='post.php') {
            ?>
            <script type="text/javascript">
                (function(w, d){
                    w.onload= function(){
                        if (wp.media) {
                                wp.media.view.Modal.prototype.on('open', function(data) {
                                    const frm12132019_filename = d.querySelector('input.frm12132019-filename');
                                    const frm12132019_postname = d.querySelector('input.frm12132019-postname');

                                    if(frm12132019_filename){
                                        input_filter(frm12132019_filename);
                                    }
                                    if(frm12132019_postname){
                                        input_filter(frm12132019_postname);
                                    }
                                    function input_filter($input_field){
                                        if(!$input_field){
                                            return;
                                        }
                                        $input_field.value = $input_field.value.toLowerCase();
                                        $input_field.value = $input_field.value.replace(/[^a-z0-9-\s]/gmi, '');
                                        $input_field.value = $input_field.value.replace(/\s+/g, '-');
                                        $input_field.value = $input_field.value.replace(/--+/g, '-');
                                        $input_field.addEventListener('input', function(event){
                                            this.value = this.value.replace(/[^a-z0-9-\s]/gmi, '');
                                            this.value = this.value.replace(/\s+/g, '-');
                                            this.value = this.value.replace(/--+/g, '-');
                                            this.value = this.value.toLowerCase();
                                        });
                                        $input_field.addEventListener('blur', function(event){
                                            if(this.value.charAt(this.value.length-1) == '-'){
                                                this.value = this.value.substr(0,this.value.length-1);
                                                this.value = this.value.toLowerCase();
                                            }
                                        });
                                    }
                                });

                                //wp.media.frame.on('all', function(e) { console.log(e); });
                            }
                        }
                })(window, document);
            </script>
            <?php
        }
    }
    public function csstyle(){
        global $pagenow;
    	if ($pagenow === 'upload.php' || $pagenow ==='post.php') {
            ?>
            <style type="text/css" rel="stylesheet">
                div#post-body-content table.compat-attachment-fields, div#post-body-content table.compat-attachment-fields tbody {
                    width: 100%;
                    display:block;
                }
                div#post-body-content table.compat-attachment-fields tbody > tr {
                    display: flex;
                    flex-direction: column;
                    width: 100%;
                    justify-content: flex-start;
                    margin-bottom: 15px;
                }
                div#post-body-content table.compat-attachment-fields tbody > tr > td input{
                    width:100%;
                }
                div#post-body-content table.compat-attachment-fields tbody > tr > td > p.help {text-align: left;}
                p.help {text-align: right;}
            </style>
            <?php
        }
    }
    public static function activate(){
        /**
        * Plugin activate hook
        * @return void
        */
    }
    public static function uninstall(){
        /**
        * Plugin uninstall hook
        * @return void
        */
    }
    public static function auth(){
        /**
        * Check user capability
        * @return void
        */
        if ( ! is_user_logged_in() ) {
            remove_filter( 'attachment_fields_to_edit', array($this,'fields'));
            remove_action( 'edit_attachment', array($this,'save_no_ajax') );
            remove_action( 'wp_ajax_save-attachment-compat', array($this,'save') );
            remove_filter( 'attachment_fields_to_save', array($this,'prepare'));
        }
        if ( !current_user_can( 'upload_files' ) ) {
            remove_filter( 'attachment_fields_to_edit', array($this,'fields'));
            remove_action( 'edit_attachment', array($this,'save_no_ajax') );
            remove_action( 'wp_ajax_save-attachment-compat', array($this,'save') );
            remove_filter( 'attachment_fields_to_save', array($this,'prepare'));
        }
        if ( ! is_admin() ) {
            remove_filter( 'attachment_fields_to_edit', array($this,'fields'));
            remove_action( 'edit_attachment', array($this,'save_no_ajax') );
            remove_action( 'wp_ajax_save-attachment-compat', array($this,'save') );
            remove_filter( 'attachment_fields_to_save', array($this,'prepare'));
        }
    }
    public static function fields( $form_fields, $post ) {
       /**
       * Adding a custom field to Attachment Edit Fields
       * @param  array $form_fields
       * @param  WP_POST $post
       * @return array
       */
       $filename = self::get('',$post->ID)->filename;
       $form_fields['filename'] = array(
           'value' => $filename ? $filename : '',
           'label' => __( 'Filename*' ),
           //'helps' => '<a href="'.$post->guid.'" target="_blank">'.$post->guid.'</a>',
           'input' => 'html',
           'html' => '<input required class="frm12132019-filename" type="text" id="attachments-'.$post->ID.'-filename" name="attachments['.$post->ID.'][filename]" value="'.($filename ? $filename : '').'" /> ',
           //'input'  => 'text',
           'field_id'=>'filename'
       );
       $post_name = $post->post_name;
       #$post_parent= ($post->post_parent) ? get_post($post->post_parent)->post_name.'/' : '';
       $form_fields['postname'] = array(
           'value' => $post_name ? $post_name : '',
           'label' => __( 'Slug URL/Post name*' ),
           'input'  => 'html',
           'html' => '<input required class="frm12132019-postname" type="text" id="attachments-'.$post->ID.'-postname" name="attachments['.$post->ID.'][postname]" value="'.($post_name ? $post_name : '').'" /> ',
           'field_id'=>'postname',
           'helps' => '<a class="paypal-donation" style="display: inline-block;" rel="referrer" target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&amp;hosted_button_id=QX8K5XTVBGV42&amp;source=url"><img style="border:solid 1px #ddd;" src="'.frm12132019::get('btn_donateCC_LG.gif')->abspath.'" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button"></a>'
       );
       return $form_fields;
    }
    public function save_no_ajax( $attachment_id ) {
       /**
        * Saving the attachment data to media custom field from edit media page (non ajax).
        * @param  integer $attachment_id
        * @return void
        */
        global $wpdb;
        $sql_bulk_update ='';

       if ( isset( $_REQUEST['attachments'][ $attachment_id ]['filename'] ) && !empty(trim($_REQUEST['attachments'][ $attachment_id ]['filename'])) ) {
           $ext = self::get('',$attachment_id)->extension;
           $filename = $_REQUEST['attachments'][ $attachment_id ]['filename'].'.'.$ext;
           $filename = self::sanitize($filename);
           $name = pathinfo($filename)['filename'];
           $filename = wp_unique_filename( self::get('',$attachment_id)->basepath, $filename );

           if(rename(self::get('',$attachment_id, true)->basepath, self::get($filename , $attachment_id, false )->basepath )){
               if ( wp_attachment_is_image( $attachment_id ) ) {
                   $metadata = get_post_meta($attachment_id, '_wp_attachment_metadata', true);
                   if($metadata){
                       if($metadata['file']){
                           $metadata['file']= self::get($filename, $attachment_id, false )->basedir;
                       }
                       if($metadata['sizes']){
                           foreach($metadata['sizes'] as $index => $key){
                               $new_file_name = str_ireplace(self::get('',$attachment_id)->filename, $name,  $key['file']);
                               $metadata['sizes'][$index]['file']= $new_file_name;
                               @rename( self::get($key['file'], $attachment_id, false)->basepath, self::get( $new_file_name, $attachment_id, false)->basepath );
                           }
                       }
                   }
               }

               $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}posts as t1 SET t1.post_content = replace(t1.post_content, %s, %s);", self::get('',$attachment_id, true)->basedir, self::get($filename, $attachment_id, false)->basedir );
               $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}comments as t1 SET t1.comment_content = replace(t1.comment_content, %s, %s);", self::get('',$attachment_id, true)->basedir, self::get($filename, $attachment_id, false)->basedir );
               $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}links as t1 SET t1.link_url = replace(t1.link_url, %s, %s);", self::get('',$attachment_id, true)->basedir, self::get($filename, $attachment_id, false)->basedir );
               $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}links as t1 SET t1.link_image = replace(t1.link_image, %s, %s);", self::get('',$attachment_id, true)->basedir, self::get($filename, $attachment_id, false)->basedir );
               $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}links as t1 SET t1.link_target = replace(t1.link_target, %s, %s);", self::get('',$attachment_id, true)->basedir, self::get($filename, $attachment_id, false)->basedir );
               $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}options as t1 SET t1.option_value = replace(t1.option_value, %s, %s);", self::get('',$attachment_id, true)->basedir, self::get($filename, $attachment_id, false)->basedir );

               if(isset($metadata)){
                   update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
                   update_post_meta( $attachment_id, '_wp_attached_file', self::get( $filename, $attachment_id, false )->basedir );
               }
               $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}posts as t1 SET t1.guid = %s WHERE t1.ID='{$attachment_id}';", self::get( $filename, $attachment_id, false )->baseurl );
           }
       }
       if ( isset( $_REQUEST['attachments'][ $attachment_id ]['postname'] ) && !empty(trim($_REQUEST['attachments'][ $attachment_id ]['postname'])) ) {
           $ext = self::get('',$attachment_id)->extension;
           $postname = $_REQUEST['attachments'][ $attachment_id ]['postname'].'.'.$ext;
           $postname = self::sanitize($postname, false);
           $postname = wp_unique_post_slug($postname, $attachment_id, 'publish', 'attachment', get_post($attachment_id)->post_parent);
           $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}posts as t1 SET t1.post_name = %s WHERE t1.ID={$attachment_id};", $postname);
       }
       dbDelta( $sql_bulk_update );
       return;
    }
    public function save_with_ajax() {
        /**
         * Saving the attachment data from custom field within media overlay (via ajax)
         * @param  integer $post_id $_POST['id']
         * @return void
         */
         global $wpdb;
         $sql_bulk_update ='';
         $attachment_id = intval($_POST['id']);

         if ( isset( $_REQUEST['attachments'][ $attachment_id ]['filename'] ) && !empty(trim($_REQUEST['attachments'][ $attachment_id ]['filename'])) ) {
             $ext = self::get('',$attachment_id)->extension;
             $filename = $_REQUEST['attachments'][ $attachment_id ]['filename'].'.'.$ext;
             $filename = self::sanitize($filename);
             $name = pathinfo($filename)['filename'];
             $filename = wp_unique_filename( self::get('',$attachment_id)->basepath, $filename );

             if(rename(self::get('',$attachment_id, true)->basepath, self::get($filename , $attachment_id, false )->basepath )){
                 if ( wp_attachment_is_image( $attachment_id ) ) {
                     $metadata = get_post_meta($attachment_id, '_wp_attachment_metadata', true);
                     if($metadata){
                         if($metadata['file']){
                             $metadata['file']= self::get($filename, $attachment_id, false )->basedir;
                         }
                         if($metadata['sizes']){
                             foreach($metadata['sizes'] as $index => $key){
                                 $new_file_name = str_ireplace(self::get('',$attachment_id)->filename, $name,  $key['file']);
                                 $metadata['sizes'][$index]['file']= $new_file_name;
                                 @rename( self::get($key['file'], $attachment_id, false)->basepath, self::get( $new_file_name, $attachment_id, false)->basepath );
                             }
                         }
                     }
                 }

                 $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}posts as t1 SET t1.post_content = replace(t1.post_content, %s, %s);", self::get('',$attachment_id, true)->basedir, self::get($filename, $attachment_id, false)->basedir );
                 $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}comments as t1 SET t1.comment_content = replace(t1.comment_content, %s, %s);", self::get('',$attachment_id, true)->basedir, self::get($filename, $attachment_id, false)->basedir );
                 $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}links as t1 SET t1.link_url = replace(t1.link_url, %s, %s);", self::get('',$attachment_id, true)->basedir, self::get($filename, $attachment_id, false)->basedir );
                 $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}links as t1 SET t1.link_image = replace(t1.link_image, %s, %s);", self::get('',$attachment_id, true)->basedir, self::get($filename, $attachment_id, false)->basedir );
                 $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}links as t1 SET t1.link_target = replace(t1.link_target, %s, %s);", self::get('',$attachment_id, true)->basedir, self::get($filename, $attachment_id, false)->basedir );
                 $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}options as t1 SET t1.option_value = replace(t1.option_value, %s, %s);", self::get('',$attachment_id, true)->basedir, self::get($filename, $attachment_id, false)->basedir );

                 if(isset($metadata)){
                     update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
                     update_post_meta( $attachment_id, '_wp_attached_file', self::get( $filename, $attachment_id, false )->basedir );
                 }
                 $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}posts as t1 SET t1.guid = %s WHERE t1.ID='{$attachment_id}';", self::get( $filename, $attachment_id, false )->baseurl );
             }
         }
         if ( isset( $_REQUEST['attachments'][ $attachment_id ]['postname'] ) && !empty(trim($_REQUEST['attachments'][ $attachment_id ]['postname'])) ) {
             $ext = self::get('',$attachment_id)->extension;
             $postname = $_REQUEST['attachments'][ $attachment_id ]['postname'].'.'.$ext;
             $postname = self::sanitize($postname, false);
             $postname = wp_unique_post_slug($postname, $attachment_id, 'publish', 'attachment', get_post($attachment_id)->post_parent);
             $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}posts as t1 SET t1.post_name = %s WHERE t1.ID={$attachment_id};", $postname);
         }
         dbDelta( $sql_bulk_update );
        clean_post_cache( $attachment_id );
    }
    public function prepare( $post, $attachment ) {
       /**
        * @param array $post
        * @param array $attachment
        * @return array
        */
       // Check the field in $attachment and save if needed
       return $post;
    }
    public static function sanitize($filename, $full=true) {
        /**
         * This function sanitized and standadized the filename of an image
         * @param  string filename or postname
         * @return string
         */
       $filename = strtolower($filename);
       $file = pathinfo($filename);
       $ext = $file['extension'];
       $name = $file['filename'];
       $name = str_replace("%", "", $name);
       $name = preg_replace( '%[^a-z0-9- ]%smiU', ' ', $name );
       $name = preg_replace( '%\s*[-_\s\%]+\s*%', ' ',  $name );
       $name = preg_replace( '%\s{1,}+%', '-', $name );
       /* Trim spaces in the start and end of string */
       $name = trim($name);
       $fullname = $name.'.'.$ext;

       if ( !function_exists( 'sanitize_file_name' ) ) {
           require_once ABSPATH . WPINC . '/formatting.php';
       }

       $fullname = sanitize_file_name($fullname);
       if($full){
         return $fullname;
       }else{
         return pathinfo($fullname)['filename'];
       }
    }
}

class_alias('FRM_12132019','frm12132019');
$frm12132019  = new frm12132019();
