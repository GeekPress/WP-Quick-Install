<?php
set_time_limit(0);

// On check si on a un fichier de pré-configuration
$data = array();
if( file_exists( 'data.ini' ) ) {
	$data = json_encode( parse_ini_file( 'data.ini' ) );
}

// On ajoute  ../ au directory
$directory = !empty( $_POST['directory'] ) ? '../' . $_POST['directory'] . '/' : '../';

// Allez, maintenant on commence les choses sérieuses haha =D
if( isset( $_GET['action'] ) ) {

	switch( $_GET['action'] ) {

		case "check_before_upload" :

			$data = array();

			/*-----------------------------------------------------------------------------------*/
			/*	On check si on arrive à se connecter à la base de données et si WP n'est pas déjà installé
			/*-----------------------------------------------------------------------------------*/

			// Test BDD
			try {
			   $db = new PDO('mysql:host='. $_POST['dbhost'] .';dbname=' . $_POST['dbname'] , $_POST['uname'], $_POST['pwd'] );
			} // try
			catch(Exception $e) {
				$data['db'] = "error etablishing connection";
			} // catch


			// Test WordPress
			if( file_exists( $directory . 'wp-config.php' ) )
				$data['wp'] = "error directory";

			// On send une réponse
			echo json_encode( $data );

			break;

		case "download_wp" :

			/*-----------------------------------------------------------------------------------*/
			/*	On récupère la dernière version de WordPress
			/*-----------------------------------------------------------------------------------*/

			if( !file_exists( 'wordpress.zip' ) )
				file_put_contents( 'wordpress.zip', file_get_contents( 'http://fr.wordpress.org/latest-fr_FR.zip' ) );

			break;

		case "unzip_wp" :

			/*-----------------------------------------------------------------------------------*/
			/*	On crée le dossier du site avec les fichiers et dossiers "wordpress"
			/*-----------------------------------------------------------------------------------*/

			// Si on souhaite mettre WordPress dans un sous-dossier, on le crée
			if( !empty( $directory ) ) {

				// On crée le dossier
				mkdir( $directory );

				// On met à jour les droits d'écriture du fichier
				chmod( $directory , 0755 );
			}


			$zip = new ZipArchive;

			// On check si on peut se servir de l'archive
			if( $zip->open( 'wordpress.zip' ) === true ) {

				// On dézip l'archive de WordPress
				$zip->extractTo( '.' );
				$zip->close();

				// On scan le dossier
				$files = scandir( 'wordpress' );

				// On supprime "." et ".." qui correspondent au dossier courant et parent
				unset( $files[0], $files[1] );

				// On déplace les fichiers et les dossiers
				foreach ( $files as $file )
					rename(  'wordpress/' . $file, $directory . '/' . $file );


				rmdir( 'wordpress' ); // On supprime le dossier wordpress
				unlink( $directory . '/license.txt' ); // On supprime le fichier licence.txt
				unlink( $directory . '/readme.html' ); // On supprime le fichier readme.html
				unlink( $directory . '/wp-content/plugins/hello.php' ); // On supprime le plugin Hello Dolly

			} // if

			break;

			case "wp_config" :

				/*-----------------------------------------------------------------------------------*/
				/*	On crée le fichier wp-config.php
				/*-----------------------------------------------------------------------------------*/

				// Ok trop cool, on a WP et ses plugins, maintenant on commence les choses sérieuses :-)

				// On récupère les lignes du fichier wp-config-sample.php sous forme de tableau
				$config_file = file( $directory . 'wp-config-sample.php' );

				// Gestion des clés de sécurité
				$secret_keys = explode( "\n", file_get_contents( 'https://api.wordpress.org/secret-key/1.1/salt/' ) );

				foreach ( $secret_keys as $k => $v ) {
					$secret_keys[$k] = substr( $v, 28, 64 );
				}

				// On remplace les lignes nécassaire du fichier
				$key = 0;
				foreach ( $config_file as &$line ) {

					if ( '$table_prefix  =' == substr( $line, 0, 16 ) ) {
						$line = '$table_prefix  = \'' . addcslashes( $_POST[ 'prefix' ], "\\'" ) . "';\r\n";
						continue;
					}

					if ( ! preg_match( '/^define\(\'([A-Z_]+)\',([ ]+)/', $line, $match ) )
						continue;

					$constant = $match[1];

					switch ( $constant ) {
						case 'WP_DEBUG'	   :

							// Mode Debug
							if( (int)$_POST['debug'] == 1 ) {
								$line = "define('" . $constant . "', 'true');\r\n";

								// Affichage des erreurs
								if( (int)$_POST['debug_display'] == 1 ) {
									$line .= "\r\n\n " . "/** Affichage des erreurs à l'écran */" . "\r\n";
									$line .= "define('WP_DEBUG_DISPLAY', 'true');\r\n";
								} // if

								// Ecriture des erreurs dans un fichier log
								if( (int)$_POST['debug_log'] == 1 ) {
									$line .= "\r\n\n " . "/** Ecriture des erreurs dans un fichier log */" . "\r\n";
									$line .= "define('WP_DEBUG_LOG', 'true');\r\n";
								}
							} // if

							// On ajoute les constantes supplémentaires
							if( !empty($_POST['uploads']) ) {
								$line .= "\r\n\n " . "/** Dossier de destination des fichiers uploadés */" . "\r\n";
								$line .= "define('UPLOADS', '" . $_POST['uploads'] . "');";
							} // if

							if( (int)$_POST['post_revisions'] >= 0 ) {
								$line .= "\r\n\n " . "/** Désactivation des révisions d'articles */" . "\r\n";
								$line .= "define('WP_POST_REVISIONS', " . (int)$_POST['post_revisions'] . ");";
							} // if

							if( (int)$_POST['disallow_file_edit'] == 1 ) {
								$line .= "\r\n\n " . "/** Désactivation de l'éditeur de thème et d'extension */" . "\r\n";
								$line .= "define('DISALLOW_FILE_EDIT', false);";
							} // if

							if( (int)$_POST['autosave_interval'] >= 60 ) {
								$line .= "\r\n\n " . "/** Intervalle des sauvegardes automatique */" . "\r\n";
								$line .= "define('AUTOSAVE_INTERVAL', " . (int)$_POST['autosave_interval'] . ");";
							} // if

							$line .= "\r\n\n " . "/** On augmente la mémoire limite */" . "\r\n";
							$line .= "define('WP_MEMORY_LIMIT', '96M');" . "\r\n";

							break;
						case 'DB_NAME'     :
							$line = "define('" . $constant . "', '" . addcslashes( $_POST[ 'dbname' ], "\\'" ) . "');\r\n";
							break;
						case 'DB_USER'     :
							$line = "define('" . $constant . "', '" . addcslashes( $_POST['uname'], "\\'" ) . "');\r\n";
							break;
						case 'DB_PASSWORD' :
							$line = "define('" . $constant . "', '" . addcslashes( $_POST['pwd'], "\\'" ) . "');\r\n";
							break;
						case 'DB_HOST'     :
							$line = "define('" . $constant . "', '" . addcslashes( $_POST['dbhost'], "\\'" ) . "');\r\n";
							break;
						case 'AUTH_KEY'         :
						case 'SECURE_AUTH_KEY'  :
						case 'LOGGED_IN_KEY'    :
						case 'NONCE_KEY'        :
						case 'AUTH_SALT'        :
						case 'SECURE_AUTH_SALT' :
						case 'LOGGED_IN_SALT'   :
						case 'NONCE_SALT'       :
							$line = "define('" . $constant . "', '" . $secret_keys[$key++] . "');\r\n";
							break;
					} // switch
				} // foreach
				unset( $line );

				$handle = fopen($directory . 'wp-config.php', 'w');
				foreach( $config_file as $line ) {
					fwrite( $handle, $line );
				} // foreach
				fclose( $handle );

				// On met à jour les droits d'écriture du fichier
				chmod( $directory . 'wp-config.php', 0666 );

				break;

			case "install_wp" :

				/*-----------------------------------------------------------------------------------*/
				/*	On installe la BDD de WordPress
				/*-----------------------------------------------------------------------------------*/

				/** Load WordPress Bootstrap */
				require_once( $directory . 'wp-load.php' );

				/** Load WordPress Administration Upgrade API */
				require_once( $directory . 'wp-admin/includes/upgrade.php' );

				/** Load wpdb */
				require_once( $directory . 'wp-includes/wp-db.php' );


				// On installe WordPress
				wp_install( $_POST[ 'weblog_title' ], $_POST['user_login'], $_POST['admin_email'], (int)$_POST[ 'blog_public' ], '', $_POST['admin_password'] );

				// On met à jour les options siteurl et home avec la bonne adresse URL
				$url = trim( str_replace( basename(dirname(__FILE__)) . '/index.php/wp-admin/install.php?action=install_wp' , str_replace( '../', '', $directory ), 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ), '/' ); // On définit l'adresse à utiliser pour les options siteurl et home_url
				update_option( 'siteurl', $url );
				update_option( 'home', $url );

				/*-----------------------------------------------------------------------------------*/
				/*	On supprime le contenu par défaut
				/*-----------------------------------------------------------------------------------*/

				if( $_POST['default_content'] == '1' ) {

					wp_delete_post( 1, true ); // On supprime l'article "Bonjour tout le monde"
					wp_delete_post( 2, true ); // On supprime la page "Page d'exemple"

					wp_delete_link( 1 ); // On supprime le lien "Documentation"
					wp_delete_link( 2 ); // On supprime le lien "Blog WordPress"
					wp_delete_link( 3 ); // On supprime le lien "Forum d'entraide en Français"
					wp_delete_link( 4 ); // On supprime le lien "Extensions"
					wp_delete_link( 5 ); // On supprime le lien "Thèmes"
					wp_delete_link( 6 ); // On supprime le lien "Remarque"
					wp_delete_link( 7 ); // On supprime le lien "La planète WordPress"
				}

				/*-----------------------------------------------------------------------------------*/
				/*	On met à jour les options des médias
				/*-----------------------------------------------------------------------------------*/

				if( !empty($_POST['thumbnail_size_w']) || !empty($_POST['thumbnail_size_h']) ) {

					update_option( 'thumbnail_size_w', (int)$_POST['thumbnail_size_w'] );
					update_option( 'thumbnail_size_h', (int)$_POST['thumbnail_size_h'] );
					update_option( 'thumbnail_crop', (int)$_POST['thumbnail_crop'] );

				}

				if( !empty($_POST['medium_size_w']) || !empty($_POST['medium_size_h'])) {

					update_option( 'medium_size_w', (int)$_POST['medium_size_w'] );
					update_option( 'medium_size_h', (int)$_POST['medium_size_h'] );

				}

				if( !empty($_POST['large_size_w']) || !empty($_POST['large_size_h']) ) {

					update_option( 'large_size_w', (int)$_POST['large_size_w'] );
					update_option( 'large_size_h', (int)$_POST['large_size_h'] );

				}

				 update_option( 'uploads_use_yearmonth_folders', (int)$_POST['uploads_use_yearmonth_folders'] );

				/*-----------------------------------------------------------------------------------*/
				/*	On ajoute les pages renseignées dans le fichier data.ini
				/*-----------------------------------------------------------------------------------*/

				// On pense bien à vérifier si le fichier data.ini existe
				if( file_exists( 'data.ini' ) ) {

					// On récupère le tableau avec les pages
					$file = parse_ini_file( 'data.ini' );

					// On vérifie qu'on a bien au moins une page
					if( count( $file['posts'] ) >= 1 ) {

						foreach( $file['posts'] as $post ) {

							// On récupère la ligne complète de configuration de la page
							$pre_config_post = explode( "-", $post );
							$post = array();

							foreach( $pre_config_post as $config_post ) {

								// On récupère le titre de l'article
								if( preg_match( '#title::#', $config_post ) == 1 )
									$post['title'] = str_replace( 'title::', '', $config_post );

								// On récupère le status de la page (publish, draft, etc...)
								if( preg_match( '#status::#', $config_post ) == 1 )
									$post['status'] = str_replace( 'status::', '', $config_post );

								// On récupère le post type de l'article (post, page ou custom post types ...)
								if( preg_match( '#type::#', $config_post ) == 1 )
									$post['type'] = str_replace( 'type::', '', $config_post );

								// On récupère le contenu de l'article
								if( preg_match( '#content::#', $config_post ) == 1 )
									$post['content'] = str_replace( 'content::', '', $config_post );

								// On récupère le slug de l'article
								if( preg_match( '#slug::#', $config_post ) == 1 )
									$post['slug'] = str_replace( 'slug::', '', $config_post );

								// On récupère le titre de la page parente
								if( preg_match( '#parent::#', $config_post ) == 1 )
									$post['parent'] = str_replace( 'parent::', '', $config_post );

							} // foreach

							if( isset( $post['title'] ) && !empty( $post['title'] ) ) {
								
								// On crée la page
								$args = array(
									'post_title' 		=> trim( $post['title'] ),
									'post_name'			=> $post['slug'],
									'post_content'		=> trim( $post['content'] ),
									'post_status' 		=> $post['status'],
									'post_type' 		=> $post['type'],
									'post_parent'		=> get_page_by_title( trim( $post['parent'] ) )->ID,
									'post_author'		=> 1,
									'post_date' 		=> date('Y-m-d H:i:s'),
									'post_date_gmt' 	=> gmdate('Y-m-d H:i:s'),
									'comment_status' 	=> 'closed',
									'ping_status'		=> 'closed'
								);
								wp_insert_post( $args );

							}

						} // foreach
					} // if count( $file['pages'] ) >= 1 )
				} // if file_exists( 'data.ini' )

				break;

			case "install_theme" :

				/** Load WordPress Bootstrap */
				require_once( $directory . 'wp-load.php' );

				/** Load WordPress Administration Upgrade API */
				require_once( $directory . 'wp-admin/includes/upgrade.php' );


				/*-----------------------------------------------------------------------------------*/
				/*	On installe le nouveau thème
				/*-----------------------------------------------------------------------------------*/

				// On check d'abord si l'archive theme.zip existe
				if( file_exists( 'theme.zip' ) ) {

					$zip = new ZipArchive;

					// On check si on peut se servir de l'archive
					if( $zip->open( 'theme.zip' ) === true ) {

						// On récupère le nom du dossier du thème
						$stat = $zip->statIndex( 0 );
						$theme_name = str_replace('/', '' , $stat['name']);

						// On dézip l'archive dans le dossier des plugins
						$zip->extractTo( $directory . 'wp-content/themes/' );
						$zip->close();

						// On active le thème
						// Note : le thème est automatiquement activé si l'utilisateur demande les suppressions des thèmes par défaut
						if( $_POST['activate_theme'] == 1 || $_POST['delete_default_themes'] == 1 )
							switch_theme( $theme_name, $theme_name );


						// On supprime les thèmes Tweenty Twelve, TwentyEleven et TweentyTen
						if( $_POST['delete_default_themes'] == 1 ) {

							delete_theme( 'twentyfourteen' ); // On supprime le thème Tweenty Fourteen
							delete_theme( 'twentythirteen' ); // On supprime le thème Tweenty Thirteen
							delete_theme( 'twentytwelve' ); // On supprime le thème Tweenty Twelve
							delete_theme( 'twentyeleven' ); // On supprime le thème Tweenty Eleven
							delete_theme( 'twentyten' ); // On supprile le thème Tweenty Ten

						} // if

						// Suppression du dossier _MACOSX (bug sur Mac lors de la décompression de l'archive)
						delete_theme( '__MACOSX' );

					} // if
				} // if

			break;

			case "install_plugins" :

				/*-----------------------------------------------------------------------------------*/
				/*	On récupère les dossiers des plugins
				/*-----------------------------------------------------------------------------------*/

				if( !empty( $_POST['plugins'] ) ) {

					$plugins = explode( ";", $_POST['plugins'] ); // On récupère la liste des plugins dans un tableau
					$plugins_directory = $directory . 'wp-content/plugins/'; // Dossier de WordPress qui contient les plugins

					 // On boucle tous les plugins que l'on doit installé
					foreach( $plugins as $plugin ) {

						// On récupère le fichier XML du plugin pour récupérer le lien vers le zip du plugin
						$xml = @simplexml_load_file('http://api.wordpress.org/plugins/info/1.0/' . trim( $plugin ) . '.xml');

					    if( $xml != NULL ) {

					    	// On récupère la dernière version de WordPress et on la place à la racine
					    	file_put_contents( $plugin . '.zip', file_get_contents( $xml->download_link ) );

					    	// On dézip le plugin
					    	$zip = new ZipArchive;
							if( $zip->open( $plugin . '.zip' ) === true ) {
								$zip->extractTo( $plugins_directory );
								$zip->close();

								// On  supprime l'archive temporaire
								unlink( $plugin . '.zip' );
							} //if
					    } // if
					} // foreach
				} // if !empty( $_POST['plugins'] )

				if( $_POST['plugins_premium'] == 1 ) {

					// On scan le dossier
					$plugins_premium = scandir( 'plugins' );

					// On supprime "." et ".." qui correspondent au dossier courant et parent
					unset( $plugins_premium[0], $plugins_premium[1] );

					// On déplace les archives des plugins et on les dézip
					foreach ( $plugins_premium as $plugin_premium ) {

						// On commence par check si on doit récupérer des plugin via le dossiers "plugins" présent à la racine de WP Quick Install
						if( preg_match( '#(.*).zip$#', $plugin_premium ) == 1 ) {

							$zip = new ZipArchive;

							// On check si on peut se servir de l'archive
							if( $zip->open( 'plugins/' . $plugin_premium ) === true ) {

								// On dézip l'archive dans le dossier des plugins
								$zip->extractTo( $plugins_directory );
								$zip->close();

							} // if
						} //if
					} // foreach
				} // if $_POST['plugins_premium'] == 1 )

				/*-----------------------------------------------------------------------------------*/
				/*	On active les extensions
				/*-----------------------------------------------------------------------------------*/

				if( $_POST['activate_plugins'] == 1 ) {

					/** Load WordPress Bootstrap */
					require_once( $directory . 'wp-load.php' );

					/** Load WordPress Plugin API */
					require_once( $directory . 'wp-admin/includes/plugin.php');

					// On active les plugins
					activate_plugins( array_keys( get_plugins() ) );
				}

			break;

			case "success" :

				/*-----------------------------------------------------------------------------------*/
				/*	En cas de succès, on ajoute les liens vers l'admin et le site
				/*-----------------------------------------------------------------------------------*/

				/** Load WordPress Bootstrap */
				require_once( $directory . 'wp-load.php' );

				// Lien vers l'administration
				echo '<a href="' . admin_url() . '" class="button" style="margin-right:5px;" target="_blank">Se connecter à l\'administration</a>';
				echo '<a href="' . home_url() . '" class="button" target="_blank">Voir le site</a>';

				break;

	} // switch
} // if isset( $_GET['action'] )
else { ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
	<head>
		<meta charset="utf-8" />
		<title>WP Quick Install</title>

		<!-- Aucune indexation du fichier sur Google ! -->
		<meta name="robots" content="noindex, nofollow">

		<!-- Fichiers CSS -->
		<link rel="stylesheet" href="css/style.min.css" type="text/css" media="screen" charset="utf-8">
		<link rel="stylesheet" href="css/bootstrap.min.css" type="text/css" media="screen" charset="utf-8">
	</head>
	<body>
		<?php
		$parent_dir = realpath( dirname ( dirname( __FILE__ ) ) );
		if( is_writable( $parent_dir ) ) { ?>

			<div id="response"></div>
			<div class="progress progress-striped active" style="display:none;">
				<div class="bar" style="width: 0%;"></div>
			</div>
			<div id="success" style="display:none; margin: 10px 0;">
				<h1 style="margin: 0">Le monde est à vous !</h1>
				<p>L'installation de WordPress s'est déroulée avec succès.</p>
			</div>
			<form method="post" action="">

				<div id="errors" class="alert alert-error" style="display:none;">
					<strong>Attention !</strong>
				</div>

				<h1>Avertissement</h1>
				<p>Ce fichier doit obligatoirement se trouver dans le dossier <em>wp-quick-install</em>. Il ne doit pas être présent à la racine du projet ou de votre FTP.</p>

				<h1>Informations de la base de données</h1>
				<p>Vous devez saisir ci-dessous les détails de connexion à votre base de données. Si vous ne les connaissez pas, contactez votre hébergeur.</p>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="dbname">Nom de la base de données</label></th>
						<td><input name="dbname" id="dbname" type="text" size="25" value="wordpress" class="required" /></td>
						<td>Le nom de la base de données dans laquelle vous souhaitez installer WordPress.</td>
					</tr>
					<tr>
						<th scope="row"><label for="uname">Identifiant</label></th>
						<td><input name="uname" id="uname" type="text" size="25" value="utilisateur" class="required" /></td>
						<td>Votre identifiant MySQL</td>
					</tr>
					<tr>
						<th scope="row"><label for="pwd">Mot de passe</label></th>
						<td><input name="pwd" id="pwd" type="text" size="25" value="mot de passe" /></td>
						<td>&hellip;et son mot de passe MySQL.</td>
					</tr>
					<tr>
						<th scope="row"><label for="dbhost">Adresse de la base de données</label></th>
						<td><input name="dbhost" id="dbhost" type="text" size="25" value="localhost" class="required" /></td>
						<td>Si <code>localhost</code> ne fonctionne pas, votre hébergeur doit pouvoir vous donner la bonne information.</td>
					</tr>
					<tr>
						<th scope="row"><label for="prefix">Préfixe des tables</label></th>
						<td><input name="prefix" id="prefix" type="text" value="wp_" size="25" class="required" /></td>
						<td>Si vous souhaitez faire tourner plusieurs installations de WordPress sur une même base de données, modifiez ce réglage.</td>
					</tr>
					<tr>
						<th scope="row"><label for="default_content">Contenu par défaut</label></th>
						<td>
							<label><input type="checkbox" name="default_content" id="default_content" value="1" checked="checked" /> Supprimer le contenu.</label>
						</td>
						<td>Si vous souhaitez supprimer le contenu ajouté par défaut par WordPress (article, page, commentaire et liens).</td>
					</tr>
				</table>

				<h1>Informations nécessaires</h1>
				<p>Merci de fournir les informations suivantes. Ne vous inquiétez pas, vous pourrez les modifier plus tard.</p>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="directory">Dossier d'installation</label>
							<p>Laissez vide pour que les fichiers de WordPress soient installés à la racine.</p>
						</th>
						<td>
							<input name="directory" type="text" id="directory" size="25" value="" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="weblog_title">Titre du site</label></th>
						<td><input name="weblog_title" type="text" id="weblog_title" size="25" value="" class="required" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="user_login">Identifiant</label></th>
						<td>
							<input name="user_login" type="text" id="user_login" size="25" value="admin" class="required" />
							<p>Les identifiants doivent contenir uniquement des caractères alphanumériques, espaces, tiret bas, tiret, points et le symbole @.</p>
						</td>
					</tr>
							<tr>
						<th scope="row">
							<label for="admin_password">Mot de passe</label>
							<p>Un mot de passe vous sera automatiquement généré si vous laissez ce champ vide.</p>
						</th>
						<td>
							<input name="admin_password" type="text" id="admin_password" size="25" value="" />
							<p>Conseil&nbsp;: votre mot de passe devrait faire au moins 7 caractères de long. Pour le rendre plus sûr, utilisez un mélange de majuscules, de minuscules, de chiffres et de symboles comme ! " ? $ %&nbsp;^&nbsp;&amp;&nbsp;).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="admin_email">Votre adresse de messagerie</label></th>
						<td><input name="admin_email" type="text" id="admin_email" size="25" value="" class="required" />
						<p>Vérifiez bien cette adresse de messagerie avant de continuer.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="blog_public">Vie privée</label></th>
						<td colspan="2"><label><input type="checkbox" id="blog_public" name="blog_public" value="1" checked="checked" /> Demander aux moteurs de recherche d&rsquo;indexer ce site.</label></td>
					</tr>
				</table>

				<h1>Informations du thème</h1>
				<p>Vous devez saisir ci-dessous les informations de votre thème personnel.</p>
				<div class="alert alert-info">
					<p style="margin:0px; padding:0px;">Pour que WP Quick Install puisse installer correctement votre thème, vous devez le placer à la racine du dossier <em>wp-quick-install</em> et avec le nom <em>theme.zip</em>. Si ce fichier existe, le script l'ajoutera automatiquement dans <strong>wp-content/themes/</strong>.</p>
				</div>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="activate_theme">Activation automatique</label>
						</th>
						<td colspan="2">
							<label><input type="checkbox" id="activate_theme" name="activate_theme" value="1" /> Activer le thème après l'installation de WordPress.</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="delete_default_themes">Thèmes par défaut</label>
						</th>
						<td colspan="2"><label><input type="checkbox" id="delete_default_themes" name="delete_default_themes" value="1" /> Supprimer les thèmes <em>Tweenty Twelve</em>, <em>TwentyTen</em> et <em>TwentyEleven</em>.</label></td>
					</tr>
				</table>

				<h1>Informations extensions</h1>
				<p>Vous devez saisir ci-dessous les extensions qui doivent être ajoutées pendant l'installation.</p>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="plugins">Extensions Gratuites</label>
							<p>Le slug d'une extension est disponible dans son adresse url (Ex: http://wordpress.org/extend/plugins/<strong>wordpress-seo</strong>/).</p>
						</th>
						<td>
							<input name="plugins" type="text" id="plugins" size="50" value="wordpress-seo; w3-total-cache" />
							<p>Vérifiez bien que les slugs des extensions soient séparés par un point virgule (;).</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="plugins">Extensions Premium</label>
							<p>Les archives doivent être présentes dans le dossier <em>plugins</em> présent à la racine de <em>wp-quick-install</em>.</p>
						</th>
						<td><label><input type="checkbox" id="plugins_premium" name="plugins_premium" value="1" /> Installer les extensions après l'installation de WordPress.</label></td>
					</tr>
					<tr>
						<th scope="row">
							<label for="plugins">Activation automatique</label>
						</th>
						<td><label><input type="checkbox" name="activate_plugins" id="activate_plugins" value="1" /> Activer les extensions après l'installation de WordPress.</label></td>
					</tr>
				</table>

				<h1>Informations médias</h1>

				<p>Les tailles précisées ci-dessous déterminent les dimensions maximales (en pixels) à utiliser lors de l’insertion d’une image dans le corps d’un article.</p>

				<table class="form-table">
					<tr>
						<th scope="row">Taille des miniatures</th>
						<td>
							<label for="thumbnail_size_w">Largeur</label>
							<input name="thumbnail_size_w" type="number" id="thumbnail_size_w" min="0" step="10" value="0" size="5" />
							<label for="thumbnail_size_h">Hauteur</label>
							<input name="thumbnail_size_h" type="number" id="thumbnail_size_h" min="0" step="10" value="0" size="5" /><br>
							<label for="thumbnail_crop" class="small-text"><input name="thumbnail_crop" type="checkbox" id="thumbnail_crop" value="1" checked="checked" />Recadrer les images pour parvenir aux dimensions exactes</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Taille moyenne</th>
						<td>
							<label for="medium_size_w">Largeur</label>
							<input name="medium_size_w" type="number" id="medium_size_w" min="0" step="10" value="0" size="5" />
							<label for="medium_size_h">Hauteur</label>
							<input name="medium_size_h" type="number" id="medium_size_h" min="0" step="10" value="0" size="5" /><br>
						</td>
					</tr>
					<tr>
						<th scope="row">Grande moyenne</th>
						<td>
							<label for="large_size_w">Largeur</label>
							<input name="large_size_w" type="number" id="large_size_w" min="0" step="10" value="0" size="5" />
							<label for="large_size_h">Hauteur</label>
							<input name="large_size_h" type="number" id="large_size_h" min="0" step="10" value="0" size="5" /><br>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="upload_dir">Stocker les fichiers envoyés dans ce dossier</label>
							<p>Par défaut, les médias sont stockés dans le dossier <em>wp-content/uploads</em></p>
						</th>
						<td>
							<input type="text" id="upload_dir" name="upload_dir" size="46" value="" /><br/>
							<label for="uploads_use_yearmonth_folders" class="small-text"><input name="uploads_use_yearmonth_folders" type="checkbox" id="uploads_use_yearmonth_folders" value="1" checked="checked" />Organiser mes fichiers envoyés dans des dossiers mensuels et annuels</label>
						</td>
					</tr>
				</table>

				<h1>Informations wp-config.php</h1>
				<p>Vous devez choisir ci-dessous les constantes supplémentaires à ajouter dans le fichier <strong>wp-config.php</strong>.</p>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="post_revisions">Révisions</label>
							<p>Par défaut, le nombre de révisions d'article est illimité.</p>
						</th>
						<td>
							<input name="post_revisions" id="post_revisions" type="number" min="0" value="0" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="plugins">Éditeur</label>
						</th>
						<td><label><input type="checkbox" id="disallow_file_edit" name="disallow_file_edit" value="1" checked='checked' /> Désactiver l'éditeur de thème et des extensions.</label></td>
					</tr>
					<tr>
						<th scope="row">
							<label for="autosave_interval">Sauvegarde automatique</label>
							<p>Par défaut, l'intervalle des sauvegardes est de 60 secondes.</p>
						</th>
						<td><input name="autosave_interval" id="autosave_interval" type="number" min="60" step="60" size="25" value="7200" /> secondes</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="debug">Mode Debug</label>
						</th>
						<td>
							<label><input type="checkbox" name="debug" id="debug" value="1" /> Activer le mode deboguage de WordPress.</label>
							<p>En cochant cette case, vous activez l'affichage des notifications d'erreurs de WordPress.</p>

							<div id="debug_options" style="display:none;">
								<label><input type="checkbox" name="debug_display" id="debug_display" value="1" /> Afficher les erreurs à l'écran.</label>
								<br/>
								<label><input type="checkbox" name="debug_log" id="debug_log" value="1" /> Ecrire les erreurs dans un fichier log <em>(wp-content/debug.log)</em>.</label>
							</div>
						</td>
					</tr>
				</table>
				<p class="step"><span id="submit" class="button">Installer WordPress</span></p>
			</form>

			<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
			<script>

				$(document).ready(function() {

					// Gestion du debug mode
					var $debug		   = $('#debug'),
						$debug_options = $('#debug_options'),
						$debug_display = $debug_options.find('#debug_display');
						$debug_log 	   = $debug_options.find('#debug_log');


					$debug.change(function() {
						if ( $debug.is(':checked') ) {
							$debug.parent().hide().siblings('p').hide();
							$debug_options.slideDown();
							$debug_display.attr('checked', true);
							$debug_log.attr('checked', true);
						}
					});

					$('#debug_display, #debug_log').change(function(){
						if( !$debug_display.is(':checked') && !$debug_log.is(':checked') ) {
							$debug_options.slideUp().siblings().slideDown();
							$debug.removeAttr('checked');
						}
					});

					<?php
					// On check si on doit pré-remplir le formulaire
					if( count( $data ) >= 1 ) { ?>

						var data = <?php echo $data; ?>;

						/*-----------------------------------------------------------------------------------*/
						/*	Dossier d'installation
						/*-----------------------------------------------------------------------------------*/

						if( typeof data.directory !='undefined' )
							$('#directory').val(data.directory);

						/*-----------------------------------------------------------------------------------*/
						/*	Titre du blog
						/*-----------------------------------------------------------------------------------*/

						if( typeof data.title !='undefined' )
							$('#weblog_title').val(data.title);

						/*-----------------------------------------------------------------------------------*/
						/*	Identifiants BDD
						/*-----------------------------------------------------------------------------------*/

						if( typeof data.db !='undefined' ) {

							if( typeof data.db.dbname !='undefined' )
							$('#dbname').val(data.db.dbname);

							if( typeof data.db.dbhost !='undefined' )
								$('#dbhost').val(data.db.dbhost);

							if( typeof data.db.prefix !='undefined' )
								$('#prefix').val(data.db.prefix);

							if( typeof data.db.uname !='undefined' )
								$('#uname').val(data.db.uname);

							if( typeof data.db.pwd !='undefined' )
								$('#pwd').val(data.db.pwd);

							if( typeof data.db.default_content !='undefined' )
								( parseInt(data.db.default_content) == 1 ) ? $('#default_content').attr('checked', 'checked') : $('#default_content').removeAttr('checked');

						} //if

						/*-----------------------------------------------------------------------------------*/
						/*	Identifiants admin
						/*-----------------------------------------------------------------------------------*/

						if( typeof data.admin !='undefined' ) {

							if( typeof data.admin.user_login !='undefined' )
								$('#user_login').val(data.admin.user_login);

							if( typeof data.admin.password !='undefined' )
								$('#admin_password').val(data.admin.password);

							if( typeof data.admin.email !='undefined' )
								$('#admin_email').val(data.admin.email);

						} // if


						/*-----------------------------------------------------------------------------------*/
						/*	Activer le SEO
						/*-----------------------------------------------------------------------------------*/

						if( typeof data.seo !='undefined' )
							( parseInt(data.seo) == 1 ) ? $('#blog_public').attr('checked', 'checked') : $('#blog_public').removeAttr('checked');


						/*-----------------------------------------------------------------------------------*/
						/*	Thèmes
						/*-----------------------------------------------------------------------------------*/

						if( typeof data.activate_theme !='undefined' )
							( parseInt(data.activate_theme) == 1 ) ? $('#activate_theme').attr('checked', 'checked') : $('#activate_theme').removeAttr('checked');

						if( typeof data.delete_default_themes !='undefined' )
							( parseInt(data.delete_default_themes) == 1 ) ? $('#delete_default_themes').attr('checked', 'checked') : $('#delete_default_themes').removeAttr('checked');

						/*-----------------------------------------------------------------------------------*/
						/*	Plugins
						/*-----------------------------------------------------------------------------------*/

						if( typeof data.plugins !='undefined' )
							$('#plugins').val( data.plugins.join(';') );

						if( typeof data.plugins_premium !='undefined' )
							( parseInt(data.plugins_premium) == 1 ) ? $('#plugins_premium').attr('checked', 'checked') : $('#plugins_premium').removeAttr('checked');

						if( typeof data.activate_plugins !='undefined' )
							( parseInt(data.activate_plugins) == 1 ) ? $('#activate_plugins').attr('checked', 'checked') : $('#activate_plugins').removeAttr('checked');

						/*-----------------------------------------------------------------------------------*/
						/*	Médias
						/*-----------------------------------------------------------------------------------*/

						if( typeof data.uploads !='undefined' ) {

							if( typeof data.uploads.thumbnail_size_w !='undefined' )
								$('#thumbnail_size_w').val(parseInt(data.uploads.thumbnail_size_w));

							if( typeof data.uploads.thumbnail_size_h !='undefined' )
								$('#thumbnail_size_h').val(parseInt(data.uploads.thumbnail_size_h));

							if( typeof data.uploads.thumbnail_crop !='undefined' )
								( parseInt(data.uploads.thumbnail_crop) == 1 ) ? $('#thumbnail_crop').attr('checked', 'checked') : $('#thumbnail_crop').removeAttr('checked');

							if( typeof data.uploads.medium_size_w !='undefined' )
								$('#medium_size_w').val(parseInt(data.uploads.medium_size_w));

							if( typeof data.uploads.medium_size_h !='undefined' )
								$('#medium_size_h').val(parseInt(data.uploads.medium_size_h));

							if( typeof data.uploads.large_size_w !='undefined' )
								$('#large_size_w').val(parseInt(data.uploads.large_size_w));

							if( typeof data.uploads.large_size_h !='undefined' )
								$('#large_size_h').val(parseInt(data.uploads.large_size_h));

							if( typeof data.uploads.upload_dir !='undefined' )
								$('#upload_dir').val(data.uploads.upload_dir);

							if( typeof data.uploads.uploads_use_yearmonth_folders !='undefined' )
								( parseInt(data.uploads.uploads_use_yearmonth_folders) == 1 ) ? $('#uploads_use_yearmonth_folders').attr('checked', 'checked') : $('#uploads_use_yearmonth_folders').removeAttr('checked');

						}


						/*-----------------------------------------------------------------------------------*/
						/*	Constantes du fichier wp-config.php
						/*-----------------------------------------------------------------------------------*/

						if( typeof data.wp_config !='undefined' ) {

							if( typeof data.wp_config.autosave_interval !='undefined' )
								$('#autosave_interval').val(data.wp_config.autosave_interval);

							if( typeof data.wp_config.post_revisions !='undefined' )
								$('#post_revisions').val(data.uploads.upload_dir);

							if( typeof data.wp_config.disallow_file_edit !='undefined' )
								( parseInt(data.wp_config.disallow_file_edit) == 1 ) ? $('#disallow_file_edit').attr('checked', 'checked') : $('#disallow_file_edit').removeAttr('checked');

							if( typeof data.wp_config.debug !='undefined' ) {
								if ( parseInt(data.wp_config.debug) == 1 ) {
									$debug.attr('checked', 'checked');
									$debug.parent().hide().siblings('p').hide();
									$debug_options.slideDown();
									$debug_display.attr('checked', true);
									$debug_log.attr('checked', true);
								} // if
								else {
									$('#debug').removeAttr('checked');
								} // else

						} // if


						} // if count( $data ) >= 1


					<?php
					} // if count( $data ) >= 0
					?>

					var $response  = $('#response');

					$('#submit').click( function() {

						errors = false;

						// On cache et on vide la div des errors
						$('#errors').hide().html('<strong>Attention !</strong>');

						$('input.required').each(function(){
							if( $.trim($(this).val()) == '' ) {
								errors = true;
								$(this).addClass('error');
								$(this).css("border", "1px solid #FF0000");
							} // if
							else {
								$(this).removeClass('error');
								$(this).css("border", "1px solid #DFDFDF");
							} // else
						});

						// Si on n'a pas d'erreur, on peut continuer
						if( !errors ) {

							/*-----------------------------------------------------------------------------------*/
							/*	On check la connexion à la BDD et si WP existe déjà ou non
							/*  Si on n'a pas d'erreurs, on lance le script
							/*-----------------------------------------------------------------------------------*/

							$.post('<?php echo $_SERVER['PHP_SELF'] ?>?action=check_before_upload', $('form').serialize(), function(data) {

								errors = false;
								data = $.parseJSON(data);

								// On check si la connexion est bonne ou non
								if( data.db == "error etablishing connection" ) {

									errors = true;
									$('#errors').show().append('<p style="margin-bottom:0px;">&bull; Erreur de connexion à la base de données.</p>');
								} // if


								// On check si WP est déjà installé ou non
								if( data.wp == "error directory" ) {
									errors = true;
									$('#errors').show().append('<p style="margin-bottom:0px;">&bull; WordPress semble déjà installé sur le dossier d\'installation que vous avez indiqué.</p>');
								}

								// Si on n'a pas d'erreur, on peut continuer
								if( !errors ) {
									$('form').fadeOut( 'fast', function() {

										$('.progress').show(); // On montre la barre de progression

										// ETAPE 1
										// On récupère l'archive de la dernière version de WordPress
										$response.html("<p>Téléchargement de l'archive de WordPress en cours...</p>");

										$.post('<?php echo $_SERVER['PHP_SELF'] ?>?action=download_wp', $('form').serialize(), function() {
											unzip_wp();
										});
									});
								} // if
								else {
									// On a une erreur, on fait remonter l'utilisateur pour qu'il puisse la lire
									$('html,body').animate( { scrollTop: $( 'html,body' ).offset().top } , 'slow' );
								} // else
							});

						} // if
						else {
							// On a une erreur, on fait remonter l'utilisateur pour qu'il puisse la lire
							$('html,body').animate( { scrollTop: $( 'input.error:first' ).offset().top-20 } , 'slow' );
						} // else
						return false;
					});

					// Décompression de l'archive de WordPress
					function unzip_wp() {
						$response.html("<p>Installation des fichiers en cours...</p>" );
						$('.progress .bar').animate({width: "16.5%"});
						$.post('<?php echo $_SERVER['PHP_SELF'] ?>?action=unzip_wp', $('form').serialize(), function(data) {
							wp_config();
						});
					}

					// Création du fichier wp-config.php
					function wp_config() {
						$response.html("<p>Création du fichier wp-config.php en cours...</p>");
						$('.progress .bar').animate({width: "33%"});
						$.post('<?php echo $_SERVER['PHP_SELF'] ?>?action=wp_config', $('form').serialize(), function(data) {
							install_wp();
						});
					}

					// Création de la BDD et de l'administrateur
					function install_wp() {
						$response.html("<p>Création de la BDD et de l'administrateur en cours...</p>");
						$('.progress .bar').animate({width: "49.5%"});
						$.post('<?php echo $_SERVER['PHP_SELF'] ?>/wp-admin/install.php?action=install_wp', $('form').serialize(), function(data) {
							install_theme();
						});
					}

					// Installation du thème
					function install_theme() {
						$response.html("<p>Installation du thème en cours...</p>");
						$('.progress .bar').animate({width: "66%"});
						$.post('<?php echo $_SERVER['PHP_SELF'] ?>/wp-admin/install.php?action=install_theme', $('form').serialize(), function(data) {
							install_plugins();
						});
					}

					// Installation des plugins
					function install_plugins() {
						$response.html("<p>Installation des extensions en cours...</p>");
						$('.progress .bar').animate({width: "82.5%"});
						$.post('<?php echo $_SERVER['PHP_SELF'] ?>?action=install_plugins', $('form').serialize(), function(data) {
							$response.html(data);
							success();
						});
					}

					// Suppression de l'archive d'origine
					function success() {
						$response.html("<p>Installation terminée.</p>");
						$('.progress .bar').animate({width: "100%"});
						$response.hide();
						$('.progress').delay(500).hide();
						$.post('<?php echo $_SERVER['PHP_SELF'] ?>?action=success',$('form').serialize(), function(data) {
							$('#success').show().append(data);
						});
					}

				});
			</script>
		<?php
		} // if is_writable( $parent_dir ) )
		else { ?>

			<div class="alert alert-error" style="margin-bottom: 0px;">
				<strong>Attention !</strong>
				<p style="margin-bottom:0px;">Le dossier <strong><?php echo basename( $parent_dir ); ?></strong> n'a pas les droits d'écriture. Merci de modifier les droits afin que WP Quick Install fonctionne correctement.</p>
			</div>

		<?php
		} // else
		?>
	</body>
</html>
<?php
}
?>
