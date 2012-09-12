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
			/*	On récupère la dernière version de WordPress et on la place à la racine
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
				
			} // if

			break;

		case "install_plugins" :

			/*-----------------------------------------------------------------------------------*/
			/*	On récupère les dossiers des plugins
			/*-----------------------------------------------------------------------------------*/

			if( !empty( $_POST['plugins'] ) ) {
				
				$plugins = explode( ";", $_POST['plugins'] ); // On récupère la liste des plugins dans un tableau
				$plugins_directory = $directory . 'wp-content/plugins/'; // Dossier qui contient les plugins

				 // On boucle tous les plugins que l'on doit installé
				foreach( $plugins as $plugin ) {

					// On check si le plugin est déjà installé
					if( !is_dir( $plugins_directory . $plugin ) ) {

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
							}
					    } // if
					} // if
				} // foreach
			} // if !empty( $_POST['plugins'] )
			break;

			case "wp_config" :

				/*-----------------------------------------------------------------------------------*/
				/*	On crée le fichier wp-config.php
				/*-----------------------------------------------------------------------------------*/

				// Ok trop cool, on a WP et ses plugins, maintenant on commence les choses sérieuses =-)

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
					$padding  = $match[2];

					switch ( $constant ) {
						case 'WP_DEBUG'	   :

							// Mode Debug
							if( (int)$_POST['debug'] == 1 ) {
								$line = "define('" . $constant . "'," . $padding . "'true');\r\n";
								
								// Affichage des erreurs
								if( (int)$_POST['debug_display'] == 1 ) {
									$line .= "\r\n\n " . "/** Affichage des erreurs à l'écran */" . "\r\n";
									$line .= "define('WP_DEBUG_DISPLAY'," . $padding . "'true');\r\n";
								} // if
								
								// Ecriture des erreurs dans un fichier log
								if( (int)$_POST['debug_log'] == 1 ) {
									$line .= "\r\n\n " . "/** Ecriture des erreurs dans un fichier log */" . "\r\n";
									$line .= "define('WP_DEBUG_LOG'," . $padding . "'true');\r\n";
								}
							} // if
							
							// On ajoute les constantes supplémentaires
							if( (int)$_POST['post_revisions'] == 1 ) {
								$line .= "\r\n\n " . "/** Désactivation des révisions d'articles */" . "\r\n";
								$line .= "define('WP_POST_REVISIONS', false);";
							} // if

							if( (int)$_POST['disallow_file_edit'] == 1 ) {
								$line .= "\r\n\n " . "/** Désactivation de l'éditeur de thème et d'extension */" . "\r\n";
								$line .= "define('DISALLOW_FILE_EDIT', false);";
							} // if

							if( (int)$_POST['autosave_interval'] >= 1 ) {
								$line .= "\r\n\n " . "/** Intervalle des sauvegardes automatique */" . "\r\n";
								$line .= "define('AUTOSAVE_INTERVAL', " . (int)$_POST['autosave_interval'] . ");";
							} // if
							
							$line .= "\r\n\n " . "/** On augmente la mémoire limite */" . "\r\n";
							$line .= "define('WP_MEMORY_LIMIT', '96M');" . "\r\n";

							break;
						case 'DB_NAME'     :
							$line = "define('" . $constant . "'," . $padding . "'" . addcslashes( $_POST[ 'dbname' ], "\\'" ) . "');\r\n";
							break;
						case 'DB_USER'     :
							$line = "define('" . $constant . "'," . $padding . "'" . addcslashes( $_POST['uname'], "\\'" ) . "');\r\n";
							break;
						case 'DB_PASSWORD' :
							$line = "define('" . $constant . "'," . $padding . "'" . addcslashes( $_POST['pwd'], "\\'" ) . "');\r\n";
							break;
						case 'DB_HOST'     :
							$line = "define('" . $constant . "'," . $padding . "'" . addcslashes( $_POST['dbhost'], "\\'" ) . "');\r\n";
							break;
						case 'AUTH_KEY'         :
						case 'SECURE_AUTH_KEY'  :
						case 'LOGGED_IN_KEY'    :
						case 'NONCE_KEY'        :
						case 'AUTH_SALT'        :
						case 'SECURE_AUTH_SALT' :
						case 'LOGGED_IN_SALT'   :
						case 'NONCE_SALT'       :
							$line = "define('" . $constant . "'," . $padding . "'" . $secret_keys[$key++] . "');\r\n";
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
				
				// Si on souhaite supprimer le contenu par défaut
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
				/*	On ajoute les pages renseignées dans le fichier data.ini
				/*-----------------------------------------------------------------------------------*/
				
				global $wpdb;

				// On pense bien à vérifier si le fichier data.ini existe
				if( file_exists( 'data.ini' ) ) {
					
					// On récupère le tableau avec les pages
					$file = parse_ini_file( 'data.ini' );
					
					// On vérifie qu'on a bien au moins une page
					if( count( $file['pages'] ) >= 1 ) {
						
						$i=0;
						foreach( $file['pages'] as $page ) {
							
							// On récupère la ligne complète de configuration de la page
							$pre_config_page = explode( "-", $page );
							$page = array();
							
							foreach( $pre_config_page as $config_page ) {
								
								// On récupère le titre de la page
								if( preg_match( '#title::#', $config_page ) == 1 )
									$page['title'] = str_replace( 'title::', '', $config_page );
								
								// On récupère le status de la page (publish, draft, etc...)
								if( preg_match( '#status::#', $config_page ) == 1 )
									$page['status'] = str_replace( 'status::', '', $config_page );
								
								// On récupère le contenu de la page
								if( preg_match( '#content::#', $config_page ) == 1 )
									$page['content'] = str_replace( 'contenu::', '', $config_page );
								
								// On récupère le slug de la page
								if( preg_match( '#slug::#', $config_page ) == 1 )
									$page['slug'] = str_replace( 'slug::', '', $config_page );
									
								// On récupère le titre de la page parente
								if( preg_match( '#slug::#', $config_page ) == 1 )
									$page['parent'] = str_replace( 'parent::', '', $config_page );
							}
							
							// On crée la page
							if( isset( $page['title'] ) && !empty( $page['title'] ) ) {
								$args = array(
												'post_parent'		=> isset( $page['parent'] ) ? get_page_by_title( $page['parent'] )->ID : 0,
												'post_title' 		=> $page['title'],
												'post_name'			=> isset( $page['slug'] ) ? $page['slug'] : sanitize_title( $page['title'] ),
												'post_status' 		=> isset( $page['status'] ) ? $page['status'] : 'draft',
												'post_author'		=> 1,
												'post_type' 		=> 'page',
												'post_date' 		=> date('Y-m-d H:i:s'),
												'post_date_gmt' 	=> gmdate('Y-m-d H:i:s'),
												'comment_status' 	=> 'closed',
												'ping_status'		=> 'closed'
								);					
								wp_insert_post( $args );
							}
						}
					}
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
		<title>WP Quick Install</title>
		<meta charset="utf-8" />
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
			<div id="success" style="display:none;">
				<h1>Le monde est à vous !</h1>
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
	
				<h1>Informations extensions</h1>
				<p>Vous devez saisir ci-dessous les extensions qui doivent être ajoutées pendant l'installation.</p>
				<div class="alert alert-info">
					<p style="margin:0px; padding:0px;">Le slug d'une extension est disponible dans son adresse url.
					<br/>ex: http://wordpress.org/extend/plugins/<strong>wordpress-seo</strong>/</p>
				</div>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="plugins">Extensions</label>
						</th>
						<td>
							<input name="plugins" type="text" id="plugins" size="50" value="wordpress-seo; w3-total-cache" />
							<p>Vérifiez bien que les slugs des extensions soient séparés par un point virgule (;).</p>
						</td>
					</tr>
				</table>
	
				<h1>Informations wp-config.php</h1>
				<p>Vous devez choisir ci-dessous les constantes supplémentaires à ajouter dans le fichier <strong>wp-config.php</strong>.</p>
	
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="plugins">Révisions</label>
						</th>
						<td colspan="2"><label><input type="checkbox" id="post_revisions" name="post_revisions" value="1" checked='checked' /> Désactiver les révisions automatiques d'articles.</label></td>
					</tr>
					<tr>
						<th scope="row">
							<label for="plugins">Éditeur</label>
						</th>
						<td colspan="2"><label><input type="checkbox" id="disallow_file_edit" name="disallow_file_edit" value="1" checked='checked' /> Désactiver l'éditeur de thème et des extensions.</label></td>
					</tr>
					<tr>
						<th scope="row">
							<label for="autosave_interval">Sauvegarde automatique</label>
							<p>L'intervalle des sauvegardes sera de 60 secondes si vous laissez ce champ vide.</p>
						</th>
						<td><input name="autosave_interval" id="autosave_interval" type="text" size="25" value="7200" /> secondes</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="debug">Mode Debug</label>
						</th>
						<td colspan="2">
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
						/*	Liste des plugins
						/*-----------------------------------------------------------------------------------*/
						if( typeof data.plugins !='undefined' )
							$('#plugins').val( data.plugins.join(';') );
						
						/*-----------------------------------------------------------------------------------*/
						/*	Constantes du fichier wp-config.php
						/*-----------------------------------------------------------------------------------*/
						
						if( typeof data.wp_config !='undefined' ) {
							
							if( typeof data.wp_config.autosave_interval !='undefined' )
								$('#autosave_interval').val(data.wp_config.autosave_interval);
							
							if( typeof data.wp_config.post_revisions !='undefined' )
								( parseInt(data.wp_config.post_revisions) == 1 ) ? $('#post_revisions').attr('checked', 'checked') : $('#post_revisions').removeAttr('checked');
							
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
											step2();
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
	
					// ETAPE 2
					// Décompression de l'archive de WordPress
					function step2() {
						$response.html("<p>Installation des fichiers en cours...</p>" );
						$('.progress .bar').animate({width: "20%"});
						$.post('<?php echo $_SERVER['PHP_SELF'] ?>?action=unzip_wp', $('form').serialize(), function(data) {
							step3();
						});
					}
	
					// ETAPE 3
					// Installation des plugins
	
					function step3() {
						$response.html("<p>Installation des extensions en cours...</p>");
						$('.progress .bar').animate({width: "40%"});
						$.post('<?php echo $_SERVER['PHP_SELF'] ?>?action=install_plugins', $('form').serialize(), function(data) {
							$response.html(data);
							step4();
						});
					}
	
					// ETAPE 4
					// Création du fichier wp-config.php
					function step4() {
						$response.html("<p>Création du fichier wp-config.php en cours...</p>");
						$('.progress .bar').animate({width: "60%"});
						$.post('<?php echo $_SERVER['PHP_SELF'] ?>?action=wp_config', $('form').serialize(), function(data) {
							step5();
						});
					}
	
					// ETAPE 5
					// Création de la BDD et de l'administrateur
					function step5() {
						$response.html("<p>Création de la BDD et de l'administrateur en cours...</p>");
						$('.progress .bar').animate({width: "80%"});
						$.post('<?php echo $_SERVER['PHP_SELF'] ?>/wp-admin/install.php?action=install_wp', $('form').serialize(), function(data) {
							step6();
						});
					}
	
					//ETAPE 6
					// Suppression de l'archive d'origine
					function step6() {
						$response.html("<p>Installation terminée.</p>");
						$('.progress .bar').animate({width: "100%"});
						$response.delay(500).hide();
						$('.progress').delay(500).hide();
						$.post('<?php echo $_SERVER['PHP_SELF'] ?>?action=success',$('form').serialize(), function(data) {
							$('#success').delay(500).show().append(data);
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