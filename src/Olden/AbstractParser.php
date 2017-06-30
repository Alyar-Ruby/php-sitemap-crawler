<?php
namespace Olden;

use Goutte\Client;
use Olden\Speller\Hunspell;
use Mekras\Speller\Source\StringSource;
use PHPExcel_IOFactory;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Main parsing class that do all work
 *
 * Class AbstractParser
 * @package Olden
 */
abstract class AbstractParser
{
    // how much pages one worker will be process
    const CHUNK_SIZE = 100;
    // how mush workers can be runned in parallel
    const MAX_CHILDREN = 10;

    /**
     * @var \PHPExcel_Worksheet
     */
    protected $workseet;
    /**
     * @var Client
     */
    protected $client;
    /**
     * @var ProgressBar
     */
    private static $bar;
    /**
     * @var ConsoleOutput
     */
    private $output;
    /**
     * @var Hunspell
     */
    private $hunspell;
    /**
     * @var Input
     */
    private $input;

    /**
     * Array of url with get retry quantity for "too many requests" handle
     *
     * @var array
     */
    private $retry = [];

    /**
     * AbstractParser constructor.
     *
     * @param \PHPExcel_Worksheet $worksheet to write to
     * @param Client $client http client to make requests
     * @param ConsoleOutput $output to write info & errors
     * @param Input $input to get command options
     */
    public function __construct(\PHPExcel_Worksheet $worksheet, Client $client, ConsoleOutput $output, Input $input, $siteMapUrl)
    {
        $this->workseet = $worksheet;
        $this->workseet->setTitle(ucfirst($this->entityName()) . 's');
        $this->client = $client;
        $this->output = $output;
        $this->hunspell = new Hunspell;
        // check if dict-path was set & it exists & set it to hanspell custom dictionary
        if (($dictPath = $input->getOption('dict-path')) && realpath($dictPath)) {
            $this->hunspell->setCustomDictionaries((array) realpath($dictPath));
        } elseif (!realpath($dictPath)) {
            throw new InvalidArgumentException('Dictionary file ' . $dictPath . ' is not readable');
        }
        $this->input = $input;
        $this->siteMapUrl = $siteMapUrl;
    }

    public function parseSitemap()
    {
        // save cursor position
        $this->output->write("\033[s");

        /**
         * get sitemap page (brands ot items)
         * @see PageConstants::BRANDS_SITEMAP_URL or PageConstants::ITEMS_SITEMAP_URL
         */
        $crawler = $this->client->request('GET', $this->siteMapUrl);

        $sitemap = [];

        $qty = 0;
        // iterate over each sitemap menus item
        $crawler->filter('.sitemap-menu-box .menucategories .submenulink a')->each(function($node) use (&$sitemap, &$qty) {
            // get submenu sitemap page
            $pages = $this->client->request('GET', $node->attr('href'));

            // iterate for every item or brand page
            $pages->filter('.sitemap-menu-box .menucategories .submenulink a')->each(function($node) use (&$sitemap, &$qty) {
                // store pagename in sitemap for future comparison
                $pageNameInSitemap = str_replace("\n", ' ', trim($node->text()));
                $matches = [];
                $sitemapName = '';
                if (preg_match('/(\d*.) (.*)/', $pageNameInSitemap, $matches)) {
                    $sitemapName = $matches[2];
                }

                // combine all links for parsing
                $sitemap[$node->attr('href')] = mb_strtolower($sitemapName);

                if ($qty % 10 == 0) {
                    $this->displayPagesCalculation($qty);
                }

                $qty++;
            });
        });
        $this->displayPagesCalculation(count($sitemap));

        // initialise progress bar with page count
        $this->progressBar(count($sitemap));

        // initialize & configure forking class
        $manager = new ForkDaemon();
        $manager->max_children_set(self::MAX_CHILDREN);
        $manager->max_work_per_child_set(1);

        // advance progress bar when child worker is done
        $manager->register_parent_child_exit(function() {
            if ($this->entityName() == 'brand') {
                // move to 1st line & erase the line
                $this->output->write("\033[1;0f\033[K");
            } else {
                // move to 2nd line & erase the line
                $this->output->write("\033[2;0f\033[K");
            }
            // change progress on bar
            $this->progressBar()->advance(self::CHUNK_SIZE);
        });

        // worker job defined here
        $manager->register_child_run(function(array $payload) {
            $i = 0;
            // iterate over all page links to parse
            foreach ($payload[0] as $url => $name) {
                try {
                    // parse page
                    $data = $this->parsePage($url, $name);
                } catch (\Exception $e) {
                    file_put_contents(getcwd() . '/parser_errors.log', 'Problem with:' . $url . ' ' . $e->getMessage() . "\r\n", FILE_APPEND);
                    continue;
                }

                // write parsed data to excel row
                $this->workseet->fromArray(array_map('trim', $data), null, "A" . ($i+2));

                $i++;
            }

            // write excel file on disk on every step for prevent data lose if script will be interrupted
            $file = PHPExcel_IOFactory::createWriter($this->workseet->getParent(), "Excel2007");
            $file->save(sys_get_temp_dir() . '/' . $this->entityName() . 's-' . uniqid() . '.xlsx');
        });

        $chunks = array_chunk($sitemap, self::CHUNK_SIZE, true);
        // add jobs for workers
        $manager->addwork($chunks);
        // run workers
        $manager->process_work(true);

        // restore cursor position & move the cursor down 1 lines
        $this->output->writeln("\033[u\033[1B");
    }

    protected function displayPagesCalculation($count)
    {
        if ($this->entityName() == 'brand') {
            // move to 1st line & erase the line
            $this->output->write("\033[1;0f\x1B[2K");
        } else {
            // move to 2nd line & erase the line
            $this->output->write("\033[2;0f\x1B[2K");
        }
        $this->output->write('Calculating ' . $this->entityName() . ' pages: ' . $count);
    }

    /**
     * Initialize output progress bar with custom stiles
     *
     * @param null $qty
     * @return ProgressBar
     */
    protected function progressBar($qty = null)
    {
        if (!self::$bar) {
            self::$bar = new ProgressBar($this->output, $qty);
            self::$bar->setFormatDefinition('custom', 'Parsed ' . $this->entityName() . ' chunks: %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');
            self::$bar->setFormat('custom');
            self::$bar->setBarCharacter('<comment>=</comment>');
        }

        return self::$bar;
    }

    /**
     * All parsing work goes here
     *
     * @param $url
     * @param null $sitemapName
     * @return array
     */
    protected function parsePage($url, $sitemapName = null)
    {
        // calculate request quantity for url
        if (isset($this->retry[$url])) {
            $this->retry[$url]++;
        } else {
            $this->retry[$url] = 1;
        }

        // get page for parsing
        $page = $this->client->request('GET', $url);

        // if we get "too many requests" try a bit later
        if ($this->client->getResponse()->getStatus() == 429
            || $page->filterXPath('//head/title')->text() == '429 - Too Many Requests.') {
            sleep((int) pow(2, $this->retry[$url] - 1));
            return $this->parsePage($url, $sitemapName);
        }

        // parse meta robots
        $index = $page->filterXPath($this->metaRobotsPath())->text();
        // parse title
        $title = $page->filterXPath($this->titlePath())->text();
        // do title comparison with link text in sitemap
        $titleComparison = $sitemapName && strpos(mb_strtolower($title), $sitemapName) !== false ? 'true' : 'false';

        // parse meta description
        $metaDescription = $page->filterXPath($this->metaDescriptionPath())->text();

        // parse h1 tag
        if (!$page->filter($this->h1Path())->count()) {
            return $this->parsePage($url, $sitemapName);
        }
        $h1 = $page->filter($this->h1Path())->text();
        // do h1 comparison with link text in sitemap
        $h1Comparison = $sitemapName && strpos(mb_strtolower($h1), $sitemapName) !== false ? 'true' : 'false';

        $seoDescription = '';
        // parse seo description if it exists
        if ($page->filter($this->seoDescriptionPath())->count()) {
            $seoDescription = $page->filter($this->seoDescriptionPath())->text();
        }

        // parse breadcrumb text
        $breadcrumbsText = $page->filter($this->breadcrumbsTextPath())->text();
        // do breadcrumb comparison with link text in sitemap
        $breadcrumbsComparison = $sitemapName && strpos(mb_strtolower($breadcrumbsText), $sitemapName) !== false ? 'true' : 'false';
        // parse breadcrumb url
        $breadcrumbsUrl = $page->filter($this->breadCrumbsLinkPath())->attr('content');

        // define which page is identified and which has na.gif
        $identified = $nagif = 'false';
        if ($page->filter($this->imagePath())->count() > 0) {
            if (strpos($page->filter($this->imagePath())->attr('src'), 'na.gif') === false) {
                $identified = 'true';
            } else {
                $nagif = 'true';
            }
        }
        // parse image alt tag
        $altTag = $page->filter($this->imagePath())->attr('alt');

        return [
            $url,
            $url,
            $index,
            $title,
            $titleComparison,
            $metaDescription,
            $this->spellCheck($metaDescription), // do spellcheck if needed
            $h1,
            $h1Comparison,
            $seoDescription,
            $this->spellCheck($seoDescription), // do spellcheck if needed
            $breadcrumbsText,
            $breadcrumbsComparison,
            $breadcrumbsUrl,
            $identified,
            $nagif,
            $altTag
        ];
    }

    /**
     * Run spell checking for given string and return mistaken words with suggestions
     *
     * @param $string
     * @return string
     */
    protected function spellCheck($string)
    {
        if (!$this->input->getOption('with-spellcheck')) {
            return '';
        }

        $source = new StringSource($string);
        $issues = $this->hunspell->checkText($source, ['en']);

        $spellchekerResults = '';
        foreach ($issues as $issue) {
            $spellchekerResults .= $issue->word . ": " . implode(', ', $issue->suggestions) . "\n";
        }

        return $spellchekerResults;
    }

    abstract protected function entityName();

    /**
     * @return string
     */
    abstract protected function metaRobotsPath();

    /**
     * @return string
     */
    abstract protected function titlePath();

    /**
     * @return string
     */
    abstract protected function metaDescriptionPath();

    /**
     * @return string
     */
    abstract protected function h1Path();

    /**
     * @return string
     */
    abstract protected function seoDescriptionPath();

    /**
     * @return string
     */
    abstract protected function breadcrumbsTextPath();

    /**
     * @return string
     */
    abstract protected function breadCrumbsLinkPath();

    /**
     * @return string
     */
    abstract protected function imagePath();
}
