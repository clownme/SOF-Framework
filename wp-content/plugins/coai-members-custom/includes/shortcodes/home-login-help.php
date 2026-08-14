<?php
// /wp-content/plugins/coai-members-custom/includes/shortcodes/home-login-help.php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [coai_home_login_help]
 * Purpose: Deterministic layout wrapper for Member Login + Voice FAQ.
 */
 
error_log('[COAI] loading home-login-help.php');

add_shortcode('coai_home_login_help', function ($atts = []) {

  $atts = shortcode_atts([
    'faq_mode'        => 'home',
    'faq_title'       => 'Need help getting started?',
    'faq_placeholder' => 'Ask about registration, renewal, or logging in…',
  ], $atts, 'coai_home_login_help');

  ob_start(); ?>
  <div class="coai-home-login-help">
    <div class="coai-home-login">
      <?php echo do_shortcode('[coai_login_box]'); ?>
    </div>

    <div class="coai-home-help">
      <div class="coai-home-help-card">
        <?php
          echo do_shortcode(sprintf(
            '[coai_voice_faq mode="%s" title="%s" placeholder="%s"]',
            esc_attr($atts['faq_mode']),
            esc_attr($atts['faq_title']),
            esc_attr($atts['faq_placeholder'])
          ));
        ?>
      </div>
    </div>
  </div>
  <?php
  return ob_get_clean();
});
