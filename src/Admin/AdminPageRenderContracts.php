<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Admin;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @phpstan-type AdminNoticeContract array{is_visible:bool,classes:string,text:string}
 * @phpstan-type AdminPageStringsContract array{page_title:string,user_label:string,application_password_label:string,role_slug_label:string,no_application_passwords_available:string,loading_selection:string,apply_selection:string}
 * @phpstan-type AdminPageFlagsContract array{has_passwords:bool,show_scope_form:bool}
 * @phpstan-type AdminRoleSummaryRow array{name:string,slug:string}
 * @phpstan-type AdminRoleSummaryContract array{title:string,empty_text:string,has_roles:bool,rows:list<AdminRoleSummaryRow>}
 * @phpstan-type AdminPasswordOptionContract array{uuid:string,name:string,selected:bool}
 * @phpstan-type AdminPasswordDetailTextContract array{kind:'text',label:string,value:string}
 * @phpstan-type AdminPasswordDetailExpirationContract array{kind:'expiration',label:string,value:string,classes:string,state:'never'|'date'|'expired',disabled:bool,input:array{id:string,name:string,value:string,form:string,aria_label:string,disabled:bool}}
 * @phpstan-type AdminPasswordDetailContract AdminPasswordDetailTextContract|AdminPasswordDetailExpirationContract
 * @phpstan-type AdminPasswordDetailSectionContract array{show_divider_before:bool,details:list<AdminPasswordDetailContract>}
 * @phpstan-type AdminPasswordWarningContract array{classes:string,text:string,role_snapshot_status:'changed'}
 * @phpstan-type AdminPasswordSummaryContract array{is_visible:bool,title:string,title_id:string,container_id:string,sections:list<AdminPasswordDetailSectionContract>,warnings:list<AdminPasswordWarningContract>}
 * @phpstan-type AdminSelectionFormContract array{selected_user_id:int,selected_uuid:string,page_slug:string,role_summary:AdminRoleSummaryContract,password_options:list<AdminPasswordOptionContract>,password_summary:AdminPasswordSummaryContract}
 * @phpstan-type AdminScopeActionContract array{name:string,value:string,label:string,classes:string,disabled:bool}
 * @phpstan-type AdminScopeAdminLockContract array{is_visible:bool,name:string,value:string,label:string,checked:bool,disabled:bool}
 * @phpstan-type AdminCapabilityGroupingModeContract array{key:'area'|'action',label:string,checked:bool}
 * @phpstan-type AdminCapabilityGroupingContract array{label:string,default_source:'wordpress',default_mode:'area',config_json:string,modes:list<AdminCapabilityGroupingModeContract>}
 * @phpstan-type AdminCapabilityItemContract array{item_key:string,name:string,type:'primitive'|'meta',field_name:'allowed_caps'|'allowed_meta_caps',checked:bool,disabled:bool,source:'wordpress'|'third_party',area:'posts'|'pages'|'media'|'taxonomy'|'comments'|'users'|'plugins'|'themes'|'general'|'network'|'privacy'|'updates'|'legacy'|'third_party',action:'read'|'create'|'edit'|'delete',has_tooltip:bool,tooltip_text:string}
 * @phpstan-type AdminCapabilitySectionContract array{id:string,label:string,count:int,items:list<AdminCapabilityItemContract>}
 * @phpstan-type AdminCapabilitySectionIndexItemContract array{target_id:string,label:string,count:int}
 * @phpstan-type AdminCapabilitySourceTabContract array{key:'wordpress'|'third_party',id:string,panel_id:string,label:string,count:int,selected:bool}
 * @phpstan-type AdminCapabilityPanelContract array{key:'wordpress'|'third_party',id:string,tab_id:string,empty_text:string,is_empty:bool,section_index:list<AdminCapabilitySectionIndexItemContract>,sections:list<AdminCapabilitySectionContract>,bulk_actions:array{select_all:array{label:string,state:string,disabled:bool},deselect_all:array{label:string,state:string,disabled:bool}}}
 * @phpstan-type AdminScopeFormContract array{id:string,user_id:int,uuid:string,heading:string,admin_lock_status:'locked'|'unlocked',super_admin_notice:AdminNoticeContract,lock_notice:AdminNoticeContract,admin_lock:AdminScopeAdminLockContract,grouping:AdminCapabilityGroupingContract,source_tabs:list<AdminCapabilitySourceTabContract>,source_panels:list<AdminCapabilityPanelContract>,actions:list<AdminScopeActionContract>}
 * @phpstan-type AdminPageRenderData array{
 *   hrefs:array{selection_form_action:string,scope_form_action:string},
 *   strings:AdminPageStringsContract,
 *   flags:AdminPageFlagsContract,
 *   classes:array{root:string},
 *   vars:array{message:AdminNoticeContract,page_notice:AdminNoticeContract,selection_form:AdminSelectionFormContract,scope_form:AdminScopeFormContract},
 *   trustedHtml:array{user_dropdown:string,scope_nonce_fields:string}
 * }
 */
final class AdminPageRenderContracts {

	private function __construct() {
	}
}
