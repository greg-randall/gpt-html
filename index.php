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


if (!isset($_POST["input"])) { //check to see if there's content to clean if not, prompt for it 
    ?>
    Clean:
     <form action="index.php" method="post">
     <textarea name="input" rows="50" cols="160"></textarea><br><br>
     <input type="submit">
     </form>
     <?php exit();
   } else {

    //get & clean the input
    $html = $_POST["input"]; 
    $html_formatted = clean_html($html); 


    //convert the input to markdown
    $converter = new HtmlConverter();
    $markdown = $converter->convert($html_formatted);
    $markdown = str_ireplace("mailto_","mailto:",$markdown); //the markdown converter didn't like mailto links, so swappeed them out for a placeholder in the cleaner functions


    //send the markdown to the AI to edit
    $prompt = "Read the following Markdown from a webpage, make it easier to read (less complicated words & shorter sentences where possible), but don't drop dates, names, or other important information! If something looks like it should be a list, heading, etc please make it into a list/heading/etc (headings must descend in size logically starting from ##). Make sure the output is in Markdown (Don't add '```markdown' or similar)!!!\n\n$markdown"; //set the prompt for the AI
    open_ai_setup($open_ai_key);
    $open_ai_output = open_ai_call($prompt, "gpt-4-1106-preview", 0.25, $open_ai_key);
    $markdown_clean = $open_ai_output->choices[0]->message->content;
    $open_ai_cost = open_ai_cost($open_ai_output);
    


    $parser = new \cebe\markdown\Markdown();
    $html_formatted_clean = beautify_html($parser->parse($markdown_clean));

 

    $jsonResult = DiffHelper::calculate($html_formatted, $html_formatted_clean, 'Json'); // may store the JSON result in your database
    $htmlRenderer = RendererFactory::make('SideBySide', array('detailLevel' => 'word'));
    $result = $htmlRenderer->renderArray(json_decode($jsonResult, true));


    $textStatistics = new TS\TextStatistics;

    

    echo "\n<style>" . file_get_contents('style.css') . "</style>";


   echo 
    "<div class='parent'>
        <div class='child'>
            Word Count Before: ". str_word_count($markdown) ."<br>
            Readability Before: ". $textStatistics->fleschKincaidReadingEase(strip_tags($html_formatted)) ."<hr>
            $html_formatted
        </div>
        <div class='child'>
            Word Count After: " . str_word_count($markdown_clean) . "<br>
            Readability After: ". $textStatistics->fleschKincaidReadingEase(strip_tags($html_formatted_clean)) ."<hr>
            $html_formatted_clean
        </div>
    </div>
    <div style=\"text-align: center;\"><strong>Cost: " . round($open_ai_cost*100,2) . "¢</strong></div>
    <hr>
    $result<hr>
    <pre>" .
        htmlentities( $html_formatted_clean)
    ."</pre><hr>
    <h2>Markdown</h2>
    <pre>$markdown</pre><hr>
    <h2>Cleaned Markdown</h2>
    <pre>$markdown_clean</pre>";

}