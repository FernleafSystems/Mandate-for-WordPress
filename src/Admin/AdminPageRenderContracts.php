<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Admin;

/**
 * @phpstan-type AdminNoticeContract array{is_visible:bool,classes:string,text:string}
 * @phpstan-type AdminPageStringsContract array{page_title:string,user_label:string,application_password_label:string,role_slug_label:string,no_application_passwords_available:string,loading_selection:string,apply_selection:string}
 * @phpstan-type AdminPageFlagsContract array{has_passwords:bool,show_scope_form:bool}
 * @phpstan-type AdminRoleSummaryRow array{name:string,slug:string}
 * @phpstan-type AdminRoleSummaryContract array{title:string,empty_text:string,has_roles:bool,rows:list<AdminRoleSummaryRow>}
 * @phpstan-type AdminPasswordOptionContract array{uuid:string,name:string,selected:bool}
 * @phpstan-type AdminPasswordDetailTextContract array{kind:'text',label:string,value:string}
 * @phpstan-type AdminPasswordDetailExpirationContract array{kind:'expiration',label:string,value:string,classes:string,state:'never'|'date'|'expired',input:array{id:string,name:string,value:string,form:string,aria_label:string}}
 * @phpstan-type AdminPasswordDetailContract AdminPasswordDetailTextContract|AdminPasswordDetailExpirationContract
 * @phpstan-type AdminPasswordDetailSectionContract array{show_divider_before:bool,details:list<AdminPasswordDetailContract>}
 * @phpstan-type AdminPasswordWarningContract array{classes:string,text:string,role_snapshot_status:'changed'}
 * @phpstan-type AdminPasswordSummaryContract array{is_visible:bool,title:string,title_id:string,container_id:string,sections:list<AdminPasswordDetailSectionContract>,warnings:list<AdminPasswordWarningContract>}
 * @phpstan-type AdminSelectionFormContract array{selected_user_id:int,selected_uuid:string,page_slug:string,role_summary:AdminRoleSummaryContract,password_options:list<AdminPasswordOptionContract>,password_summary:AdminPasswordSummaryContract}
 * @phpstan-type AdminScopeActionContract array{name:string,value:string,label:string,classes:string,disabled:bool}
 * @phpstan-type AdminScopeTabContract array{key:string,id:string,panel_id:string,label:string,classes:string,aria_selected:string}
 * @phpstan-type AdminCapabilityItemContract array{name:string,field_name:string,checked:bool,has_tooltip:bool,tooltip_text:string}
 * @phpstan-type AdminCapabilitySectionContract array{id:string,label:string,empty_text:string,is_empty:bool,items:list<AdminCapabilityItemContract>}
 * @phpstan-type AdminCapabilityPanelContract array{key:string,id:string,tab_id:string,sections:list<AdminCapabilitySectionContract>,bulk_actions:array{select_all:array{label:string,state:string},deselect_all:array{label:string,state:string}}}
 * @phpstan-type AdminScopeFormContract array{id:string,user_id:int,uuid:string,heading:string,tablist_label:string,super_admin_notice:AdminNoticeContract,tabs:list<AdminScopeTabContract>,panels:list<AdminCapabilityPanelContract>,actions:list<AdminScopeActionContract>}
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
