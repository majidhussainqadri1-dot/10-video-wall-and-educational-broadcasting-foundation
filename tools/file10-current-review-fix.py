from pathlib import Path

root = Path('.')
sec_path = root / 'video-wall-and-live-broadcasting/includes/class-vwlb-security.php'
plugin_path = root / 'video-wall-and-live-broadcasting/includes/class-vwlb-plugin.php'
js_path = root / 'video-wall-and-live-broadcasting/assets/js/vwlb.js'
run_path = root / 'tests/run-all.sh'
reg_path = root / 'tests/fresh-40-review-contracts.sh'

sec = sec_path.read_text()
if "private static $rest_idempotency = array();" not in sec:
    sec = sec.replace("final class VWLB_Security {\n", "final class VWLB_Security {\n\tprivate static $rest_idempotency = array();\n", 1)

old_scope = "\tprivate static function idem_scope($scope){return substr(sanitize_key($scope).':'.get_current_user_id(),0,100);}\n"
new_scope = "\tprivate static function idem_scope($scope){$uid=get_current_user_id();$actor=$uid?'u'.$uid:'a'.substr(VWLB_Helpers::ip_hash(),0,32);return substr(sanitize_key($scope).':'.$actor,0,100);}\n"
if old_scope in sec:
    sec = sec.replace(old_scope, new_scope, 1)
elif new_scope not in sec:
    raise SystemExit('R04 idem scope pattern missing')

old_finish = "\tpublic static function idempotency_finish($key,$scope,$response){global $wpdb;$scope=self::idem_scope($scope);$wpdb->update(VWLB_Helpers::table('idempotency'),array('status'=>'complete','response_json'=>VWLB_Helpers::json_encode($response)),array('idempotency_key'=>$key,'scope'=>$scope),array('%s','%s'),array('%s','%s'));}\n}"
new_finish = r'''	public static function idempotency_finish($key,$scope,$response){global $wpdb;$scope=self::idem_scope($scope);$wpdb->update(VWLB_Helpers::table('idempotency'),array('status'=>'complete','response_json'=>VWLB_Helpers::json_encode($response)),array('idempotency_key'=>$key,'scope'=>$scope),array('%s','%s'),array('%s','%s'));}
	public static function idempotency_abort($key,$scope){global $wpdb;$scope=self::idem_scope($scope);$wpdb->delete(VWLB_Helpers::table('idempotency'),array('idempotency_key'=>VWLB_Helpers::text($key,128),'scope'=>$scope,'status'=>'processing'),array('%s','%s','%s'));}
	private static function rest_file10($request){$route=(string)$request->get_route();foreach(VWLB_Contracts::namespaces() as $n){if(str_starts_with($route,'/'.$n.'/'))return true;}return false;}
	private static function rest_callback_name($handler){$cb=$handler['callback']??null;if(is_array($cb)&&isset($cb[1]))return sanitize_key((string)$cb[1]);return 'mutation';}
	private static function rest_request_hash($request){$params=$request->get_params();$normal=function(&$v)use(&$normal){if(is_array($v)){ksort($v);foreach($v as &$x)$normal($x);}};$normal($params);$headers=array('content-range'=>(string)$request->get_header('Content-Range'),'content-type'=>(string)$request->get_header('Content-Type'));return hash('sha256',strtoupper((string)$request->get_method()).'|'.$request->get_route().'|'.VWLB_Helpers::json_encode($params).'|'.hash('sha256',(string)$request->get_body()).'|'.VWLB_Helpers::json_encode($headers));}
	/** Cross-surface mutation contract: rate-limit every mutation and require durable idempotency except signed provider webhooks, which have provider event dedupe/replay controls. */
	public static function rest_mutation_before($response,$handler,$request){
		if(null!==$response||!self::rest_file10($request))return $response;$method=strtoupper((string)$request->get_method());if(in_array($method,array('GET','HEAD','OPTIONS'),true))return $response;$name=self::rest_callback_name($handler);
		$limit=max(1,(int)apply_filters('vwlb_rest_mutation_rate_limit',600,$name,$request));$window=max(1,(int)apply_filters('vwlb_rest_mutation_rate_window',60,$name,$request));$rate=self::rate_limit('rest_mutation_'.$name,$limit,$window);if(is_wp_error($rate))return $rate;
		if('webhook'===$name)return $response;
		$key=VWLB_Helpers::text($request->get_header('Idempotency-Key'),128);$scope='rest_'.$method.'_'.$name;$idem=self::idempotency_begin($key,$scope,self::rest_request_hash($request));if(is_wp_error($idem))return $idem;
		if(!empty($idem['replay'])){$stored=(array)$idem['response'];$replay=new WP_REST_Response($stored['data']??null,absint($stored['status']??200)?:200);$replay->header('X-VWLB-Idempotent-Replay','true');return $replay;}
		self::$rest_idempotency[spl_object_hash($request)]=array('key'=>$key,'scope'=>$scope);return $response;
	}
	public static function rest_mutation_after($response,$handler,$request){
		$hash=spl_object_hash($request);if(empty(self::$rest_idempotency[$hash]))return $response;$ctx=self::$rest_idempotency[$hash];unset(self::$rest_idempotency[$hash]);
		if(is_wp_error($response)){self::idempotency_abort($ctx['key'],$ctx['scope']);return $response;}$wrapped=rest_ensure_response($response);$status=(int)$wrapped->get_status();if($status>=500){self::idempotency_abort($ctx['key'],$ctx['scope']);return $response;}self::idempotency_finish($ctx['key'],$ctx['scope'],array('status'=>$status,'data'=>$wrapped->get_data()));return $response;
	}
}'''
if old_finish in sec:
    sec = sec.replace(old_finish, new_finish, 1)
elif "public static function rest_mutation_before" not in sec:
    raise SystemExit('R04 security tail pattern missing')
sec_path.write_text(sec)

plugin = plugin_path.read_text()
needle = "\t\tVWLB_Future_Intelligence::register();\n\n"
insert = "\t\tVWLB_Future_Intelligence::register();\n\t\tadd_filter('rest_request_before_callbacks',array('VWLB_Security','rest_mutation_before'),10,3);\n\t\tadd_filter('rest_request_after_callbacks',array('VWLB_Security','rest_mutation_after'),10,3);\n\n"
if needle in plugin:
    plugin = plugin.replace(needle, insert, 1)
elif "rest_mutation_before" not in plugin:
    raise SystemExit('R04 plugin guard registration pattern missing')
plugin_path.write_text(plugin)

js = js_path.read_text()
old = "  const request = async (path, options = {}) => {\n    const headers = Object.assign({'Content-Type': 'application/json'}, options.headers || {});\n    if (cfg.nonce) headers['X-WP-Nonce'] = cfg.nonce;\n"
new = "  const idempotencyKey = () => {\n    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();\n    if (window.crypto && typeof window.crypto.getRandomValues === 'function') { const b = new Uint8Array(16); window.crypto.getRandomValues(b); return Array.from(b, (v) => v.toString(16).padStart(2, '0')).join(''); }\n    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;\n  };\n  const request = async (path, options = {}) => {\n    const headers = Object.assign({'Content-Type': 'application/json'}, options.headers || {});\n    const method = String(options.method || 'GET').toUpperCase();\n    if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && !headers['Idempotency-Key']) headers['Idempotency-Key'] = options.idempotencyKey || idempotencyKey();\n    if (cfg.nonce) headers['X-WP-Nonce'] = cfg.nonce;\n"
if old in js:
    js = js.replace(old, new, 1)
elif "const idempotencyKey = () =>" not in js:
    raise SystemExit('R04 browser request pattern missing')
js_path.write_text(js)

reg = r'''#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
P="$ROOT/video-wall-and-live-broadcasting"
fail(){ echo "FAIL fresh-40-review: $*" >&2; exit 1; }
need(){ grep -R -F -- "$1" "$2" >/dev/null || fail "$3"; }
# R02 — serialized base/extension/Future migration and activation parity.
need "MIGRATION_LOCK" "$P/includes/class-vwlb-activator.php" r02-migration-lock
need "VWLB_Future_Intelligence::install_schema" "$P/includes/class-vwlb-activator.php" r02-future-activation-schema
need "VWLB_Activator::reconcile_schema" "$P/includes/class-vwlb-plugin.php" r02-runtime-reconcile
# R03 — publisher/broadcaster cannot self-assert human-reviewed Future states.
need "Human review permission is required to change a generated track review state" "$P/includes/class-vwlb-future-intelligence.php" r03-track-review-guard
need "Timestamp corrections require independent review permission" "$P/includes/class-vwlb-future-intelligence.php" r03-correction-review-guard
# R04 — all File 10 REST mutations are rate-limited and idempotency guarded; signed webhooks retain provider replay dedupe.
need "rest_request_before_callbacks" "$P/includes/class-vwlb-plugin.php" r04-before-hook
need "rest_request_after_callbacks" "$P/includes/class-vwlb-plugin.php" r04-after-hook
need "rest_mutation_before" "$P/includes/class-vwlb-security.php" r04-mutation-guard
need "idempotency_abort" "$P/includes/class-vwlb-security.php" r04-idempotency-abort
need "'webhook'===\$name" "$P/includes/class-vwlb-security.php" r04-webhook-exception
need "Idempotency-Key" "$P/assets/js/vwlb.js" r04-browser-idempotency
printf '%s\n' 'fresh 40-review regression contracts PASS'
'''
reg_path.write_text(reg)

run = run_path.read_text()
line = 'bash "$ROOT/tests/fresh-40-review-contracts.sh"\n'
if line not in run:
    run = run.replace('bash "$ROOT/tests/static-contracts.sh"\n', 'bash "$ROOT/tests/static-contracts.sh"\n' + line, 1)
run_path.write_text(run)
