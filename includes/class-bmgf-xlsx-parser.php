<?php
/**
 * BMGF XLSX/CSV Parser
 * Parses XLSX files natively using ZipArchive + SimpleXML (no external dependencies)
 * Also handles CSV files via fgetcsv()
 */

if (!defined('ABSPATH')) {
    exit;
}

class BMGF_XLSX_Parser {
    private const BUILD_ID = 'parser-domxpath-2026-02-18-02';

    /**
     * Build identifier for runtime diagnostics.
     */
    public static function build_id(): string {
        return self::BUILD_ID;
    }

    /**
     * Parse a file (XLSX or CSV) and return headers + rows
     *
     * @param string $file_path Absolute path to the file
     * @return array ['headers' => [...], 'rows' => [[...], ...]]
     * @throws Exception on parse failure
     */
    public static function parse(string $file_path, string $declared_ext = '', string $preferred_sheet = ''): array {
        if (!file_exists($file_path)) {
            throw new Exception('File not found: ' . basename($file_path));
        }

        // Detect format by content, not just extension (tmp files have no extension)
        $ext = strtolower(trim($declared_ext));
        if ($ext === '') {
            $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        }

        // If no extension (temp file), try to detect by content
        if ($ext === '' || $ext === 'tmp') {
            $ext = self::detect_format($file_path);
        }

        // Increase limits for large files
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        if ($ext === 'xlsx') {
            return self::parse_xlsx($file_path, $preferred_sheet);
        } elseif ($ext === 'csv') {
            return self::parse_csv($file_path);
        }

        // Last resort: try XLSX first, then CSV
        try {
            return self::parse_xlsx($file_path, $preferred_sheet);
        } catch (Exception $e) {
            try {
                return self::parse_csv($file_path);
            } catch (Exception $e2) {
                throw new Exception('Could not parse file as XLSX or CSV. XLSX error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Detect file format by reading magic bytes
     */
    private static function detect_format(string $file_path): string {
        $handle = fopen($file_path, 'rb');
        if ($handle === false) return '';

        $header = fread($handle, 4);
        fclose($handle);

        // XLSX files are ZIP archives (PK magic bytes)
        if ($header !== false && substr($header, 0, 2) === "PK") {
            return 'xlsx';
        }

        return 'csv';
    }

    /**
     * Parse XLSX file using ZipArchive + SimpleXML
     */
    private static function parse_xlsx(string $file_path, string $preferred_sheet = ''): array {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive PHP extension is required to parse XLSX files. Contact your hosting provider.');
        }

        $zip = new ZipArchive();
        $result = $zip->open($file_path);
        if ($result !== true) {
            throw new Exception('Could not open XLSX file (error code: ' . $result . '). The file may be corrupted.');
        }

        // Read shared strings
        $shared_strings = self::read_shared_strings($zip);

        // Try preferred sheet first, then any other worksheet as fallback.
        $sheet_candidates = self::get_sheet_candidates($zip, $preferred_sheet);
        $last_error = '';

        foreach ($sheet_candidates as $sheet_file) {
            $sheet_xml = $zip->getFromName($sheet_file);
            if ($sheet_xml === false) {
                $last_error = 'Could not read worksheet "' . $sheet_file . '" from XLSX file.';
                continue;
            }

            try {
                $parsed = self::parse_sheet_xml($sheet_xml, $shared_strings);
                if (!empty($parsed['rows'])) {
                    $zip->close();
                    return $parsed;
                }
                $last_error = 'Worksheet "' . $sheet_file . '" contains no data rows.';
            } catch (Exception $e) {
                $last_error = $e->getMessage();
            }
        }

        $zip->close();
        throw new Exception($last_error !== '' ? $last_error : 'No worksheet with data rows was found in XLSX file.');
    }

    /**
     * Build ordered worksheet candidate list: preferred target first, then all worksheets.
     */
    private static function get_sheet_candidates(ZipArchive $zip, string $preferred_sheet = ''): array {
        $candidates = [];

        try {
            $preferred = self::find_target_sheet($zip, $preferred_sheet);
            if ($preferred !== '') {
                $candidates[] = $preferred;
            }
        } catch (Exception $e) {
            // Ignore and continue with scan fallback below.
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name) && !in_array($name, $candidates, true)) {
                $candidates[] = $name;
            }
        }

        if (empty($candidates)) {
            throw new Exception('No worksheet found in XLSX file.');
        }

        return $candidates;
    }

    /**
     * Read shared strings from XLSX
     */
    private static function read_shared_strings(ZipArchive $zip): array {
        $shared_strings = [];
        $ss_xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss_xml === false) {
            return $shared_strings;
        }

        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            return $shared_strings;
        }

        $dom = new DOMDocument();
        $loaded = @$dom->loadXML($ss_xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        if (!$loaded) {
            return $shared_strings;
        }

        $xpath = new DOMXPath($dom);
        $si_nodes = $xpath->query('/*[local-name()="sst"]/*[local-name()="si"]');
        if ($si_nodes === false || $si_nodes->length === 0) {
            return $shared_strings;
        }

        /** @var DOMElement $si */
        foreach ($si_nodes as $si) {
            $t_nodes = $xpath->query('.//*[local-name()="t"]', $si);
            $value = '';
            if ($t_nodes !== false) {
                foreach ($t_nodes as $t) {
                    $value .= (string)$t->nodeValue;
                }
            }
            $shared_strings[] = $value;
        }

        return $shared_strings;
    }

    /**
     * Find the target worksheet file path inside the XLSX zip
     * Prefers sheets named "All_*" or "All *", falls back to first sheet
     */
    private static function find_target_sheet(ZipArchive $zip, string $preferred_sheet = ''): string {
        // Try to read workbook to find the right sheet
        $workbook_xml = $zip->getFromName('xl/workbook.xml');
        if ($workbook_xml === false) {
            // Fallback: try common sheet paths
            return self::find_sheet_fallback($zip);
        }

        $wb = @new SimpleXMLElement($workbook_xml);
        if ($wb === false) {
            return self::find_sheet_fallback($zip);
        }

        $ns = $wb->getNamespaces(true);
        $default_ns = $ns[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $wb->registerXPathNamespace('s', $default_ns);

        $sheets = $wb->xpath('//s:sheet');
        if (empty($sheets)) {
            return self::find_sheet_fallback($zip);
        }

        // Find target sheet:
        // 1) explicit preferred sheet name (exact, case-insensitive)
        // 2) generic "All_*" fallback
        $target_index = 0;
        $preferred_sheet = strtolower(trim($preferred_sheet));

        if ($preferred_sheet !== '') {
            foreach ($sheets as $idx => $sheet) {
                $name = strtolower(trim((string)$sheet['name']));
                if ($name === $preferred_sheet) {
                    $target_index = $idx;
                    break;
                }
            }
        } else {
            foreach ($sheets as $idx => $sheet) {
                $name = (string)$sheet['name'];
                if (stripos($name, 'All_') === 0 || stripos($name, 'All ') === 0) {
                    $target_index = $idx;
                    break;
                }
            }
        }

        // Try to resolve via relationships
        $target_sheet = $sheets[$target_index];
        $r_id = self::get_relationship_id($target_sheet);

        if ($r_id !== '') {
            $resolved = self::resolve_relationship($zip, $r_id);
            if ($resolved !== '' && $zip->getFromName($resolved) !== false) {
                return $resolved;
            }
        }

        // Fallback: use sheet index + 1 as filename
        $sheet_num = $target_index + 1;
        $candidates = [
            'xl/worksheets/sheet' . $sheet_num . '.xml',
            'xl/worksheets/sheet' . ($target_index + 2) . '.xml', // sometimes 1-indexed differently
        ];

        foreach ($candidates as $candidate) {
            if ($zip->getFromName($candidate) !== false) {
                return $candidate;
            }
        }

        return self::find_sheet_fallback($zip);
    }

    /**
     * Get the r:id attribute from a sheet element
     */
    private static function get_relationship_id(SimpleXMLElement $sheet): string {
        // Try different namespace approaches
        $r_ns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        $attrs = $sheet->attributes($r_ns);
        if ($attrs && isset($attrs['id'])) {
            return (string)$attrs['id'];
        }

        // Try r:id directly
        $namespaces = $sheet->getNamespaces(true);
        foreach ($namespaces as $prefix => $uri) {
            if (strpos($uri, 'relationships') !== false) {
                $attrs = $sheet->attributes($uri);
                if ($attrs && isset($attrs['id'])) {
                    return (string)$attrs['id'];
                }
            }
        }

        return '';
    }

    /**
     * Resolve a relationship ID to a file path
     */
    private static function resolve_relationship(ZipArchive $zip, string $r_id): string {
        $rels_xml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($rels_xml === false) {
            return '';
        }

        $rels = @new SimpleXMLElement($rels_xml);
        if ($rels === false) {
            return '';
        }

        foreach ($rels->children() as $rel) {
            if ((string)$rel['Id'] === $r_id) {
                $target = (string)$rel['Target'];
                // Handle relative paths
                if (strpos($target, '/') === 0) {
                    return ltrim($target, '/');
                }
                return 'xl/' . $target;
            }
        }

        return '';
    }

    /**
     * Fallback: find any worksheet by scanning zip entries
     */
    private static function find_sheet_fallback(ZipArchive $zip): string {
        // Try common paths
        $candidates = ['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'];

        foreach ($candidates as $candidate) {
            if ($zip->getFromName($candidate) !== false) {
                return $candidate;
            }
        }

        // Scan all entries
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#xl/worksheets/sheet\d+\.xml#', $name)) {
                return $name;
            }
        }

        throw new Exception('No worksheet found in XLSX file.');
    }

    /**
     * Parse sheet XML into headers and rows
     */
    private static function parse_sheet_xml(string $sheet_xml, array $shared_strings): array {
        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            throw new Exception('DOMDocument PHP extension is required to parse worksheet XML.');
        }

        $dom = new DOMDocument();
        $loaded = @$dom->loadXML($sheet_xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        if (!$loaded) {
            throw new Exception('Could not parse worksheet XML.');
        }

        $xpath = new DOMXPath($dom);
        $xml_rows = $xpath->query('/*[local-name()="worksheet"]/*[local-name()="sheetData"]/*[local-name()="row"]');
        if ($xml_rows === false || $xml_rows->length === 0) {
            throw new Exception('No data found in the worksheet.');
        }

        $all_rows = [];
        $max_col = 0;

        /** @var DOMElement $xml_row */
        foreach ($xml_rows as $xml_row) {
            $row_data = [];
            $cells = $xpath->query('./*[local-name()="c"]', $xml_row);
            if ($cells === false) {
                $all_rows[] = $row_data;
                continue;
            }

            /** @var DOMElement $cell */
            foreach ($cells as $cell) {
                $ref = $cell->getAttribute('r');
                if ($ref === '') {
                    continue;
                }

                $col_index = self::col_ref_to_index($ref);
                $type = $cell->getAttribute('t');
                $value = '';

                $v_node = $xpath->query('./*[local-name()="v"]', $cell);
                if ($v_node !== false && $v_node->length > 0) {
                    $raw = (string)$v_node->item(0)->nodeValue;
                    if ($type === 's') {
                        $idx = (int)$raw;
                        $value = $shared_strings[$idx] ?? '';
                    } else {
                        $value = $raw;
                    }
                } else {
                    // inline string (including rich text runs)
                    $t_nodes = $xpath->query('./*[local-name()="is"]//*[local-name()="t"]', $cell);
                    if ($t_nodes !== false && $t_nodes->length > 0) {
                        $parts = [];
                        foreach ($t_nodes as $t_node) {
                            $parts[] = (string)$t_node->nodeValue;
                        }
                        $value = implode('', $parts);
                    }
                }

                $row_data[$col_index] = $value;
                if ($col_index > $max_col) {
                    $max_col = $col_index;
                }
            }

            $all_rows[] = $row_data;
        }

        if (empty($all_rows)) {
            throw new Exception('No data rows found in the worksheet.');
        }

        // Normalize: fill missing columns with empty strings
        $headers_raw = $all_rows[0];
        $headers = [];
        for ($i = 0; $i <= $max_col; $i++) {
            $headers[] = trim($headers_raw[$i] ?? '');
        }

        $rows = [];
        for ($r = 1; $r < count($all_rows); $r++) {
            $row = [];
            for ($i = 0; $i <= $max_col; $i++) {
                $row[] = $all_rows[$r][$i] ?? '';
            }
            // Skip completely empty rows
            $non_empty = array_filter($row, fn($v) => $v !== '');
            if (!empty($non_empty)) {
                $rows[] = $row;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Parse CSV file
     */
    private static function parse_csv(string $file_path): array {
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            throw new Exception('Could not open CSV file.');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new Exception('Could not read CSV headers.');
        }

        $headers = array_map('trim', $headers);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            // Pad row to match headers length
            while (count($row) < count($headers)) {
                $row[] = '';
            }
            $non_empty = array_filter($row, fn($v) => trim($v) !== '');
            if (!empty($non_empty)) {
                $rows[] = $row;
            }
        }

        fclose($handle);
        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Convert column reference (e.g., "A1", "B2", "AA1", "AZ123") to 0-based column index
     */
    private static function col_ref_to_index(string $ref): int {
        preg_match('/^([A-Z]+)/', $ref, $matches);
        $letters = $matches[1] ?? 'A';

        $index = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    /**
     * Validate that required columns exist in headers
     *
     * @param array $headers The parsed headers
     * @param array $required List of required column names
     * @return array Missing column names (empty if all found)
     */
    public static function validate_columns(array $headers, array $required): array {
        $normalized = array_map(fn($h) => strtolower(trim($h)), $headers);
        $missing = [];

        foreach ($required as $col) {
            // Allow either a single required column name or a list of acceptable aliases.
            // Example: ['Publisher_Norm', 'Publisher'] means "either column is acceptable".
            $candidates = is_array($col) ? $col : [$col];

            $found = false;
            foreach ($candidates as $candidate) {
                if (in_array(strtolower(trim((string)$candidate)), $normalized, true)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // Keep the first candidate as the "missing" label to avoid noisy messages.
                $missing[] = (string)($candidates[0] ?? '');
            }
        }

        return $missing;
    }

    /**
     * Get column index by name (case-insensitive)
     */
    public static function col_index(array $headers, string $name): int {
        $normalized = array_map(fn($h) => strtolower(trim($h)), $headers);
        $idx = array_search(strtolower(trim($name)), $normalized, true);
        return $idx !== false ? $idx : -1;
    }
}
