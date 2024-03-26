<?php
    // remeber to run:
    // composer install

    // remember to copy the file 'blank_keys.php' & rename it 'keys.php' and fill in your OpenAi key



    require 'vendor/autoload.php';
    include_once 'functions.php';
    include_once 'keys.php';

    use Jfcherng\Diff\DiffHelper;
    use Jfcherng\Diff\Factory\RendererFactory;
    use League\HTMLToMarkdown\HtmlConverter;
    use DaveChild\TextStatistics as TS;


    $base_prompt = "Read the following Markdown, make edits to increase clarity while making things more succinct, make sure the writing flows naturally, and make sure the document is an 8th grade reading level! If some of the markdown formatting looks off please fix it, make sure that headings descend in size logically, if you see raw URLs change them into links with words, and Do Not Remove Links (reformatting is ok). Make sure the output is in Markdown (don't add '```markdown' or similar)!!!";

    //check to see if there's content to clean if not, prompt for it
    if ( !isset( $_POST[ "input" ] ) || !isset( $_POST[ "prompt" ] ) ) {  
?>
        <h1>GPT HTML</h1>
        
        <p style="width:40%;">Use this tool to clean a dirty webpage. It does general HTML cleanup (making the output something that WordPress will happily use in Gutenberg). It also attempts to do a light text edit as well as fix issues of things not being headings, lists, etc using ChatGPT.</p>
        <p style="width:40%;">Edit the prompt below to meet your needs, but note that the content passed to ChatGPT is Markdown (seems that ChatGPT works better with Markdown than HTML). The program also expects ChatGPT to return Markdown.</p>

        <form action="index.php" method="post">
        <h2>Prompt:</h2>
        <textarea name="prompt" rows="10" cols="100" ><?php echo $base_prompt; ?></textarea><br><br>
        <h2>Input HTML:</h2>
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
        $prompt         = $_POST[ "prompt" ] . "\n\n$markdown"; //set the prompt for the AI

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
                <h2>Preview Before</h2>
                Word Count: $before_wordcount<br>
                Readability: $before_reading_ease<hr>
                $html_formatted
            </div>
            <div class='child'>
                <h2>Preview After</h2>
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
                <h2>Markdown Before</h2>
                ". str_replace( "\n", "<br>", $markdown )  ."
            </div>
            <div class='child'>
                <h2>Markdown After</h2>
                ". str_replace( "\n", "<br>", $markdown_clean )  ."
            </div>
        </div> 
        <hr>
        <h2>HTML for Copying</h2>
        <pre style=\"width:50%;margin-left:5%;margin-right:5%;\">" . htmlentities( $html_formatted_clean ) . "</pre>           
        <style>" . file_get_contents( 'style.css' ) . "</style>"; // insert the styles mostly for the compare table
    }