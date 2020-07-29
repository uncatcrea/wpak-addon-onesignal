<?php
/*
  Plugin Name: OneSignal for WP-AppKit
  Description: Subscribe users and send notifications without pain with OneSignal
  Author: Uncategorized Creations
  Author URI:  http://getwpappkit.com
  Version: 1.0.1
  License:     GPL-2.0+
  License URI: http://www.gnu.org/licenses/gpl-2.0.txt
  Copyright:   2013-2018 Uncategorized Creations

  This plugin, like WordPress, is licensed under the GPL.
  Use it to make something cool, have fun, and share what you've learned with others.
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
            add_filter( 'wpak_pwa_manifest', array( __CLASS__, 'add_onesignal_pwa_manifest_fields' ), 10, 2 );
            add_filter( 'wpak_pwa_service_worker', array( __CLASS__, 'add_onesignal_service_worker' ), 10, 2 );
            add_filter( 'wpak_config_xml_custom_preferences', array( __CLASS__, 'add_min_sdk_preference'), 10, 2 );

            //OneSignal's WordPress plugin integration: allow to send Mobile apps notifications with post deeplink:
            add_filter( 'onesignal_send_notification', array( __CLASS__, 'add_launch_route_to_onesignal_payload'), 10, 4 );
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
            $addon = new WpakAddon( 'OneSignal for WP AppKit', self::slug, ['ios','android','pwa'] );

            $addon->set_location( __FILE__ );

            //Native plateforms JS (don't include it for PWA):
            $addon->add_js( 'js/wpak-onesignal.js', 'module', '', ['android','ios'] );
            $addon->add_js( 'js/wpak-onesignal-app.js', 'theme', 'after', ['android','ios'] ); //After theme so that we can catch notification events in theme.

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
            
            if( WpakAddons::addon_activated_for_app( self::slug, $app_id ) && $export_type !== 'pwa' ) {
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

            //Handle wpak_launch_route:
            //(see https://documentation.onesignal.com/docs/web-push-sdk#section--addlistenerfornotificationopened-)
            $onesignal_script .= '    OneSignal.push(["addListenerForNotificationOpened", function(notification_data) {'."\r\n";
            $onesignal_script .= '        console.log("Received OneSignal NotificationOpened:", notification_data);  '."\r\n";
            $onesignal_script .= '        if ( notification_data.hasOwnProperty(\'data\')'."\r\n";
            $onesignal_script .= '            && notification_data.data.hasOwnProperty(\'wpak_launch_route\')'."\r\n";
            $onesignal_script .= '            && notification_data.data.wpak_launch_route.length ) {'."\r\n";
            $onesignal_script .= '            window.location.href = notification_data.data.wpak_launch_route;'."\r\n";
            $onesignal_script .= '        }'."\r\n";
            $onesignal_script .= '    }]);'."\r\n";

            $onesignal_script .= '</script>'."\r\n";

            $index_content = str_replace( '</head>', "\r\n". $onesignal_script ."\r\n</head>", $index_content );

            return $index_content;
        }

        public static function add_onesignal_pwa_manifest_fields( $manifest, $app_id ) {

            if ( !WpakAddons::addon_activated_for_app( self::slug, $app_id ) ) {
                return $manifest;
            }

            $manifest['gcm_sender_id'] = "482941778795";
            $manifest['gcm_sender_id_comment'] = "Do not change the GCM Sender ID";

            return $manifest;
        }

        public static function add_onesignal_service_worker( $service_worker_content, $app_id ) {
            
            if ( !WpakAddons::addon_activated_for_app( self::slug, $app_id ) ) {
                return $service_worker_content;
            }

            $onesignal_service_worker = "importScripts('https://cdn.onesignal.com/sdks/OneSignalSDK.js');\n\n";
            $service_worker_content = $onesignal_service_worker . $service_worker_content;

            return $service_worker_content;
        }

        public static function add_min_sdk_preference( $preferences, $app_id ) {

            if ( WpakAddons::addon_activated_for_app( self::slug, $app_id ) ) {
                //Force minSdkVersion to 15 to avoid the "uses-sdk:minSdkVersion 14 cannot be smaller than 
                //version 15 declared in library" error when building on PhoneGap build
                //(see https://github.com/OneSignal/OneSignal-Cordova-SDK/issues/375#issuecomment-392872631 )
                $preferences[] = [ 'name' => 'android-minSdkVersion', 'value' => '15' ];
            }

            return $preferences;
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

        /**
         * Send a specific push for mobile apps only, to be able to use our own "wpak_launch_route" (passed
         * in OneSignal's "additionnal data") instead of opening the notifications's url in the browser.
         * 
         * Inspired by OneSignal plugin's suggestion here: 
         * https://documentation.onesignal.com/docs/web-push-wordpress-faq
         *
         * Note: this hook callback will only be called if OneSignal's WordPress plugin is installed.
         */
        public static function add_launch_route_to_onesignal_payload( $fields, $new_status, $old_status, $post ) {
            
            $onesignal_wp_settings = OneSignal::get_onesignal_settings();

            if ($onesignal_wp_settings['send_to_mobile_platforms'] == true) {
                //Goal: We don't want to modify the original $fields array, because we want the original 
                //web push notification to go out unmodified. However, we want to send an additional notification 
                //to Android and iOS devices with an additionalData property.
                $fields_mobile = $fields;
                $fields_mobile['isAndroid'] = true;
                $fields_mobile['isIos'] = true;
                $fields_mobile['isAnyWeb'] = false;
                $fields_mobile['isWP'] = false;
                $fields_mobile['isAdm'] = false;
                $fields_mobile['isChrome'] = false;

                //Set our WP-AppKit custom launch route:
                $fields_mobile['data'] = array(
                    "wpak_launch_route" => "single/posts/". $post->ID
                );

                //Important: Unset the URL to prevent opening the browser when the notification is clicked
                unset($fields_mobile['url']);

                self::send_onesignal_notification( $fields_mobile );

                //Remove mobile apps push from the main web push:
                $fields['isAndroid'] = false;
                $fields['isIos'] = false;
            }

            //Return fields for the main web push notification to browsers
            return $fields;
        }

        /**
         * Manually send a OneSignal push
         *
         * Inspired by OneSignal plugin's suggestion here:
         * https://documentation.onesignal.com/docs/web-push-wordpress-faq
         * and OneSignal_Admin::send_notification_on_wp_post() (onesignal-admin.php)
         */
        protected static function send_onesignal_notification( $fields_mobile ) {

            self::onesignal_debug('Initializing cURL (Custom push from addon OneSignal for WP-AppKit).');
            $ch = curl_init();
            $onesignal_post_url = "https://onesignal.com/api/v1/notifications";
            $onesignal_wp_settings = OneSignal::get_onesignal_settings();
            $onesignal_auth_key = $onesignal_wp_settings['app_rest_api_key'];
            curl_setopt($ch, CURLOPT_URL, $onesignal_post_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Authorization: Basic ' . $onesignal_auth_key
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields_mobile));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            if ( defined('ONESIGNAL_DEBUG') ) {
                //Turn off host verification if SSL errors for local testing
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            }

            if (class_exists('WDS_Log_Post')) {
                //Optional: cURL settings to help log cURL output response
                curl_setopt($ch, CURLOPT_FAILONERROR, false);
                curl_setopt($ch, CURLOPT_HTTP200ALIASES, array(400));
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                curl_setopt($ch, CURLOPT_STDERR, $out);
            }

            $response = curl_exec($ch);

            if ( defined('ONESIGNAL_DEBUG') ) {
                //Optional: Log cURL output response
                fclose($out);
                $debug_output = ob_get_clean();
                $curl_effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                $curl_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
                self::onesignal_debug('OneSignal API POST Data:', $fields);
                self::onesignal_debug('OneSignal API URL:', $curl_effective_url);
                self::onesignal_debug('OneSignal API Response Status Code:', $curl_http_code);
                if ($curl_http_code != 200) {
                    self::onesignal_debug('cURL Request Time:', $curl_total_time, 'seconds');
                    self::onesignal_debug('cURL Error Number:', curl_errno($ch));
                    self::onesignal_debug('cURL Error Description:', curl_error($ch));
                    self::onesignal_debug('cURL Response:', print_r($response, true));
                    self::onesignal_debug('cURL Verbose Log:', $debug_output);
                }
            }

            curl_close($ch);
        }

        protected static function onesignal_debug() {
            //Function 'onesignal_debug' has been removed in last version of WordPress "OneSignal Push Notifications" plugin.
            //This is a temporary fix to avoid PHP errors:
            if ( function_exists('onesignal_debug' ) ) {
                $args = func_get_args();
                call_user_func_array('onesignal_debug', $args);
            }
        }

    }

    WpAppKitOneSignal::hooks();
}
