<?php
/**
 * Plugin Name:       File Media Renamer
 * Plugin URI:        http://wordpress.org/plugins/file-media-renamer/
 * Description:       This plugin allows you rename uploaded files available in wordpress media and change the postname or slug name.
 * Version:           1.1
 * Requires at least: 4.6
 * Tested up to:      5.3
 * Stable tag:        1.1
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

class File_Media_Renamer{
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
    public static function get($string=''){
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
            foreach(explode('-', 'file-media-renamer') as $index){
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
        return $defination;
    }
    public function init(){
        /**
        * Plugin initialization
        * @return void
        */
        add_filter( 'manage_media_columns', array($this, 'add_filename_column'), 99);
        add_action( 'manage_media_custom_column', array($this, 'add_filename_column_content'), 10, 2 );
        add_action( 'admin_footer', array($this,"jscripts"), 100, true );
        add_filter( 'attachment_fields_to_edit', array($this,'fields'), null, 2 );
        add_action( 'edit_attachment', array($this,'save_no_ajax') );
        add_action( 'wp_ajax_save-attachment-compat', array($this, 'save_with_ajax'), 0, 1 );

        add_action( 'wp_ajax_fmrequest', array($this,"ajax_renamer") );
        add_action( 'wp_ajax_nopriv_fmrequest', array($this,"ajax_renamer") );

        add_filter( 'attachment_fields_to_save', array($this,'prepare'), 20, 2 );
        add_action( 'admin_head', array($this, 'csstyle') );
        register_activation_hook(  __FILE__, [__CLASS__,'activate'] );
    	register_uninstall_hook(  __FILE__ , [__CLASS__,'uninstall'] );
    }
	public function add_filename_column($columns) {
        /**
    	 * Adds the "Filename" and "Postname" column at the media posts listing page
    	 *
    	 * @param array $columns
    	 * @return array
    	 */
        $columns['filename'] = 'Filename';
        $columns['postname'] = 'Slug URL/Postname';
		return $columns;
	}
	public function add_filename_column_content($column_name, $post_id) {
        /**
    	 * Adds the "Filename" and "Postname" column content at the media posts listing page
    	 *
    	 * @param array $column_name
    	 * @param integer $post_id
    	 * @return string
    	 */
		if ($column_name == 'filename') {
             $filename = pathinfo(get_post_meta( $post_id , '_wp_attached_file', true ))['filename'];
             $ext = pathinfo(get_post_meta( $post_id , '_wp_attached_file', true ))['extension'];
            echo '<input required class="FileMediaRenamer-filename" type="text" id="attachments-'.$post_id.'-filename" name="attachments['.$post_id.'][filename]" value="'.($filename ? $filename : '').'" /><span class="indicator"></span><span style="font-weight:500; padding-left:5px;">.'.$ext.'</span>' ;
		}
        if ($column_name == 'postname') {
            $post_name = get_post($post_id)->post_name;
			echo '<input required class="FileMediaRenamer-postname" type="text" id="attachments-'.$post_id.'-postname" name="attachments['.$post_id.'][postname]" value="'.($post_name ? $post_name : '').'" /><span class="indicator"></span> ';
		}
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
                                    wp.media.view.Modal.prototype.on("open", function(data) {
                                        var FileMediaRenamer_filename = d.querySelectorAll("input.FileMediaRenamer-filename");
                                        var FileMediaRenamer_postname = d.querySelectorAll("input.FileMediaRenamer-postname");

                                        if(FileMediaRenamer_filename){
                                            for(let i =0; i < FileMediaRenamer_filename.length; i++){
                                                input_filter(FileMediaRenamer_filename[i]);
                                            }
                                        }
                                        if(FileMediaRenamer_postname){
                                            for(let i =0; i < FileMediaRenamer_postname.length; i++){
                                                input_filter(FileMediaRenamer_postname[i]);
                                            }
                                        }
                                    });
                                    //wp.media.frame.on("all", function(e) { console.log(e); });
                                }

                                const FileMediaRenamer_bulk_actions = d.querySelector(".tablenav select[name^=action]");
                                if(FileMediaRenamer_bulk_actions){
                                    let renamer_option = d.createElement("option");
                                        renamer_option.value="rename";
                                        renamer_label = d.createTextNode("Update Selected Files");
                                        renamer_option.appendChild(renamer_label);
                                    FileMediaRenamer_bulk_actions.insertBefore(renamer_option, FileMediaRenamer_bulk_actions.lastElementChild);
                                }

                                const FileMediaRenamer_bulk_update = d.querySelector(".tablenav .button.action");
                                if(FileMediaRenamer_bulk_update){
                                    FileMediaRenamer_bulk_update.addEventListener("click", function(){
                                        FileMediaRenamer_submit_bulk_update();
                                    });
                                }

                                var FileMediaRenamer_filename = d.querySelectorAll("input.FileMediaRenamer-filename");
                                var FileMediaRenamer_postname = d.querySelectorAll("input.FileMediaRenamer-postname");

                                if(FileMediaRenamer_filename){
                                    for(let i =0; i < FileMediaRenamer_filename.length; i++){
                                        input_filter(FileMediaRenamer_filename[i]);
                                    }
                                }
                                if(FileMediaRenamer_postname){
                                    for(let i =0; i < FileMediaRenamer_postname.length; i++){
                                        input_filter(FileMediaRenamer_postname[i]);
                                    }
                                }

                                function input_filter($input_field){
                                    if(!$input_field){
                                        return;
                                    }
                                    $input_field.value = $input_field.value.toLowerCase();
                                    $input_field.value = $input_field.value.replace(/[^a-z0-9-\s]/gmi, "");
                                    $input_field.value = $input_field.value.replace(/\s+/g, "-");
                                    $input_field.value = $input_field.value.replace(/--+/g, "-");
                                    $input_field.addEventListener("input", function(event){
                                        this.value = this.value.replace(/[^a-z0-9-\s]/gmi, "");
                                        this.value = this.value.replace(/\s+/g, "-");
                                        this.value = this.value.replace(/--+/g, "-");
                                        this.value = this.value.toLowerCase();
                                    });
                                    $input_field.addEventListener("blur", function(event){
                                        if(this.value.charAt(this.value.length-1) == "-"){
                                            this.value = this.value.substr(0,this.value.length-1);
                                            this.value = this.value.toLowerCase();
                                        }
                                    });
                                }

                                function FileMediaRenamer_submit_bulk_update(){
                                    //disable submit button to prevent multiple press
                                    d.getElementById("doaction").setAttribute("disabled","disabled");
                                    const FileMediaRenamer_form = d.getElementById("posts-filter");
                                    const FileMediaRenamer_fieldnames = d.querySelectorAll("#the-list input:checked");
                                    for(let i=0; i < FileMediaRenamer_fieldnames.length; i++){
                                        console.log(FileMediaRenamer_fieldnames[i].value)
                                        console.log(FileMediaRenamer_fieldnames[i].closest("tr").querySelector(".FileMediaRenamer-filename"));
                                        console.log(FileMediaRenamer_fieldnames[i].closest("tr").querySelector(".FileMediaRenamer-postname"));
                                        var f_indicator = FileMediaRenamer_fieldnames[i].closest("tr").querySelector(".FileMediaRenamer-filename + span.indicator");
                                        var p_indicator = FileMediaRenamer_fieldnames[i].closest("tr").querySelector(".FileMediaRenamer-postname + span.indicator");
                                        f_indicator.classList.add("active");
                                        p_indicator.classList.add("active");
                                        ajax_request(json2_payload({
                                                "id":FileMediaRenamer_fieldnames[i].value,
                                                "filename":FileMediaRenamer_fieldnames[i].closest("tr").querySelector(".FileMediaRenamer-filename").value,
                                                "postname":FileMediaRenamer_fieldnames[i].closest("tr").querySelector(".FileMediaRenamer-postname").value,
                                                "security":"<?php echo wp_create_nonce( "nonce" ); ?>",
                                                "action":"fmrequest"
                                            }), f_indicator, p_indicator);
                                    }

                                    var FileMediaRenamer_check_inputs = d.querySelectorAll(".wp-list-table input[type='checkbox']");
                                    for(let i=0; i < FileMediaRenamer_check_inputs.length; i++){
                                        FileMediaRenamer_check_inputs[i].setAttribute("disabled","disabled");
                                    }
                                }
                                var ajax_pool_request =[];
                                function ajax_abort_request(){
                                    // our abort function
                                    for (let i = 0; i < ajax_pool_request.length; i++){
                                        ajax_pool_request[i].abort();
                                    }
                                    ajax_pool_request.length = 0;
                                    d.getElementById("doaction").removeAttribute("disabled");
                                    var FileMediaRenamer_check_inputs = d.querySelectorAll(".wp-list-table input[type='checkbox']");
                                    for(let i=0; i < FileMediaRenamer_check_inputs.length; i++){
                                        FileMediaRenamer_check_inputs[i].removeAttribute("disabled");
                                    }
                                };
                                function json2_payload(json_data){
                                    let payload ="";
                                    for(key in json_data){
                                        payload+=[key]+"="+json_data[key]+"&";
                                    };
                                    return payload.substr(0,payload.length-1);
                                }
                                function ajax_request(json_data, f_indicator=null, p_indicator=null){
                                    // Create an ajax request for the plugin settings, and image tag attribute values
                                    const xhttp = (window.XMLHttpRequest) ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
                                    xhttp.onreadystatechange = function() {
                                         if (this.readyState == 4 && this.status == 200) {
                                            console.group("Server Request - Success");
                                            console.info("Response: "+JSON.stringify(this.response));
                                            console.info("Status: "+this.status);
                                            console.info("Status Text: "+this.statusText);
                                            console.info("Data Sent: "+JSON.stringify(json_data));
                                            console.table("Request Headers: "+xhttp.getAllResponseHeaders());
                                            console.groupEnd();
                                            f_indicator.classList.add('success');
                                            f_indicator.classList.remove('active');
                                            f_indicator.classList.remove('failed');


                                            p_indicator.classList.add('success');
                                            p_indicator.classList.remove('active');
                                            p_indicator.classList.remove('failed');
                                        }
                                        else if(this.readyState == 4 && this.status != 200)
                                        {
                                            console.group("Server Request - Error");
                                            console.info("Response: "+JSON.stringify(this.response));
                                            console.info("Status: "+this.status);
                                            console.info("Status Text: "+this.statusText);
                                            console.info("Data Sent: "+JSON.stringify(json_data));
                                            console.table("Request Headers: "+xhttp.getAllResponseHeaders());
                                            console.groupEnd();

                                            f_indicator.classList.add('failed');
                                            f_indicator.classList.remove('active');
                                            f_indicator.classList.remove('success');

                                            p_indicator.classList.add('failed');
                                            p_indicator.classList.remove('active');
                                            p_indicator.classList.remove('success');
                                        }
                                        if(this.readyState == 4){
                                            let index = ajax_pool_request.indexOf(xhttp);
                                            if (index > -1){
                                                ajax_pool_request.splice(index, 1);
                                            }
                                            if(ajax_pool_request.length == 0){
                                                d.getElementById("doaction").removeAttribute("disabled");
                                                var FileMediaRenamer_check_inputs = d.querySelectorAll(".wp-list-table input[type='checkbox']");//#the-list
                                                for(let i=0; i < FileMediaRenamer_check_inputs.length; i++){
                                                    FileMediaRenamer_check_inputs[i].removeAttribute("disabled");
                                                }

                                                var FileMediaRenamer_indicator = d.querySelectorAll("#the-list input:checked");
                                                for(let i=0; i < FileMediaRenamer_indicator.length; i++){
                                                    FileMediaRenamer_indicator[i].closest("tr").querySelector(".FileMediaRenamer-filename + span.indicator").classList.remove("active");
                                                    FileMediaRenamer_indicator[i].closest("tr").querySelector(".FileMediaRenamer-filename + span.indicator").classList.remove("success");
                                                    FileMediaRenamer_indicator[i].closest("tr").querySelector(".FileMediaRenamer-filename + span.indicator").classList.remove("failed");

                                                    FileMediaRenamer_indicator[i].closest("tr").querySelector(".FileMediaRenamer-postname + span.indicator").classList.remove("active");
                                                    FileMediaRenamer_indicator[i].closest("tr").querySelector(".FileMediaRenamer-postname + span.indicator").classList.remove("success");
                                                    FileMediaRenamer_indicator[i].closest("tr").querySelector(".FileMediaRenamer-postname + span.indicator").classList.remove("failed");
                                                }
                                            }
                                        }
                                    };

                                    xhttp.open("POST", "<?php echo admin_url( 'admin-ajax.php' ); ?>", true);
                                    ajax_pool_request.push(xhttp);
                                    xhttp.responseType = "json";
                                    xhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
                                    xhttp.send(json_data);
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
                .widefat td, .widefat th{position:relative;}
                span.indicator {
                    display: none;
                    position: absolute;
                    right: 35px;
                    top: 8px;
                    width: 25px;
                    height: 30px;
                    background-position: center center;
                    background-repeat: no-repeat;
                }
                span.indicator.active {
                    display: block;
                    background-image: url(/wordpress/wp-content/plugins/file-media-renamer/loader.gif);
                }
                span.indicator.success {
                    display: block;
                    background-image: url(/wordpress/wp-content/plugins/file-media-renamer/success.png);
                }
                span.indicator.failed {
                    display: block;
                    background-image: url(/wordpress/wp-content/plugins/file-media-renamer/failed.png);
                }
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
       $filename = pathinfo(get_post_meta( $post->ID , '_wp_attached_file', true ))['filename'];
       $form_fields['filename'] = array(
           'value' => $filename ? $filename : '',
           'label' => __( 'Filename*' ),
           //'helps' => '<a href="'.$post->guid.'" target="_blank">'.$post->guid.'</a>',
           'input' => 'html',
           'html' => '<input required class="FileMediaRenamer-filename" type="text" id="attachments-'.$post->ID.'-filename" name="attachments['.$post->ID.'][filename]" value="'.($filename ? $filename : '').'" /> ',
           //'input'  => 'text',
           'field_id'=>'filename'
       );
       $post_name = $post->post_name;
       #$post_parent= ($post->post_parent) ? get_post($post->post_parent)->post_name.'/' : '';
       $form_fields['postname'] = array(
           'value' => $post_name ? $post_name : '',
           'label' => __( 'Slug URL/Post name*' ),
           'input'  => 'html',
           'html' => '<input required class="FileMediaRenamer-postname" type="text" id="attachments-'.$post->ID.'-postname" name="attachments['.$post->ID.'][postname]" value="'.($post_name ? $post_name : '').'" /> ',
           'field_id'=>'postname',
           'helps' => '<a class="paypal-donation" style="display: inline-block;" rel="referrer" target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&amp;hosted_button_id=QX8K5XTVBGV42&amp;source=url"><img style="border:solid 1px #ddd;" src="'.self::get('btn_donateCC_LG.gif')->abspath.'" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button"></a>'
       );
       return $form_fields;
    }
    public function save_no_ajax( $attachment_id ) {
       /**
        * Saving the attachment data to media custom field from edit media page (non ajax).
        * @param  integer $attachment_id
        * @return void
        */
        $ext = pathinfo(get_post_meta( $attachment_id , '_wp_attached_file', true ))['extension'];
        if ( isset( $_REQUEST['attachments'][ $attachment_id ]['filename'] ) && !empty(trim($_REQUEST['attachments'][ $attachment_id ]['filename'])) ) {
            $filename = self::sanitize($_REQUEST['attachments'][ $attachment_id ]['filename'].'.'.$ext);
            self::do_rename($attachment_id, $filename);
        }
        if ( isset( $_REQUEST['attachments'][ $attachment_id ]['postname'] ) && !empty(trim($_REQUEST['attachments'][ $attachment_id ]['postname'])) ) {
            $postname = self::sanitize($_REQUEST['attachments'][ $attachment_id ]['postname'].'.'.$ext, false);
            self::do_update_slug($attachment_id, $postname);
        }
        return;
    }
    public function save_with_ajax() {
        /**
         * Saving the attachment data from custom field within media overlay (via ajax)
         * @param  integer $post_id $_POST['id']
         * @return void
         */
         $attachment_id = intval($_POST['id']);
         $ext = pathinfo(get_post_meta( $attachment_id , '_wp_attached_file', true ))['extension'];
         if ( isset( $_REQUEST['attachments'][ $attachment_id ]['filename'] ) && !empty(trim($_REQUEST['attachments'][ $attachment_id ]['filename'])) ) {
             $filename = self::sanitize($_REQUEST['attachments'][ $attachment_id ]['filename'].'.'.$ext);
             self::do_rename($attachment_id, $filename);
         }
         if ( isset( $_REQUEST['attachments'][ $attachment_id ]['postname'] ) && !empty(trim($_REQUEST['attachments'][ $attachment_id ]['postname'])) ) {
             $postname = self::sanitize($_REQUEST['attachments'][ $attachment_id ]['postname'].'.'.$ext, false);
             self::do_update_slug($attachment_id, $postname);
         }
         return;
    }
    public function ajax_renamer(){
        check_ajax_referer( 'nonce', 'security' );
            if ( ! empty( $_POST ) && isset($_POST['id']) ) {
                /**
                 * Saving the attachment data from custom field within media overlay (via ajax)
                 * @param  integer $post_id $_POST['id']
                 * @return void
                 */
                 $attachment_id = intval($_POST['id']);
                 $ext = pathinfo(get_post_meta( $attachment_id , '_wp_attached_file', true ))['extension'];
                 if ( isset( $_POST['filename'] ) && !empty(trim($_POST['filename'])) ) {
                     $filename = self::sanitize($_POST['filename'].'.'.$ext);
                     self::do_rename($attachment_id, $filename);
                 }
                 if ( isset( $_POST['postname'] ) && !empty(trim($_POST['postname'])) ) {
                     $postname = self::sanitize($_POST['postname'].'.'.$ext, false);
                     self::do_update_slug($attachment_id, $postname);
                 }
                 return;
            }
    }
    public static function do_rename($attachment_id=0, $filename='') {
         //Check user capability
         self::auth();
         if(!$attachment_id) return;

         global $wpdb;
         $sql_bulk_update ='';

         $path = get_post_meta( $attachment_id , '_wp_attached_file', true );
         $path_info = pathinfo($path);

         $ext = $path_info['extension'];
         $basedir =  dirname($path);
         $basepath = trailingslashit(realpath(wp_upload_dir()['basedir'])).$basedir;
         $baseurl = trailingslashit(wp_upload_dir()['baseurl']).$basedir;

         $old_file_name = $path_info['filename'];
         $old_file_basename = $path_info['basename'];
         $old_file_basepath = trailingslashit($basepath).$old_file_basename;
         $old_file_basedir =trailingslashit($basedir).$old_file_basename;

         if($old_file_basename == $filename){
             return;
         }

         $basename = wp_unique_filename( $basepath, $filename );

         $new_file_name = pathinfo($basename)['filename'];
         $new_file_basename =  pathinfo($basename)['basename'];
         $new_file_basepath = trailingslashit($basepath).$new_file_basename;
         $new_file_basedir = trailingslashit($basedir).$new_file_basename;
         $new_file_baseurl= trailingslashit($baseurl).$new_file_basename;

        if(rename($old_file_basepath, $new_file_basepath) ){
            if ( wp_attachment_is_image( $attachment_id ) ) {
                $metadata = get_post_meta($attachment_id, '_wp_attachment_metadata', true);
                if($metadata){
                    if($metadata['file']){
                        $metadata['file']= $new_file_basedir;
                    }
                    if($metadata['sizes']){
                        foreach($metadata['sizes'] as $index => $key){
                            $new_file_name_sizes = str_ireplace($old_file_name, $new_file_name,  $key['file']);
                            $metadata['sizes'][$index]['file']= $new_file_name_sizes;
                            $new_file_name_sizes_basepath = trailingslashit($basepath).$new_file_name_sizes;
                            $old_file_name_sizes_basepath = trailingslashit($basepath).$key['file'];
                            @rename( $old_file_name_sizes_basepath, $new_file_name_sizes_basepath );
                        }
                    }
                }
            }

            $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}posts as t1 SET t1.post_content = replace(t1.post_content, %s, %s);", $old_file_basedir, $new_file_basedir );
            $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}comments as t1 SET t1.comment_content = replace(t1.comment_content, %s, %s);", $old_file_basedir, $new_file_basedir );
            $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}links as t1 SET t1.link_url = replace(t1.link_url, %s, %s);", $old_file_basedir, $new_file_basedir );
            $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}links as t1 SET t1.link_image = replace(t1.link_image, %s, %s);", $old_file_basedir, $new_file_basedir );
            $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}links as t1 SET t1.link_target = replace(t1.link_target, %s, %s);", $old_file_basedir, $new_file_basedir );
            $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}options as t1 SET t1.option_value = replace(t1.option_value, %s, %s);", $old_file_basedir, $new_file_basedir );

            if(isset($metadata)){
                update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
            }
            update_post_meta( $attachment_id, '_wp_attached_file', $new_file_basedir );
            $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}posts as t1 SET t1.guid = %s WHERE t1.ID='{$attachment_id}';", $new_file_baseurl );
        }
         dbDelta( $sql_bulk_update );
         clean_post_cache( $attachment_id );
    }
    public static function do_update_slug($attachment_id=0, $postname='') {
        //Check user capability
        self::auth();
        if(!$attachment_id) return;

        global $wpdb;
        $sql_bulk_update ='';

        $path = get_post_meta( $attachment_id , '_wp_attached_file', true );
        $path_info = pathinfo($path);
        $ext = $path_info['extension'];

        if(get_post($attachment_id)->post_name == $postname){
            return;
        }

        $postname = wp_unique_post_slug($postname, $attachment_id, get_post($attachment_id)->post_status, get_post($attachment_id)->post_type, get_post($attachment_id)->post_parent);
        $sql_bulk_update .= $wpdb->prepare("UPDATE {$wpdb->prefix}posts as t1 SET t1.post_name = %s WHERE t1.ID={$attachment_id};", $postname);

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
       $name = preg_replace( '%[^\w]%smiU', ' ', $name ); //remover any nonword characters
       $name = preg_replace( '%\s*[-_\s\%]+\s*%', ' ',  $name ); //replace multiple spaces with single spaces
       $name = trim($name); /* Trim spaces in the start and end of string */
       $name = preg_replace( '%\s{1,}+%', '-', $name ); //replace sigle spaces with hypen

       $fullname = $name.'.'.$ext;

       if($full){
         return $fullname;
       }else{
         return pathinfo($fullname)['filename'];
       }
    }
}
class_alias('File_Media_Renamer','FileMediaRenamer');
$FileMediaRenamer  = new FileMediaRenamer();
