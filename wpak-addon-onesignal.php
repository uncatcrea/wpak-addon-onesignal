<?php
/*
  Plugin Name: OneSignal for WP-AppKit
  Description: Subscribe users and send notifications without pain with OneSignal
  Version: 1.0.0
 */

if ( !class_exists( 'WpAppKitOneSignal' ) ) {

    /**
     * OneSignal addon main manager class.
     */
    class WpAppKitOneSignal {

        const slug = 'wpak-addon-onesignal';
        const i18n_domain = 'wpak-addon-onesignal';

        /**
         * Main entry point.
         *
         * Adds needed callbacks to some hooks.
         */
        public static function hooks() {
            add_filter( 'wpak_addons', array( __CLASS__, 'wpak_addons' ) );
            add_filter( 'wpak_default_phonegap_build_plugins', array( __CLASS__, 'wpak_default_phonegap_build_plugins' ), 10, 3 );
            add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
            add_filter( 'wpak_licenses', array( __CLASS__, 'add_license' ) );
            add_filter( 'wpak_app_index_content', array( __CLASS__, 'add_onesignal_script_to_index' ), 10, 3 );
        }

        /**
         * Attached to 'wpak_addons' hook.
         *
         * Filter available addons and register this one for all WP-AppKit applications.
         *
         * @param array             $addons            Available addons.
         *
         * @return array            $addons            Addons with OneSignal (this one).
         */
        public static function wpak_addons( $addons ) {
            $addon = new WpakAddon( 'OneSignal for WP AppKit', self::slug );

            $addon->set_location( __FILE__ );

            $addon->add_js( 'js/wpak-onesignal.js', 'module' );
            
            $addon->add_js( 'js/wpak-onesignal-app.js', 'theme', 'after' ); 
            //After theme so that we can catch notification events in theme.

            $addon->require_php( dirname(__FILE__) .'/wpak-onesignal-bo-settings.php' );

            $addons[] = $addon;

            return $addons;
        }

        /**
         * Attached to 'wpak_default_phonegap_build_plugins' hook.
         *
         * Filter default plugins included into the PhoneGap Build config.xml file.
         *
         * @param array             $default_plugins            The default plugins.
         * @param string            $export_type                Export type : 'phonegap-build' or 'phonegap-cli'
         * @param int               $app_id                     The App ID.
         *
         * @return array            $default_plugins            Plugins with OneSignal one in addition.
         */
        public static function wpak_default_phonegap_build_plugins( $default_plugins, $export_type, $app_id ) {
            
            if( WpakAddons::addon_activated_for_app( self::slug, $app_id ) ) {
                $default_plugins['onesignal-cordova-plugin'] = array( 'source' => 'npm' );
            }

            return $default_plugins;
        }

        /**
         * Add OneSignal script to index.html
         */
        public static function add_onesignal_script_to_index( $index_content, $app_id, $export_type ) {
            
            if ( !WpakAddons::addon_activated_for_app( self::slug, $app_id ) || $export_type !== 'pwa' ) {
                return $index_content;
            }

            $app_id = WpakOneSignalAdmin::get_onesignal_app_id( $app_id );

            $onesignal_script = '<script src="https://cdn.onesignal.com/sdks/OneSignalSDK.js" async=""></script>'."\r\n";
            $onesignal_script .= '<script>'."\r\n";
            $onesignal_script .= '    var OneSignal = window.OneSignal || [];'."\r\n";
            $onesignal_script .= '    OneSignal.push(function() {'."\r\n";
            $onesignal_script .= '        OneSignal.init({'."\r\n";
            $onesignal_script .= '            appId: "'. esc_attr( $app_id ) .'",'."\r\n";
            $onesignal_script .= '        });'."\r\n";
            $onesignal_script .= '    });'."\r\n";
            $onesignal_script .= '</script>'."\r\n";

            $index_content = str_replace( '</head>', "\r\n". $onesignal_script ."\r\n</head>", $index_content );

            return $index_content;
        }

        /**
         * Attached to 'plugins_loaded' hook.
         *
         * Register the addon textdomain for string translations.
         */
        public static function plugins_loaded() {
            load_plugin_textdomain( self::i18n_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
        }

        /**
         * Register license management for this addon.
         *
         * @param array $licenses Licenses array given by WP-AppKit's core.
         * @return array
         */
        public static function add_license( $licenses ) {
            $licenses[] = array(
                'file' => __FILE__,
                'item_name' => 'OneSignal for WP-AppKit',
                'version' => '1.0.0',
                'author' => 'Uncategorized Creations',
            );
            return $licenses;
        }

    }

    WpAppKitOneSignal::hooks();
}