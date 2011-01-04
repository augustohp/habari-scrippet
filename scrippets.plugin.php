<?php
/**
 * Plugin that format screenplay texts based on the format
 * proposed by John August.
 * 
 * Based on some work by Nima Yousefi (2008)
 * 
 * @author Augusto Pascutti <augusto.hp@gmail.com>
 */
class Scrippets extends Plugin {

    /**
     * Returns information about the plugin to Habari
     *
     * @return array
     */
    public function info() {
        return array(
                'name'=>'Scrippets',
                'version'=>'0.1',
                'url'=>'http://www.augustopascutti.com/scrippets',
                'author'=>'Augusto Pascutti',
                'authorurl'=>'http://www.augustopascutti.com/',
                'license'=>'Apache License 2.0',
                'description'=>'A format designed by John August to display nicely-formatted screenplay texts on your blog.'
                );
    }

    /**
     * Receives a string and changes it to the scrippted one, here is where all
     * the magic happen (such a gay thing to say ...)
     *
     * @param Post|string $post
     * @return void|string
     * @author Nima Yousefi - 2008
     */
    public function format_post($post) {
        // let us know if this method is called by a filter or action hook
        $action_hook = (boolean) ($post instanceof Post);
        // gets post content from habari
        $text = ($action_hook) ? $post->content_out : $post ;
        // pattern of the scrippet
        $scrippet_pattern = "/[\[<]scrippet[\]>](.*?)[\[<]\/scrippet[\]>]/si";
        // verifies the need to run throught the whole method
        if (preg_match($scrippet_pattern,$text) === 0) { 
            return $text;
        }

        // Create arrays & setup some basic character replacements
        $pattern   = array('/\r/', '/&amp;/', '/\.{3}|…/', '/\-{2}|—|–/');
        $replace   = array('', '&', '&#46;&#46;&#46;', '&#45;&#45;');
        // Sceneheaders must start with INT, EXT, or EST
        $pattern[] = '/(INT|EXT|EST)([\.\-\s]+?)(.+?)([A-Za-z0-9\)\s\.])\n/';
        $replace[] = '<p class="sceneheader">\1\2\3\4</p>' . "\n";
        // Catches transitions
        // Looks for a colon, with some hard coded exceptions that don't use colons.
        $pattern[] = '/\n([^<>\na-z]*?:|FADE TO BLACK\.|FADE OUT\.|CUT TO BLACK\.)[\s]??\n/';
        $replace[] = '<p class="transition">\1</p>' . "\n";
        // Catches multi-line action blocks
        // looks for all caps without punctuation, then two Newlines.
        // This differentiates from character cues because Cues will only have a single break, then the dialogue/parenthetical.
        $pattern[] = '/\n{2}(([^a-z\n\:]+?[\.\?\,\s\!]*?)\n{2}){1,2}/';
        $replace[] = "\n" . '<p class="action">\2</p>' . "\n";
        // Catches character cues
        // Looks for all caps, parenthesis (for O.S./V.O.), then a single newline.
        $pattern[] = '/\n([^<>a-z\s][^a-z:\!\?]*?[^a-z\(\!\?:,][\s]??)\n{1}/'; // minor change that makes it work better
        $replace[] = '<p class="character">\1</p>';
        // Catches parentheticals
        // Just looks for text between parenthesis.
        $pattern[] = '/(\([^<>]*?\)[\s]??)\n/';
        $replace[] = '<p class="parenthetical">\1</p>';
        // Catches dialogue
        // Must follow a character cue or parenthetical.
        $pattern[] = '/(<p class="character">.*<\/p>|<p class="parenthetical">.*<\/p>)\n{0,1}(.+?)\n/';
        $replace[] = '\1' . "\n" . '<p class="dialogue">\2</p>' . "\n";
        // Defaults.
        $pattern[] = '/([^<>]*?)\n/';
        $replace[] = '<p class="action">\1</p>' . "\n";
        // Hack - cleans up the mess the action regex is leaving behind.
        $pattern[] = '/<p class="action">[\n\s]*?<\/p>/';
        $replace[] = "";

            // Styling
            $pattern[] = '/(\*{2}|\[b\])(.*?)(\*{2}|\[\/b\])/';
            $replace[] = '<b>\2</b>';

            $pattern[] = '/(\*{1}|\[i\])(.*?)(\*{1}|\[\/i\])/';
            $replace[] = '<i>\2</i>';

            $pattern[] = '/(_|\[u\])(.*?)(_|\[\/u\])/';
            $replace[] = '<u>\2</u>';

        // Find all the scrippet blocks.
        // Only text between matched scrippet blocks will be processed by the text replacement.
        preg_match_all($scrippet_pattern, $text, $matches);
        $matches = $matches[1];             // we only need the matches of the (.*?) group
        $output = '';                       // initialize

        $num_matches = count($matches);
        if($num_matches > 0) {
            for($i=0; $i < $num_matches; $i++) {
                // Remove any HTML tags in the scrippet block
                $matches[$i] = preg_replace('/<\/p>|<br(\/)?>/i', "\n", $matches[$i]);
                $matches[$i] = strip_tags($matches[$i]);

                $matches[$i] = $matches[$i] . "\n";   // this is a hack to eliminate some weirdness at the end of the scrippet

                // Regular Expression Magic!
                $output  = '<div class="scrippet">'.preg_replace($pattern, $replace, $matches[$i]).'</div>';
                $text = preg_replace($scrippet_pattern, $output, $text, 1);
            }
        }
        if ($action_hook) {
            $post->content = $text;
        }
        return $text;
        
    }

    /**
     * Inserts CSS into the page
     *
     * @return void
     */
    public function insert_css() {
        $stylesheet = $this->get_url().'/style.css';
        Stack::add('template_stylesheet', array($stylesheet, 'screen'), 'style');
    }

    /**
     * Hooks this plugin to Habari
     *
     * @return array
     */
    public function alias() {
        return array(
            'format_post' => array('filter_post_content_out'),
            'insert_css'  => array('theme_header'),
        );
    }
}