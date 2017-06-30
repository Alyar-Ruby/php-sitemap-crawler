<?php
namespace Olden\Commands;

use Goutte\Client;
use Olden\BrandParser;
use Olden\ForkDaemon;
use Olden\ItemParser;
use Olden\PageConstants;
use PHPExcel;
use PHPExcel_Cell;
use PHPExcel_IOFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Symfony console command
 *
 * @see http://symfony.com/doc/current/components/console.html
 *
 * Class ParseCommand
 * @package Olden\Commands
 */
class ParseCommand extends Command
{
    /**
     * set command name with description and options
     */
    protected function configure()
    {
        $this->setName('parse')
            ->setDescription('Parse brands & items pages')
            ->addOption(
                'with-spellcheck',
                null,
                InputOption::VALUE_NONE,
                'Run parsing with spell check for description fields'
            )
            ->addOption(
                'dict-path',
                null,
                InputOption::VALUE_OPTIONAL,
                'Txt file with stopwords for spellcheck. Single word on file row'
            )
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \PHPExcel_Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // initialise PHPExcel class
        $excel = new PHPExcel();

        // get default(0) sheet, set its name & write first row with headers
        $excel->getActiveSheet()->setTitle('Brands')->fromArray(PageConstants::REPORT_HEADERS);
        $excel->addSheet(new \PHPExcel_Worksheet($excel))->setTitle('Items')->fromArray(PageConstants::REPORT_HEADERS);

        // define which column is needed to be customized
        $columnForTuning = $input->getOption('with-spellcheck') ? ['F', 'G', 'J', 'K'] : ['F', 'J'];
        // iterate over sheets
        foreach ($excel->getWorksheetIterator() as $worksheet) {
            $excel->setActiveSheetIndex($excel->getIndex($worksheet));

            $sheet = $excel->getActiveSheet();
            // set auto height
            $sheet->getRowDimension(1)->setRowHeight(-1);
            // iterate over cells
            $cellIterator = $sheet->getRowIterator()->current()->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);
            /** @var PHPExcel_Cell $cell */
            foreach ($cellIterator as $cell) {
                // set defined width size & text wrapping for customized columns
                if (in_array($cell->getColumn(), $columnForTuning)) {
                    $sheet->getColumnDimension($cell->getColumn())->setAutoSize(false);
                    $sheet->getStyle($cell->getColumn())->getAlignment()->setWrapText(true);
                    $sheet->getColumnDimension($cell->getColumn())->setWidth(100);
                // set auto width size to others columns
                } else {
                    $sheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
                }
            }
        }

        // initialize parsing vendor library
        $goutte = new Client();

        // Clear the screen, move to (0,0)
        $output->write("\033[2J");
        // puts the cursor at line 1 and column 0
        $output->write("\033[1;0f");

        // initialize & configure forking class
        $manager = new ForkDaemon();
        // 2 child for brand & item parsing
        $manager->max_children_set(2);
        $manager->max_work_per_child_set(1);
        $manager->register_child_run(function(array $data) {
            $data[0]->parseSitemap();
        });

        // add jobs for workers
        $manager->addwork([
            new BrandParser($excel->getSheet(0), $goutte, $output, $input, PageConstants::BRANDS_SITEMAP_URL),
            new ItemParser($excel->getSheet(1), $goutte, $output, $input, PageConstants::ITEMS_SITEMAP_URL)
        ]);
        // run workers
        $manager->process_work(true);

        // combine all excel files chunks that are produced by children into one
        foreach (['brands', 'items'] as $k => $entity) {
            $finder = new Finder();
            $writeWorksheet = $excel->getSheet($k);
            $i = 2;
            /** @var SplFileInfo $file */
            foreach ($finder->ignoreUnreadableDirs()->name("$entity*")->in(sys_get_temp_dir()) as $file) {
                $excelReader = new \PHPExcel_Reader_Excel2007();
                $readedWorksheet = $excelReader->load($file->getPathname())->getSheet($k);
                $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($readedWorksheet->getHighestColumn());

                for ($row = 2; $row <= $readedWorksheet->getHighestRow(); ++$row) {
                    $rowData = [];
                    for ($col = 0; $col <= $highestColumnIndex; ++$col) {
                        $rowData[$col] = $readedWorksheet->getCellByColumnAndRow($col, $row)->getValue();
                    }
                    $writeWorksheet->fromArray($rowData, null, "A$i");
                    $i++;
                }
                unlink($file);
            }
            $file = PHPExcel_IOFactory::createWriter($excel, "Excel2007");
            #to do - add date
            $file->save(getcwd() . "/parsed.xlsx");
        }
    }
}
