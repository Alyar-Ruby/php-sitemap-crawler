<?php
namespace Olden\Commands;

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
 * Class DiffCommand
 * @package Olden\Commands
 */
class DiffCommand extends Command
{
    /**
     * set command name with description and options
     */
    protected function configure()
    {
        $this->setName('diff')
            ->setDescription('Get difference with before')
        ;
    }

    const REPORT_FILE_TYPE = ".xlsx";

    protected function openExcelFile($fileName)
    {
        $objReader = PHPExcel_IOFactory::createReader("Excel2007");
        return $objReader->load($fileName);
    }

    protected function openChangesetFile()
    {
        // Open "changes.xlsx" file
        $filename = getcwd() . "/changes" . self::REPORT_FILE_TYPE;
        if (file_exists($filename))
        {
            $excel = $this->openExcelFile($filename);

            // ToDo: Validation check if changes.xlsx file has stable report format
            
        }
        else {
            // initialise PHPExcel class
            $excel = new PHPExcel();

            // get default(0) sheet, set its name & write first row with headers
            $excel->getActiveSheet()->setTitle('Brands')->fromArray(PageConstants::REPORT_CHANGES_HEADERS);
            $excel->addSheet(new \PHPExcel_Worksheet($excel))->setTitle('Items')->fromArray(PageConstants::REPORT_CHANGES_HEADERS);
            $excel->addSheet(new \PHPExcel_Worksheet($excel))->setTitle('BrandRecords')->fromArray(PageConstants::REPORT_DETAILS_HEADERS);
            $excel->addSheet(new \PHPExcel_Worksheet($excel))->setTitle('ItemRecords')->fromArray(PageConstants::REPORT_DETAILS_HEADERS);

            // define which column is needed to be customized
            $columnForTuning = ['F', 'G', 'J', 'K']; //$input->getOption('with-spellcheck') ? ['F', 'G', 'J', 'K'] : ['F', 'J'];
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
        }

        return $excel;
    }

    protected function saveChangesetFile($excel)
    {
        $filename = getcwd() . "/changes" . self::REPORT_FILE_TYPE;
        $objWriter = PHPExcel_IOFactory::createWriter($excel, "Excel2007");
        $objWriter->save($filename);
    }

    protected function readAllReportData($filename, $sheetIndex)
    {
        // Open excel file
        $readedWorksheet = $this->openExcelFile($filename)->getSheet($sheetIndex);
        $highestColumnIndex = 0; //PHPExcel_Cell::columnIndexFromString($readedWorksheet->getHighestColumn());
        while ($readedWorksheet->getCellByColumnAndRow($highestColumnIndex)->getValue() != null) {
            $highestColumnIndex++;
        }

        // Read all rows except header
        $sheetData = array();
        for ($row = 2; $row <= $readedWorksheet->getHighestRow(); ++$row) {
            $rowData = array();
            for ($col = 0; $col < $highestColumnIndex; ++$col) {
                $rowData[$col] = $readedWorksheet->getCellByColumnAndRow($col, $row)->getValue();
            }
            if ($rowData[0] == null)
                break;
            $sheetData[] = $rowData;
        }

        // Sort array by url and return data
        usort($sheetData, function($a, $b) {
            return strcmp($a[0], $b[0]);
        });

        return $sheetData;
    }

    protected function fileNameToDateTime($filename)
    {
        $t = basename($filename, self::REPORT_FILE_TYPE);
        return "20" . substr($t, 0, 2) . "-" . substr($t, 2, 2) . "-" . substr($t, 4, 2) .
                " " . substr($t, 6, 2) . ":" . substr($t, 8, 2) . ":" . substr($t, 10, 2);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \PHPExcel_Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Generate report list first
        $files = glob(getcwd() . "/*.xlsx");

        // Get Report file names only
        $reportfiles = array();
        foreach ($files as $file)
        {
            $filetitle = basename($file, ".xlsx");
            // File name must be yymmddHHMMSS
            if (strlen($filetitle) == 12 && is_numeric($filetitle))
                array_push($reportfiles, $file);
        }

        // Open "changes.xlsx"
        $excel = $this->openChangesetFile();

        // combine all excel files chunks that are produced by children into one
        foreach (['Brands', 'Items'] as $k => $entity) {
            $writeWorksheet = $excel->getSheet($k);
            $lastIndex = $writeWorksheet->getHighestRow();
            $writeDetailWorksheet = $excel->getSheet($k + 2);

            // Select report file to start compare
            $i = 0;
            if ($lastIndex > 1)
            {
                $lastReportName = $writeWorksheet->getCellByColumnAndRow(0, $lastIndex)->getValue();
                while ($i < count($reportfiles) && strcmp(basename($reportfiles[$i]), $lastReportName) <= 0)
                    $i++;
            }
            if ($i >= count($reportfiles))
            {
                continue;   // This file contains latest version, no need to update
            }

            // Calculate difference for each files
            if ($i > 0)
            {
                $lastData = $this->readAllReportData($reportfiles[$i - 1], $k);
                $lastDataCount = count($lastData);
            }

            for (; $i < count($reportfiles); $i++)
            {
                // Initialize row data
                $rowData = [ basename($reportfiles[$i]) ];
                for ($col = 1; $col < count(PageConstants::REPORT_CHANGES_HEADERS); $col++) {
                    $rowData[] = 0;
                }
                unset($deletedRows);    $deletedRows = array();
                unset($addedRows);      $addedRows = array();
                $dataCols = count(PageConstants::REPORT_HEADERS);

                // Get new data
                $newData = $this->readAllReportData($reportfiles[$i], $k);
                $newDataCount = count($newData);

                if ($lastIndex > 1)
                {
                    // Compare with previous one
                    // $lastData and $newData are both sorted
                    // So we can use "Merge two sorted arrays into a sorted array" algorithm
                    // For more details, please check below
                    // https://stackoverflow.com/questions/5958169/how-to-merge-two-sorted-arrays-into-a-sorted-array

                    $ii = $jj = 0;

                    while ($ii < $lastDataCount && $jj < $newDataCount)
                    {
                        $cmp = strcmp($lastData[$ii][0], $newData[$jj][0]);
                        if ($cmp < 0)
                        {
                            // A row is removed
                            $deletedRows[] = $lastData[$ii];
                            $ii++;
                        }
                        else if ($cmp > 0)
                        {
                            // New row is added
                            $addedRows[] = $newData[$jj];
                            $jj++;
                        }
                        else
                        {
                            // Check each field is changed or not
                            for ($kk = 0; $kk < $dataCols; $kk++)
                            {
                                if ($lastData[$ii][$kk] != $newData[$jj][$kk])
                                {
                                    $rowData[$kk + 1]++;
                                }
                            }

                            $ii++; $jj++;
                        }
                    }

                    while ($ii < $lastDataCount)
                    {
                        $deletedRows[] = $lastData[$ii];
                        $ii++;
                    }

                    while ($jj < $newDataCount)
                    {
                        $addedRows[] = $newData[$jj];
                        $jj++;
                    }
                }

                // Move to next
                $lastData = $newData;
                $lastDataCount = $newDataCount;
                $lastIndex++;

                // Write changes into sheet
                for ($col = 1; $col < count(PageConstants::REPORT_CHANGES_HEADERS); $col++) {
                    $rowData[$col] = (string)$rowData[$col];
                }
                $writeWorksheet->fromArray($rowData, null, "A$lastIndex");

                // Write Add/Removed records into sheet
                $addRowPos = $writeDetailWorksheet->getHighestRow() + 1;
                $updateTime = $this->fileNameToDateTime($reportfiles[$i]);
                for ($ii = 0; $ii < count($deletedRows); $ii++)
                {
                    $rowData = $deletedRows[$ii];
                    $rowData[] = $updateTime;
                    $rowData[] = "Delete";

                    $writeDetailWorksheet->fromArray($rowData, null, "A$addRowPos");
                    $addRowPos++;
                }
                for ($ii = 0; $ii < count($addedRows); $ii++)
                {
                    $rowData = $addedRows[$ii];
                    $rowData[] = $updateTime;
                    $rowData[] = "Insert";

                    $writeDetailWorksheet->fromArray($rowData, null, "A$addRowPos");
                    $addRowPos++;
                }
            }
        }

        $this->saveChangesetFile($excel);
    }
}
