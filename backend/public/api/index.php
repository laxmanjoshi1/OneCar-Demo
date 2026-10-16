<?php
declare(strict_types=1);
$configFile = dirname(__DIR__, 2) . '/config.php';
if (!is_file($configFile)) $configFile = dirname(__DIR__, 2) . '/config.example.php';
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'Asia/Kathmandu');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin = (string)($config['frontend_origin'] ?? 'http://localhost:5173');
if ($origin !== '' && hash_equals($allowedOrigin, $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
$secure = (bool)($config['session_secure'] ?? false);
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>$config['session_samesite'] ?? 'Lax']);
session_start();

function db(): PDO {
    static $pdo = null; global $config;
    if ($pdo instanceof PDO) return $pdo;
    $c = $config['db'];
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
    return $pdo;
}
function body(): array { $v = json_decode(file_get_contents('php://input') ?: '{}', true); return is_array($v) ? $v : []; }
function respond($data, int $status=200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function fail(string $message, int $status=400): never { respond(['message'=>$message], $status); }
function admin(): array { if (empty($_SESSION['admin_id'])) fail('Administrator authentication required.', 401); return ['id'=>(int)$_SESSION['admin_id'],'name'=>(string)($_SESSION['admin_name'] ?? 'Administrator'),'email'=>(string)($_SESSION['admin_email'] ?? '')]; }
function one(PDO $pdo, string $sql, array $args=[]): ?array { $s=$pdo->prepare($sql); $s->execute($args); $r=$s->fetch(); return $r ?: null; }
function all(PDO $pdo, string $sql, array $args=[]): array { $s=$pdo->prepare($sql); $s->execute($args); return $s->fetchAll(); }
function jsonBool($v): bool { return filter_var($v, FILTER_VALIDATE_BOOLEAN); }
function fareFor(float $km, string $category, string $rideType, PDO $pdo): array {
    $rule = one($pdo, 'SELECT * FROM pricing_rules WHERE vehicle_category=? AND active=1 LIMIT 1', [$category]);
    if (!$rule) fail('The selected vehicle category has no active pricing rule.', 422);
    $surcharge = $rideType === 'Airport' ? (float)$rule['airport_surcharge'] : ($rideType === 'Outstation' ? (float)$rule['outstation_surcharge'] : 0.0);
    $fare = (int)ceil((float)$rule['base_fare'] + $km * (float)$rule['per_km'] + $surcharge);
    return ['fare'=>$fare,'rule'=>$rule,'surcharge'=>$surcharge];
}
$method = $_SERVER['REQUEST_METHOD'];
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$pos = strpos($uriPath, '/api');
$route = $pos !== false ? substr($uriPath, $pos + 4) : $uriPath;
$route = '/' . trim($route, '/'); if ($route === '/') $route='/';
try {
    $pdo = db();
    if ($route === '/health' && $method === 'GET') respond(['ok'=>true,'service'=>'OneCar demo API','mode'=>'educational demo']);
    if ($route === '/pricing/config' && $method === 'GET') {
        $rules=all($pdo,'SELECT vehicle_category AS name, vehicle_category AS category, base_fare AS base, per_km AS perKm, airport_surcharge, outstation_surcharge, active FROM pricing_rules WHERE active=1 ORDER BY sort_order');
        $vehicles=[]; foreach($rules as $r){ $vehicles[$r['name']]=['base'=>(float)$r['base'],'perKm'=>(float)$r['perKm'],'airport_surcharge'=>(float)$r['airport_surcharge'],'outstation_surcharge'=>(float)$r['outstation_surcharge']]; }
        respond(['data'=>['vehicles'=>$vehicles,'currency'=>'NPR','commission_rate'=>(float)$config['commission_rate']]]);
    }
    if ($route === '/pricing/estimate' && $method === 'POST') {
        $b=body(); $km=filter_var($b['distance_km'] ?? null,FILTER_VALIDATE_FLOAT); if($km===false || $km<=0 || $km>1500) fail('Distance must be between 0 and 1500 km.');
        $r= fareFor((float)$km,(string)($b['vehicle_category']??''),(string)($b['ride_type']??'City ride'),$pdo);
        respond(['data'=>['distance_km'=>round((float)$km,2),'duration_minutes'=>max(0,(int)($b['duration_minutes']??0)),'total_fare'=>$r['fare'],'currency'=>'NPR','pricing_rule'=>['base'=>(float)$r['rule']['base_fare'],'per_km'=>(float)$r['rule']['per_km'],'surcharge'=>$r['surcharge']]]]);
    }
    if ($route === '/bookings' && $method === 'POST') {
        $b=body();
        foreach(['customer_name','customer_phone','pickup_location','destination','pickup_date','pickup_time','vehicle_category','ride_type','payment_method'] as $key) if(!isset($b[$key]) || trim((string)$b[$key])==='') fail("Missing required field: $key");
        if(strlen(trim((string)$b['customer_name']))>120 || strlen(trim((string)$b['customer_phone']))>40) fail('Customer details are too long.');
        if(!in_array($b['payment_method'],['cash','demo_online'],true)) fail('Unsupported demo payment method.');
        $pickupAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', (string)$b['pickup_date'].' '.(string)$b['pickup_time'], new DateTimeZone($config['timezone']));
        if(!$pickupAt || $pickupAt->format('Y-m-d H:i') !== $b['pickup_date'].' '.$b['pickup_time'] || $pickupAt <= new DateTimeImmutable('now',new DateTimeZone($config['timezone']))) fail('Pickup date and time must be in the future.');
        $km=filter_var($b['distance_km']??null,FILTER_VALIDATE_FLOAT); if($km===false || $km<=0 || $km>1500) fail('Invalid route distance.');
        foreach(['pickup_lat','pickup_lng','destination_lat','destination_lng'] as $k) if(!isset($b[$k]) || !is_numeric($b[$k])) fail("Missing route coordinate: $k");
        $coords=[(float)$b['pickup_lat'],(float)$b['pickup_lng'],(float)$b['destination_lat'],(float)$b['destination_lng']]; if($coords[0]<-90||$coords[0]>90||$coords[2]<-90||$coords[2]>90||$coords[1]<-180||$coords[1]>180||$coords[3]<-180||$coords[3]>180) fail('Invalid coordinates.');
        $calc=fareFor((float)$km,(string)$b['vehicle_category'],(string)$b['ride_type'],$pdo); // Never trust fare sent by browser.
        $pdo->beginTransaction();
        try {
            $email=isset($b['customer_email']) && filter_var($b['customer_email'],FILTER_VALIDATE_EMAIL) ? trim((string)$b['customer_email']) : null;
            $customer=one($pdo,'SELECT id FROM customers WHERE phone=? LIMIT 1',[trim((string)$b['customer_phone'])]);
            if($customer){$customerId=(int)$customer['id'];$st=$pdo->prepare('UPDATE customers SET full_name=?,email=COALESCE(?,email),updated_at=NOW() WHERE id=?');$st->execute([trim((string)$b['customer_name']),$email,$customerId]);}
            else {$st=$pdo->prepare('INSERT INTO customers(full_name,phone,email) VALUES(?,?,?)');$st->execute([trim((string)$b['customer_name']),trim((string)$b['customer_phone']),$email]);$customerId=(int)$pdo->lastInsertId();}
            $reference='OC-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
            $st=$pdo->prepare('INSERT INTO bookings(booking_reference,customer_id,ride_type,vehicle_category,pickup_location,destination,pickup_lat,pickup_lng,destination_lat,destination_lng,distance_km,duration_minutes,pickup_at,total_fare,currency,payment_method,payment_status,status,is_demo) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)');
            $st->execute([$reference,$customerId,(string)$b['ride_type'],(string)$b['vehicle_category'],trim((string)$b['pickup_location']),trim((string)$b['destination']),$coords[0],$coords[1],$coords[2],$coords[3],round((float)$km,3),max(0,(int)($b['duration_minutes']??0)),$pickupAt->format('Y-m-d H:i:s'),$calc['fare'],'NPR',$b['payment_method'],$b['payment_method']==='cash'?'pending_cash':'pending','pending']);
            $bookingId=(int)$pdo->lastInsertId();
            $st=$pdo->prepare('INSERT INTO payments(booking_id,provider,method,amount,currency,status,transaction_reference,is_demo) VALUES(?,?,?,?,?,?,?,1)');
            $st->execute([$bookingId,$b['payment_method']==='cash'?'cash':'demo_gateway',$b['payment_method'],$calc['fare'],'NPR',$b['payment_method']==='cash'?'pending_cash':'pending',$reference]);
            $paymentId=(int)$pdo->lastInsertId();
            $pdo->commit();
        } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
        respond(['data'=>['id'=>$bookingId,'payment_id'=>$paymentId,'booking_reference'=>$reference,'total_fare'=>$calc['fare'],'payment_status'=>$b['payment_method']==='cash'?'pending_cash':'pending','status'=>'pending','storage_mode'=>'mysql']],201);
    }
    if ($route === '/payments/simulate' && $method === 'POST') {
        $b=body(); $id=filter_var($b['booking_id']??null,FILTER_VALIDATE_INT); if(!$id || !in_array($b['outcome']??'success',['success','failed'],true)) fail('Invalid payment simulation request.');
        $booking=one($pdo,'SELECT * FROM bookings WHERE id=? AND is_demo=1',[$id]); if(!$booking) fail('Demo booking not found.',404);
        $status=($b['outcome']??'success')==='success'?'paid_demo':'failed';
        $pdo->beginTransaction(); try {
            $pdo->prepare('UPDATE payments SET status=?,provider_reference=?,verified_at=NOW(),updated_at=NOW() WHERE booking_id=?')->execute([$status,'SIM-'.bin2hex(random_bytes(5)),$id]);
            $pdo->prepare('UPDATE bookings SET payment_status=?,updated_at=NOW() WHERE id=?')->execute([$status,$id]);
            $pdo->prepare('INSERT INTO transactions(booking_id,type,amount,currency,status,description,is_demo) VALUES(?,?,?,?,?,?,1)')->execute([$id,'payment_simulation',(float)$booking['total_fare'],'NPR',$status,'Simulated payment; no real money moved']);
            $pdo->commit();
        } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        respond(['data'=>['booking_id'=>$id,'status'=>$status,'message'=>'Simulation only. No real payment was taken.']]);
    }
    if ($route === '/admin/login' && $method === 'POST') {
        $b=body(); if(!filter_var($b['email']??'',FILTER_VALIDATE_EMAIL) || !is_string($b['password']??null)) fail('Enter a valid email and password.',422);
        $user=one($pdo,'SELECT id,full_name,email,password_hash,role,active FROM admins WHERE email=? LIMIT 1',[strtolower(trim($b['email']))]);
        if(!$user || !$user['active'] || !password_verify($b['password'],$user['password_hash'])) fail('Invalid credentials.',401);
        session_regenerate_id(true); $_SESSION['admin_id']=(int)$user['id'];$_SESSION['admin_name']=$user['full_name'];$_SESSION['admin_email']=$user['email'];$_SESSION['admin_role']=$user['role'];
        respond(['data'=>['id'=>(int)$user['id'],'name'=>$user['full_name'],'email'=>$user['email'],'role'=>$user['role']]]);
    }
    if ($route === '/admin/me' && $method === 'GET') respond(['data'=>admin()]);
    if ($route === '/admin/logout' && $method === 'POST') { admin(); $_SESSION=[]; if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',$p['secure'],$p['httponly']);} session_destroy(); respond(['message'=>'Signed out.']); }
    if (str_starts_with($route,'/admin')) admin();
    if ($route === '/admin/dashboard' && $method === 'GET') {
        $stats=one($pdo,"SELECT COUNT(*) total_bookings, SUM(status='pending') pending_bookings, SUM(status='confirmed') confirmed_bookings, SUM(status='completed') completed_bookings, SUM(payment_status IN ('pending','pending_cash')) pending_payments, COALESCE(SUM(CASE WHEN status='completed' THEN total_fare ELSE 0 END),0) completed_fares FROM bookings") ?: [];
        $stats['active_vehicles']=(int)(one($pdo,'SELECT COUNT(*) n FROM vehicles WHERE active=1')['n']??0);
        $stats['commission_rate']=(float)$config['commission_rate']; $stats['estimated_commission']=round((float)($stats['completed_fares']??0)*(float)$config['commission_rate'],2);
        respond(['data'=>$stats]);
    }
    if ($route === '/admin/bookings' && $method === 'GET') respond(['data'=>all($pdo,'SELECT b.*,c.full_name customer_name,c.phone customer_phone,c.email customer_email FROM bookings b JOIN customers c ON c.id=b.customer_id ORDER BY b.created_at DESC LIMIT 500')]);
    if (preg_match('#^/admin/bookings/(\d+)/status$#',$route,$m) && $method === 'PATCH') {
        $b=body();$status=$b['status']??'';if(!in_array($status,['pending','confirmed','cancelled','completed'],true))fail('Invalid booking status.');
        $booking=one($pdo,'SELECT * FROM bookings WHERE id=?',[(int)$m[1]]);if(!$booking)fail('Booking not found.',404);
        if($booking['status']==='cancelled' && $status!=='cancelled')fail('Cancelled bookings cannot be reopened in this demo.',409);
        if($status==='completed' && $booking['payment_status']==='failed')fail('A failed online payment must be resolved before completing this booking.',409);
        $pdo->beginTransaction();try{
          $pdo->prepare('UPDATE bookings SET status=?,updated_at=NOW() WHERE id=?')->execute([$status,(int)$m[1]]);
          if($status==='completed' && $booking['status']!=='completed'){
            $gross=(float)$booking['total_fare'];$commission=round($gross*(float)$config['commission_rate'],2);$net=round($gross-$commission,2);
            $pdo->prepare('INSERT INTO driver_earnings(booking_id,driver_id,gross_fare,commission_amount,net_earnings,status,is_demo) VALUES(?,NULL,?,?,?,?,1) ON DUPLICATE KEY UPDATE gross_fare=VALUES(gross_fare),commission_amount=VALUES(commission_amount),net_earnings=VALUES(net_earnings)')->execute([(int)$m[1],$gross,$commission,$net,'unassigned']);
            $pdo->prepare('INSERT INTO transactions(booking_id,type,amount,currency,status,description,is_demo) VALUES(?,?,?,?,?,?,1)')->execute([(int)$m[1],'platform_commission',$commission,'NPR','recorded','Demo commission accrual; not actual cash received']);
          }
          $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        respond(['message'=>'Booking updated.']);
    }
    if ($route === '/admin/vehicles' && $method === 'GET') respond(['data'=>all($pdo,'SELECT * FROM vehicles ORDER BY created_at DESC')]);
    if ($route === '/admin/vehicles' && $method === 'POST') {
        $b=body();$name=trim((string)($b['name']??''));$category=(string)($b['category']??'Comfort Sedan');if($name===''||strlen($name)>120)fail('Enter a valid vehicle name.');
        $allowed=['Comfort Sedan','Premium SUV','Executive Class'];if(!in_array($category,$allowed,true))fail('Unsupported vehicle category.');
        $st=$pdo->prepare('INSERT INTO vehicles(name,category,passengers,luggage,base_fare,active) VALUES(?,?,?,?,?,?)');$st->execute([$name,$category,max(1,min(20,(int)($b['passengers']??3))),max(0,min(30,(int)($b['luggage']??2))),max(0,(float)($b['base_fare']??100)),!array_key_exists('active',$b)||jsonBool($b['active'])]);respond(['data'=>['id'=>(int)$pdo->lastInsertId(),'name'=>$name,'category'=>$category]],201);
    }
    if ($route === '/admin/payments' && $method === 'GET') respond(['data'=>all($pdo,'SELECT p.*,b.booking_reference,b.pickup_location,b.destination,c.full_name customer_name FROM payments p JOIN bookings b ON b.id=p.booking_id JOIN customers c ON c.id=b.customer_id ORDER BY p.created_at DESC LIMIT 500')]);
    if ($route === '/admin/earnings' && $method === 'GET') respond(['data'=>all($pdo,'SELECT e.*,b.booking_reference,b.vehicle_category,b.total_fare FROM driver_earnings e JOIN bookings b ON b.id=e.booking_id ORDER BY e.created_at DESC LIMIT 500')]);
    if ($route === '/admin/payouts' && $method === 'GET') respond(['data'=>all($pdo,'SELECT * FROM payouts ORDER BY created_at DESC LIMIT 500')]);
    if ($route === '/admin/payouts' && $method === 'POST') {
        $b=body();$amount=filter_var($b['amount']??null,FILTER_VALIDATE_FLOAT);if($amount===false||$amount<=0||$amount>10000000)fail('Enter a valid payout amount.');
        $ref='DEMO-PAYOUT-'.strtoupper(bin2hex(random_bytes(4)));
        $st=$pdo->prepare("INSERT INTO payouts(driver_id,amount,currency,status,payout_reference,notes,is_demo) VALUES(NULL,?,'NPR','paid_demo',?,?,1)");$st->execute([$amount,$ref,trim((string)($b['notes']??'Simulated payout only; no bank transfer was made.'))]);
        respond(['data'=>['id'=>(int)$pdo->lastInsertId(),'amount'=>$amount,'status'=>'paid_demo','payout_reference'=>$ref,'message'=>'Demo payout recorded; no real money was transferred.']],201);
    }
    if ($route === '/admin/refunds' && $method === 'GET') respond(['data'=>all($pdo,'SELECT r.*,p.booking_id,p.amount payment_amount,b.booking_reference FROM refunds r JOIN payments p ON p.id=r.payment_id JOIN bookings b ON b.id=p.booking_id ORDER BY r.created_at DESC LIMIT 500')]);
    if ($route === '/admin/refunds' && $method === 'POST') {
        $b=body();$paymentId=filter_var($b['payment_id']??null,FILTER_VALIDATE_INT);$reason=trim((string)($b['reason']??'Demo refund'));if(!$paymentId||$reason==='')fail('Payment and refund reason are required.');
        $payment=one($pdo,'SELECT * FROM payments WHERE id=?',[$paymentId]);if(!$payment)fail('Payment not found.',404);if($payment['status']!=='paid_demo')fail('Only a successful simulated payment can be refunded in this demo.',409);
        $pdo->beginTransaction();try{$pdo->prepare("INSERT INTO refunds(payment_id,amount,reason,status,is_demo) VALUES(?,?,?,'refunded_demo',1)")->execute([$paymentId,(float)$payment['amount'],$reason]);$pdo->prepare("UPDATE payments SET status='refunded',updated_at=NOW() WHERE id=?")->execute([$paymentId]);$pdo->prepare("UPDATE bookings SET payment_status='refunded',updated_at=NOW() WHERE id=?")->execute([(int)$payment['booking_id']]);$pdo->prepare('INSERT INTO transactions(booking_id,type,amount,currency,status,description,is_demo) VALUES(?,?,?,?,?,?,1)')->execute([(int)$payment['booking_id'],'refund_simulation',(float)$payment['amount'],'NPR','refunded_demo','Simulated refund; no real money returned']);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        respond(['message'=>'Demo refund recorded. No real money was returned.']);
    }
    if ($route === '/admin/customers' && $method === 'GET') respond(['data'=>all($pdo,'SELECT id,full_name,phone,email,created_at FROM customers ORDER BY created_at DESC LIMIT 500')]);
    if ($route === '/admin/pricing' && $method === 'GET') respond(['data'=>all($pdo,'SELECT * FROM pricing_rules ORDER BY sort_order')]);
    if ($route === '/admin/pricing' && $method === 'PATCH') {
        $b=body();$category=(string)($b['vehicle_category']??'');$base=filter_var($b['base_fare']??null,FILTER_VALIDATE_FLOAT);$per=filter_var($b['per_km']??null,FILTER_VALIDATE_FLOAT);if(!$category||$base===false||$per===false||$base<0||$per<0||$base>100000||$per>100000)fail('Invalid pricing values.');
        $existing=one($pdo,'SELECT id FROM pricing_rules WHERE vehicle_category=?',[$category]);if(!$existing)fail('Pricing rule not found.',404);$st=$pdo->prepare('UPDATE pricing_rules SET base_fare=?,per_km=?,airport_surcharge=?,outstation_surcharge=?,updated_at=NOW() WHERE vehicle_category=?');$st->execute([$base,max(0,$per),max(0,(float)($b['airport_surcharge']??250)),max(0,(float)($b['outstation_surcharge']??850)),$category]);respond(['message'=>'Pricing rule updated.']);
    }
    fail('Endpoint not found.',404);
} catch (PDOException $e) {
    error_log('OneCar database error: '.$e->getMessage());
    if (($config['demo_mode'] ?? false) === true) fail('Database request failed. Check database credentials and import database/onecar.sql.',500);
    fail('Internal server error.',500);
} catch (Throwable $e) {
    error_log('OneCar API error: '.$e->getMessage());
    fail('Internal server error. Check the PHP server log.',500);
}
