<?php
require 'vendor/autoload.php';

use Elastic\Elasticsearch\ClientBuilder;
use League\Csv\Reader;

$INPUT_CSV = "test.csv";
$NDJSON = "test.ndjson";
$PY = "embed_batch.py"; // Python script for batch embeddings
$ES_INDEX = "competitor_offers_test";

// --- Elasticsearch connection ---
$client = ClientBuilder::create()
    ->setHosts(['https://localhost:9200'])
    ->setBasicAuthentication('elastic', 'YOUR PASSWORD')
    ->setSSLVerification(false)
    ->build();

// --- Helper functions ---
function normalizePrice($v){ 
    if($v===""||$v===null) return null; 
    $c=preg_replace('/[^\d.]/','',$v); 
    return is_numeric($c)?(int)$c:null; 
}
function normalizeGoldKarat($v){ 
    if(!$v) return null; 
    preg_match('/\d+/',$v,$m); 
    return isset($m[0])?intval($m[0]):null; 
}
function normalizeRingSize($v){ 
    if(!$v) return null; 
    $s=strtolower(trim($v)); 
    if($s==='click to edit') return 7.0; 
    $c=preg_replace('/[^\d.]/','',$v); 
    return is_numeric($c)?floatval($c):null; 
}
function normalizeCarat($v){
    if(!$v) return null;
    return is_numeric($v)?floatval($v):floatval(preg_replace('/[^\d.]/','',$v));
}
function safeStr($v){ 
    return isset($v) && trim($v)!==''?trim($v):null; 
}

// --- Load CSV ---
$csv = Reader::createFromPath($INPUT_CSV,'r');
$csv->setHeaderOffset(0);
$records = iterator_to_array($csv->getRecords());

// --- Filter rows missing name or price ---
$records = array_filter($records, fn($r) => !empty($r['name']) && !empty($r['price']));
echo "✅ ".count($records)." rows will be uploaded.\n";

// --- Build texts for embedding ---
$texts = [];
$rows = [];
foreach ($records as $i => $row) {
    $rows[$i] = $row; // store original row for later
    $text = implode(" | ", array_filter([
        safeStr($row['name']),
        (safeStr($row['stone_type']).' '.safeStr($row['stone_shape']).' Clarity '.safeStr($row['stone_clarity']).' Color '.safeStr($row['stone_color']).' '.normalizeCarat($row['stone_carat_weight']).' Carat'),
        'Metal: '.safeStr($row['metal_type']).' '.safeStr($row['metal_color']).' '.normalizeGoldKarat($row['gold_karat']),
        'Ring Size: '.normalizeRingSize($row['ring_size']),
        'Category: '.safeStr($row['category']),
        'Source: '.safeStr($row['source'])
    ]));
    $texts[] = $text;
}

// --- Call Python once for all embeddings ---
$input_json = json_encode(['texts' => $texts]);
$descriptors = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
$proc = proc_open("python3 ".escapeshellarg($PY), $descriptors, $pipes);

if(!is_resource($proc)) die("Failed to start Python process");

fwrite($pipes[0], $input_json);
fclose($pipes[0]);

$out = stream_get_contents($pipes[1]);
fclose($pipes[1]);

$err = stream_get_contents($pipes[2]);
fclose($pipes[2]);

$code = proc_close($proc);
if($code!==0) die("Python error: $err");

$embeddings_json = json_decode($out, true);
$all_embeddings = $embeddings_json['embeddings'] ?? [];

// --- Prepare NDJSON and actions ---
$actions = [];
$nd = fopen($NDJSON,'w');

foreach($records as $i => $row){
    $id = "1stdibs-".($i+1);
    $embedding = $all_embeddings[$i] ?? null;

    $doc = [
        'name'=>safeStr($row['name']),
        'price'=>normalizePrice($row['price']),
        'url'=>safeStr($row['url']),
        'stone_type'=>safeStr($row['stone_type']),
        'stone_shape'=>safeStr($row['stone_shape']),
        'stone_clarity'=>safeStr($row['stone_clarity']),
        'stone_color'=>safeStr($row['stone_color']),
        'stone_carat_weight'=>normalizeCarat($row['stone_carat_weight']),
        'metal_type'=>safeStr($row['metal_type']),
        'metal_color'=>safeStr($row['metal_color']),
        'gold_karat'=>normalizeGoldKarat($row['gold_karat']),
        'ring_size'=>normalizeRingSize($row['ring_size']),
        'category'=>safeStr($row['category']),
        'source'=>safeStr($row['source']),
        'created_at' => date('c'), // ISO 8601 timestamp
        'embedding'=>$embedding
    ];

    $actions[] = ['update'=>['_index'=>$ES_INDEX,'_id'=>$id]];
    $actions[] = ['doc'=>$doc,'doc_as_upsert'=>true];

    fwrite($nd, json_encode(['update'=>['_index'=>$ES_INDEX,'_id'=>$id]]) . "\n");
    fwrite($nd, json_encode(['doc'=>$doc,'doc_as_upsert'=>true]) . "\n");
}

fclose($nd);

// --- Bulk upsert ---
try {
    $resp = $client->bulk(['body'=>$actions]);
    echo "✅ Bulk upsert done\n";
    print_r($resp->asArray());
} catch(Throwable $e){
    echo "❌ Bulk failed: ".$e->getMessage().PHP_EOL;
}
