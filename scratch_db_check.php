<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Exam;
use App\Models\ExamCategory;

echo "Exam Categories:\n";
foreach (ExamCategory::all() as $cat) {
    echo "ID: {$cat->id}, Name: {$cat->name}, Slug: {$cat->slug}\n";
}

echo "\nUnique Exam Types in Exams table:\n";
$types = Exam::select('exam_type')->distinct()->pluck('exam_type');
foreach ($types as $type) {
    echo "Exam Type: '$type'\n";
}
