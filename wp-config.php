<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress_db' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         ',c!#Cm8/c8y qBQn -B,JZ77OHi0J0d!8+wV%DwZ: !sFs~dcT,f;Z@i]Q;SF.p?' );
define( 'SECURE_AUTH_KEY',  '{%hrdkiB8joMWoPNVQqx#jk.IdXgJ+rXx9t00^K!%X0Zb4l7,#q]8nkM<gJS>|Ne' );
define( 'LOGGED_IN_KEY',    '[T-SO|>`&7l(4zE$wkf0`>{<5Ydf|]F7-}2o-3Vujf<n0vw,(`&Hz-1Uz|ZSqbDE' );
define( 'NONCE_KEY',        ' `:]Df)`9lf44Z[{<S^?Ua!,q nWFIgy.GASSja,bT2pl>fi5*^f6diU^3)>-m.@' );
define( 'AUTH_SALT',        ' Y=/.%h#I3ILt$ObdW)DCk~@w;H`t;QJjmo7]o]k]E%%>AG2x/%IUf7JOt3Ir?T-' );
define( 'SECURE_AUTH_SALT', 'XyX|1q5`OC80L4o%=i3FN8ii$sNXrI5B&_A]&4 =O3-RgR2Is8eLcHFyJ0u2of(6' );
define( 'LOGGED_IN_SALT',   ' poVRlRoXfb{Kkc|MUZ`#`qsdl%]M[00GxPS)G_Q$DB]k1iwq}Iv!Fku^0H8@1+&' );
define( 'NONCE_SALT',       'O[O`A`l`1<1LAh8fy?j=DxZNJ4:.)E/@#WGk|=NYXd4ilW>GRCY}><HAYYU<&=Rw' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */

define('FS_METHOD', 'direct');


/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
