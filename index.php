<?php
set_time_limit(0);

/*-----------------------------------------------------------------------------------*/
/*	On construit le tableau de configuration
/*-----------------------------------------------------------------------------------*/

// On récupère les données du fichier data.txt
if( file_exists( 'data.txt' ) ) {
	
	$data = array();
	
	foreach ( file( 'data.txt' ) as $line ) {
		$line = explode( ":", $line );
		$data[trim($line[0])] = trim( $line[1] );
	}
	
	// On transforme les éléments du tableau en variable
	extract($data);
	
	// On ajoute  ../ au directory
	$directory = !empty($directory) ? '../' .$directory . '/' : '../' ;
	
	// On construit un tableau avec les configurations de la BDD
	$db_config = array();
	$tmp_db_config = explode( ";", $db );
	
	foreach ( $tmp_db_config as $db_line ) {
		$db_lines = explode( "=", $db_line );
		$db_config[trim($db_lines[0])] = trim( $db_lines[1] );
	} // foreach
	
	// On construit un tableau avec les configurations de l'admnistrateur
	$admin_config = array();
	$tmp_admin_config = explode( ";", $admin );
	
	foreach ( $tmp_admin_config as $admin_line ) {
		$admin_lines = explode( "=", $admin_line );
		$admin_config[trim($admin_lines[0])] = trim( $admin_lines[1] );
	} // foreach
}

// Allez, maintenant on commence les choses sérieuses haha =D
if( isset( $_GET['action'] ) ) {
	
	switch( $_GET['action'] ) {
		
		case "check_db_connection" :
			
			/*-----------------------------------------------------------------------------------*/
			/*	On check si on arrive à se connecter à la base de données
			/*-----------------------------------------------------------------------------------*/
			
			try {
			   $db = new PDO('mysql:host='. $_POST['dbhost'] .';dbname=' . $_POST['dbname'] , $_POST['uname'], $_POST['pwd'] );
			} // try
			catch(Exception $e) {
				echo "error etablishing connection";
			} // catch
			
			break;
		
		case "create_data" :
			
			/*-----------------------------------------------------------------------------------*/
			/*	On crée le fichier data.txt qui va contenir l'ensemble de la configuration
			/*-----------------------------------------------------------------------------------*/
				
			// On définit le contenu du fichier data.txt
			$data_file = 'directory: ' . $_POST[ 'directory' ] . "\r\n";
			$data_file .= 'weblog_title: ' . $_POST[ 'weblog_title' ] . "\r\n";
			$data_file .= 'db: prefix=' . $_POST[ 'prefix' ] . '; dbname=' . $_POST[ 'dbname' ] . '; dbhost=' . $_POST[ 'dbhost' ] . '; uname=' . $_POST[ 'uname' ] . '; pwd=' . $_POST[ 'pwd' ] . "\r\n";
			$data_file .= 'admin: user_name= ' . $_POST[ 'user_name' ] . '; admin_password= ' . $_POST[ 'admin_password' ] . '; admin_email=' . $_POST[ 'admin_email' ] . ' ' . "\r\n";
			$data_file .= 'seo: ' . (int)$_POST[ 'seo' ] . "\r\n";
			$data_file .= 'plugins: ' . $_POST[ 'plugins' ];
			
			// On crée le fichier data.txt
			file_put_contents( 'data.txt', $data_file);
			
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
			
			// On check si WP n'est pas déjà installé
			if( !file_exists( $directory . 'wp-config.php' ) ) {
				
				// Si on souhaite mettre WordPress dans un sous-dossier, on le crée
				if( !empty( $directory ) ) {
					mkdir( $directory );
					
					// On met à jour les droits d'écriture du fichier
					chmod( $directory , 0755);
				}
				
				// On dézip le fichier
				exec( 'unzip wordpress' );
				
				// On fait une copie du dossier
				exec( 'cp -rp wordpress/* ' . $directory );
				
				// On supprime le dossier wordpress
				exec( 'rm -rf wordpress' );
				
			}
			break;
		
		case "install_plugins" :
			
			/*-----------------------------------------------------------------------------------*/
			/*	On récupère les dossiers des plugins
			/*-----------------------------------------------------------------------------------*/
			
			if( !empty( $plugins ) ) {
					
				$plugins_directory = $directory . 'wp-content/plugins/'; // Dossier qui contient les plugins			
			
				 // On boucle tous les plugins que l'on doit installé
				foreach( explode( ";" , $plugins ) as $plugin ) {
					
					// On check si le plugin est déjà installé
					if( !is_dir( $plugins_directory . $plugin ) ) {
						
						// On récupère le fichier XML du plugin pour récupérer le lien vers le zip du plugin
						$xml = @simplexml_load_file('http://api.wordpress.org/plugins/info/1.0/' . trim( $plugin ) . '.xml');
						
					    if( $xml != NULL ) {
					    	
					    	// On récupère la dernière version de WordPress et on la place à la racine
					    	file_put_contents( $plugins_directory . md5( $plugin ) . '.zip', file_get_contents( $xml->download_link ) );
					    	
					    	// On dézip le fichier
							exec( 'tar xvzf ' . $plugins_directory . md5( $plugin ) . '.zip -C ' . $plugins_directory .'/' );
					    	
					    	// On  supprime l'archive temporaire
					    	unlink( $plugins_directory . md5( $plugin ) . '.zip' );
					    	
					    	// On send un message à l'utilisateur - Soyons un minimum poli :-)
					    	echo '<p>L\'extension <strong>' . $plugin . '</strong>  a été installé avec succès.</p>' ;
					    } // if
					    else {
						    
						    // On send un message à l'utilisateur - Soyons un minimum poli :-)
						    echo '<p>L\'extension <strong>'. $plugin .'</strong>  n\'existe pas<./p>';
					    } // else						
					}					
				} // foreach				
			}
			else {
				// On send un message à l'utilisateur - Soyons un minimum poli :-)
				echo '<p>Aucune extension a installé.</p>';				
			}
			
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
						$line = '$table_prefix  = \'' . addcslashes( $db_config['prefix'], "\\'" ) . "';\r\n";
						continue;
					}
			
					if ( ! preg_match( '/^define\(\'([A-Z_]+)\',([ ]+)/', $line, $match ) )
						continue;
			
					$constant = $match[1];
					$padding  = $match[2];
			
					switch ( $constant ) {
						case 'DB_NAME'     :
							$line = "define('" . $constant . "'," . $padding . "'" . addcslashes( $db_config['dbname'], "\\'" ) . "');\r\n";
							break;
						case 'DB_USER'     :
							$line = "define('" . $constant . "'," . $padding . "'" . addcslashes( $db_config['uname'], "\\'" ) . "');\r\n";
							break;
						case 'DB_PASSWORD' :
							$line = "define('" . $constant . "'," . $padding . "'" . addcslashes( $db_config['pwd'], "\\'" ) . "');\r\n";
							break;
						case 'DB_HOST'     :
							$line = "define('" . $constant . "'," . $padding . "'" . addcslashes( $db_config['dbhost'], "\\'" ) . "');\r\n";
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
					fwrite($handle, $line);
				} // foreach
				fclose($handle);
				
				// On met à jour les droits d'écriture du fichier
				chmod($directory . 'wp-config.php', 0666);
				
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
				wp_install( $weblog_title, $admin_config['user_name'], $admin_config['admin_email'], (int)$seo, '', $admin_config['admin_password'] );
				
				// On met à jour les options siteurl et home avec la bonne adresse URL
				$url = trim( str_replace( basename(dirname(__FILE__)) . '/index.php/wp-admin/install.php?action=install_wp' , str_replace( '../', '', $directory ), 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ), '/' ); // On définit l'adresse à utiliser pour les options siteurl et home_url				
				update_option( 'siteurl', $url );
				update_option( 'home', $url );
				
				break;
			
			case "delete_data" :
				
				/*-----------------------------------------------------------------------------------*/
				/*	On supprime le fichier data.txt.
				/*  NOTE : On ne supprime pas le dossier wordpress.zip si on souhaite dse resservir du script
				/*	sans re-télécharger WordPress
				/*-----------------------------------------------------------------------------------*/
			
				unlink( 'data.txt' );
				
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
		<div id="response"></div>
		<div class="progress progress-striped" style="display:none;">
			<div class="bar" style="width: 0%;"></div>
		</div>
		<div id="success" style="display:none;">
			<h1>Le monde est à vous !</h1>
			<p>L'installation de WordPress s'est déroulée avec succès.</p>
		</div>
		<form method="post" action="">
			
			<div class="alert alert-error" style="display:none;">
				<strong>Attention !</strong>
				<p style="margin-bottom:0px;">Erreur de connexion à la base de données. Merci de vérifier vos identifiants.</p>
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
					<td><input name="pwd" id="pwd" type="text" size="25" value="mot de passe" class="required" /></td>
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
					<th scope="row"><label for="user_name">Identifiant</label></th>
					<td>
						<input name="user_name" type="text" id="user_login" size="25" value="admin" class="required" />
						<p>Les identifiants doivent contenir uniquement des caractères alphanumériques, espaces, tiret bas, tiret, points et le symbole @.</p>
					</td>
				</tr>
						<tr>
					<th scope="row">
						<label for="admin_password">Mot de passe</label>
						<p>Un mot de passe vous sera automatiquement généré si vous laissez ce champ vide.</p>
					</th>
					<td>
						<input name="admin_password" type="text" id="pass" size="25" value="" class="required" />
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
					<td colspan="2"><label><input type="checkbox" name="blog_public" value="1" checked='checked' /> Demander aux moteurs de recherche d&rsquo;indexer ce site.</label></td>
				</tr>
			</table>
			
			<h1>Informations extensions</h1>
			<p>Vous devez saisir ci-dessous les noms des extensions qui doivent être installé.</p>
			<p>Le slug d'une extension est disponible dans son adresse url. 
			<br/>Ex: http://wordpress.org/extend/plugins/<strong>wordpress-seo</strong>/</p>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="plugins">Extensions</label>
						<p>Séparez les slugs des plugins par un ;</p>
					</th>
					<td>
						<input name="plugins" type="text" id="plugins" size="50" value="wordpress-seo; w3-total-cache" />
					</td>
				</tr>
			</table>
			
			<p class="step"><span id="submit" class="button">Installer WordPress</span></p>
		</form>
		
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
		<script>
		
			$(document).ready(function() {
									
				var $response = $('#response');
				
				$('#submit').click( function( evt ) {
					
					var errors = false;
					
					$('input.required').each(function(){
						if( $.trim($(this).val()) == '' ) {
							errors = true;
							$(this).css("border", "1px solid #FF0000");
						} // if
						else {
							$(this).css("border", "1px solid #DFDFDF");
						} // else
					});
					
					// On check la connexion à la BDD
					$.post('<?php echo $_SERVER['PHP_SELF'] ?>?action=check_db_connection', $('form').serialize(), function(data) {
						if( data == "error etablishing connection" ) {
							$('html,body').animate( { scrollTop: $('html,body').offset().top } , 'slow' );
							$('.alert-error').show();
						} // if
						else {
							// Si on n'a pas d'erreur, on peut continuer
							if( !errors ) {
								$('form').fadeOut( 'fast', function(){
									
									// On récupère les données du formulaire
									$.post('<?php echo $_SERVER['PHP_SELF'] ?>?action=create_data', $('form').serialize(), function(data) {
										step1();
									});	
								});
							}
							else {
								$('html,body').animate( { scrollTop: $( 'input.required:first' ).offset().top } , 'slow' );
							}
							return false;
						} // else
					});	
				});
					
				// ETAPE 1
				// On récupère l'archive de la dernière version de WordPress
				function step1() {
					$response.html("<p>Téléchargement de l'archive de WordPress en cours...</p>");
					// On montre la barre de progression
					$('.progress').show();
					$.get('<?php echo $_SERVER['PHP_SELF'] ?>?action=download_wp', function(data) {
						step2();
					});
				}
				
				// ETAPE 2
				// Décompression de l'archive de WordPress					
				function step2() {
					$response.html("<p>Installation des fichiers en cours...</p>" );
					$('.progress .bar').animate({width: "20%"});
					$.get('<?php echo $_SERVER['PHP_SELF'] ?>?action=unzip_wp', function(data) {
						step3();
					});
				}
				
				// ETAPE 3
				// Installation des plugins
				
				function step3() {
					$response.html("<p>Installation des plugins en cours...</p>");
					$('.progress .bar').animate({width: "40%"});
					$.get('<?php echo $_SERVER['PHP_SELF'] ?>?action=install_plugins', function(data) {
						step4();
					});
				}
				
				// ETAPE 4
				// Création du fichier wp-config.php
				function step4() {
					$response.html("<p>Création du fichier wp-config.php en cours...</p>");
					$('.progress .bar').animate({width: "60%"});
					$.get('<?php echo $_SERVER['PHP_SELF'] ?>?action=wp_config', function(data) {
						step5();
					});
				}
						
				// ETAPE 5
				// Création de la BDD et de l'administrateur
				function step5() {
					$response.html("<p>Création de la BDD et de l'administrateur en cours...</p>");
					$('.progress .bar').animate({width: "80%"});
					$.get('<?php echo $_SERVER['PHP_SELF'] ?>/wp-admin/install.php?action=install_wp', function(data) {
						step6();
					});
				}
				
				//ETAPE 6
				// Suppression de l'archive d'origine
				function step6() {
					$response.html("<p>Installation terminée.</p>");
					$('.progress .bar').animate({width: "100%"});
					$.get('<?php echo $_SERVER['PHP_SELF'] ?>?action=delete_data', function(data) {
						$response.delay(500).fadeOut();
						$('.progress').delay(500).fadeOut();
						$('#success').delay(500).fadeIn();
					});
				}
			});
		</script>
	</body>
</html>
	
<?php
}
?>
