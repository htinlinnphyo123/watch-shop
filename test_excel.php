<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$products = \App\Models\Product::limit(1)->get();
if ($products->count() > 0) {
    $product = $products->first();
    $data = $product->toArray();
    print_r(array_keys($data));
} else {
    echo "No products";
}
