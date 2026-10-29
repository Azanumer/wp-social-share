<?php
/**
 * Plugin Name:       Social Share
 * Plugin URI:        https://github.com/Azanumer/wp-social-share
 * Description:       Lightweight share buttons (X, Facebook, WhatsApp, LinkedIn, Telegram + copy link) — no external scripts, auto-appended or via [social_share] shortcode.
 * Version:           1.0.0
 * Author:            Azan Umer
 * License:           MIT
 * Text Domain:       wss
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WSS_VERSION', '1.0.0' );

/** Default settings. */
function wss_defaults() {
    return array(
        'networks'    => array( 'x', 'facebook', 'whatsapp', 'linkedin', 'telegram' ),
        'auto_insert' => 1,
        'position'    => 'after', // before | after | both
        'show_counts' => 0,      // reserved: share counts need external APIs, kept off
        'post_types'  => array( 'post' ),
    );
}

function wss_settings() {
    $saved = get_option( 'wss_settings', array() );
    if ( ! is_array( $saved ) ) {
        $saved = array();
    }
    return wp_parse_args( $saved, wss_defaults() );
}

/** Share URL for each network, given a post URL and title. */
function wss_share_url( $network, $url, $title ) {
    $u = rawurlencode( $url );
    $t = rawurlencode( $title );
    switch ( $network ) {
        case 'x':
            return "https://twitter.com/intent/tweet?url={$u}&text={$t}";
        case 'facebook':
            return "https://www.facebook.com/sharer/sharer.php?u={$u}";
        case 'whatsapp':
            return "https://wa.me/?text={$t}%20{$u}";
        case 'linkedin':
            return "https://www.linkedin.com/sharing/share-offsite/?url={$u}";
        case 'telegram':
            return "https://t.me/share/url?url={$u}&text={$t}";
        default:
            return '';
    }
}

/** Network labels, filterable so themes can rename or add networks. */
function wss_networks() {
    $networks = array(
        'x'        => 'X',
        'facebook' => 'Facebook',
        'whatsapp' => 'WhatsApp',
        'linkedin' => 'LinkedIn',
        'telegram' => 'Telegram',
    );
    return apply_filters( 'wss_networks', $networks );
}

/**
 * Render the button row for a post. $post_id defaults to the current post.
 * Copy-link is a plain button with a tiny inline script — no third-party JS.
 */
function wss_render( $post_id = 0 ) {
    $post_id  = $post_id ? (int) $post_id : get_the_ID();
    $settings = wss_settings();
    $url      = get_permalink( $post_id );
    $title    = get_the_title( $post_id );
    if ( ! $url ) {
        return '';
    }

    $out = '<div class="wss-share" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:1em 0;">';
    $out .= '<span class="wss-label" style="font-weight:600;">Share:</span>';
    foreach ( $settings['networks'] as $network ) {
        $networks = wss_networks();
        if ( ! isset( $networks[ $network ] ) ) {
            continue;
        }
        $share = wss_share_url( $network, $url, $title );
        if ( ! $share ) {
            continue;
        }
        $out .= sprintf(
            '<a class="wss-btn wss-%1$s" href="%2$s" target="_blank" rel="noopener noreferrer" style="padding:6px 12px;border:1px solid #ccc;border-radius:999px;text-decoration:none;">%3$s</a>',
            esc_attr( $network ),
            esc_url( $share ),
            esc_html( $networks[ $network ] )
        );
    }
    // Copy link: navigator.clipboard with a textarea fallback, button text swaps on success.
    $out .= sprintf(
        '<button type="button" class="wss-copy" data-url="%1$s" style="padding:6px 12px;border:1px solid #ccc;border-radius:999px;cursor:pointer;background:#fff;">Copy link</button>',
        esc_attr( $url )
    );
    $out .= '</div>';
    $out .= '<script>(function(){document.querySelectorAll(".wss-copy").forEach(function(b){b.addEventListener("click",function(){var u=b.getAttribute("data-url");var done=function(){var t=b.textContent;b.textContent="Copied!";setTimeout(function(){b.textContent=t;},2000);};if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(u).then(done);}else{var ta=document.createElement("textarea");ta.value=u;document.body.appendChild(ta);ta.select();try{document.execCommand("copy");done();}catch(e){}ta.remove();}});});})();</script>';
    return $out;
}

add_shortcode( 'social_share', function ( $atts ) {
    $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'social_share' );
    return wss_render( (int) $atts['id'] );
} );

/** Auto-append/prepend on single posts of the enabled post types. */
add_filter( 'the_content', function ( $content ) {
    if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }
    $settings = wss_settings();
    if ( ! $settings['auto_insert'] || ! in_array( get_post_type(), (array) $settings['post_types'], true ) ) {
        return $content;
    }
    $buttons = wss_render();
    if ( 'before' === $settings['position'] ) {
        return $buttons . $content;
    }
    if ( 'both' === $settings['position'] ) {
        return $buttons . $content . $buttons;
    }
    return $content . $buttons;
} );

/** Settings page: Settings → Social Share. */
add_action( 'admin_menu', function () {
    add_options_page( 'Social Share', 'Social Share', 'manage_options', 'wss-settings', 'wss_settings_page' );
} );

function wss_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.' ) );
    }
    if ( isset( $_POST['wss_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wss_nonce'] ) ), 'wss_save' ) ) {
        $networks = isset( $_POST['wss_networks'] ) && is_array( $_POST['wss_networks'] )
            ? array_map( 'sanitize_key', wp_unslash( $_POST['wss_networks'] ) )
            : array();
        $networks = array_values( array_intersect( $networks, array_keys( wss_networks() ) ) );
        update_option( 'wss_settings', array(
            'networks'    => $networks,
            'auto_insert' => isset( $_POST['wss_auto_insert'] ) ? 1 : 0,
            'position'    => in_array( sanitize_key( wp_unslash( $_POST['wss_position'] ?? 'after' ) ), array( 'before', 'after', 'both' ), true )
                ? sanitize_key( wp_unslash( $_POST['wss_position'] ) ) : 'after',
            'post_types'  => array( 'post' ),
        ) );
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }
    $s = wss_settings();
    ?>
    <div class="wrap"><h1>Social Share</h1>
    <form method="post">
        <?php wp_nonce_field( 'wss_save', 'wss_nonce' ); ?>
        <table class="form-table">
            <tr><th>Networks</th><td>
                <?php foreach ( wss_networks() as $key => $label ) : ?>
                    <label style="display:block;"><input type="checkbox" name="wss_networks[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $s['networks'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
                <?php endforeach; ?>
            </td></tr>
            <tr><th>Auto-insert</th><td><label><input type="checkbox" name="wss_auto_insert" value="1" <?php checked( $s['auto_insert'] ); ?>> Append buttons to single posts</label></td></tr>
            <tr><th>Position</th><td><select name="wss_position">
                <?php foreach ( array( 'before', 'after', 'both' ) as $pos ) : ?>
                    <option value="<?php echo esc_attr( $pos ); ?>" <?php selected( $s['position'], $pos ); ?>><?php echo esc_html( ucfirst( $pos ) ); ?></option>
                <?php endforeach; ?>
            </select></td></tr>
        </table>
        <?php submit_button(); ?>
    </form>
    <p>Or place <code>[social_share]</code> anywhere in a post.</p></div>
    <?php
}
