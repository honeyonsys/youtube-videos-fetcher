<?php
/*
Plugin Name: YouTube Sync Plugin
Description: Sync YouTube videos and thumbnails to WordPress database.
Version: 1.0
Author: Harish Kumar
*/

// Hook to add admin menu
add_action('admin_menu', 'youtube_sync_plugin_menu');
add_action('wp_ajax_youtube_sync_plugin_fetch_videos', 'youtube_sync_plugin_fetch_videos');
add_action('wp_ajax_youtube_sync_plugin_import_videos', 'youtube_sync_plugin_import_videos');

function youtube_sync_plugin_menu() {
    // Add top-level menu page
    add_menu_page(
        'YouTube List', // Page title
        'MVT YouTube Sync', // Menu title
        'manage_options', // Capability
        'youtube-sync-plugin-list', // Menu slug
        'youtube_sync_plugin_list_page', // Function to display the list page
        '', // Icon URL
        6 // Position
    );

    // Add first submenu item (YouTube List)
    add_submenu_page(
        'youtube-sync-plugin-list', // Parent slug
        'YouTube List', // Page title
        'YouTube List', // Menu title
        'manage_options', // Capability
        'youtube-sync-plugin-list', // Menu slug
        'youtube_sync_plugin_list_page' // Function to display the list page
    );

    // Add second submenu item (Settings)
    add_submenu_page(
        'youtube-sync-plugin-list', // Parent slug
        'Settings', // Page title
        'Settings', // Menu title
        'manage_options', // Capability
        'youtube-sync-plugin-settings', // Menu slug
        'youtube_sync_plugin_settings_page' // Function to display the settings page
    );
}

function youtube_sync_plugin_list_page() {
    ?>
    <div class="wrap">
        <h1>YouTube Video List</h1>
        <form method="post" action="">
            <input type="hidden" name="youtube_sync_manual_sync" value="1" />
            <input type="submit" class="button button-primary" value="Sync Videos" />
        </form>
        <div id="video-list"></div>
    </div>
    <?php
}

function youtube_sync_plugin_settings_page() {
    ?>
    <div class="wrap">
        <h1>YouTube Sync Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('youtube_sync_plugin_options');
            do_settings_sections('youtube-sync-plugin');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'youtube_sync_plugin_settings_init');

function youtube_sync_plugin_settings_init() {
    register_setting('youtube_sync_plugin_options', 'youtube_sync_plugin_api_key');
    register_setting('youtube_sync_plugin_options', 'youtube_sync_plugin_playlist_id'); // Register new setting

    add_settings_section(
        'youtube_sync_plugin_section',
        'YouTube API Settings',
        null,
        'youtube-sync-plugin'
    );

    add_settings_field(
        'youtube_sync_plugin_api_key',
        'YouTube API Key',
        'youtube_sync_plugin_api_key_callback',
        'youtube-sync-plugin',
        'youtube_sync_plugin_section'
    );

    add_settings_field(
        'youtube_sync_plugin_playlist_id',
        'YouTube Playlist ID',
        'youtube_sync_plugin_playlist_id_callback', // New callback function
        'youtube-sync-plugin',
        'youtube_sync_plugin_section'
    );
}

function youtube_sync_plugin_api_key_callback() {
    $api_key = get_option('youtube_sync_plugin_api_key');
    ?>
    <input type="text" name="youtube_sync_plugin_api_key" value="<?php echo esc_attr($api_key); ?>" />
    <?php
}

function youtube_sync_plugin_playlist_id_callback() {
    $playlist_id = get_option('youtube_sync_plugin_playlist_id');
    ?>
    <input type="text" name="youtube_sync_plugin_playlist_id" value="<?php echo esc_attr($playlist_id); ?>" />
    <?php
}

function youtube_sync_plugin_fetch_videos() {
    $api_key = get_option('youtube_sync_plugin_api_key');
    $playlist_id = get_option('youtube_sync_plugin_playlist_id');
    $page_token = isset($_POST['page_token']) ? sanitize_text_field($_POST['page_token']) : '';

    if (!$api_key || !$playlist_id) {
        wp_send_json_error('API key or Playlist ID not set');
    }

    $api_url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId={$playlist_id}&maxResults=50&pageToken={$page_token}&key={$api_key}";
    $response = wp_remote_get($api_url);
    
    if (is_wp_error($response)) {
        wp_send_json_error('Failed to fetch data from YouTube API');
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (!isset($data->items)) {
        wp_send_json_error('No videos found');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'youtube_videos';
    $videos = array();

    foreach ($data->items as $item) {
        if (isset($item->snippet->resourceId->videoId)) {
            $video_id = $item->snippet->resourceId->videoId;
            $title = $item->snippet->title;
            $description = $item->snippet->description;
            $thumbnail = $item->snippet->thumbnails->default->url;
            $publishedAt = $item->snippet->publishedAt;

            // Check if the video already exists in the database
            $existing_video = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE video_id = %s", $video_id));

            $videos[] = array(
                'video_id' => $video_id,
                'title' => $title,
                'thumbnail' => $thumbnail,
                'description' =>$description,
                'publishedAt' => $publishedAt,
                'status' => $existing_video > 0 ? 'Imported' : 'Not Imported'
            );
        }
    }

    wp_send_json_success(array(
        'videos' => $videos,
        'nextPageToken' => isset($data->nextPageToken) ? $data->nextPageToken : null,
        'totalResults' => $data->pageInfo->totalResults,
        'resultsPerPage' => $data->pageInfo->resultsPerPage
    ));
}

function youtube_sync_plugin_import_videos() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'youtube_videos';
    $video_ids = isset($_POST['video_ids']) ? $_POST['video_ids'] : array();
    $videos = isset($_POST['videos']) ? $_POST['videos'] : array();

    if (empty($video_ids) || empty($videos)) {
        wp_send_json_error('No videos selected for import');
    }

    foreach ($video_ids as $video_id) {
        if (isset($videos[$video_id])) {
            $video = $videos[$video_id];

            $iso8601_date = $video['publishedAt']; // Assuming this is the ISO 8601 date
            $datetime = new DateTime($iso8601_date);
            $formatted_date = $datetime->format('Y-m-d H:i:s');

            // $wpdb->replace(
            //     $table_name,
            //     array(
            //         'video_id' => $video['video_id'],
            //         'title' => $video['title'],
            //         'thumbnail' => $video['thumbnail'],
            //         'description' => $video['description'],
            //         'publishedAt' =>  $formatted_date
            //     ),
            //     array(
            //         '%s',
            //         '%s',
            //         '%s',
            //         '%s',
            //         '%s'
            //     )
            // );

            $sql = $wpdb->prepare(
                "
                INSERT INTO $table_name (video_id, title, thumbnail, description, publishedAt)
                VALUES (%s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    thumbnail = VALUES(thumbnail),
                    description = VALUES(description),
                    publishedAt = VALUES(publishedAt)
                ",
               $video['video_id'], $video['title'], $video['thumbnail'], $video['description'], $formatted_date
            );
            
            $wpdb->query($sql);






        }
    }

    wp_send_json_success('Videos imported successfully');
}

function youtube_sync_plugin_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'youtube_videos';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        video_id varchar(50) NOT NULL,
        title text NOT NULL,
        thumbnail varchar(255) DEFAULT '' NOT NULL,
        description text,
        publishedAt datetime,
        PRIMARY KEY  (id),
        UNIQUE KEY video_id (video_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'youtube_sync_plugin_create_table');

// Enqueue scripts
add_action('admin_enqueue_scripts', 'youtube_sync_plugin_enqueue_scripts');

function youtube_sync_plugin_enqueue_scripts($hook) {
    if ('toplevel_page_youtube-sync-plugin-list' !== $hook) {
        return;
    }

    wp_enqueue_script('youtube-sync-plugin-script', plugin_dir_url(__FILE__) . 'youtube-sync-plugin.js', array('jquery'), null, true);

    wp_localize_script('youtube-sync-plugin-script', 'youtubeSyncPlugin', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));

    // Add inline CSS
    add_action('admin_head', function() {
        echo '<style>
            #loader-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1000;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .loader {
                border: 16px solid #f3f3f3;
                border-radius: 50%;
                border-top: 16px solid #3498db;
                width: 120px;
                height: 120px;
                animation: spin 2s linear infinite;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .pagination {
                display: list-item;
                justify-content: center;
                margin: 20px 0;
                padding: 10px;
                width: 100%;
                overflow-y: scroll;
            }
            .pagination a {
                padding: 8px 12px;
                margin: 0 4px;
                border: 1px solid #ccc;
                text-decoration: none;
                color: #0073aa;
                background-color: #f1f1f1;
                border-radius: 3px;
            }
            .pagination a:hover {
                background-color: #0073aa;
                color: #fff;
            }
            .pagination a.current {
                background-color: #0073aa;
                color: #fff;
                pointer-events: none;
            }
            .imported {
                color: green;
            }
            .not-imported {
                color: red;
            }
            #import-selected-videos {
                float:right;
                margin-bottom:5px;
            }
        </style>';
    });

}



function enqueue_my_scripts() {

    wp_enqueue_style( 'yt-style', plugin_dir_url(__FILE__)  . 'assets/css/style.css', array(), time(), 'all' );
    wp_enqueue_script('my-infinite-scroll',   plugin_dir_url(__FILE__) . 'assets/js/infinite-scroll.js', array('jquery'), null, true);
    wp_enqueue_script('my-fontawesome',   'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css', null, true);

    wp_localize_script('my-infinite-scroll', 'myAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_my_scripts');

// Shortcode function to display initial data

function display_video_data() {

    ob_start();
 
    ?>
       <div class="listing-sec view-all-outer" data-selected-cat="">
           <div id="append_video_content" class="tiles-area filter_data_section "> </div>
      </div>
      <div class="loader-wrapper" style="display: none;"><div class="loader_inkish"><img src="https://inkish.tv/wp-content/plugins/the-preloader/images/preloader.GIF" alt="Loader"></div></div>
    <?php
 

    return ob_get_clean();
}


// REST API endpoint to fetch additional data
function fetch_videos_data() {
    global $wpdb;

    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    $table_name = $wpdb->prefix . 'youtube_videos';
    $query = $wpdb->prepare("SELECT * FROM $table_name ORDER BY publishedAt DESC LIMIT %d OFFSET %d", $per_page, $offset);
    $results = $wpdb->get_results($query);

    // Return results as JSON
    wp_send_json($results);
}
add_action('wp_ajax_fetch_videos_data', 'fetch_videos_data');
add_action('wp_ajax_nopriv_fetch_videos_data', 'fetch_videos_data');

// Register the shortcode
function register_video_shortcode() {
    add_shortcode('video_data', 'display_video_data');
}
add_action('init', 'register_video_shortcode');



function truncate_description($text, $chars = 100) {
    if (strlen($text) <= $chars) {
        return $text;
    }
    $truncated = substr($text, 0, $chars) . '...';
    return $truncated;
}
