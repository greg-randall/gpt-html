<?php
    // remeber to run:
    // composer install

    require 'vendor/autoload.php';
    include_once 'functions.php';
    include_once 'keys.php';

    use Jfcherng\Diff\DiffHelper;
    use Jfcherng\Diff\Factory\RendererFactory;
    use League\HTMLToMarkdown\HtmlConverter;
    use DaveChild\TextStatistics as TS;

    if ( !isset( $_POST[ "input" ] ) ) { //check to see if there's content to clean if not, prompt for it 
?>
        <h2>Clean:</h2>
        <form action="index.php" method="post">
        <textarea name="input" rows="40" cols="100"></textarea><br><br>
        <input type="submit">
        </form>
        <?php
        exit( );

    } else {
        //get & clean the input
        $html           = $_POST[ "input" ];
        $html_formatted = clean_html( $html );


        //convert the input to markdown
        $converter      = new HtmlConverter();
        $markdown       = $converter->convert( $html_formatted );
        $markdown       = str_ireplace( "mailto_", "mailto:", $markdown ); //the markdown converter didn't like mailto links, so swappeed them out for a placeholder in the cleaner functions, swapping back here


        //send the markdown to the AI to edit
        $prompt         = "Read the following Markdown make edits to increase clairty (reduce word length and sentence length generally, without losing meaning), but try to remove redundant information while sounding natural! Don't drop dates, names, or other important information! If something looks like it should be a list, heading, link, etc please make it into a list/heading/actual link/etc (headings must descend in size logically starting from ##). Make sure the output is in Markdown (Don't add '```markdown' or similar)!!!\n\n$markdown"; //set the prompt for the AI

        open_ai_setup( $open_ai_key );
        $open_ai_output       = open_ai_call( $prompt, "gpt-4-1106-preview", 0.25, $open_ai_key );
        $markdown_clean       = $open_ai_output->choices[ 0 ]->message->content;
        $open_ai_cost         = open_ai_cost( $open_ai_output );


        //convert the cleaned markdown back to html
        $parser               = new \cebe\markdown\Markdown();
        $html_formatted_clean = beautify_html( $parser->parse( $markdown_clean ) );


        //compare the two htmls
        $jsonResult           = DiffHelper::calculate( $html_formatted, $html_formatted_clean, 'Json' );
        $htmlRenderer         = RendererFactory::make( 'SideBySide', array(
             'detailLevel' => 'word' 
        ) );
        $result               = $htmlRenderer->renderArray( json_decode( $jsonResult, true ) );


        //generate text statistics
        $textStatistics       = new TS\TextStatistics;
        $before_reading_ease  = $textStatistics->fleschKincaidReadingEase( strip_tags( $html_formatted ) );
        $after_reading_ease   = $textStatistics->fleschKincaidReadingEase( strip_tags( $html_formatted_clean ) );
        $before_wordcount     = str_word_count( $markdown );
        $after_wordcount      = str_word_count( $markdown_clean );


        //output the results
        echo 
        "<div class='parent'>
            <div class='child'>
                <h2>Before</h2>
                Word Count: $before_wordcount<br>
                Readability: $before_reading_ease<hr>
                $html_formatted
            </div>
            <div class='child'>
                <h2>After</h2>
                Word Count: $after_wordcount<br>
                Readability: $after_reading_ease<hr>
                $html_formatted_clean
            </div>
        </div>
        <div style=\"text-align: center;\">
            <strong>Cost: " . round( $open_ai_cost * 100, 2 ) . "¢</strong>
        </div>
        <hr>
        
        <div style=\"margin-left:5%;margin-right:5%;\">
            <h2>Diff</h2>
            $result
        </div>
        <hr>
        <div class='parent'>
            <div class='child'>
                <h2>Before</h2>
                ". str_replace( "\n", "<br>", $markdown )  ."
            </div>
            <div class='child'>
                <h2>After</h2>
                ". str_replace( "\n", "<br>", $markdown_clean )  ."
            </div>'
        </div> 
        <hr>
        <h2>HTML for Copying</h2>
        <pre style=\"width:50%;margin-left:5%;margin-right:5%;\">" . htmlentities( $html_formatted_clean ) . "</pre>           
        <style>" . file_get_contents( 'style.css' ) . "</style>"; // insert the styles mostly for the compare table
    }