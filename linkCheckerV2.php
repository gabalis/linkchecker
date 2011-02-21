<?php

/*
 MODx - LinkChecker (ou HyperLinkChecker, nom à proposer)
 Ce snippet récupère tous les liens dans tous les documents et permet une correction groupée.
 Dernière modification par bto le 13 décembre 2006
*/

// pour récupérer la variable "site_url"
require_once($_SERVER["DOCUMENT_ROOT"]."/manager/includes/config.inc.php");
// pour la fonction permettant de récupérer l'url d'un document via son id
//include_once($_SERVER["DOCUMENT_ROOT"]."/manager/includes/document.parser.class.inc.php");
// pour récupérer le préfixe et le suffixe des friendly urls
//include_once($_SERVER["DOCUMENT_ROOT"]."/manager/includes/settings.inc.php");

# on inclut les fonctions
require_once("fonctions.php");


# variables générales
$checked = ' checked="checked"';
$waitMsg = ' Veuillez patienter...';
$linkErrCpt = 0; // compteur d'erreurs

# récupération des pages et de leurs liens

// table des documents d'où on va extraire les liens
//$tbl_content = $modx->dbConfig['dbase'].".".$modx->dbConfig['table_prefix']."site_content";
$tbl_content = $modx->getFullTableName("site_content");
// sélection des différents documents
$sql = "SELECT `id`, `alias`, `pagetitle`, `content`, `parent`, `published`, `isfolder` FROM ".$tbl_content." ORDER BY `parent` ASC, `pagetitle` ASC;";
// exécution de la requête
$rs = $modx->dbQuery($sql);
// nombre de documents trouvés
$nbPages = $modx->recordCount($rs);

// contiendra toutes les infos issues de la requête $sql
$infosDocuments = array();
// contiendra tous les liens des pages
$links = array();
// correspondances
$alias2id = array();
$id2alias = array();
$id2parent = array();

/****************************************/
/*     début gestion corrections [1]    */
/****************************************/

// si le formulaire est posté et qu'il s'agit des corrections
if ($_SERVER['REQUEST_METHOD'] == 'POST' && strstr($_POST['submitbtn'], 'correction'))
{
	// tableaux qui contiendront les modèles à remplacer et les remplacements
	$patternArr = array();
	$replaceArr = array();
		
	// boucle sur les valeurs postées
	foreach ($_POST as $docPOST_name => $docPOST_valueArr)
	{
		// récupération des informations sur base du nom des champs -> doc_id_type
		$docPOST_parts = explode('_', $docPOST_name);
		$docPOST_id = $docPOST_parts[1];
		$docPOST_type = $docPOST_parts[2];
		
		if ( !is_array($docPOST_valueArr) ) {
			$docPOST_valueArr = array();
		}
		
		
		// boucle sur les valeurs reçues
		foreach ($docPOST_valueArr as $docPOST_value)
		{	
			// nettoyage de la chaîne et ajout d'un "=" devant les guillemets pour le remplacement
			//$docPOST_value = str_replace('&nbsp;', '', $docPOST_value); // suppression des espaces
			//$docPOST_value = str_replace('"', '', $docPOST_value); // suppression des éventuels guillemets
			//$docPOST_value = str_replace("&nbsp;", "", $docPOST_value);
			
			// format de la chaine
			$strFormat = '="%s"';
			
			// [DEBUG] affichage de la chaine
			//var_dump($docPOST_value);
		
			// si c'est une valeur originale
			if ($docPOST_type == 'orig') {
				// ajout de cette valeur au tableau des modèles
				$patternArr[$docPOST_id][] = '`'.sprintf($strFormat, $docPOST_value).'`';
			}
			// si c'est une valeur corrigée
			elseif ($docPOST_type == 'corr') {
				// ajout de cette valeur au tableau des remplacements
				$replaceArr[$docPOST_id][] = sprintf($strFormat, trim($docPOST_value));
			}
		}
	}
}

/***************************************/
/*     fin gestion corrections [1]     */
/***************************************/


# boucle sur toutes les pages trouvés
	
for ($i = 0; $i < $nbPages; $i++)
{
	// récupération des informations sur la page en cours
	$sqlRow = $modx->fetchRow($rs);
	
	
	/****************************************/
	/*     début gestion corrections [2]    */
	/****************************************/
	
	// si le formulaire est posté et que les modèles ont été attribués
	if ($_SERVER['REQUEST_METHOD'] == 'POST' && is_array($patternArr))
	{	
		// si le document contient des liens qui comportent des erreurs
		if (isset($patternArr[$sqlRow['id']]))
		{
			// récupération des modèles et des remplacements pour l'id courant
			$pattern = $patternArr[$sqlRow['id']];
			$replace = $replaceArr[$sqlRow['id']];
			
			// [DEBUG] afficahge du contenu des modèles et des remplacements
			//var_dump($pattern);
			//var_dump($replace);
			
			// replacement des anciens liens par les liens corrigés
			$newContent = preg_replace($pattern, $replace, $sqlRow['content']);
			
			// attribution du nouveau contenu
			$sqlRow['content'] = $newContent;
			
			// [DEBUG] affichage du contenu corrigé
			//var_dump($sqlRow['content']);
			
			// requête de mise à jour du contenu dans la base de données
			$sql_upd = 'UPDATE '.$tbl_content.' SET `content` = "'.addslashes($sqlRow['content']).'" WHERE `id` = "'.$sqlRow['id'].'" LIMIT 1;';
			
			// [DEBUG] affichage de la requête
			// encodage des liens "[~id~]" pour éviter que MODx ne les interprêtent
			//$sqlRow['content'] = str_replace("~", "&#126;", $sqlRow['content']);
			//echo $sqlRow['content'];
			
			// exécution de la requête
			$rs_upd = $modx->dbQuery($sql_upd);
		}
	}
	
	/**************************************/
	/*     fin gestion corrections [2]    */
	/**************************************/
	
	// tableau "copie" des résultats récupérés de la requête
	$infosDocuments[$sqlRow['id']] = $sqlRow;
	
	// récupération des liens dans la page en cours
	$pagelinks = checkLinks($sqlRow['content'], $sqlRow['published']);
	
	// aucun lien, possible ? 
	
	
	// boucle sur tous les liens trouvés
	foreach ($pagelinks as $pagelinktext => $pagelink) {
	
		// récupération des différentes informations sur les liens
		if (!empty($pagelinktext)) { // intitulé du lien
			$doc['texte'] = $pagelinktext;
		} else {
			$doc['texte'] = '';
		}
	
		$doc['lien'] = $pagelink;
		$doc['docParent'] = $sqlRow['parent'];
		$links[$sqlRow['id']][] = $doc;
	
	}
	
	$alias2id[$sqlRow['alias']] = $sqlRow['id'];
	$id2alias[$sqlRow['id']] = $sqlRow['alias'];
	$id2parent[$sqlRow['id']] = $sqlRow['parent'];
}


if ($_SERVER["REQUEST_METHOD"] == "POST")
{

	# filtres

	$v_type = $_POST["type"];
	$v_dossier = $_POST["dossier"];
	$v_mode = $_POST["mode"];

	# messages à afficher en fonction des résultats
	
	$msg = array
	(
		"LinkIsEmpty"							=> "Le lien est vide.",
		"LinkContainsSpaces" 					=> "Il y a un ou plusieurs espaces au début ou à la fin du lien.",
		"LinkIsJustOneSlash" 					=> "Lien vers la racine du site : OK.",
		"LinkShortcutIsValidAndPublished" 		=> "Le lien MODx est bon.",
		"LinkShortcutIsValidButNotPublished"	=> "Le lien MODx est bon mais le document n'est pas publié.",
		"LinkShortcutIsNotValid" 				=> "Le lien pointe vers un document local inexistant.",
		"LinkEmailIsValidAndComplete"			=> "L'adresse semble correcte.",
		"LinkEmailIsValidButIncomplete"			=> "L'adrese semble correcte mais doit être précédée de 'mailto:'",
		"LinkExternalIsValid"					=> "Le lien externe est correct.",
		"LinkExternalIsNotValid" 				=> "Le lien externe n'est pas valide.",
		"LinkInternalIsValid"					=> "Le lien interne est correct.",
		"LinkInternalIsNotValid"				=> "Le lien interne n'est pas valide.",
		"LinkJavascriptIsValid"					=> "Le lien Javascript semble correct."
	);
	
	
	// $links contient autant de sous array qu'il y a des documents
	// et chaque sous array contient tous les liens du document en question
	
	$output = array();
	
	# boucle sur tous les liens trouvés
	
	foreach ($links as $docId => $docLinks)
	{
	
		# filtre sur les dossiers
		
		if (is_numeric($v_dossier)) {
			if ($docId != $v_dossier && $infosDocuments[$docId]["parent"] != $v_dossier) {
				continue;
			}
		} elseif ($v_dossier == "/") {
			if (!$infosDocuments[$docId]["isfolder"] || $infosDocuments[$docId]["parent"] != 0) {
				continue;
			}
		}	
	
		$originalDocumentUrl = $modx->rewriteUrls("[~$docId~]");
		
		$url_split = explode("/", $originalDocumentUrl);
		$id_to_check = $alias2id[$url_split[0]];
		if ($infosDocuments[$id_to_check]["published"] == '0') continue;
		
		$pagetitle = $infosDocuments[$docId]["pagetitle"];

		$outputDoc = array();

		$outputDoc[] = '<h3>'.$pagetitle.' (<a href="/'.$originalDocumentUrl.'" target="_blank">'.$originalDocumentUrl.'</a>)</h3>';
		$outputDoc[] = '<span class="top">[ <a href="'.makeLink().'#formulaire">revenir au formulaire</a> ]</span>';
		$outputDoc[] = '<ul>';
	
		# boucle sur les liens page par page
	
		$outputLink = array();
		
		foreach ($docLinks as $doc)
		{
			// on ne connaît pas le lien de remplacement encore
			$replacementLink_t = '';
			
			$sourceLink = $doc['lien'];
			$sourceLinkText = trim($doc['texte']);
			//echo $link."<br />";
			
			# si le lien est vide
			if (trim($sourceLink) == "") {
				$message = $msg["LinkIsEmpty"];
				$linkStatus = 0;
				$replacementLink_t = '';
			}
			
			# si le lien contient des espaces
			elseif (strlen($sourceLink) != strlen(trim($sourceLink))) {
				$message = $msg["LinkContainsSpaces"];
				$linkStatus = 1;
				//$replacementLink_t = str_replace(" ", "&nbsp;", $sourceLink);
				//$sourceLink = str_replace(' ', '&nbsp;', $sourceLink); remplacement au moment de l'affichage
				$replacementLink_t = trim($sourceLink);
			}
			
			# si le lien est juste un "/" ou pointe vers un ancre
			elseif ($sourceLink == "/" || $sourceLink{0} == "#") {
			
				if ($v_type == "ext") continue;
				
				$message = $msg["LinkIsJustOneSlash"];
				$linkStatus = 2;
				//$replacementLink_t = $modx->config["site_url"] . $sourceLink;
				// bto 20061213 : suppression de "/" en double
				//$replacementLink_t = str_replace("//", "/", $replacementLink_t);
			}
			
			# si c'est un code javascript
			
			elseif (strpos($sourceLink, "javascript:") === 0) {
			
				if ($v_type == "ext") continue;
			
				$message = $msg["LinkJavascriptIsValid"];
				$linkStatus = 2;
				$replacementLink_t = '';
			}
			
			# si c'est un lien MODx ex: [~34~]
			
			elseif (preg_match('`^(\[\~([0-9]*)\~\](#.*)?)$`', $sourceLink, $matches)) {
			
				if ($v_type == "ext") continue;
				
				// encodage du lien pour éviter que MODx ne l'interprête
				$sourceLink = str_replace("~", "&#126;", $sourceLink);
			
				$infosDoc = $infosDocuments[$matches[2]];
				if (!empty($infosDoc)) {
					if ($infosDoc['published']) {
						$message = $msg["LinkShortcutIsValidAndPublished"];
						$linkStatus = 2;
					} else {
						$message = $msg["LinkShortcutIsValidButNotPublished"];
						$linkStatus = 1;
						$replacementLink_t = $sourceLink;
					}
				} else {
					$message = $msg["LinkShortcutIsNotValid"];
					$linkStatus = 0;
					$replacementLink_t = $sourceLink;					
				}
				
				//$replacementLink_t = $modx->config["site_url"] . $modx->rewriteUrls($matches[1]);
			}
			
			# si c'est un adresse mail
			
			elseif (preg_match('`^(mailto:)?[[:alnum:]]([-_.]?[[:alnum:]])*@[[:alnum:]]([-.]?[[:alnum:]])*\.([a-z]{2,4})$`', $sourceLink, $matches)) {
				if ($matches[1] == 'mailto:') { // 1ère paranthèse
					$message = $msg["LinkEmailIsValidAndComplete"];
					$linkStatus = 2;
				} else {
					$message = $msg["LinkEmailIsValidButIncomplete"];
					$linkStatus = 1;
					$replacementLink_t = $sourceLink;
				}
				
			}
			
			# si le lien commence par "http://" mais ne contient pas l'url du site : lien externe
			
			elseif ( strpos($sourceLink, 'http://') === 0 && strpos($sourceLink, $modx->config["site_url"]) === false ) {
			
				//if ($v_type == "int") continue;
				if ($v_type == $v_type) continue;
					
				if (checkLinkSocket($sourceLink)) {
					$message = $msg["LinkExternalIsValid"];
					$linkStatus = 2;	
				} else {
					$message = $msg["LinkExternalIsNotValid"];
					$linkStatus = 0;
					$replacementLink_t = $sourceLink;
				}
			
			}
			
			# sinon, c'est un lien interne
			
			else {
	
				if ($v_type == "ext") continue;
	
				# vérification : lien interne fichier ou lien interne MODx
				
				//$docMethod = $modx->getDocumentMethod();
				
				// on supprime l'url du site dans le lien à vérifier
				$docIdentifier = str_replace($modx->config["site_url"], "", $sourceLink);
				// on nettoie l'url
				$docIdentifier = $modx->cleanDocumentIdentifier($docIdentifier);
				
				// on récupère chaque partie de l'url en coupant aux "/"
				$url_parts = explode("/", $docIdentifier);
				
				// on boucle sur chaque partie et on vérifie si elle existe et a un parent correspondant à sa position
				// le parent de départ est 0 pour la première partie de l'url, on cherche donc WHERE alias= et parent=
				// on sort de la boucle dès qu'on trouve une partie de lien inexistante
				// si tout se passe bien jusqu'au bout, on récupère les infos du document
				$url_valide = true;
				$parent_to_match = 0;
				foreach($url_parts as $url_part) {
					// on sélectionne l'enregistrement dans la base de données
					$sql_verifyPart_t = "SELECT * FROM $tbl_content WHERE `alias`='$url_part' AND `parent`=$parent_to_match;";
					// echo $sql_verifyPart_t;
					$rs_verifyPart_i = mysql_query($sql_verifyPart_t);
					// si on trouve des résultats et qu'il n'y en a qu'un seul
					if ($rs_verifyPart_i !== false && mysql_num_rows($rs_verifyPart_i) === 1) {
						// on récupère le document trouvé
						$result_verifyPart_at = mysql_fetch_array($rs_verifyPart_i);
						// et on prend l'id du parent pour le prochain tour de boucle
						$parent_to_match = $result_verifyPart_at['parent'];
					}
					// sinon, si aucun résultat
					else {
						// on sort et on le signale
						$url_valide = false;
						break;
					}
				}
				
				// si l'url est valide, on récupère l'objet contenant les infos du document
				if ($url_valide) {
					$docObject = $result_verifyPart_at;
					$docIdentifier = $docObject['id'];
				} else {
					$docObject = "pas de docObject!";
				}
				
				/* Lignes commentées par bto le 13/12/2006
				   La méthode "getDocumentObject" renvoit vers la page 404 si le document n'est pas accessible
				   On remplace cette méthode par une simple requête SQL qui récupérera les données voulues
				   
				if($docMethod == "alias") {
					// Check use_alias_path and check if $this->virtualDir is set to anything, then parse the path
					if ($modx->config['use_alias_path'] == 1) {
						$alias = (strlen(dirname($modx->virtualDir)) > 0 ? dirname($modx->virtualDir).'/' : '').$docIdentifier;
						if (array_key_exists($alias, $modx->documentListing)) {
				    		$docIdentifier = $modx->documentListing[$alias];
				    	}
					}
					else {
						$docIdentifier = $modx->documentListing[$docIdentifier];
					}
					$docMethod = 'id';
				}
				
				$docObject = $modx->getDocumentObject($docMethod, $docIdentifier);
				
				   Fin des lignes commentées
				*/
								
				# s'il s'agit d'un fichier interne
				
				if (!is_numeric($docIdentifier)) {
	
	
					/*if ($docObject["id"] == $modx->config["error_page"]) {
						$msg = "error!";
					}
					
					elseif ($docObject["id"] == $modx->config["unauthorized_page"]) {
						$msg = "interdit";
					}*/
				
					// suppression suffixe
					$page = basename($sourceLink, $modx->config["friendly_url_suffix"]);
					// recherche préfixe
					$checkPrefix = (!empty($modx->config["friendly_url_prefix"])) ? strpos($page, $modx->config["friendly_url_prefix"]) : false;
					// si préfixe trouvé
					if ($checkPrefix !== false) {
						// suppression préfixe
						$page = subtr($page, strlen($modx->config["friendly_url_prefix"]), strlen($page));
					}
					// récupération de l'id
					$id = $alias2id[$page];
				
					if (empty($id)) {
					
						$tmp_link = $sourceLink;
						/* 20060608 par benjamin : modifié pour supprimer l'adresse du site pour le test */
						/* commenté pour utiliser le test socket et pas "file_exists"
						if (strpos($sourceLink, 'http://') === 0) {
							$site_url_length = strlen($modx->config["site_url"]);
							$tmp_link = substr_replace($tmp_link, '', 0, $site_url_length-1);
							$tmp_link = $_SERVER['DOCUMENT_ROOT'].$tmp_link;
						}*/
						if (strpos($tmp_link, 'http://') === false) {
							if ($tmp_link{0} == '/') $tmp_link = substr($tmp_link, 1);
							$tmp_link = $modx->config["site_url"].$tmp_link; // ajout de l'url de base
							//$tmp_link = strtr('//', '/', $tmp_link); // suppression des doublons de "/"
						}
						
						//var_dump($modx->config["site_url"]);
						//var_dump($tmp_link);
						
						if (checkLinkSocket($tmp_link)) {
						//if (file_exists($tmp_link)) {
							$message = $msg["LinkInternalIsValid"];
							$linkStatus = 2;	
						} else {
							$message = $msg["LinkInternalIsNotValid"];
							$linkStatus = 0;
							$replacementLink_t = $sourceLink;
						}
						
						// récupération du lien de base depuis l'original (document qui contient le lien)
						$splitLink = explode("/", $originalDocumentUrl);
						// on supprime le dernier élément
						array_pop($splitLink);
						// on recompose le tableau
						$originalDocumentUrlSplitted = implode("/", $splitLink);
						
						//$replacementLink_t = $sourceLink;
						$replacementLink_t = $originalDocumentUrlSplitted.$sourceLink;
						// encodage du lien pour éviter que MODx ne l'interprête
						//$sourceLink = str_replace("~", "&#126;", $sourceLink);
						
					}
				
					// lien interne hors modx
					else {
						// on serait donc sur un lien texte
						// qui mène à un document MODx
						$message = "Lien textuel vers un document MODX, utilisez [~id~]";
						
						$linkStatus = 1;
						$replacementLink_t = "[&#126;$id&#126;]";
						
					}
					
				
				}
				
				// lien interne modx
				else {
					
					if ($v_type == "ext") continue;
					
					if ($docObject["published"] === 1) {
						$message = "lien interne modx ok";
						$linkStatus = 2;
					}
					
					else {
						$message = "lien interne modx ok mais pas publié";
						$linkStatus = 1;
						$replacementLink_t = $sourceLink;
					}
				}
				
			} // fin lien interne
			
			// affichage résultat pour le lien
			$image = array("check_error", "check_warning", "check_ok");
			$imageCheck = "images/" . $image[$linkStatus] . ".png";
			
			if (!strpos($link, 'http://')) {
				$baseDir = dirname($originalDocumentUrl);
			} else {
				$baseDir = '';
			}
			//$replacementLink_t = $baseDir . $sourceLink;
			
			$outputLinkTemp = array();
			
			$outputLinkTemp[] = '<li style="background-image: url('.$imageCheck.');">';
			$outputLinkTemp[] = '	<ul>';
			
			$outputLinkTemp[] = "

<li><span>- Lien trouvé : </span><strong>" . str_replace(' ','&nbsp;',$sourceLink) . "</strong><br />
	<span>- Titre de la page : </span>$sourceLinkText.</li>";

			/*if (!empty($sourceLinkText)) {
			$outputLinkTemp[] = '		<li><span>- Intitulé du lien : </span>"'.$sourceLinkText.'"</li>';
			}*/
			
			if (!empty($replacementLink_t)) {
			$outputLinkTemp[] = '		<li><span>- Adresse réelle : </span>'.makeLink($replacementLink_t).'</li>';
			}
			
			$outputLinkTemp[] = "		<li><span>- Résultat : </span>$message</li>";
			
			// si le lien comporte des erreurs
			if ($linkStatus != 2)
			{
				$linkErrCpt++; // compteur d'erreurs, est utilisé pour l'affichage du formulaire de correction
			
				//$longueurChamp = (strlen($sourceLink)!=0) ? strlen($sourceLink)/1.6 : 40;
				$longueurChamp = 45;
				
				$outputLinkTemp[] = '		<li style="margin: 5px 0 7px 0;"><span>- Correction : </span>';

/*				
				if ($message != $msg["LinkContainsSpaces"])
				{
					$outputLinkTemp[] = '       <input type="text" name="doc_'.$docId.'_corr[]" value="'.trim($sourceLink).'" style="width: '.$longueurChamp.'em;" />';
					$outputLinkTemp[] = '       <input type="hidden" name="doc_'.$docId.'_orig[]" value="'.$sourceLink.'" />';
				} else {
*/
//					$outputLinkTemp[] = '       (<em>les espaces seront automatiquement supprimés lors de la correction</em>)';
					$outputLinkTemp[] = '       <input type="text" name="doc_'.$docId.'_corr[]" value="'.$replacementLink_t.'" size="' . $longueurChamp . '" />';
					$outputLinkTemp[] = '       <input type="hidden" name="doc_'.$docId.'_orig[]" value="'.$sourceLink.'" />';
//				}
				
				$outputLinkTemp[] = '       </li>';
			}
			
			$outputLinkTemp[] = '	</ul>';
			$outputLinkTemp[] = '</li>';
			
			
			# filtre sur le mode
			
			if ($v_mode == "errors") {
				if ($linkStatus == 2) { // 2 = OK, 0 = erreur et 1 = avertissement
					continue;
				}
			}
			
			$outputLink = array_merge($outputLink, $outputLinkTemp);
		
		}
		// end 2e for
		
		if (empty($outputLink)) {
			continue;
		}
		
		$outputDoc = array_merge($outputDoc, $outputLink);
		$outputDoc[] = '</ul>';
		
		$output = array_merge($output, $outputDoc);
		
	}
	// end 1er for

	if (empty($output)) {
		$output[] = '<p>Tous les liens vérifiés sont corrects.</p>';
	} else {
		array_unshift($output, "<h2>Résultats</h2>");
	}

} //end if 'post'

?>
<?php
function encodeToUTF8($t) {
	global $modx;
	if ($modx->config['modx_charset'] == "UTF-8") {
		// old stuff, we all use UTF8 now
		return $t;
		//return utf8_encode($t);
	} else {
		return $t;
	}
}
ob_start("encodeToUTF8");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
<head>
<?php
	$cut = strlen($_SERVER["DOCUMENT_ROOT"]);
	if ($_SERVER["HTTP_HOST"]{strlen($_SERVER["HTTP_HOST"])-1} != '/') $_SERVER["HTTP_HOST"] .= '/';
	$baseDir = "http://" . $_SERVER["HTTP_HOST"] . substr(dirname(__FILE__), $cut) . "/";
?>
	<base href="<?php echo $baseDir; ?>" />
	<meta content="text/html; charset=UTF-8" http-equiv="content-type" />
	<title>Vérification des liens dans MODx - [(site_name)]</title>
	<link media="screen" type="text/css" href="style.css" rel="stylesheet" />	
</head>
<body>

	<h1>Vérification des liens dans MODx</h1>

	<a name="formulaire"></a>
	<h2>Formulaire</h2>

	<form method="post" action="<?php echo makeLink(); ?>#verification">
		<p>Liens à vérifier :</p>
		<p>
			<fieldset>
				<input type="radio" name="type" value="all" id="type1"<?php echo ($v_type == "all") ? $checked : ''; ?> />
				<label for="type1">tous les liens</label><br />
				<input type="radio" name="type" value="int" id="type2"<?php echo ($v_type == "int" || empty($v_type)) ? $checked : ''; ?> />
				<label for="type2">les liens internes</label><br />
				<input type="radio" name="type" value="ext" id="type3"<?php echo ($v_type == "ext") ? $checked : ''; ?> />
				<label for="type3">les liens externes</label>
			</fieldset>
		</p>
		<p>Pages à vérifier :</p>
		<p>
			<fieldset>
				<input type="radio" name="dossier" value="all" id="dossier1"<?php echo ($v_dossier == "all" || empty($v_dossier)) ? $checked : ''; ?> />
				<label for="dossier1">toutes les pages</label><br />
				<input type="radio" name="dossier" value="/" id="dossier2"<?php echo ($v_dossier == "/") ? $checked : ''; ?> />
				<label for="dossier2">les pages sans dossier</label><br />
			<?php foreach($links as $id => $link) : ?>
			<?php
				if (empty($cpt)) $cpt = 2;
				$dossier = ($infosDocuments[$id]["isfolder"] && $infosDocuments[$id]["parent"] == 0) ? $infosDocuments[$id]["pagetitle"] : '';
				if (empty($dossier)) continue;
				$cpt++;
			?>
				<input type="radio" name="dossier" value="<?php echo $id; ?>" id="dossier<?php echo $cpt; ?>"<?php echo ($v_dossier == $id) ? $checked : ''; ?> />
				<label for="dossier<?php echo $cpt; ?>">les pages "<?php echo $dossier; ?>"</label><br />
			<?php endforeach; ?>
			</fieldset>
		</p>
		<p>Affichage des résultats :</p>
		<p>
			<fieldset>
				<input type="radio" name="mode" value="detail" id="mode1"<?php echo ($v_mode == "detail") ? $checked : ''; ?> />
				<label for="mode1">afficher tous les liens trouvés</label><br />
				<input type="radio" name="mode" value="errors" id="mode2"<?php echo ($v_mode == "errors" || empty($v_mode)) ? $checked : ''; ?> />
				<label for="mode2">n'afficher que les liens qui comportent des erreurs</label>	
			</fieldset>
		</p>
		<p>
			<input type="submit" name="submitbtn" value="Lancer la vérification" onclick="document.getElementById('formCheckMsg').innerHTML='<?php echo $waitMsg; ?>';" />
			<span id="formCheckMsg"></span>
		</p>
		
	</form>
	
	<a name="verification"></a>
	
	<?php if ($_SERVER['REQUEST_METHOD'] == "POST" && $linkErrCpt > 0) : /* si des erreurs ont été trouvées */ ?>
	
	<form action="<?php echo makeLink(); ?>#verification" method="post">
	
		<?php echo implode("\n", $output); ?>
		
		<input type="hidden" name="type" value="<?php echo $v_type; ?>" />
		<input type="hidden" name="mode" value="<?php echo $v_mode; ?>" />
		<input type="hidden" name="dossier" value="<?php echo $v_dossier; ?>" />
		
		<p>
			<input type="submit" name="submitbtn" value="Valider toutes les corrections" onclick="document.getElementById('formCorrMsg').innerHTML='<?php echo $waitMsg; ?>';" />
			<span id="formCorrMsg"></span>
		</p>
	
	</form>
	
	<?php endif; ?>
	
	<p class="footer">[ <a href="<?php echo makeLink(); ?>#">haut de page</a> ]</p>
	
	<p class="copyright">&copy; <a href="http://www.hypernovae.be/">Hypernovae</a> 2006-2010</p>
	
</body>
</html>
<?php ob_end_flush(); ?>
