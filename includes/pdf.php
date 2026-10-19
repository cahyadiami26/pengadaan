<?php
/**
 * Helper domPDF (install manual, tanpa composer).
 * Ekstrak release: https://github.com/dompdf/dompdf/releases -> folder /dompdf
 */

// Autoloader bawaan release domPDF (menangani vendor mahatma/resvg dll secara internal)
require_once BASE_PATH . '/dompdf/dompdf/autoload.inc.php';

function pdf_init(): Dompdf\Dompdf
{
    $dompdf = new Dompdf\Dompdf([
        'isRemoteEnabled' => true,   // logo & avatar dimuat dari URL
        'isHtml5ParserEnabled' => true,
        'defaultFont' => 'DejaVu Sans',
        'chroot' => [BASE_PATH, BASE_PATH . '/dompdf'],
    ]);
    return $dompdf;
}

/** Render HTML menjadi PDF dan kirim ke browser (download/inline) */
function pdf_output(string $html, string $filename, bool $inline = false, string $paper = 'A4', string $orientation = 'portrait'): void
{
    $dompdf = pdf_init();
    $dompdf->setPaper($paper, $orientation);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => !$inline]);
    exit;
}

/** Header/cover standar dokumen PDF LJU */
function pdf_doc_header(string $title, string $subtitle = ''): string
{
    $logo = LOGO_BASE_URL . 'logo.png';
    $h  = '<div style="display:block;border-bottom:3px solid #0f2a43;padding-bottom:10px;margin-bottom:18px">';
    $h .= '<table width="100%"><tr>';
    $h .= '<td width="70"><img src="' . e($logo) . '" width="56" height="56" style="vertical-align:middle"></td>';
    $h .= '<td><div style="font-size:15px;font-weight:bold;color:#0f2a43">' . e(APP_COMPANY) . '</div>'
        .  '<div style="font-size:10px;color:#6b7280">Sistem Pengadaan Barang/Jasa &middot; ' . e(APP_URL) . '</div></td>';
    $h .= '<td align="right" style="font-size:9px;color:#6b7280">Dicetak: ' . date('d-m-Y H:i') . '</td>';
    $h .= '</tr></table></div>';
    $h .= '<div style="text-align:center;margin-bottom:14px"><div style="font-size:16px;font-weight:bold;text-transform:uppercase">' . e($title) . '</div>';
    if ($subtitle !== '') $h .= '<div style="font-size:11px;color:#475569">' . e($subtitle) . '</div>';
    $h .= '</div>';
    return $h;
}

/** Bungkus body html dengan css dasar tabel */
function pdf_wrap(string $inner): string
{
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
      body{font-family:"DejaVu Sans",sans-serif;font-size:10.5px;color:#1f2937}
      table.data{width:100%;border-collapse:collapse;margin-top:8px}
      table.data th{background:#0f2a43;color:#fff;padding:6px;font-size:9.5px;text-align:left;border:1px solid #0f2a43}
      table.data td{border:1px solid #cbd5e1;padding:5px 6px;font-size:10px}
      table.data tr:nth-child(even) td{background:#f4f6fa}
      .muted{color:#6b7280;font-size:9px}
      .right{text-align:right}.center{text-align:center}
      @page{margin:22mm 14mm}
      .footer-doc{position:fixed;bottom:-6mm;left:0;right:0;text-align:center;font-size:8px;color:#9ca3af}
    </style></head><body>' . $inner . '</body></html>';
}
