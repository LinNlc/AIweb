<?php
declare(strict_types=1);

require_once __DIR__ . '/Utils.php';

/**
 * 构建排班导出的表头与行数据。
 *
 * @param array $employees 员工姓名列表（按列排列）
 * @param array $dates     日期列表（YYYY-MM-DD）
 * @param array $data      以日期->员工为键的排班结果
 *
 * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
 */
function exporter_build_schedule_matrix(array $employees, array $dates, array $data): array
{
    $header = array_merge(['日期', '星期'], $employees);
    $rows = [];

    foreach ($dates as $date) {
        $row = [$date, '周' . cn_week($date)];
        foreach ($employees as $employee) {
            $row[] = $data[$date][$employee] ?? '';
        }
        $rows[] = $row;
    }

    return [$header, $rows];
}

/**
 * 输出 XLSX，如失败则自动回退 CSV 导出。
 */
function exporter_output_schedule(string $start, string $end, array $header, array $rows): void
{
    $filenameBase = '排班_' . $start . '_' . $end;
    $autoloadPath = __DIR__ . '/../../vendor/autoload.php';

    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $column = 1;
            foreach ($header as $text) {
                $sheet->setCellValueByColumnAndRow($column++, 1, $text);
            }

            $rowIndex = 2;
            foreach ($rows as $rowValues) {
                $column = 1;
                foreach ($rowValues as $value) {
                    $sheet->setCellValueByColumnAndRow($column++, $rowIndex, $value);
                }
                $rowIndex++;
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filenameBase . '.xlsx"');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (\Throwable $e) {
            // 如果生成 XLSX 失败，继续向下执行 CSV 导出。
        }
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');

    $out = fopen('php://output', 'w');
    if ($out !== false) {
        fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, $header);
        foreach ($rows as $rowValues) {
            fputcsv($out, $rowValues);
        }
        fclose($out);
    }
    exit;
}

/**
 * 统一封装排班导出：内部构建矩阵并输出附件。
 */
function exporter_send_schedule(string $start, string $end, array $employees, array $data): void
{
    $dates = ymd_range($start, $end);
    [$header, $rows] = exporter_build_schedule_matrix($employees, $dates, $data);
    exporter_output_schedule($start, $end, $header, $rows);
}
