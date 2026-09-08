<?php

use dokuwiki\Parsing\Handler;
use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\File\PageResolver;
use dokuwiki\Logger;
use dokuwiki\Search\MetadataSearch;

/**
 * DokuWiki Syntax Plugin Backlinks.
 *
 * Shows a list of pages that link back to a given page.
 *
 * Syntax:  {{backlinks>[pagename][#filterNS][|option1][|option2]}}
 *
 *   [pagename] - a valid wiki pagename or a . for the current page
 *   [filterNS] - a valid, absolute namespace name, optionally prepended with ! to exclude
 *   [option]   - optional display option:
 *                context - show the text line containing the backlink
 *                sorted  - sort context entries alphabetically
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Michael Klier <chi@chimeric.de>
 * @author  Mark C. Prins <mprins@users.sf.net>
 */

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class.
 */
class syntax_plugin_backlinks extends SyntaxPlugin
{
    /**
     * Syntax Type.
     *
     * Needs to return one of the mode types defined in $PARSER_MODES in parser.php.
     *
     * @see DokuWiki_Syntax_Plugin::getType()
     */
    public function getType(): string
    {
        return 'substition';
    }

    /**
     * @see DokuWiki_Syntax_Plugin::getPType()
     */
    public function getPType(): string
    {
        return 'block';
    }

    /**
     * @see Doku_Parser_Mode::getSort()
     */
    public function getSort(): int
    {
        return 304;
    }

    /**
     * Connect pattern to lexer.
     *
     * @see Doku_Parser_Mode::connectTo()
     */
    public function connectTo($mode): void
    {
        $this->Lexer->addSpecialPattern('\{\{backlinks>.+?\}\}', $mode, 'plugin_backlinks');
    }

    /**
     * Handler to prepare matched data for the rendering process.
     *
     * @see DokuWiki_Syntax_Plugin::handle()
     */
    public function handle($match, $state, $pos, Handler $handler): array
    {
        // strip {{backlinks> from start and }} from end
        $match = substr($match, 12, -2);

        $options = [];
        if (str_contains($match, "|")) {
            $options = explode('|', substr(strstr($match, "|"), 1));
            $match   = strstr($match, "|", true);
        }

        $includeNS = '';
        if (str_contains($match, "#")) {
            $includeNS = substr(strstr($match, "#"), 1);
            $match     = strstr($match, "#", true);
        }

        return ([$match, $includeNS, $options]);
    }

    /**
     * Handles the actual output creation.
     *
     * @see DokuWiki_Syntax_Plugin::render()
     */
    public function render($format, Doku_Renderer $renderer, $data): bool
    {
        global $lang;
        global $INFO;
        global $ID;

        $id = $ID;
        // If it's a sidebar, get the original id.
        if ($INFO != null) {
            $id = $INFO['id'];
        }
        $match = $data[0];
        $match = ($match == '.') ? $id : $match;
        if (str_contains($match, ".:")) {
            $resolver = new PageResolver($id);
            $match = $resolver->resolveId($match);
        }

        if ($format == 'xhtml') {
            $renderer->info['cache'] = false;

            $backlinks = (new MetadataSearch())->backlinks($match);

            Logger::debug("backlinks: all backlinks to: $match", $backlinks);

            $renderer->doc .= '<div id="plugin__backlinks">' . "\n";

            $filterNS = $data[1];
            $options = $data[2];

            $displayContext = in_array('context', $options, true);
            $sortLines = in_array('sorted', $options, true);

            if ($backlinks !== [] && !empty($filterNS)) {
                if (stripos($filterNS, "!") === 0) {
                    $filterNS = substr($filterNS, 1);
                    Logger::debug("backlinks: excluding all of namespace: $filterNS");
                    $backlinks = array_filter(
                        $backlinks,
                        static fn($ns) => stripos($ns, $filterNS) !== 0
                    );
                } else {
                    Logger::debug("backlinks: including namespace: $filterNS only");
                    $backlinks = array_filter(
                        $backlinks,
                        static fn($ns) => stripos($ns, (string) $filterNS) === 0
                    );
                }
            }

            Logger::debug("backlinks: all backlinks to be rendered", $backlinks);

            if ($backlinks !== []) {
                $renderer->doc .= '<ul class="idx">';

                if ($displayContext) {
                    $outputLines = [];

                    foreach ($backlinks as $backlink) {
                        $name = p_get_metadata($backlink, 'title');
                        if (empty($name)) {
                            $name = $backlink;
                        }

                        $lines = p_wiki_xhtml($backlink);
                        $test = explode(PHP_EOL, $lines);

                        foreach ($test as $line) {
                            if (strpos($line, ' data-wiki-id="' . $match . '"') !== false) {
                                $line = preg_replace('/<br\/>$/', '', $line);
                                $line = preg_replace(
                                    '/<a.*?' . preg_quote($match, '/') . '.*?((<\/)\w+(>))/',
                                    '',
                                    $line
                                );
                                $line = preg_replace('/<li.*?(>)/', '', $line);
                                $line = preg_replace('/<div.*?(>)/', '', $line);
                                $line = preg_replace('/<\/div.*?(>)/', '', $line);

                                $outputLines[] = '<li><div class="li">' . $line . ' - '
                                    . html_wikilink(':' . $backlink, $name)
                                    . '</div></li>' . "\n";
                            }
                        }
                    }

                    if ($sortLines) {
                        sort($outputLines);
                    }

                    foreach ($outputLines as $line) {
                        $renderer->doc .= $line;
                    }
                } else {
                    if ($sortLines) {
                        sort($backlinks);
                    }
                    foreach ($backlinks as $backlink) {
                        $name = p_get_metadata($backlink, 'title');
                        if (empty($name)) {
                            $name = $backlink;
                        }

                        $renderer->doc .= '<li><div class="li">';
                        $renderer->doc .= html_wikilink(':' . $backlink, $name);
                        $renderer->doc .= '</div></li>' . "\n";
                    }
                }

                $renderer->doc .= '</ul>' . "\n";
            } else {
                $renderer->doc .= "<strong>Plugin Backlinks: " . $lang['nothingfound'] . "</strong>" . "\n";
            }

            $renderer->doc .= '</div>' . "\n";

            return true;
        }
        return false;
    }
}
