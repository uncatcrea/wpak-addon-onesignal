define( function ( require ) {
	"use strict";

	var Phonegap = require( 'core/phonegap/utils' );
	var Config = require( 'root/config' );
	var Utils = require( 'core/app-utils' );
	var App = require( 'core/app' );

	var onesignal = {};

	onesignal.init = function () {
		
		if ( !Phonegap.isLoaded() || "undefined" == typeof Config.options.onesignal || !window.plugins || !window.plugins.OneSignal ) {
			Utils.log( "OneSignal init failed: maybe you're in the browser or OneSignal Cordova plugin is not activated." );
			return;
		}

		// Enable this to debug OneSignal issues:
		// window.plugins.OneSignal.setLogLevel({logLevel: 4, visualLevel: 4});

		//Callback triggered when a notification is opened in the app:
		//Use App.on( 'info:onesignal:notification-opened', callback ) in theme to 
		//do something when a notification is opened.
		var notificationOpenedCallback = function ( notification_data ) {
			App.triggerInfo( 'onesignal:notification-opened', notification_data );
		};

		window.plugins.OneSignal
				.startInit( Config.options.onesignal.app_id )
				.handleNotificationOpened( notificationOpenedCallback )
				.endInit();

		window.plugins.OneSignal.clearOneSignalNotifications();

	};

	return onesignal;
} );