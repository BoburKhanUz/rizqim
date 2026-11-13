// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'accounts.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Accounts _$AccountsFromJson(Map<String, dynamic> json) => Accounts(
  uuid: json['uuid'] as String,
  name: json['name'] as String,
  phone: json['phone'] as String?,
  groupName: json['group_name'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
  creditLimit: json['credit_limit'] as String?,
  balance: json['balance'] as String,
  availableCredit: json['available_credit'] as String?,
  isBlocked: json['is_blocked'] as bool,
  note: json['note'] as String?,
  kind: CustomerAccountKind.fromJson(json['kind'] as String),
  kindLabel: json['kind_label'] as String,
  companyName: json['company_name'] as String?,
  inn: json['inn'] as String?,
  contactName: json['contact_name'] as String?,
  contactPhone: json['contact_phone'] as String?,
  invoiceEmail: json['invoice_email'] as String?,
  monthlyLimit: json['monthly_limit'] as String?,
  chargeApproval: json['charge_approval'] as bool,
  talonMode: TalonMode.fromJson(json['talon_mode'] as String),
  membersCount: (json['members_count'] as num).toInt(),
  createdAt: json['created_at'] as String?,
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$AccountsToJson(Accounts instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'name': instance.name,
  'phone': instance.phone,
  'group_name': instance.groupName,
  'branch_id': instance.branchId,
  'credit_limit': instance.creditLimit,
  'balance': instance.balance,
  'available_credit': instance.availableCredit,
  'is_blocked': instance.isBlocked,
  'note': instance.note,
  'kind': instance.kind,
  'kind_label': instance.kindLabel,
  'company_name': instance.companyName,
  'inn': instance.inn,
  'contact_name': instance.contactName,
  'contact_phone': instance.contactPhone,
  'invoice_email': instance.invoiceEmail,
  'monthly_limit': instance.monthlyLimit,
  'charge_approval': instance.chargeApproval,
  'talon_mode': instance.talonMode,
  'members_count': instance.membersCount,
  'created_at': instance.createdAt,
  'updated_at': instance.updatedAt,
};
