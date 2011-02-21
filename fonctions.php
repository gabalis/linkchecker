<?php

/* Version 2 également, accompagne les amélioration apportées à LinkChecker lui-même. */

# makeLink

function makeLink($link='') {
	
	global $modx;
	
	if (empty($link))
	{	
		if ($_SERVER["HTTP_HOST"] {strlen($_SERVER["HTTP_HOST"])-1} != '/') $_SERVER["HTTP_HOST"] .= '/';
		/*return "http://" . $_SERVER["HTTP_HOST"] . $modx->config["friendly_url_prefix"] . $_GET["q"] . $modx->config["friendly_url_suffix"];*/
		//return "http://" . $_SERVER["HTTP_HOST"] . $_GET["q"];
		return $_SERVER['REQUEST_URI'];
	}
	
	else
	{
		return preg_replace('`((https?|ftp)://([^":]+(:[^":@]+)?@)?(\.?[[:alnum:]])+\.[[:alpha:]]{2,5}[#\?/]?[^"]*[\S])`', '<a href="\\1">\\1</a>', $link);
	}
}


# la fonction reçoit le contenu du document analysé (et son état de publication)

function checkLinks($content, $ispub)
{	

	// tableau à retourner
	$pagelinks = array();
	
	// récupération du contenu des liens "a"
	preg_match_all('`<a\s[^>]*href="([^"]*)"[^>]*>([^<]*)</a>`si', $content, $results, PREG_SET_ORDER);
	foreach ($results as $index => $result) {
		if (!empty($result[2])) {	
			$pagelinks[$result[2]] = $result[1];
		} else {
			$pagelinks[] = $result[1];
		}
	}
	// récupération du contenu des liens "href" (sauf les "a")
	preg_match_all('`<[^a>][^>]+ href="([^"]*)"[^>]*>`si', $content, $results, PREG_PATTERN_ORDER);
	foreach ($results[1] as $r) {
		$pagelinks[] = $r;
	}
	// récupération du contenu des liens "src"
	preg_match_all('` src="([^"]*)"`si', $content, $results, PREG_PATTERN_ORDER);
	foreach ($results[1] as $r) {
		$pagelinks[] = $r;
	}
	
	return $pagelinks;
}




/* SOCKET TEST */

function checkLinkSocket($url)
{
	// remplacement des "&" dans l'url, puisque ça semble poser problème à la fonction...
	$url = str_replace('&amp;', '%26', $url);

	// si l'url n'existe pas, est vide ou incomplète
	//if ((!isset($url)) || ($url == '') || ($url == 'http://'))
	if (!preg_match('`((https?|ftp)://([^":]+(:[^":@]+)?@)?(\.?[[:alnum:]])+\.[[:alpha:]]{2,5}[#\?/]?[^"]*[\S])`', $url))
	{
		return 0;
	}
	
	// sinon, si l'url semble valide
	else
	{
		// on récupère les différentes parties de l'url
		// scheme (ex: http) | host | port | user | pass | path | query (après le ?) | fragment (après le #)
		$url_parts = @parse_url($url);
		
		// si l'hôte est vide
		if (empty($url_parts['host']))
		{
			return 0;
		}
		
		// si le chemin n'est pas vide
		if (!empty($url_parts['path']))
		{
			// on le récupère
			$documentpath = $url_parts['path'];
		}
		
		// sinon, on attribue le chemin
		else {
			$documentpath = '/';
		}
		
		// si des infos sont passées en GET, on les ajoute au chemin
		if (!empty($url_parts['query']))
		{
			$documentpath .= "?".$url_parts['query'];
		}
		
		// on récupère l'hôte et le port
		$host = $url_parts['host'];
		$port = $url_parts['port'];

		// si le port n'est pas défini, on l'attribue
		if (empty($port))
		{
			$port = '80';
		}
		
		// ouverture de la connexion test
		$socket = @fsockopen($host, $port, $errno, $errstr, 30);

		// si l'ouverture échoue
		if (!$socket)
		{
			return 0;
		}
		
		// sinon, si l'ouverture fonctionne
		else
		{	
			// envoi d'une en-tête
			@fwrite ($socket, "HEAD ".$documentpath." HTTP/1.0\r\nHost: $host\r\n\r\n");
			
			// récupération de la réponse du serveur
			$http_response = @fgets($socket, 22);
		
			//echo $http_response;
			
			// si la réponse du serveur est dans la liste des erreurs
			/*if ((ereg("302", $http_response, $regs))	// REDIRECT - NOFOUND
			 or (ereg("400", $http_response, $regs))	// BAD REQUEST
			 or (ereg("401", $http_response, $regs))	// UNAUTHORIZED
			 or (ereg("402", $http_response, $regs))	// PAYMENT REQUIRED
			 or (ereg("403", $http_response, $regs))	// FORBIDDEN
			 or (ereg("404", $http_response, $regs))	// NOT FOUND
			 or (ereg("500", $http_response, $regs))	// INTERNAL ERROR
			 or (ereg("501", $http_response, $regs))	// NOT IMPLEMENTED
			 or (ereg("502", $http_response, $regs))	// SERVICE TEMPORARILY OVERLOADED
			 or (ereg("503", $http_response, $regs)))	// GATEWAY TIMEOUT*/
//			 if (ereg("404", $http_response, $regs))

			 if ( strpos ($http_response, '404') )
			{
				return 0;
				
				// fermeture de la connexion
				fclose($socket);
			}
			
			// sinon, si la réponse est bonne
			else
			{
				return 1;
				
				// fermeture de la connexion
				fclose($socket);
			}
			
		}
		
	}
	
}

?>
