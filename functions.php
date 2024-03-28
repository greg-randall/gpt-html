<?php

// Function to calculate the cost of using OpenAI's models
function open_ai_cost($response){
    // Define the cost per token for each model
    $open_ai_cost = array( 
        'gpt-4-0125-preview' =>           array( 'input' => .01/1000, 'output' => 0.03/1000),
        'gpt-4-1106-preview' =>           array( 'input' => .01/1000, 'output' => 0.03/1000),
        'gpt-4-1106-vision-preview' =>    array( 'input' => .01/1000, 'output' => 0.03/1000),
        'gpt-4' =>                        array( 'input' => .03/1000, 'output' => 0.06/1000),
        'gpt-4-32k' =>                    array( 'input' => .06/1000, 'output' => 0.12/1000),
        'gpt-3.5-turbo-0125' =>           array( 'input' => .0005/1000, 'output' => 0.0015/1000),
        'gpt-3.5-turbo-instruct' =>       array( 'input' => .0015/1000, 'output' => 0.0020/1000),
        'gpt-3.5-turbo-1106' =>           array( 'input' => .0010/1000, 'output' => 0.0020/1000),
        'gpt-3.5-turbo-0613' =>           array( 'input' => .0015/1000, 'output' => 0.0020/1000),
        'gpt-3.5-turbo-16k-0613' =>       array( 'input' => .0030/1000, 'output' => 0.0040/1000),
        'gpt-3.5-turbo-0301' =>           array( 'input' => .0015/1000, 'output' => 0.0020/1000),
        );

    // Get the model used from the response
    $model = $response->model;

    // If the model used is not in the cost array, default to 'gpt-4-32k' since it's the most expensive
    if(!array_key_exists($model, $open_ai_cost)){
        $model = 'gpt-4-32k';
    }

    // Get the input and output costs for the model used
    $input = $open_ai_cost[$model]['input'];
    $output = $open_ai_cost[$model]['output'];

    // Get the number of input and output tokens from the response
    $prompt_tokens = $response->usage->prompt_tokens;
    $completion_tokens = $response->usage->completion_tokens;

    // Calculate the total cost
    $cost = ($prompt_tokens * $input) + ($completion_tokens * $output);

    // Return the total cost
    return($cost);
}



function open_ai_setup($open_ai_key){
    global $openaiClient;
    $openaiClient = \Tectalic\OpenAi\Manager::build(
        new \GuzzleHttp\Client(),
        new \Tectalic\OpenAi\Authentication($open_ai_key)
    );
}


function open_ai_call($prompt, $model, $temperature, $open_ai_key){
    global $openaiClient;
  
    /** @var \Tectalic\OpenAi\Models\ChatCompletions\CreateResponse $response */
    $response = $openaiClient->chatCompletions()->create(
        new \Tectalic\OpenAi\Models\ChatCompletions\CreateRequest([
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                    'temperature' => $temperature,
                ],
            ],
        ])
    )->toModel();

    
    return($response);
}



// Cleaner that I wrote a while back for a different project, packaged it up into a function for this project

/*
Note that the cleaner sends the html to DirtyMarkup for formatting.
 
 
Example input:	
  <div class=WordSection1>	
  <p class=MsoNormal align=center style='text-align:center'><span	
  style='font-size:16.0pt;line-height:107%;font-family:"Abadi Extra Light",sans-serif'>Test	
  Clean<o:p></o:p></span></p>	
  <p class=MsoNormal><span style='font-size:12.0pt;line-height:107%;mso-bidi-font-family:	
  Calibri;mso-bidi-theme-font:minor-latin'>Test Paragraph, qwerty <span	
  class=SpellE>qwerty</span> <span class=SpellE>qwerty</span> <span class=SpellE>qwerty</span>	
  <span class=SpellE>qwerty</span> <span class=SpellE>qwerty</span> <span	
  class=SpellE>qwerty</span> <span class=SpellE>qwerty</span> <span class=SpellE>qwerty</span>.<o:p></o:p></span></p>	
  <p class=MsoNormal><o:p>&nbsp;</o:p></p>	
  <p class=MsoNormal><o:p>&nbsp;</o:p></p>	
  </div>	
Example Output:	
  <p>Test Clean</p>	
  <p>Test Paragraph, qwerty qwerty qwerty qwerty qwerty qwerty qwerty qwerty qwerty.</p> 
*/
function clean_html($html){
    /* configuration */
    $allowed_attribute = [
        // attributes to keep on the html i.e. <a href="www.asdf.com">
        "content",
        "http-equiv",
        "src",
        "href",
        "src",
        "alt",
        "colspan",
        "rowspan",
        "id",
    ];
    $tags_to_remove = [
        //tags to remove
        "div",
        "span",
        "figure",
        "font",
    ];

    $remove_fancy_quotes = true; // changes  ‘ ’   “  ” and some similar stuff to  ' and "
    $remove_fancy_spaces = true; // changes &nbsp; &thinsp; etc to a regular space.
    $remove_fancy_dashes = true; // changes EM dashes, EN dashes, etc to regular dashes
    $remove_empy_td = false; // keeps or removes empty table cells
    $convert_chars_to_entities = true; // converts html entities to their character equivalent i.e. & to &amp;
    $run_wordpress_paragraph_tag_adder = true;

    /* end configuration */



  
    error_reporting(E_ERROR | E_PARSE); //DOMDocument throws a fair number of errors, we'll quiet them down

    

    //$html = $_POST["input"];

    $html = removeHtmlComments($html);

    if (substr_count($html, "<html") > 0) {
        //determine if the input is a full html document or not, gets passed to the dirtymarkup cleaning below
        $html_fragment = "full";
    } else {
        $html_fragment = "fragment";
    }
 
    if($run_wordpress_paragraph_tag_adder){
        $html = wpautop($html);
    }

    if($convert_chars_to_entities){
       //encodes charecters into html entities, but only in the text
       $doc = new DOMDocument();
       $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
       $html = $doc->saveHTML();
    }

    //this is done prior to the domdocument cleaning, we remove empty tags i.e. '<p></p>' or '<p> </p>', but will not remove '<p>&nbsp;</p>', this solves that issue
    //note we won't remove tags like '<a href="www.asdf.com"></a>'
    if ($remove_fancy_spaces) {
        $html = str_ireplace(
            [   " ",
                "&#8192;",
                " ",
                "&#8193;",
                " ",
                "&#8194;",
                "&ensp;",
                " ",
                "&#8195;",
                "&emsp;",
                " ",
                "&#8196;",
                " ",
                "&#8197;",
                " ",
                "&#8198;",
                " ",
                "&#8199;",
                " ",
                "&#8200;",
                " ",
                "&#8201;",
                "&thinsp;",
                " ",
                "&#8202;",
                "​",
                "&#8203;",
                "&#160;",
                "&nbsp;",],
            " ",
            $html
        ); //change spaces to a regular spaces.
        $html = preg_replace("/\s+/", " ", $html); // catches any extra odd stragglers or if the previous step put two spaces next to eachother collapses them.
    }

    $html = beautify_html($html); //run the html cleaner before processing-- it fixes some html errors that won't make the cleaning as effective
    
    // this is a bit kludgy, but i have a function below that removes empty tags, but you probably don't want empty table tags removed
    // i used the string '~~..~~' since it's unlikely to appear in actual text
    if (!$remove_empy_td) {
        $html = preg_replace("/\> ?<\/td>/", ">~~..~~</td>", $html);
        $html = preg_replace("/\> ?<\/th>/", ">~~..~~</th>", $html);
    }

    $dom = new DOMDocument();
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $elements = $xpath->query("//*");
    foreach ($elements as $element) {
        //loops through all the elements
        for ($i = $element->attributes->length; --$i >= 0; ) {
            //loops through all the attributes backwards (which is required apparently)
            $name = $element->attributes->item($i)->name;
            if (!in_array($name, $allowed_attribute)) {
                //if the attribute doesn't match one of the ones we're saving, we delete it.
                $element->removeAttribute($name);
            }
        }
    }

    //generates an appropriate list of tags to remove for the xpath query
    for ($i = 0; $i < count($tags_to_remove); $i++) {
        $tags_to_remove[$i] = "//$tags_to_remove[$i]";
    }
    $tags_to_remove = implode(" | ", $tags_to_remove);

    //delete all div & span tags
    foreach ($xpath->query($tags_to_remove) as $remove) {
        // Move all span tag content to its parent node just before it.
        while ($remove->hasChildNodes()) {
            $child = $remove->removeChild($remove->firstChild);
            $remove->parentNode->insertBefore($child, $remove);
        }
        $remove->parentNode->removeChild($remove);
    }

    //removes empty tags
    //not(*) does not have children elements
    //not(@*) does not have attributes
    //text()[normalize-space()] nodes that include whitespace text
    while (
        ($node_list = $xpath->query(
            "//*[not(*) and not(@*) and not(text()[normalize-space()])]"
        )) &&
        $node_list->length
    ) {
        foreach ($node_list as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    // Query all comment nodes
    $commentNodes = $xpath->query('//comment()');

    // Iterate over each comment node
    foreach ($commentNodes as $commentNode) {
        // Check if the comment contains "wp:"
        if (strpos($commentNode->nodeValue, 'wp:') !== false) {
            // Remove the comment node
            $commentNode->parentNode->removeChild($commentNode);
        }
    }

    $clean = $dom->saveHTML();

    if ($remove_fancy_quotes) {
        // sometimes apostorphies change into &iuml;&iquest;&frac12;
        $clean = str_ireplace(
            [   "&iuml;&iquest;&frac12;",
                "&lsquo;",
                "&rsquo;",
                "&#8216;",
                "&#8217;",
                "&apos;",
                "&prime;",
                "&#8242;",
                "’",
                "‘",
                "`",],
            "'",
            $clean
        ); //change curly single quote to regular
        $clean = str_ireplace(
            [   "&ldquo;",
                "&rdquo;",
                "&#8220;",
                "&#8221;",
                "&quot;",
                "&Prime;",
                "&#8243;",
                "”",
                "“",
                "''",],
            '"',
            $clean
        ); //change curly double quotes to regular
    }
    if ($remove_fancy_dashes) {
        $clean = str_ireplace(
            [  "&#8208;",
                "‑",
                "&#8209;",
                "‒",
                "&#8210;",
                "–",
                "&#8211;",
                "&ndash;",
                "—",
                "&#8212;",
                "&mdash;",
                "―",
                "&#8213;",],
            "-",
            $clean
        ); //change fancy dashes to a regular hyphen
    }

    // this removes the placeholder text in empty th/td. this is a bit kludgy, but it works fine.
    if (!$remove_empy_td) {
        $clean = preg_replace("/~~\.\.~~/", "", $clean);
    }


    $clean = preg_replace('/mailto:([^"]*)/', "mailto_$1", $clean); //the markdown converter didn't like mailto links, so swappeed them out for a placeholder in the cleaner functions

    return beautify_html($clean); // run the html formatter again after processing
}

function beautify_html($html, $html_fragment = "fragment")
{
    //return $html;
    $url = "https://www.10bestdesign.com/dirtymarkup/api/html";
    $context = stream_context_create([
        "http" => [
            "method" => "POST",
            "content" => http_build_query([
                "code" => $html,
                "output" => $html_fragment,
            ]),
            "timeout" => 60,
        ],
    ]);
    $resp = file_get_contents($url, false, $context);
    $resp = json_decode($resp, true);
    return array_pop($resp);
}



function removeHtmlComments($html) {
    // Create a new DOMDocument instance
    $dom = new DOMDocument();

    // Load the HTML into the DOMDocument instance
    // Suppress warnings due to invalid HTML
    @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // Create a new DOMXPath instance
    $xpath = new DOMXPath($dom);

    // Find all comment nodes
    $comments = $xpath->query('//comment()');

    // Loop through all comment nodes
    foreach ($comments as $comment) {
        // Remove the comment node from its parent node
        $comment->parentNode->removeChild($comment);
    }

    // Return the HTML without comments
    return $dom->saveHTML();
}

//////////////////////////////////////////////////////////////////////
/////scooped some functions from wordpress to add paragraph tags//////
//////////////////////////////////////////////////////////////////////

//https://developer.wordpress.org/reference/functions/wpautop/
function wpautop( $text, $br = true ) {
	$pre_tags = array();

	if ( trim( $text ) === '' ) {
		return '';
	}

	// Just to make things a little easier, pad the end.
	$text = $text . "\n";

	/*
	 * Pre tags shouldn't be touched by autop.
	 * Replace pre tags with placeholders and bring them back after autop.
	 */
	if ( str_contains( $text, '<pre' ) ) {
		$text_parts = explode( '</pre>', $text );
		$last_part  = array_pop( $text_parts );
		$text       = '';
		$i          = 0;

		foreach ( $text_parts as $text_part ) {
			$start = strpos( $text_part, '<pre' );

			// Malformed HTML?
			if ( false === $start ) {
				$text .= $text_part;
				continue;
			}

			$name              = "<pre wp-pre-tag-$i></pre>";
			$pre_tags[ $name ] = substr( $text_part, $start ) . '</pre>';

			$text .= substr( $text_part, 0, $start ) . $name;
			$i++;
		}

		$text .= $last_part;
	}
	// Change multiple <br>'s into two line breaks, which will turn into paragraphs.
	$text = preg_replace( '|<br\s*/?>\s*<br\s*/?>|', "\n\n", $text );

	$allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';

	// Add a double line break above block-level opening tags.
	$text = preg_replace( '!(<' . $allblocks . '[\s/>])!', "\n\n$1", $text );

	// Add a double line break below block-level closing tags.
	$text = preg_replace( '!(</' . $allblocks . '>)!', "$1\n\n", $text );

	// Add a double line break after hr tags, which are self closing.
	$text = preg_replace( '!(<hr\s*?/?>)!', "$1\n\n", $text );

	// Standardize newline characters to "\n".
	$text = str_replace( array( "\r\n", "\r" ), "\n", $text );

	// Find newlines in all elements and add placeholders.
	$text = wp_replace_in_html_tags( $text, array( "\n" => ' <!-- wpnl --> ' ) );

	// Collapse line breaks before and after <option> elements so they don't get autop'd.
	if ( str_contains( $text, '<option' ) ) {
		$text = preg_replace( '|\s*<option|', '<option', $text );
		$text = preg_replace( '|</option>\s*|', '</option>', $text );
	}

	/*
	 * Collapse line breaks inside <object> elements, before <param> and <embed> elements
	 * so they don't get autop'd.
	 */
	if ( str_contains( $text, '</object>' ) ) {
		$text = preg_replace( '|(<object[^>]*>)\s*|', '$1', $text );
		$text = preg_replace( '|\s*</object>|', '</object>', $text );
		$text = preg_replace( '%\s*(</?(?:param|embed)[^>]*>)\s*%', '$1', $text );
	}

	/*
	 * Collapse line breaks inside <audio> and <video> elements,
	 * before and after <source> and <track> elements.
	 */
	if ( str_contains( $text, '<source' ) || str_contains( $text, '<track' ) ) {
		$text = preg_replace( '%([<\[](?:audio|video)[^>\]]*[>\]])\s*%', '$1', $text );
		$text = preg_replace( '%\s*([<\[]/(?:audio|video)[>\]])%', '$1', $text );
		$text = preg_replace( '%\s*(<(?:source|track)[^>]*>)\s*%', '$1', $text );
	}

	// Collapse line breaks before and after <figcaption> elements.
	if ( str_contains( $text, '<figcaption' ) ) {
		$text = preg_replace( '|\s*(<figcaption[^>]*>)|', '$1', $text );
		$text = preg_replace( '|</figcaption>\s*|', '</figcaption>', $text );
	}

	// Remove more than two contiguous line breaks.
	$text = preg_replace( "/\n\n+/", "\n\n", $text );

	// Split up the contents into an array of strings, separated by double line breaks.
	$paragraphs = preg_split( '/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY );

	// Reset $text prior to rebuilding.
	$text = '';

	// Rebuild the content as a string, wrapping every bit with a <p>.
	foreach ( $paragraphs as $paragraph ) {
		$text .= '<p>' . trim( $paragraph, "\n" ) . "</p>\n";
	}

	// Under certain strange conditions it could create a P of entirely whitespace.
	$text = preg_replace( '|<p>\s*</p>|', '', $text );

	// Add a closing <p> inside <div>, <address>, or <form> tag if missing.
	$text = preg_replace( '!<p>([^<]+)</(div|address|form)>!', '<p>$1</p></$2>', $text );

	// If an opening or closing block element tag is wrapped in a <p>, unwrap it.
	$text = preg_replace( '!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', '$1', $text );

	// In some cases <li> may get wrapped in <p>, fix them.
	$text = preg_replace( '|<p>(<li.+?)</p>|', '$1', $text );

	// If a <blockquote> is wrapped with a <p>, move it inside the <blockquote>.
	$text = preg_replace( '|<p><blockquote([^>]*)>|i', '<blockquote$1><p>', $text );
	$text = str_replace( '</blockquote></p>', '</p></blockquote>', $text );

	// If an opening or closing block element tag is preceded by an opening <p> tag, remove it.
	$text = preg_replace( '!<p>\s*(</?' . $allblocks . '[^>]*>)!', '$1', $text );

	// If an opening or closing block element tag is followed by a closing <p> tag, remove it.
	$text = preg_replace( '!(</?' . $allblocks . '[^>]*>)\s*</p>!', '$1', $text );

	// Optionally insert line breaks.
	if ( $br ) {
		// Replace newlines that shouldn't be touched with a placeholder.
		$text = preg_replace_callback( '/<(script|style|svg|math).*?<\/\\1>/s', 'autop_newline_preservation_helper', $text );

		// Normalize <br>
		$text = str_replace( array( '<br>', '<br/>' ), '<br />', $text );

		// Replace any new line characters that aren't preceded by a <br /> with a <br />.
		$text = preg_replace( '|(?<!<br />)\s*\n|', "<br />\n", $text );

		// Replace newline placeholders with newlines.
		$text = str_replace( '<WPPreserveNewline />', "\n", $text );
	}

	// If a <br /> tag is after an opening or closing block tag, remove it.
	$text = preg_replace( '!(</?' . $allblocks . '[^>]*>)\s*<br />!', '$1', $text );

	// If a <br /> tag is before a subset of opening or closing block tags, remove it.
	$text = preg_replace( '!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $text );
	$text = preg_replace( "|\n</p>$|", '</p>', $text );

	// Replace placeholder <pre> tags with their original content.
	if ( ! empty( $pre_tags ) ) {
		$text = str_replace( array_keys( $pre_tags ), array_values( $pre_tags ), $text );
	}

	// Restore newlines in all elements.
	if ( str_contains( $text, '<!-- wpnl -->' ) ) {
		$text = str_replace( array( ' <!-- wpnl --> ', '<!-- wpnl -->' ), "\n", $text );
	}

	return $text;
}

//https://developer.wordpress.org/reference/functions/wp_replace_in_html_tags/
function wp_replace_in_html_tags( $haystack, $replace_pairs ) {
	// Find all elements.
	$textarr = wp_html_split( $haystack );
	$changed = false;

	// Optimize when searching for one item.
	if ( 1 === count( $replace_pairs ) ) {
		// Extract $needle and $replace.
		foreach ( $replace_pairs as $needle => $replace ) {
		}

		// Loop through delimiters (elements) only.
		for ( $i = 1, $c = count( $textarr ); $i < $c; $i += 2 ) {
			if ( str_contains( $textarr[ $i ], $needle ) ) {
				$textarr[ $i ] = str_replace( $needle, $replace, $textarr[ $i ] );
				$changed       = true;
			}
		}
	} else {
		// Extract all $needles.
		$needles = array_keys( $replace_pairs );

		// Loop through delimiters (elements) only.
		for ( $i = 1, $c = count( $textarr ); $i < $c; $i += 2 ) {
			foreach ( $needles as $needle ) {
				if ( str_contains( $textarr[ $i ], $needle ) ) {
					$textarr[ $i ] = strtr( $textarr[ $i ], $replace_pairs );
					$changed       = true;
					// After one strtr() break out of the foreach loop and look at next element.
					break;
				}
			}
		}
	}

	if ( $changed ) {
		$haystack = implode( $textarr );
	}

	return $haystack;
}

//https://developer.wordpress.org/reference/functions/wp_html_split/
function wp_html_split( $input ) {
	return preg_split( get_html_split_regex(), $input, -1, PREG_SPLIT_DELIM_CAPTURE );
}

//https://developer.wordpress.org/reference/functions/get_html_split_regex/
function get_html_split_regex() {
	static $regex;

	if ( ! isset( $regex ) ) {
		// phpcs:disable Squiz.Strings.ConcatenationSpacing.PaddingFound -- don't remove regex indentation
		$comments =
			'!'             // Start of comment, after the <.
			. '(?:'         // Unroll the loop: Consume everything until --> is found.
			.     '-(?!->)' // Dash not followed by end of comment.
			.     '[^\-]*+' // Consume non-dashes.
			. ')*+'         // Loop possessively.
			. '(?:-->)?';   // End of comment. If not found, match all input.

		$cdata =
			'!\[CDATA\['    // Start of comment, after the <.
			. '[^\]]*+'     // Consume non-].
			. '(?:'         // Unroll the loop: Consume everything until ]]> is found.
			.     '](?!]>)' // One ] not followed by end of comment.
			.     '[^\]]*+' // Consume non-].
			. ')*+'         // Loop possessively.
			. '(?:]]>)?';   // End of comment. If not found, match all input.

		$escaped =
			'(?='             // Is the element escaped?
			.    '!--'
			. '|'
			.    '!\[CDATA\['
			. ')'
			. '(?(?=!-)'      // If yes, which type?
			.     $comments
			. '|'
			.     $cdata
			. ')';

		$regex =
			'/('                // Capture the entire match.
			.     '<'           // Find start of element.
			.     '(?'          // Conditional expression follows.
			.         $escaped  // Find end of escaped element.
			.     '|'           // ...else...
			.         '[^>]*>?' // Find end of normal element.
			.     ')'
			. ')/';
		// phpcs:enable
	}

	return $regex;
}

//https://developer.wordpress.org/reference/functions/_autop_newline_preservation_helper/
function autop_newline_preservation_helper( $matches ) {
	return str_replace( "\n", '<WPPreserveNewline />', $matches[0] );
}
?>