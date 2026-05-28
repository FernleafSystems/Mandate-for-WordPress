<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin;

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
 * @phpstan-type AdminSummaryDetailTextContract array{kind:'text',label:string,value:string}
 * @phpstan-type AdminSummaryDetailExpirationContract array{kind:'expiration',label:string,value:string,classes:string,state:'never'|'date'|'expired',disabled:bool,input:array{id:string,name:string,value:string,form:string,aria_label:string,disabled:bool}}
 * @phpstan-type AdminSummaryDetailAdminLockContract array{kind:'admin_lock',label:string,help_text:string,input:array{id:string,name:string,value:string,form:string,checked:bool,disabled:bool}}
 * @phpstan-type AdminSummaryDetailContract AdminSummaryDetailTextContract|AdminSummaryDetailExpirationContract|AdminSummaryDetailAdminLockContract
 * @phpstan-type AdminSummaryWarningContract array{classes:string,text:string,role_snapshot_status:'changed'}
 * @phpstan-type AdminSummaryContract array{is_visible:bool,title:string,title_id:string,title_placement:'inside'|'outside',container_id:string,details:list<AdminSummaryDetailContract>,warnings:list<AdminSummaryWarningContract>}
 * @phpstan-type AdminSelectionFormContract array{selected_user_id:int,selected_uuid:string,page_slug:string,role_summary:AdminRoleSummaryContract,password_options:list<AdminPasswordOptionContract>,password_info:AdminSummaryContract,scope_summary:AdminSummaryContract}
 * @phpstan-type AdminScopeActionContract array{name:string,value:string,label:string,classes:string,disabled:bool}
 * @phpstan-type AdminCapabilityGroupingModeContract array{key:'area'|'action',label:string,checked:bool}
 * @phpstan-type AdminCapabilityGroupingContract array{label:string,default_source:'wordpress',default_mode:'area',config_json:string,modes:list<AdminCapabilityGroupingModeContract>}
 * @phpstan-type AdminCapabilityBulkActionsContract array{select_all:array{label:string,state:'checked',disabled:bool},deselect_all:array{label:string,state:'unchecked',disabled:bool}}
 * @phpstan-type AdminCapabilityItemContract array{item_key:string,input_id:string,name:string,type:'primitive'|'meta',field_name:'allowed_caps'|'allowed_meta_caps',checked:bool,disabled:bool,source:'wordpress'|'third_party',area:'posts'|'pages'|'media'|'taxonomy'|'comments'|'users'|'plugins'|'themes'|'general'|'network'|'privacy'|'updates'|'legacy'|'third_party',action:'read'|'write'|'delete',action_label:string,action_abbreviation:'R'|'W'|'D',has_tooltip:bool,tooltip_text:string,tooltip_aria_label:string}
 * @phpstan-type AdminCapabilitySectionContract array{id:string,label:string,count:int,items:list<AdminCapabilityItemContract>,bulk_actions:AdminCapabilityBulkActionsContract}
 * @phpstan-type AdminCapabilitySectionIndexItemContract array{target_id:string,label:string,count:int}
 * @phpstan-type AdminCapabilitySourceTabContract array{key:'wordpress'|'third_party',id:string,panel_id:string,label:string,count:int,selected:bool}
 * @phpstan-type AdminCapabilityPanelContract array{key:'wordpress'|'third_party',id:string,tab_id:string,empty_text:string,is_empty:bool,section_index:list<AdminCapabilitySectionIndexItemContract>,sections:list<AdminCapabilitySectionContract>,bulk_actions:AdminCapabilityBulkActionsContract}
 * @phpstan-type AdminScopeFormContract array{id:string,user_id:int,uuid:string,heading:string,admin_lock_status:'locked'|'unlocked',super_admin_notice:AdminNoticeContract,lock_notice:AdminNoticeContract,grouping:AdminCapabilityGroupingContract,source_tabs:list<AdminCapabilitySourceTabContract>,source_panels:list<AdminCapabilityPanelContract>,actions:list<AdminScopeActionContract>}
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
