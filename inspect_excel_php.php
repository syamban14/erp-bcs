<?php

require 'vendor/autoload.php';

$file = 'jadwalKerjaRoster.xlsx';

if (!file_exists($file)) {
    die("File not found: $file\n");
}

try {
    // Maatwebsite Excel v1.x uses PHPExcel
    $excel = PHPExcel_IOFactory::load($file);
    $sheet = $excel->getActiveSheet();
    
    echo "Inspecting $file...\n";
    echo "Highest Row: " . $sheet->getHighestRow() . "\n";
    echo "Highest Column: " . $sheet->getHighestColumn() . "\n\n";

    $rows = [];
    for ($i = 1; $i <= min(10, $sheet->getHighestRow()); $i++) {
        $row = [];
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $row[] = $sheet->getCell($col . $i)->getValue();
        }
        $rows[] = $row;
    }

    foreach ($rows as $i => $row) {
        echo "Row " . ($i + 1) . ": " . implode(" | ", array_map(fn($v) => is_null($v) ? 'NULL' : $v, $row)) . "\n";
    }

} catch (Exception $e) {
    echo "Error loading file: " . $e->getMessage() . "\n";
}
