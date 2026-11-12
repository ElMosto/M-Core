<?php
/**
 * TimbraXlsx — generador mínimo de archivos .xlsx (Office Open XML) usando
 * únicamente la extensión `zip` que trae PHP por defecto. No depende de
 * PhpSpreadsheet ni de ninguna librería externa — consistente con la
 * filosofía "ligero, sin dependencias pesadas" del resto del proyecto.
 *
 * Soporta lo mínimo necesario para dibujar un diagrama de Gantt: celdas de
 * texto, un juego fijo de estilos (título, encabezados, barras de color) y
 * ancho de columnas. Todas las celdas se escriben como texto en línea
 * (inlineStr), así que no hace falta tabla de "shared strings".
 */
final class TimbraXlsx
{
    /** @var array<int, array<int, array{text:string, style:int}>> */
    private array $celdas = [];
    /** @var array<int, float> ancho por columna (1-based) */
    private array $anchos = [];
    private int $maxFila = 0;
    private int $maxCol = 0;

    // Índices de estilo fijos — deben coincidir con el orden de cellXfs en styles.xml.
    public const S_NORMAL          = 0;
    public const S_TITULO          = 1;
    public const S_SUBTITULO       = 2;
    public const S_ETAPA           = 3;
    public const S_ENCABEZADO      = 4;
    public const S_BARRA_COMPLETADA = 5;
    public const S_BARRA_PROGRESO   = 6;
    public const S_BARRA_PENDIENTE  = 7;
    public const S_CELDA_GRID       = 8;

    private int $freezeX = 0;
    private int $freezeY = 0;
    private string $freezeTopLeft = '';

    public function congelarPanel(int $xSplit, int $ySplit, string $topLeftCell): void
    {
        $this->freezeX = $xSplit;
        $this->freezeY = $ySplit;
        $this->freezeTopLeft = $topLeftCell;
    }

    public function setCell(int $fila, int $col, string $texto, int $estilo = self::S_NORMAL): void
    {
        $this->celdas[$fila][$col] = ['text' => $texto, 'style' => $estilo];
        $this->maxFila = max($this->maxFila, $fila);
        $this->maxCol = max($this->maxCol, $col);
    }

    public function setAnchoColumna(int $col, float $ancho): void
    {
        $this->anchos[$col] = $ancho;
    }

    public static function colLetra(int $col): string
    {
        $letra = '';
        while ($col > 0) {
            $resto = ($col - 1) % 26;
            $letra = chr(65 + $resto) . $letra;
            $col = intdiv($col - 1, 26);
        }
        return $letra;
    }

    private static function xml(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function construirSheetXml(): string
    {
        $cols = '';
        foreach ($this->anchos as $col => $ancho) {
            $cols .= sprintf('<col min="%1$d" max="%1$d" width="%2$s" customWidth="1"/>', $col, $ancho);
        }

        $filasXml = '';
        for ($f = 1; $f <= $this->maxFila; $f++) {
            $celdasFila = $this->celdas[$f] ?? [];
            if (!$celdasFila) {
                continue;
            }
            $celdasXml = '';
            foreach ($celdasFila as $c => $celda) {
                $ref = self::colLetra($c) . $f;
                $texto = self::xml($celda['text']);
                $celdasXml .= sprintf(
                    '<c r="%s" t="inlineStr" s="%d"><is><t xml:space="preserve">%s</t></is></c>',
                    $ref, $celda['style'], $texto
                );
            }
            $filasXml .= sprintf('<row r="%d">%s</row>', $f, $celdasXml);
        }

        $dim = 'A1:' . self::colLetra(max($this->maxCol, 1)) . max($this->maxFila, 1);

        $sheetViews = '<sheetViews><sheetView workbookViewId="0">';
        if ($this->freezeX > 0 || $this->freezeY > 0) {
            $sheetViews .= sprintf(
                '<pane xSplit="%d" ySplit="%d" topLeftCell="%s" activePane="bottomRight" state="frozen"/>',
                $this->freezeX, $this->freezeY, self::xml($this->freezeTopLeft)
            );
        }
        $sheetViews .= '</sheetView></sheetViews>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<dimension ref="' . $dim . '"/>' .
            $sheetViews .
            '<cols>' . $cols . '</cols>' .
            '<sheetData>' . $filasXml . '</sheetData>' .
            '</worksheet>';
    }

    private function stylesXml(): string
    {
        // Paleta consistente con el tema visual del front (tinta verde-sello,
        // ámbar para "en progreso", gris-azulado para "pendiente").
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts count="5">' .
            '<font><sz val="10"/><name val="Calibri"/></font>' .
            '<font><b/><sz val="14"/><color rgb="FF12211F"/><name val="Calibri"/></font>' .
            '<font><sz val="9"/><color rgb="FF5C6B68"/><name val="Calibri"/></font>' .
            '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>' .
            '<font><b/><sz val="10"/><color rgb="FF12211F"/><name val="Calibri"/></font>' .
        '</fonts>' .
        '<fills count="7">' .
            '<fill><patternFill patternType="none"/></fill>' .
            '<fill><patternFill patternType="gray125"/></fill>' .
            '<fill><patternFill patternType="solid"><fgColor rgb="FFE7ECEA"/></patternFill></fill>' . // etapa
            '<fill><patternFill patternType="solid"><fgColor rgb="FF12211F"/></patternFill></fill>' . // encabezado
            '<fill><patternFill patternType="solid"><fgColor rgb="FF0F6E5C"/></patternFill></fill>' . // completada
            '<fill><patternFill patternType="solid"><fgColor rgb="FFC9832B"/></patternFill></fill>' . // en progreso
            '<fill><patternFill patternType="solid"><fgColor rgb="FF8A9895"/></patternFill></fill>' . // pendiente
        '</fills>' .
        '<borders count="2">' .
            '<border><left/><right/><top/><bottom/><diagonal/></border>' .
            '<border><left style="thin"><color rgb="FFDAE3E0"/></left><right style="thin"><color rgb="FFDAE3E0"/></right><top style="thin"><color rgb="FFDAE3E0"/></top><bottom style="thin"><color rgb="FFDAE3E0"/></bottom><diagonal/></border>' .
        '</borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="9">' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .                                   // 0 normal
            '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' .                     // 1 titulo
            '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>' .                     // 2 subtitulo
            '<xf numFmtId="0" fontId="4" fillId="2" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyFont="1"/>' .            // 3 etapa
            '<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyFont="1"/>' .            // 4 encabezado
            '<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' .      // 5 barra completada
            '<xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' .      // 6 barra en progreso
            '<xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' .      // 7 barra pendiente
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>' .                    // 8 celda de grid
        '</cellXfs>' .
        '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
        '</styleSheet>';
    }

    public function guardar(string $rutaDestino, string $nombreHoja = 'Gantt'): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($rutaDestino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '</Relationships>');

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="' . self::xml($nombreHoja) . '" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>');

        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->construirSheetXml());

        return $zip->close();
    }
}
