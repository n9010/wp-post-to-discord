<?php
/*
 * Plugin Name: Post to Discord with Configurable Default Image
 * Plugin URI: https://github.com/n9010/wp-post-to-discord
 * Description: Announces new WordPress posts on Discord with configurable default image and image sharing control.
 * Version: 0.2
 * Author: Ravenfort
 * Author URI: https://github.com/n9010
*/

function post_to_discord($new_status, $old_status, $post) {
    if (get_option('discord_webhook_url') == null)
        return;

    if ($new_status != 'publish' || $old_status == 'publish' || $post->post_type != 'post')
        return;

    $webhookURL = get_option('discord_webhook_url');
    $id = $post->ID;

    $author = $post->post_author;
    $authorName = get_the_author_meta('display_name', $author);
    $postTitle = $post->post_title;
    $permalink = get_permalink($id);

    // Determine whether to use featured images or default image
    $use_images = get_option('discord_use_images', true);
    $thumbnail_url = $use_images && has_post_thumbnail($id)
        ? get_the_post_thumbnail_url($id, 'thumbnail') // Use smaller image size
        : get_option('discord_default_image_url', 'https://via.placeholder.com/150x150.png');

    // Verify if the image URL is accessible
    $response = wp_remote_head($thumbnail_url);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
        error_log("Image URL inaccessible or invalid: $thumbnail_url");
        $thumbnail_url = get_option('discord_default_image_url', 'https://via.placeholder.com/150x150.png');
    }

    error_log("Final image URL: $thumbnail_url");

    $embed = array(
        "title" => $postTitle,
        "url" => $permalink,
        "thumbnail" => array(
            "url" => $thumbnail_url
        ),
//        "image" => array(
//           "url" => $thumbnail_url
//        ),
        "timestamp" => gmdate('Y-m-d\TH:i:s\Z'),
        "footer" => array(
            "text" => "New Post",
        ),
    );

    $postData = array(
        'embeds' => array($embed)
    );

    $curl = curl_init($webhookURL);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode != 204) {
        error_log('Discord Webhook Response: ' . $response);
        error_log('HTTP Code: ' . $httpCode);
    }
}

add_action('transition_post_status', 'post_to_discord', 10, 3);

function post_to_discord_section_callback() {
    echo "<p>Configure the Discord Webhook URL, the default image, and image usage policies.</p>";
}

function post_to_discord_webhook_input_callback() {
    echo '<input name="discord_webhook_url" id="discord_webhook_url" type="text" value="' . get_option('discord_webhook_url') . '" required>';
}

function post_to_discord_default_image_input_callback() {
    echo '<input name="discord_default_image_url" id="discord_default_image_url" type="text" value="' . get_option('discord_default_image_url', 'https://via.placeholder.com/150x150.png') . '">';
}

function post_to_discord_use_images_callback() {
    $checked = get_option('discord_use_images', true) ? 'checked' : '';
    echo '<input name="discord_use_images" id="discord_use_images" type="checkbox" value="1" ' . $checked . '> Allow featured images in posts.';
}

function validate_discord_webhook_url($input) {
    if (filter_var($input, FILTER_VALIDATE_URL) === false) {
        add_settings_error(
            'discord_webhook_url',
            'invalid-url',
            'The Discord Webhook URL is not a valid URL.'
        );
        return get_option('discord_webhook_url');
    }

    // Check if the URL is a valid Discord Webhook URL
    $response = wp_remote_post($input, array(
        'body' => json_encode(array('content' => 'Test message')),
        'headers' => array('Content-Type' => 'application/json'),
        'timeout' => 10,
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 204) {
        add_settings_error(
            'discord_webhook_url',
            'invalid-webhook',
            'The Discord Webhook URL is not valid or reachable.'
        );
        return get_option('discord_webhook_url');
    }

    return $input;
}

// Create a specific settings section for the plugin
function post_to_discord_settings_init() {
    add_options_page(
        'Post-to-Discord Settings',
        'Post-to-Discord',
        'manage_options',
        'post_to_discord',
        'post_to_discord_settings_page'
    );

    register_setting('post_to_discord_settings', 'discord_webhook_url', array(
        'sanitize_callback' => 'validate_discord_webhook_url'
    ));
    register_setting('post_to_discord_settings', 'discord_default_image_url');
    register_setting('post_to_discord_settings', 'discord_use_images');
}
add_action('admin_menu', 'post_to_discord_settings_init');

function post_to_discord_settings_page() {
    echo '<div class="wrap">';
    echo '<h1>Post-to-Discord Settings</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('post_to_discord_settings');
    do_settings_sections('post_to_discord_settings');

    echo '<table class="form-table">';

    echo '<tr valign="top">';
    echo '<th scope="row">Discord Webhook URL</th>';
    echo '<td><input type="text" name="discord_webhook_url" value="' . get_option('discord_webhook_url') . '" size="50"></td>';
    echo '</tr>';

    echo '<tr valign="top">';
    echo '<th scope="row">Default Image URL</th>';
    echo '<td><input type="text" name="discord_default_image_url" value="' . get_option('discord_default_image_url', 'https://via.placeholder.com/150x150.png') . '" size="50"></td>';
    echo '</tr>';

    echo '<tr valign="top">';
    echo '<th scope="row">Use Featured Images</th>';
    echo '<td><input type="checkbox" name="discord_use_images" value="1" ' . (get_option('discord_use_images', true) ? 'checked' : '') . '></td>';
    echo '</tr>';

    echo '</table>';

    submit_button();

    echo '</form>';
    echo '</div>';
}
