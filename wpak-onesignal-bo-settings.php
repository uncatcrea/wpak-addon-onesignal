<?php

if ( !class_exists( 'WpakOneSignalAdmin' ) ) {
    /**
     * OneSignal backoffice forms manager class.
     */
    class WpakOneSignalAdmin {
        /**
         * Main entry point.
         *
         * Adds needed callbacks to some hooks.
         */
        public static function hooks() {
            if( is_admin() ) {
                add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
                add_filter( 'wpak_default_options', array( __CLASS__, 'wpak_default_options' ) );
            }
        }

        /**
         * Attached to 'add_meta_boxes' hook.
         *
         * Register OneSignal configuration meta box for WP-AppKit applications forms.
         */
        public static function add_meta_boxes() {
            add_meta_box(
                'wpak_onesignal_config',
                __( 'OneSignal Configuration', WpAppKitOneSignal::i18n_domain ),
                array( __CLASS__, 'inner_config_box' ),
                'wpak_apps',
                'normal',
                'low'
            );
        }

        /**
         * Displays OneSignal configuration meta box on backoffice form.
         *
         * @param WP_Post               $post           The app object.
         * @param array                 $current_box    The box settings.
         */
        public static function inner_config_box( $post, $current_box ) {
            $options = WpakOptions::get_app_options( $post->ID );
            ?>
            <a href="#" class="hide-if-no-js wpak_help"><?php _e( 'Help me', WpAppKitOneSignal::i18n_domain ); ?></a>
            <div class="wpak_settings field-group">
                <div class="field-group">
                    <label for="wpak_onesignal_pwid"><?php _e( 'OneSignal App ID', WpAppKitOneSignal::i18n_domain ) ?></label>
                    <input id="wpak_onesignal_app_id" type="text" name="wpak_app_options[onesignal][app_id]" value="<?php echo $options['onesignal']['app_id'] ?>" />
                    <span class="description"><?php _e( 'Provided in the OneSignal interface: open your app, go to "App Settings", then "Keys & Ids"', WpAppKitOneSignal::i18n_domain ) ?></span>
                </div>
            </div>
            <?php
        }

        /**
         * Attached to 'wpak_default_options' hook.
         *
         * Filter default options available for an app in WP-AppKit.
         *
         * @param array             $default            The default options.
         *
         * @return array            $default            Options with OneSignal keys.
         */
        public static function wpak_default_options( $default ) {
            $default['onesignal'] = array(
                'app_id' => '',
            );

            return $default;
        }

    }

    WpakOneSignalAdmin::hooks();
}
