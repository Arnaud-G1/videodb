<?php
/**
 * Allocine Parser
 *
 * Parses data from the Allocine.fr
 *
 * @package Engines
 * @author  Douglas Mayle   <douglas@mayle.org>
 * @author  Andreas Gohr    <a.gohr@web.de>
 * @author  tedemo          <tedemo@free.fr>
 * @link    http://www.allocine.fr  Internet Movie Database
 * @version $Id: allocine.php,v 1.16 2010/01/06 13:59:28 jdrien Exp $
 */

 // Load the file.
 require_once "engines/api-allocine-helper-php/api-allocine-helper-2.3.php";
  
$GLOBALS['allocineIdPrefix']    = 'allocine:';
$GLOBALS['allocineServer']	    = 'http://www.allocine.fr';

$eastern_countries = '(japon|coree|chine|iran)';

/**
 * Get meta information about the engine
 *
 * @todo    Include image search capabilities etc in meta information
 */
 
function allocineMeta()
{
    return array('name' => 'Allocine (fr)','stable' => 1);
}

/**
 * Encode title search to allow results with accentued characters
 * @author Martin Vauchel <martin@vauchel.com>
 * @param string	The search string
 * @return string	The search string with no accents
 */
function removeAccents($title, $charset = 'UTF-8')
{
    // Allocine uses ISO-8859-1 encoding while php uses UTF-8
    if (($default_charset = 'UTF-8') && ($charset != 'UTF-8'))
        $title = iconv($charset, "UTF-8//TRANSLIT", $title);

	$accentued = array("à","á","â","ã","ä","ç","è","é","ê","ë","ì",
	"í","î","","ï","ñ","ò","ó","ô","õ","ö","ù","ú","û","ü","ý","ÿ",
	"À","Á","Â","Ã","Ä","Ç","È","É","Ê","Ë","Ì","Í","Î","Ï","Ñ","Ò",
	"Ó","Ô","Õ","Ö","Ù","Ú","Û","Ü","Ý");
	$nonaccentued = array("a","a","a","a","a","c","e","e","e","e","i","i",
	"i","i","n","o","o","o","o","o","u","u","u","u","y","y","A","A","A",
	"A","A","C","E","E","E","E","I","I","I","I","N","O","O","O","O","O",
	"U","U","U","U","Y");
	
	$title = str_replace($accentued, $nonaccentued, $title);
	
	return $title;
}

/**
 * Get Url to search Allocine for a movie
 *
 * @author  Douglas Mayle <douglas@mayle.org>
 * @author  Andreas Goetz <cpuidle@gmx.de>
 * @param   string    The search string
 * @return  string    The search URL (GET)
 */
function allocineSearchUrl($title)
{
	global $allocineServer;
	// The removeAccents function is added here
	return $allocineServer.'/recherche/1/?q='.urlencode(removeAccents($title));
}

/**
 * Get Url to visit Allocine for a specific movie
 *
 * @author  Douglas Mayle <douglas@mayle.org>
 * @author  Andreas Goetz <cpuidle@gmx.de>
 * @param   string    $id    The movie's external id
 * @return  string        The visit URL
 */
function allocineContentUrl($id)
{
   global $allocineServer;
   global $allocineIdPrefix;

   $allocineID = preg_replace('/^'.$allocineIdPrefix.'/', '', $id);
   return $allocineServer.'/film/fichefilm_gen_cfilm='.$allocineID.'.html';
}


/**
 * Search a Movie
 *
 * Searches for a given title on Allocine and returns the found links in
 * an array
 *
 * @author  Douglas Mayle <douglas@mayle.org>
 * @author  Tiago Fonseca <t_r_fonseca@yahoo.co.uk>
 * @author  Charles Morgan <cmorgan34@yahoo.com>
 * @param   string    The search string
 * @return  array     Associative array with id and title
 */
function allocineSearch($title)
{
    $allohelper = new AlloHelper;
	
    $page = 1;
    
    $search_str = removeAccents($title);

	try
    {
		$allo_data =  $allohelper->search(trim($search_str), $page);
        //echo '<pre>';
        //dump($allo_data->movie);
        //echo '</pre>';
	}
	// Error
    catch ( ErrorException $e )
    {
        // Print a error message.
        echo "Error accessing Allocine database" . $e->getCode() . ": " . $e->getMessage();
    }
		
    $data = array();

    // add encoding
    $data['encoding'] = 'iso-8859-1'; // default - legacy from prev code

    // direct match (redirecting to individual title)?
    $single = array();
    if ( $allo_data->results->movie  == 1 )
    {
        $data[0]['id']   = 'allocine:'.$allo_data->movie[0]['code'];
        $data[0]['title']= $allo_data->movie[0]['originalTitle'];
        return $data;
    }
/*
    // multiple matches
    // We remove all the multiples spaces and line breakers
	$resp['data'] = preg_replace('/[\s]{2,}/','',$resp['data']);
	// To have the result zone
	$debutr  = strpos($resp['data'], '<table class="totalwidth noborder purehtml">')+strlen('<table class="totalwidth noborder purehtml">');
	$finr    = strpos($resp['data'], '</table>', $debutr);
	$chaine  = substr($resp['data'], $debutr, $finr-$debutr);
	
    preg_match_all('#<a href=\'/film/fichefilm_gen_cfilm=(\d+).html\'>(.*)<span class=\"fs11\">(.*)<br />(.*)<br />#mU', $chaine, $m, PREG_SET_ORDER);
*/        
    if ( $allo_data->results->movie > 1 )
    {
        foreach ($allo_data->movie as $row) 
        {
            $info['id']     = 'allocine:'.$row['code'];
            $info['title']  = $row['originalTitle'];
            
            // add year (helpful in case of multiple matches)
            $info['year'] = $row['productionYear'];
            // add director (helpful in case of multiple matches)
            $info['director'] = $row['castingShort']['directors'];
            if (strcasecmp($row['title'],$row['originalTitle'])) $info['subtitle'] = $row['title'];
            else $info['subtitle'] = "";
            
            $data[]          = $info;
        }
    }

    return $data;
}

/**
 * Fetches the data for a given Allocine-ID
 *
 * @author  Douglas Mayle <douglas@mayle.org>
 * @author  Tiago Fonseca <t_r_fonseca@yahoo.co.uk>
 * @param   int   imdb-ID
 * @return  array Result data
 */
function allocineData($imdbID) 
{
    global $allocineIdPrefix;
    global $CLIENTERROR;
    global $eastern_countries;
    
    $allohelper = new AlloHelper;

    $allocineID = preg_replace('/^'.$allocineIdPrefix.'/', '', $imdbID);

    // fetch mainpage
	    // Il est important d'utiliser le bloc try-catch pour gérer les erreurs.
    try
    {
    $details = $allohelper->movie($allocineID);
	}
	// Error
    catch ( ErrorException $e )
    {
        // Print a error message.
        echo "Error accessing Allocine database" . $e->getCode() . ": " . $e->getMessage();
		return array();
    }

    $data   = array(); // result
    $ary    = array(); // temp
    
    //echo '<pre>';
    //dump($details);
    //echo '</pre>';
    
    // add encoding
    $data['encoding'] = 'iso-8859-1'; // default - legacy from prev code

    // Allocine ID
    $data['id'] = "allocine:".$allocineID;
    
    $data['title']    = $details['originalTitle'];
    
    // Put subtitle only if different
    if (strcasecmp(removeAccents($details['title'],'iso-8859-1'),removeAccents($details['originalTitle'],'iso-8859-1'))) 
        $data['subtitle'] = $details['title'];
    else 
        $data['subtitle'] = "";
    
    $data['language'] = ""; //$details['language'][0]['$'];
    
    // Remove accent in first letter
    $data['title'] = removeAccents(substr($data['title'], 0, 1),'iso-8859-1').substr($data['title'], 1);
    
    $data['year'] = $details['productionYear'];

    $release_date = "\r\nDate de sortie cinéma : ".$details['release']['releaseDate'] ;

    $data['coverurl'] = $details['poster']['href'];
    $data['runtime']  = round($details['runtime'] / 60);

    $data['director'] = $details['castingShort']['directors'];
   
    // Allocine rating is based on 5, imdb is based on 10
    $data['rating'] = round($details['statistics']['userRating']  * 2,2);
    $data['press_rating'] = round($details['statistics']['pressRating']  * 2,2);
    
    $country_list = array();
    foreach ($details['nationality'] as $country) {
        $country_list[] =  $country['$'];  
    }
    $data['country'] = trim(join(', ', $country_list));  
    
    // Use title as main title for eastern countries
    if (preg_match("/$eastern_countries/i",$data['country']))
    {
        //$data['title'] = $details['title'];
        //$data['subtitle']    = $details['originalTitle'].'xx';
    }
    
	$data['plot'] = $details['synopsis'];

    /*
     Genres (as Array)
    */

    $map_genres = array(
          'Action'            	=> 'Action',
          'Animation'         	=> 'Animation',
          'Arts Martiaux'     	=> 'Action',
          'Aventure'            => 'Adventure',
          'Biopic'              => 'Biography',
          'Bollywood'           => 'Musical',
          'Classique'           => '-',
          'Comédie Dramatique'  => 'Drama',
          'Comédie musicale'    => 'Musical',
          'Comédie'             => 'Comedy',
          'Dessin animé'        => 'Animation', 
          'Divers'              => '-',
          'Documentaire'        => 'Documentary',
          'Drame'               => 'Drama',
          'Epouvante-horreur'   => 'Horror',
          'Erotique'            => 'Adult',
          'Espionnage'          => '-',  
          'Famille'             => 'Family',
          'Fantastique'         => 'Fantasy',
          'Guerre'              => 'War',
          'Historique'          => 'History',
          'Horreur'             => 'Horror',
          'Musique'             => 'Musical',
          'Policier'            => 'Crime',
          'Péplum'              => 'History',
          'Romance'             => 'Romance',
          'Science fiction'     => 'Sci-Fi',
          'Thriller'            => 'Thriller',
          'Western'             => 'Western');
        
    //if (preg_match_all('#Genre :(.*)</a><br/>#U', $resp['data'], $ary, PREG_PATTERN_ORDER) > 0)
    {
      //$genrelist = explode(",", trim(join(', ', $ary[1])));
      $genrelist = $details['genre'];
        
      foreach ($genrelist as $genre)
      {
        $mapped_genre_found = '';        
        foreach ($map_genres as $pattern => $mapped_genre)
        {
          if (preg_match_all('/'.$pattern.'/i', $genre['$'], $junk, PREG_PATTERN_ORDER) > 0)
          {
            $mapped_genre_found = $mapped_genre;
            break;
          }
        }
        $data['genres'][] = ($mapped_genre_found != '-') ? $mapped_genre_found : trim($genre);
      }
    }


    /*
      CREDITS AND CAST
    */
    $cast = "";
	foreach ($details['castMember'] as $castmember)
	{
        //echo '<pre>';
        //print_r($castmember['role']);
        //echo '</pre>';
        $role = "";
        if ($castmember['activity']['code'] == '8001')
        {
            if (isset($castmember['role'])) {
               $role = $castmember['role']; // NOT WORKING!!!
            }
            $cast .= $castmember['person']['name']."::".$role."::allocine:".$castmember['person']['code']."\n";
        }
        //print 'Role: '.$role.'<br>';
        //$cast .= $castmember."::".$ary[3][$count]."::allocine:".$ary[1][$count]."\n";
	}
	$data['cast'] = trim($cast);
       
    /*
      Comments
    */
    // By default
    //$data['language'] = 'french';


	// Return the data collected
	return $data;
}

/**
 * Parses Actor-Details
 *
 * Find image and detail URL for actor, not sure if this can be made
 * a one-step process?  Completion waiting on update of actor
 * functionality to support more than one engine.
 *
 * @author  Douglas Mayle <douglas@mayle.org>
 * @author                Andreas Goetz <cpuidle@gmx.de>
 * @param  string  $name  Name of the Actor
 * @return array          array with Actor-URL and Thumbnail
 */
function allocineActor($name, $actorid)
{
    global $allocineServer;
    global $allocineIdPrefix;
    global $CLIENTERROR;
    
    if (empty ($actorid)) {
        return;
    }

    $allohelper = new AlloHelper;


    // fetch mainpage
	    // Il est important d'utiliser le bloc try-catch pour gérer les erreurs.
    try
    {
        $details = $allohelper->person($actorid);
	}
	// Error
    catch ( ErrorException $e )
    {
        // Print a error message.
        echo "Error accessing Allocine actor database" . $e->getCode() . ": " . $e->getMessage();
		return array();
    }
    
    $url = 'http://www.allocine.fr/personne/fichepersonne_gen_cpersonne='.urlencode($actorid).'.html';

    $ary[0][0]=$url;
    $ary[0][1]=$details['picture']['href'];
    return $ary;
        
}

/**
 * Get Url to visit IMDB for a specific actor
 *
 * @author  Michael Kollmann <acidity@online.de>
 * @param   string  $name   The actor's name
 * @param   string  $id The actor's external id
 * @return  string      The visit URL
 */
function allocineActorUrl($name, $id)
{
    global $allocineServer;
    
    if (empty ($id)) {
        return;
    }

    $path = 'personne/fichepersonne_gen_cpersonne='.urlencode($id).'.html';

    return $allocineServer.'/'.$path;
}

?>
