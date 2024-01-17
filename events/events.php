<?php
/*
Plugin Name: Events
Description: Custom Events Plugins for Listing Events with custom texonomies and custom post type, "use [event_listing] shortcode for listing it on the page."
Version: 1.0.0
Author: Tushar Kanojiya
*/

// Add Datepicker Script
function enqueue_datepicker_script() {
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-style', 'https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css');
}
add_action('admin_enqueue_scripts', 'enqueue_datepicker_script');

// Add Bootstrap Style
function enqueue_bootstrap_styles(){ 
    wp_enqueue_style('bootstrap_css', '//stackpath.bootstrapcdn.com/bootstrap/4.4.0/css/bootstrap.min.css');
}
add_action( 'wp_enqueue_scripts', 'enqueue_bootstrap_styles' );

// Add Bootstrap Script
function enqueue_bootstrap_scripts() {  
    wp_enqueue_script( 'bootstrap_jquery', '//code.jquery.com/jquery-3.4.1.slim.min.js');
    wp_enqueue_script( 'bootstrap_javascript', '//stackpath.bootstrapcdn.com/bootstrap/4.4.0/js/bootstrap.min.js');
}
add_action( 'wp_enqueue_scripts', 'enqueue_bootstrap_scripts' );



// Register Custom Post Type (Events)
function create_event_post_type(){
    register_post_type('events',
        array(
            'labels' => array(
                'name' => __('Events'),
                'singular_name' => __('Event'),
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array(
                'title',
                'editor',
                'thumbnail',
            ),
            'register_meta_box_cb' => 'add_event_metabox',
        )
    );
}

add_action('init', 'create_event_post_type');


// Register Meta Box and Add Date Field
function add_event_metabox() {
    add_meta_box(
        'event_metabox',
        'Event Details',
        'event_metabox_callback',
        'events',
        'normal',
        'high'
    );
}

// Create Datepicker Custom Field
function event_metabox_callback($post) {
    // Get the current values of the meta fields
    $event_date = get_post_meta($post->ID, 'event_date', true);

    // Nonce field to validate form request came from current site
    wp_nonce_field(basename(__FILE__), 'event_details_nonce');

    // Output the fields
    ?>
    <p>
        <label for="event_date"><?php _e('Event Date'); ?></label>
        <input type="text" name="event_date" id="event_date" class="datepicker" value="<?php echo esc_attr($event_date); ?>">
    </p>
    
    <script>
        jQuery(document).ready(function ($) {
            // Enable Datepicker
            $('.datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true,
            });
        });
    </script>
    <?php
}

// Register Custom Taxonomy
function create_event_taxonomy(){
    register_taxonomy(
        'event_category',
        'events',
        array(
            'label' => __('Event Categories'),
            'hierarchical' => true,
        )
    );
}

add_action('init', 'create_event_taxonomy');


// Hook Date Metabox to the event post
function save_event_metabox_data($post_id) {
    // Check if our nonce is set.
    if (!isset($_POST['event_details_nonce'])) {
        return;
    }

    // Verify that the nonce is valid.
    if (!wp_verify_nonce($_POST['event_details_nonce'], basename(__FILE__))) {
        return;
    }

    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user's permissions.
    if ('page' === $_POST['post_type']) {
        if (!current_user_can('edit_page', $post_id)) {
            return;
        }
    } else {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }

    // Sanitize user input.
    $event_date = isset($_POST['event_date']) ? sanitize_text_field($_POST['event_date']) : '';

    // Update the meta fields in the database.
    update_post_meta($post_id, 'event_date', $event_date);
}

add_action('save_post', 'save_event_metabox_data');

// Create Short Code with listing events
function event_listing_shortcode($argv){
    $args = array(
        'post_type' => 'events',
        'posts_per_page' => -1
    );

    $events_query = new WP_Query($args);

    ob_start();

    if($events_query->have_posts()) : 
        while ($events_query->have_posts()) : $events_query->the_post();
    ?>
        <div class="event container-fluid">
            <div class="row">
                <div class="col-12">
                    <h3>
                        <a href="<?php echo get_post_permalink(get_the_ID()); ?>" style="color:black;"><?php the_title(); ?></a>
                    </h3>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <img style="width:100%;" src="<?php echo wp_get_attachment_url(get_post_thumbnail_id(get_the_ID(), 'thumbnail')); ?>" />
                </div>
            </div>
            <div class="row">
                <div class="col-6">
                    <p style="text-align:left;">Date: <b><?php echo get_post_meta(get_the_ID(), 'event_date', true); ?></b></p>
                </div>
                <div class="col-6">
                    <p style="text-align:right;">Category : <?php echo get_the_term_list(get_the_ID(), 'event_category', '', ', '); ?></p>
                </div>
            </div>
            <hr/>
            <div class="row">
                <div class="col-12">
                    <?php the_content(); ?>
                </div>
            </div>
            <hr/>
        </div>
    <?php
        endwhile;
    else :
        echo 'No events found.';
    endif;

    wp_reset_postdata();

    return ob_get_clean();
}

add_shortcode('event_listing', 'event_listing_shortcode');