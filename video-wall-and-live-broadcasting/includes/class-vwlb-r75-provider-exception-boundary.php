<?php
/** R75: provider/plugin exceptions must become typed failures instead of aborting queues or reconciliation loops. */
defined( 'ABSPATH' ) || exit;
final class VWLB_R75_Safe_Provider implements VWLB_Provider_Interface {
	private $inner;private $provider_id;
	public function __construct($inner){$this->inner=$inner;$this->provider_id=sanitize_key((string)$inner->id());}
	public function id(){return $this->provider_id;}
	private function failure($method,$e){do_action('vwlb_operational_failure','provider','vwlb_provider_adapter_exception',array('provider'=>$this->provider_id,'method'=>sanitize_key($method),'exception'=>sanitize_key(get_class($e))));return VWLB_Helpers::error('vwlb_provider_adapter_exception',__('Media provider failed safely and can be retried or reconciled.',VWLB_TEXT_DOMAIN),503,array('provider'=>$this->provider_id,'operation'=>sanitize_key($method)));}
	public function capabilities(){try{return (array)$this->inner->capabilities();}catch(Throwable $e){$this->failure('capabilities',$e);return array();}}
	public function normalize_source($source){try{return $this->inner->normalize_source($source);}catch(Throwable $e){return $this->failure('normalize_source',$e);}}
	public function create_live($event){try{return $this->inner->create_live($event);}catch(Throwable $e){return $this->failure('create_live',$e);}}
	public function issue_ingest($event){try{return $this->inner->issue_ingest($event);}catch(Throwable $e){return $this->failure('issue_ingest',$e);}}
	public function playback($object,$viewer){try{return $this->inner->playback($object,$viewer);}catch(Throwable $e){return $this->failure('playback',$e);}}
	public function process_asset($asset,$job){try{return $this->inner->process_asset($asset,$job);}catch(Throwable $e){return $this->failure('process_asset',$e);}}
	public function verify_webhook($headers,$body){try{return (bool)$this->inner->verify_webhook($headers,$body);}catch(Throwable $e){$this->failure('verify_webhook',$e);return false;}}
	public function reconcile($object_type,$object){try{return $this->inner->reconcile($object_type,$object);}catch(Throwable $e){return $this->failure('reconcile',$e);}}
}
final class VWLB_R75_Provider_Exception_Boundary {
	public static function register(){foreach(VWLB_Providers::all() as $provider){if($provider instanceof VWLB_R75_Safe_Provider)continue;VWLB_Providers::register(new VWLB_R75_Safe_Provider($provider));}}
}
