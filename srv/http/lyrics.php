<?php
// LyricsCore //
// https://github.com/Smile4ever/LyricsCore


// modified ********************************************************
$artist = $_GET[ 'artist' ];
$song = $_GET[ 'song' ];
$filelyrics = '/srv/http/data/lyrics/'.strtolower( $artist.' - '.$song ).'.txt';
// existing
$lyrics = @file_get_contents( $filelyrics );
if ( $lyrics ) {
	echo $lyrics;
	die();
}

include 'simple_html_dom.php';

$source = '';
$url = '';

$lyrics = get_lyrics( $artist, $song );

if ( $source == 'LyricsMania' ) {
	$lyrics = str_replace( "\r\n\r\n\r\n", "\n\n", $lyrics );
	$lyrics = preg_replace( "/[\x00-\x09]/", '', $lyrics );
}
$lyrics = str_replace( "</p>", "\n\n", $lyrics );
$lyrics = trim( strip_tags( html_entity_decode( $lyrics ) ) );

echo $lyrics;

if ( !$lyrics ) die();
// save new
file_put_contents( $filelyrics, $lyrics );

// modified end ****************************************************

function get_parameter($parametername){
	$parameter = isset($_GET[$parametername]) ? $_GET[$parametername] : '';
	if($parameter == ""){
		$parameter = getenv(strtoupper($parametername));
	}
	return $parameter;
}

function debug_print($message){
	debug_print_importance($message, "debug");
}

function debug_print_importance($message, $importance){
	global $debugmsgs;
	$extrainfo = get_parameter("extrainfo");
	
	if($importance == "extrainfo"){
		if($extrainfo != "true"){
			return;
		}
	}
	
	$mode = get_parameter("mode");
	$format = get_parameter("format");
	if($mode == "debug"){
		switch($format){
			case "xml":
				print "<debug>$message</debug>";
				break;
			case "text":
				print "$message\n";
				break;
			case "json":
				$debugmsgs[] = $message;
				break;
			default:	
				print "DEBUG: $message<br/>";
				break;
		}
	}
}

function get_lyrics($artist_x, $title_x){
	global $source;
	global $url;
	
	if($title_x == "" || $artist_x == ""){
		return "";
	}

	$title_x = trim($title_x);
	$artist_x = trim($artist_x);

	$title_x = str_replace(" ", "_", $title_x);
	$artist_x = str_replace(' _ ', ' and ', $artist_x); // Womack _ Womack - Friends
	$artist_x = str_replace(" ", "_", $artist_x);

	$title_x = strtolower($title_x);
	$artist_x = strtolower($artist_x);

	$title_x = str_replace("-", "_", $title_x);
	$original_title = $title_x;
	//$title_x = str_replace($title_x, '[^%w_]',''); TODO: what does this do?
	$title_x = str_replace('&','and', $title_x);
	$artist_x = str_replace('&', 'and', $artist_x);
	//$artist_x = str_replace($artist_x, '_&_','_and_');
	$artist_x = str_replace('_&_','_', $artist_x);
	$artist_x = str_replace('.','', $artist_x);
	$artist_x = str_replace('ó', 'o', $artist_x); // Róisín Murphy
	$artist_x = str_replace('í', 'i', $artist_x); // Róisín Murphy
		 
	$artist_metro = str_replace('-','', $artist_x); //a-ha is aha on metrolyrics, but a_ha on lyricsmode
	$artist_metro = str_replace('_','-', $artist_metro);
	
	$artist_x = str_replace('-','_', $artist_x);
	//$artist_x = str_replace('[^%w_]','', $artist_x);
		
	$metrotitle = str_replace('_','-', $title_x);

	$url = "";
	$lyric_string = "";
	
	if(is_lyric_page($lyric_string) == false){		
		$metrourl = "http://www.metrolyrics.com/$metrotitle-lyrics-$artist_metro.html";
		//lyric_string = fetch_lyrics(metrourl)
		
		$artist_and_location = strpos($artist_metro, "-and-");

		if($artist_and_location > -1){
			$artist_and_location = strpos($artist_metro, "and", $artist_and_location - 2);
			if ($artist_and_location > -1){
				debug_print("MetroLyrics: artist_metro ($artist_metro) contains and");
				if(is_lyric_page($lyric_string) == false){
					if(strlen($artist_metro) - $artist_and_location < 14){
						//quick code path to reduce the number of false tries
						//probably not two separate artists, but one with a & in the name
						//together
						$lyric_string = fetch_lyrics($metrourl);
						$tried_together_and = true;
						
						if(is_lyric_page($lyric_string) == false){
							// together with a dash
							$new_artist_metro = substr($artist_metro, 0, $artist_and_location - 1) . substr($artist_metro, $artist_and_location + 3); // removed "-" . 
							$url = "http://www.metrolyrics.com/$metrotitle-lyrics-$new_artist_metro.html"; //must be the same as above
							debug_print("metrolyrics together with a dash: " . $url);

							$lyric_string = fetch_lyrics($url);
							$tried_together_withdash = true;
						}
					}
				}
					
				//$first_artist_url		
				if(is_lyric_page($lyric_string) == false){
					//first artist
					$new_artist_metro = substr($artist_metro, 0, $artist_and_location - 1);
					$url = "http://www.metrolyrics.com/$metrotitle-lyrics-$new_artist_metro.html";
					$first_artist_url = $url;
					debug_print("first artist: $url");
					$lyric_string = fetch_lyrics($url);
				}
				if(is_lyric_page($lyric_string) == false){
					//second artist
					$new_artist_metro = substr($artist_metro, $artist_and_location + 4);
					$url = "http://www.metrolyrics.com/$metrotitle-lyrics-$new_artist_metro.html";
					if($first_artist_url != $url){
						$lyric_string = fetch_lyrics($url);
					}
				}
				
				if(is_lyric_page($lyric_string) == false && $tried_together_withdash == false){
					//together with a dash
					// VLC: was 1
					$new_artist_metro = substr($artist_metro, 0, $artist_and_location - 1) . "-" . substr($artist_metro, $artist_and_location + 3);
					$url = "http://www.metrolyrics.com/$metrotitle-lyrics-$new_artist_metro.html";
					$lyric_string = fetch_lyrics($url);
				}
				if(is_lyric_page($lyric_string) == false){
					//try again without and between artists
					// VLC: 1 -> 0
					// VLc: 2 -> 1
					$new_artist_metro = substr($artist_metro, 0, $artist_and_location - 1) . substr($artist_metro, $artist_and_location + 3); // VLC: 4 -> 3
					$url = "http://www.metrolyrics.com/$metrotitle-lyrics-$new_artist_metro.html";
					debug_print("try again without and between artists: " . $url);
					$lyric_string = fetch_lyrics($url);
				}

				if(is_lyric_page($lyric_string) == false && $tried_together_and == false){
					//together
					$lyric_string = fetch_lyrics($metrourl);
				}
			}
		}else{
			debug_print("MetroLyrics (normal): $metrourl");
			$lyric_string = fetch_lyrics($metrourl);
			
			if(is_lyric_page($lyric_string) == false && strpos($artist_metro, 'the-') > -1){
				$new_artist_metro = str_replace('the-','', $artist_metro);
				$url="http://www.metrolyrics.com/$metrotitle-lyrics-$new_artist_metro.html";
				$lyric_string = fetch_lyrics($url);
				debug_print("MetroLyrics (normal): match THE at $url");
			}
		}
	}
	//best coverage, but a bit slow to put first
	/*if(is_lyric_page($lyric_string) == false){
		$url = "http://sonichits.com/video/$artist_x/" . str_replace("-", "_", $original_title);
		$lyric_string = fetch_lyrics($url);
	}*/
	
	$artist_and_location = strpos($artist_x, "_and_");
	if($artist_and_location){
		$artist_and_location = strpos($artist_x, "and", $artist_and_location - 2);
	}
	
	if($artist_and_location > -1){
		if(is_lyric_page($lyric_string) == false){
			$new_artist_x = substr($artist_x, 0, $artist_and_location - 1) . substr($artist_x, $artist_and_location + 3); // . "_" 
			$url = "http://www.lyricsmode.com/lyrics/".substr($new_artist_x, 0, 1)."/".$new_artist_x."/".$title_x.".html";
			debug_print("lyricsmode1: $url");
			$lyric_string = fetch_lyrics($url);
		}
	}
	
	if(is_lyric_page($lyric_string) == false){		
		if($artist_and_location > -1){
			$artist_and_location = strpos($artist_x, "and", $artist_and_location - 2);
			//try again without and (first artist)
			$new_artist_x = substr($artist_x, 0, $artist_and_location - 1);
			$first_artist_name = $new_artist_x;
			$url = "http://www.lyricsmode.com/lyrics/".substr($new_artist_x, 0, 1)."/".$new_artist_x."/".$title_x.".html";
			debug_print("lyricsmode2: $url");
			$lyric_string = fetch_lyrics($url);
		}
		if(is_lyric_page($lyric_string) == false){
			$url = "http://www.lyricsmode.com/lyrics/".substr($artist_x, 0, 1)."/".$artist_x."/".$title_x.".html";
			debug_print("lyricsmode3: $url");
			$lyric_string = fetch_lyrics($url);
		}
	}
	
	if(is_lyric_page($lyric_string) == false){
		$artist_and_location = strpos($artist_x, "_and_");
		if($artist_and_location > -1){
			$artist_and_location = strpos($artist_x, "and", $artist_and_location - 2);
			//try again without and (second artist)
			$new_artist_x = substr($artist_x, $artist_and_location + 4); //length of and + 1
			if($first_artist_name == $new_artist_x){
				// try again without and (before: do nothing)
				// Womack & Womack - MPB
				$new_artist_x = substr($artist_x, 0, $artist_and_location - 1) . "_" . substr($artist_x, $artist_and_location + 3);
				$url = "http://www.lyricsmode.com/lyrics/".substr($new_artist_x, 0, 1)."/".$new_artist_x."/".$title_x.".html";
				debug_print("lyricsmode4: $url");
				$lyric_string = fetch_lyrics($url);
			}else{
				$url = "http://www.lyricsmode.com/lyrics/".substr($new_artist_x, 0, 1)."/".$new_artist_x."/".$title_x.".html";
				debug_print("lyricsmode5: $url");
				$lyric_string = fetch_lyrics($url);
			}
		}
	}
	
	if(is_lyric_page($lyric_string) == false){
		$title_dec_loc = strpos($title_x, "twee");
		if($title_dec_loc > -1){
			//try again, replacing the full word with a decimal
			$new_title_x = str_replace("twee", "2", $title_x);
			$url = "http://www.lyricsmode.com/lyrics/".substr($artist_x, 0,1)."/".$artist_x."/".$new_title_x.".html";
			debug_print("lyricsmode6: $url");
			$lyric_string = fetch_lyrics($url);
		}
	}
	
	if(is_lyric_page($lyric_string) == false){
		$artist_the_location = strpos($artist_x, "the");
		if($artist_the_location > -1){
			debug_print("lyricsmode: try again without the");
			$new_artist_x = str_replace("the_", "", $artist_x);
			$url = "http://www.lyricsmode.com/lyrics/".substr($new_artist_x, 0, 1)."/".$new_artist_x."/".$title_x.".html";
			debug_print("lyricsmode7: $url");
			$lyric_string = fetch_lyrics($url);
		}
	}
	
	
	if(is_lyric_page($lyric_string) && $source=="LyricsMode"){
		//LyricsMode has some problems with encoding, fix these before showing
		$lyric_string = str_replace("ґ", "%'", $lyric_string); //replace ґt with 't
		$lyric_string = str_replace("й", "é", $lyric_string); //French
		$lyric_string = str_replace("к", "ê", $lyric_string); //French
		$lyric_string = str_replace("и", "è", $lyric_string); //French
		$lyric_string = str_replace("ы", "û", $lyric_string); //French
		$lyric_string = str_replace("њ", "œ", $lyric_string); //French		
		$lyric_string = str_replace("д", "ä", $lyric_string); //German	
			
		//cleanup first lines
		$lower_artist_name = strtolower($artist); // (artist:get_text()) TODO: verify if this code block works
		$lower_title = strtolower($title); //title:get_text())
		$lower_lyric_string = strtolower($lyric_string);
		$pos_author = strpos($lower_lyric_string, $lower_artist_name);
		$pos_title = strpos($lower_lyric_string, $lower_title);
		$pos_newline = strpos($lower_lyric_string, "\n");
		
		// TODO: verify if this works
		//check if the first line is empty
		if($pos_newline){
			if($pos_newline == 1){
				$lyric_string = substr($lyric_string, $pos_newline+1); //remove the first line (=empty line)
			}
		}
		
		if($pos_author){
			//remove author name from first line(s)
			if($pos_author < $pos_newline){
				//contains
				$lyric_string = substr($lyric_string, $pos_newline+1); //remove the first line (=artist name)
			}
		}
		
		if($pos_title){
			//remove title from first line(s)
			//$pos_newline = strpos($lyric_string, "\n", $pos_newline+1);
			if($pos_title < $pos_newline){
				//contains
				$lyric_string = substr($lyric_string, $pos_newline+1); //remove the first line (=title)
			}
		}
		
		if($pos_newline){
			$pos_new_newline = strpos($lyric_string, "\n", $pos_newline+1);

			if($pos_new_newline == $pos_newline+1){
				//next line is empty
				$lyric_string = substr($lyric_string, $pos_new_newline+1);
			}
		}
	}	
	
	if(is_lyric_page($lyric_string) == false){
		$url = "http://www.golyr.de/" . str_replace("_","-", $artist_x) . "/songtext-" . str_replace("_", "-", $title_x);
		debug_print("golyr.de: $url");
		$lyric_string = fetch_lyrics($url);
	}
	
	if(is_lyric_page($lyric_string) == false){
		$artist_az = str_replace("_", "", $artist_x);
		$title_az = str_replace("_", "", $title_x);
		
		$url = "http://www.azlyrics.com/lyrics/" . $artist_az . "/" . $title_az .".html";
		debug_print("azlyrics.com: $url");
		$lyric_string = fetch_lyrics($url);
		
		if(is_lyric_page($lyric_string) == false && strpos($artist_az, 'the') == 1){
			$new_artist_az = str_replace('the-','', $artist_az);
			$url = "http://www.azlyrics.com/lyrics/" . substr($new_artist_az, 3) . "/" . $title_az .".html";
			$lyric_string = fetch_lyrics($url);
			debug_print("azlyrics.com (THE): $url");
		}
		if(is_lyric_page($lyric_string) == false && strpos($artist_az, 'and') > 0){
			$andlocation = strpos($artist_az, "and");
			$new_artist_az = substr($artist_az, 0, $andlocation); //     preg_replace('and.*', '', $artist_az);
			$url = "http://www.azlyrics.com/lyrics/" . $new_artist_az . "/" . $title_az .".html";
			$lyric_string = fetch_lyrics($url);
			debug_print("azlyrics.com (without AND part): $url");
		}
	}
	
	if(is_lyric_page($lyric_string) == false){
		$url = "http://www.lyrics.com/".str_replace("_", "-", $title_x)."-lyrics-".str_replace("_", "-", $artist_x).".html";
		debug_print("lyrics.com: $url");
		$lyric_string = fetch_lyrics($url);
	}
	
	
	if(is_lyric_page($lyric_string) == false){
		$url = "http://www.lyricsmania.com/" . str_replace("-", "_", $title_x)."_lyrics_$artist_x.html";
		debug_print("lyricsmania.com: $url");
		$lyric_string = fetch_lyrics($url);
	}
	
	$title_x_normal = str_replace("_", "-", $title_x);
	if(is_lyric_page($lyric_string) == false){
		//http://songteksten.net/search/title.html?q=climbing+to+the+top&type=title
		$url = "http://songteksten.net/search/title.html?q=" . str_replace("-", "+", $title_x)."&amp;type=title";
		debug_print("songteksten.net: $url");
		$data = file_get_contents($url);
		
		$posurl = strrpos($data, "http://songteksten.net/lyric");
		$middlelinkpos = strpos($data, '"', $posurl);
		
		$url = substr($data, $posurl, $middlelinkpos-$posurl);
		debug_print($url . " with title " . $title_x_normal);
		
		if(strpos($url, $title_x_normal) == true){
			$lyric_string = fetch_lyrics($url);
		}
	}
	
	
	if(is_lyric_page($lyric_string) == false){
		$source = "";
	}
		
	return clean_lyrics($lyric_string);
}

function is_lyric_page($lyric_string){
	if($lyric_string==""){
		return false;
	}
	
	$licensing = "We are not in a position to display these lyrics due to licensing restrictions. Sorry for the inconvenience.";
	if(strpos($lyric_string, $licensing) > -1){
		debug_print($licensing);
		return false;
	}
	$dailylimit = "You've reached the daily limit of 10 videos. Log in to watch more";
	if(strpos($lyric_string, $dailylimit) > -1){
		debug_print($dailylimit);
		return false;
	}
	$dailylimit = "Daily limit reached for Sonic Hits";
	if(strpos($lyric_string, $dailylimit) > -1){
		debug_print_importance($dailylimit, "extrainfo");
		return false;
	}
	
	if(strpos($lyric_string, "Select your carrier...") > -1){
		debug_print("Low quality lyrics");
		return false;
	}
	
	if(strpos($lyric_string, "No lyrics found for this song") > -1){
		debug_print("Data dump: $lyric_string");
		return false;
	}
	
	if(strlen($lyric_string) < 40){
		debug_print("(info) Partial page / not a valid lyrics page");
		debug_print("Data dump: $lyric_string");
		return false;
	}
	
	return true;
}

function fetch_lyrics($url){
	global $source;
	
	$metro_pos = strpos($url, 'metrolyrics');
	$lyricsmania = strpos($url, 'lyricsmania');
	$lyricscom = strpos($url, 'lyrics.com');
	$sonichits = strpos($url, 'sonichits');
	$azlyrics = strpos($url, 'azlyrics');
	$lyricsmode = strpos($url, 'lyricsmode');
	$musixmatch = strpos($url, 'musixmatch');
	$golyr = strpos($url, 'golyr');
	$songteksten = strpos($url, 'songteksten.net');

	$mode = get_parameter("mode");
	if($mode != "debug")
		error_reporting(E_ERROR | E_PARSE);
	$data = file_get_contents($url);
	
	//$data = string.gsub(data, "&#(%d+)", string.char);
	
	if($metro_pos){
		//MetroLyrics
		$source="MetroLyrics";
		
		if($data == "") return "";
		$html = str_get_html($data);
		
		//$lyrics_body_text = $html->find('div[id=lyrics-body-text]');
	
		$verses = $html->find('p[class=verse]');;

		$metrolyrics_text = "";
		foreach ($verses as &$verse) {
			$metrolyrics_text = $metrolyrics_text . str_replace("<br>", "\n", $verse);
		}

		return str_replace("\n ", "\n", $metrolyrics_text); 
		//return $verses;
	}
	// LyricsMania is very slow
	if($lyricsmania){
		$source="LyricsMania";
		$strong_text = "</strong>";
		$data = str_replace("\t", "", $data);
		$data = str_replace("<div class=\"p402_premium\">\r\n<br>", "", $data);	
		$lyrics_to = strpos($data, "Lyrics to");
		if($lyrics_to == false){
			return "";
		}
		$a = strpos($data, $strong_text, $lyrics_to);
		if($a == false){
			return "";
		}
		
		$b = strpos($data, "</div>", $a + strlen($strong_text));
		$lyricsresult = substr($data, $a+strlen($strong_text),$b-2-$a);
		$lyricsresult = str_replace('</div>', '', $lyricsresult);
		$lyricsresult = str_replace('<br>', "\n", $lyricsresult);
		$lyricsresult = str_replace('<br> <br> <br>', '', $lyricsresult);
		$lyricsresult = str_replace('<div class="fb-quotable">', '', $lyricsresult);
		
		return $lyricsresult;
	}
	if($lyricsmode){
		$source="LyricsMode";
		$identifier = '<p id="lyrics_text" class="ui-annotatable">';
		$a = strpos($data, $identifier);
		if($a == false){
			return "";
		}
		$b = strpos($data, "</p>", $a) + 4; // +4 includes </p>, which is a workaround for a missing letter (http://www.lyricsmode.com/lyrics/c/céline_dion/think_twice.html) (is compensated for by converting unclean html to clean text to clean html)
		$lengthofidentifier = strlen($identifier);
		$lyricsmode_result = substr($data, $a, $b-$a);
		
		return $lyricsmode_result;
	}
	if($sonichits){
		$source="Sonic Hits";
		// TODO: verify if this works
		
		// You've reached the daily limit of 10 videos. Log in to watch more
		$dailylimit = strpos($data, "You've reached the daily limit of 10 videos. Log in to watch more");
		if($dailylimit){
			return "Daily limit reached for Sonic Hits";
		}
		
		$a = strpos($data, '<div id="lyrics"');
		//$a = strpos($data, 'Lyrics: ');  // Lyrics: 
		if($a == false){
			return "";
		}
		/*if($a){
			return substr($data, $a, 1000);
		}*/
		
		$position = strpos($data, '<br><br>', $a);
		if($position == false){
			return "";
		}
		
		$contributedby = strpos($data, "Contributed by", $a);
		$lyricsc = strpos($data, "Lyrics", $a);
		
		if($contributedby == false && $lyricsc == false){
			return "";
		}
		if($contributedby){
			echo "contributed by";
			$b = strpos($data, "</div>", $a);
		}	
		if($lyricsc){
			echo "lyricscopy";
			$b = strpos($data, "<br>", $lyricsc-10);
		}

		if($b == false){
			return "";
		}

		return substr($data, $position+strlen("<br><br>"),$b-1-$position);
	}
	
	if($golyr){
		$source="Golyr";
		$a = strpos($data, '<div id="lyrics"');
		if($a == false){
			return "";
		}
		$endofstring = 'h2';
		$position = strpos($data, $endofstring, $a);
		if($position == false){
			return "";
		}
		
		$position = strpos($data, "h2", $position+1);
		if($position == false){
			return "";
		}

		$b = strpos($data, "div", $position);

		if($b == false){
			return "";
		}

		return substr($data, $position+4,$b-1-($position+5));
	}
	
	if($azlyrics){
		$source="AZ Lyrics";
		$a = strpos($data, 'ringtone');

		if($a == false){
			return "";
		}
		$endofstring = '<div>';
		$position = strpos($data, $endofstring, $a);
		if($position == false){
			return "";
		}

		$b = strpos($data, "</div>", $position);

		return substr($data, $position+5,$b-1-($position+5));
	}
	if($songteksten){
		$source = "Songteksten.net";
		$a = strpos($data, 'body_right');

		if($a == false){
			return "";
		}
		$a = strpos($data, '</h1>');
		if($a == false){
			return "";
		}
		
		$endofstring = 'div';
		$position = strpos($data, $endofstring, $a);
		if($position == false){
			return "";
		}
		return substr($data, $a + 5, $position-($a+6));
	}
	
	if($lyricscom){
		$source="Lyrics.com";
		$a = strpos($data, 'itemprop="description');
		if($a == false){
			return "";
		}
		$b = strpos($data, "---", $a);
		return substr($data, $a+strlen('itemprop="description">'),$b-($a+strlen('itemprop="description">')));
	}
	
	return "";
}

function clean_lyrics($lyrics_text_input){
	$lyrics_text_input = trim_all($lyrics_text_input, "\\x00-\\x09");
	$lyrics_text_input = trim_all($lyrics_text_input, "\\x0B-\\x0C");
	$lyrics_text_input = trim_all($lyrics_text_input, "\\x0E-\\x1F");
	
	$lyrics_text_input = trim_all($lyrics_text_input, "\\x0D\\x0A", "\n"); // \r\n
	
	return $lyrics_text_input;
}

// http://pageconfig.com/post/remove-undesired-characters-with-trim_all-php
function trim_all( $str , $what = NULL , $with = ' ' )
{
    if( $what === NULL )
    {
         //  Character      Decimal      Use
         //  "\0"            0           Null Character
         //  "\t"            9           Tab
         //  "\n"           10           New line
         //  "\x0B"         11           Vertical Tab
         //  "\r"           13           New Line in Mac
         //  " "            32           Space
          
         //$what   = "\\x00-\\x20";    //all white-spaces and control chars
         
         $what = "\\x09";
    }
       
    return trim( preg_replace( "/[".$what."]+/" , $with , $str ) , $what );
}
