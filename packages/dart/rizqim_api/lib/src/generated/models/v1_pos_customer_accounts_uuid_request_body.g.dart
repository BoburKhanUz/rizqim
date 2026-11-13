// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_customer_accounts_uuid_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosCustomerAccountsUuidRequestBody
_$V1PosCustomerAccountsUuidRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosCustomerAccountsUuidRequestBody(
      name: json['name'] as String?,
      phone: json['phone'] as String?,
      groupName: json['group_name'] as String?,
      creditLimit: json['credit_limit'] as num?,
      isBlocked: json['is_blocked'] as bool?,
      note: json['note'] as String?,
      branchId: (json['branch_id'] as num?)?.toInt(),
      approvalToken: json['approval_token'] as String?,
      kind: json['kind'] == null
          ? null
          : CustomerAccountKind.fromJson(json['kind'] as String),
      companyName: json['company_name'] as String?,
      inn: json['inn'] as String?,
      contactName: json['contact_name'] as String?,
      contactPhone: json['contact_phone'] as String?,
      invoiceEmail: json['invoice_email'] as String?,
      monthlyLimit: json['monthly_limit'] as num?,
      chargeApproval: json['charge_approval'] as bool?,
      talonMode: json['talon_mode'] == null
          ? null
          : TalonMode.fromJson(json['talon_mode'] as String),
    );

Map<String, dynamic> _$V1PosCustomerAccountsUuidRequestBodyToJson(
  V1PosCustomerAccountsUuidRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'phone': instance.phone,
  'group_name': instance.groupName,
  'credit_limit': instance.creditLimit,
  'is_blocked': instance.isBlocked,
  'note': instance.note,
  'branch_id': instance.branchId,
  'approval_token': instance.approvalToken,
  'kind': instance.kind,
  'company_name': instance.companyName,
  'inn': instance.inn,
  'contact_name': instance.contactName,
  'contact_phone': instance.contactPhone,
  'invoice_email': instance.invoiceEmail,
  'monthly_limit': instance.monthlyLimit,
  'charge_approval': instance.chargeApproval,
  'talon_mode': instance.talonMode,
};
