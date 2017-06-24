<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'wordpress');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', '0|?3|]x+:`:{9c69H,IY}V*O3|;#x&2,+GpAsW{x;H63#ey6I!6u/m>pVeO1}GRa');
define('SECURE_AUTH_KEY', 'u9TjFsx*WA&]m:<3KSfC].RT +1Wb3?*!M6(_5So/OXA9]`/M +S$l2Z?jR$GVD9');
define('LOGGED_IN_KEY', 'D*iT*1con6fh/~LX<#~(TAg=J-> 8|)--?gNV<.]1v[67:NvknP.:L>eMl-~Ac<c');
define('NONCE_KEY', 'IKf$L39o e)Zb-22|*d;L,{F38]8H0_-|AeL3T$VBD?|;7w$+R ww?XVOVf8,cyq');
define('AUTH_SALT', 'p >vAN+hej<ALu9Rx20I:h}a3mqxc,kV|GAA3{_oo`/G)8pE~-:pi|RRls1H74#t');
define('SECURE_AUTH_SALT', '*192x0- /p~G|~cAvdYp)VC4`5[1u@9fD`D>!@fL=ox2AikW7)Y,)y@R^&vEsv<~');
define('LOGGED_IN_SALT', 'qkQC?$?!m#D:]eiekruek]yk 6# FW;5#@f)]]ZCk=#stcrjb%)L%Rau{{assTJ^');
define('NONCE_SALT', 'gNRwBA5g_?&VO}zv6xG7E=|!64<Ed|ou-[~--+,6WJx/dh7aW) FF=)7>s}2-*^l');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);


 /** Désactivation des révisions d'articles */
define('WP_POST_REVISIONS', 0);

 /** Désactivation de l'éditeur de thème et d'extension */
define('DISALLOW_FILE_EDIT', true);

 /** Intervalle des sauvegardes automatique */
define('AUTOSAVE_INTERVAL', 7200);

 /** On augmente la mémoire limite */
define('WP_MEMORY_LIMIT', '96M');

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
