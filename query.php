<?php
// ضبط الهيدر لإرجاع JSON
header('Content-Type: application/json');

// التأكد من أن الطلب هو POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'يجب أن يكون الطلب POST']);
    exit;
}

// قراءة الـ JSON المرسل من الجافاسكريبت
$input = json_decode(file_get_contents('php://input'), true);
$modem_number = $input['modem'] ?? null;

if (empty($modem_number)) {
    echo json_encode(['success' => false, 'message' => 'لم يتم إرسال رقم المودم.']);
    exit;
}

// --- بداية كود الكشط (Scraping) باستخدام PHP cURL ---

$url = "https://adsl-yemen.com/yem4g.php";
$payload = http_build_query([
    'mobile' => $modem_number,
    'action' => 'query'
]);

// 1. تهيئة cURL
$ch = curl_init();

// 2. ضبط الخيارات
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // إرجاع النتيجة كنص
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // مهلة 15 ثانية

// 3. تنفيذ الطلب
$html = curl_exec($ch);

// 4. التحقق من أخطاء cURL (مثل أخطاء الشبكة)
if (curl_errno($ch)) {
    echo json_encode(['success' => false, 'message' => 'خطأ في الاتصال: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

// --- نهاية كود cURL ---

// --- بداية كود تحليل الـ HTML (Parsing) ---

// سنستخدم DOMDocument المدمج في PHP لتحليل الـ HTML
$doc = new DOMDocument();
// نستخدم @ لإخفاء الأخطاء الناتجة عن HTML غير النظيف
@$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);

$xpath = new DOMXPath($doc);

// 1. البحث عن حاوية النتائج
$container = $xpath->query("//div[contains(@class, 'results')]")->item(0);

if (!$container) {
    echo json_encode(['success' => false, 'message' => 'الرقم غير صحيح أو لا توجد بيانات.']);
    exit;
}

// 2. استخراج التفاصيل
$details = [];
$boxes = $xpath->query(".//div[contains(@class, 'result-box')]", $container);

foreach ($boxes as $box) {
    $title_node = $xpath->query(".//div[contains(@class, 'result-title')]", $box)->item(0);
    $value_node = $xpath->query(".//div[contains(@class, 'result-value')]", $box)->item(0);

    if ($title_node && $value_node) {
        $title = trim($title_node->nodeValue);
        $value = trim($value_node->nodeValue);
        if (!empty($title)) {
            $details[$title] = $value;
        }
    }
}

if (empty($details)) {
    echo json_encode(['success' => false, 'message' => 'تم العثور على الصفحة ولكن فشل استخراج البيانات.']);
    exit;
}

// --- إرسال النتيجة الناجحة ---
echo json_encode(['success' => true, 'details' => $details]);

?>