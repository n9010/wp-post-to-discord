<?php
/*
 * Plugin Name: Post to Discord
 * Plugin URI: https://badecho.com
 * Description: Announces new WordPress posts on Discord.
 * Version: 1.1
 * Author: Matt Weber
 * Author URI: https://badecho.com
 */

function post_to_discord($new_status, $old_status, $post) {
    if(get_option('discord_webhook_url') == null)
        return;

    if ( $new_status != 'publish' || $old_status == 'publish' || $post->post_type != 'post')
        return;

    $webhookURL = get_option('discord_webhook_url');
    $id = $post->ID;

    $author = $post->post_author;
    $authorName = get_the_author_meta('display_name', $author);
    $postTitle = $post->post_title;
    $permalink = get_permalink($id);

    // Recupera l'URL dell'immagine in evidenza
    if ( has_post_thumbnail( $id ) ) {
        $thumbnail_url = get_the_post_thumbnail_url( $id, 'full' );
    } else {
        $thumbnail_url = ''; // Puoi impostare un'immagine predefinita se preferisci
    }

    // Costruisci l'embed
    $embed = array(
        "title" => $postTitle,
        "url" => $permalink,
        "author" => array(
            "name" => $authorName,
        ),
        "thumbnail" => array(
            "url" => $thumbnail_url
        ),
        "timestamp" => gmdate( 'Y-m-d\TH:i:s\Z' ),
        "footer" => array(
            "text" => "New Post Announcement",
        ),
    );

    // Prepara il payload con l'embed
    $postData = array(
        'embeds' => array( $embed )
    );

    $curl = curl_init($webhookURL);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

    $response = curl_exec($curl);
    $errors = curl_error($curl);

    if ($response === false) {
        log_message($errors);
    }

    curl_close($curl);
}

function log_message($log) {
    if (true === WP_DEBUG) {
        if (is_array($log) || is_object($log)) {
            error_log(print_r($log, true));
        } else {
            error_log($log);
        }
    }
}

add_action('transition_post_status', 'post_to_discord', 10, 3);

function post_to_discord_section_callback() {
    echo "<p>È necessario un URL valido di Discord Webhook per il canale degli annunci.</p>";
}

function post_to_discord_input_callback() {
    echo '<input name="discord_webhook_url" id="discord_webhook_url" type="text" value="' . esc_attr( get_option('discord_webhook_url') ) . '" required>';
}

function validate_discord_webhook_url($input) {
    if (filter_var($input, FILTER_VALIDATE_URL) === false) {
        add_settings_error(
            'discord_webhook_url',
            'invalid-url',
            'L\'URL del Discord Webhook non è valido.'
        );
        return get_option('discord_webhook_url');
    }

    // Verifica se l'URL è un Discord Webhook valido
    $response = wp_remote_post($input, array(
        'body' => json_encode(array('content' => 'Messaggio di test')),
        'headers' => array('Content-Type' => 'application/json'),
        'timeout' => 10,
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 204) {
        add_settings_error(
            'discord_webhook_url',
            'invalid-webhook',
            'L\'URL del Discord Webhook non è valido o non è raggiungibile.'
        );
        return get_option('discord_webhook_url');
    }

    return esc_url_raw($input);
}

function post_to_discord_settings_init() {
    add_settings_section(
        'discord_webhook_url_section',
        'Post to Discord',
        'post_to_discord_section_callback',
        'general'
    );

    add_settings_field(
        'discord_webhook_url',
        'Discord Webhook URL',
        'post_to_discord_input_callback',
        'general',
        'discord_webhook_url_section'
    );

    register_setting('general', 'discord_webhook_url', array(
        'sanitize_callback' => 'validate_discord_webhook_url'
    ));
}

add_action('admin_init', 'post_to_discord_settings_init');