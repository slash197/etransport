<?php

declare(strict_types=1);

/**
 * Records what a reference Schematron processor (Saxon with SchXslt) reports
 * for every test case, in tests/golden/rules.json. The tests then hold the
 * package to it. Run with: make golden
 */

use slash197\eTransport\Tests\Support\Cases;
use slash197\eTransport\Validation\Location;
use slash197\eTransport\Validation\Validator;

require __DIR__ . '/../vendor/autoload.php';

const SAXON = 'java -cp /opt/saxon.jar net.sf.saxon.Transform';
const SVRL = 'http://purl.oclc.org/dsdl/svrl';

$work = sys_get_temp_dir() . '/golden-' . getmypid();
mkdir("{$work}/in", 0777, true);
mkdir("{$work}/out");

$run = static function (string $arguments) use ($work): bool {
    exec(SAXON . " {$arguments} 2>>{$work}/log", $output, $status);

    return $status === 0;
};

if (!$run('-s:' . escapeshellarg(Validator::RULES) . " -xsl:/opt/schxslt/xslt/2.0/pipeline-for-svrl.xsl -o:{$work}/rules.xsl")) {
    fwrite(STDERR, "The rules file could not be compiled:\n" . file_get_contents("{$work}/log"));
    exit(1);
}

$cases = Cases::all();
$files = [];
foreach (array_keys($cases) as $i => $name) {
    $files[$name] = sprintf('%03d', $i);
    file_put_contents("{$work}/in/{$files[$name]}.xml", $cases[$name]);
}

// One run over the directory; a document Saxon cannot finish leaves no output and is retried alone to make sure.
$run("-s:{$work}/in -xsl:{$work}/rules.xsl -o:{$work}/out");

$golden = [];
foreach ($cases as $name => $xml) {
    $out = "{$work}/out/{$files[$name]}.xml";
    if (!is_file($out) && !$run("-s:{$work}/in/{$files[$name]}.xml -xsl:{$work}/rules.xsl -o:{$out}")) {
        $golden[$name] = 'stops';

        continue;
    }

    $document = new DOMDocument();
    $document->loadXML($xml);
    $source = new DOMXPath($document);
    $source->registerNamespace('x', Cases::NS);

    $report = new DOMDocument();
    $report->load($out);
    $svrl = new DOMXPath($report);
    $svrl->registerNamespace('s', SVRL);

    $failed = [];
    foreach ($svrl->query('//s:failed-assert') ?: [] as $assert) {
        assert($assert instanceof DOMElement);
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $svrl->evaluate('string(s:text)', $assert)));

        // Two asserts in ANAF's file carry their id in the text only.
        $id = $assert->getAttribute('id');
        if ($id === '' && preg_match('/\[([A-Z]+(?:-[A-Z0-9]+)+)\]/', $text, $match) === 1) {
            $id = $match[1];
        }

        $path = str_replace(['Q{' . Cases::NS . '}', 'Q{}'], ['x:', ''], $assert->getAttribute('location'));
        $nodes = $source->query($path);
        $node = $nodes === false ? null : $nodes->item(0);
        if (!$node instanceof DOMNode) {
            fwrite(STDERR, "Cannot find {$path} in case \"{$name}\".\n");
            exit(1);
        }

        $failed[] = [
            'rule' => $id,
            'location' => Location::of($node),
            'message' => (string) preg_replace('/^.*?\[' . preg_quote($id, '/') . '\]\s*-\s*/u', '', $text, 1),
        ];
    }

    usort($failed, static fn (array $a, array $b): int => [$a['rule'], $a['location']] <=> [$b['rule'], $b['location']]);
    $golden[$name] = $failed;
}

file_put_contents(
    __DIR__ . '/../tests/golden/rules.json',
    json_encode($golden, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
);

$stops = count(array_filter($golden, 'is_string'));
echo count($golden) . " cases recorded, {$stops} of which the reference processor could not finish.\n";
