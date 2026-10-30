// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'staff6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Staff6 _$Staff6FromJson(Map<String, dynamic> json) => Staff6(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  roleLabel: json['role_label'] as String,
  branch: json['branch'] == null
      ? null
      : Branch10.fromJson(json['branch'] as Map<String, dynamic>),
  courierType: json['courier_type'] as String?,
  phone: json['phone'] as String?,
  loginPhoneEnabled: json['login_phone_enabled'] as bool,
  loginPhoneProof: json['login_phone_proof'] == null
      ? null
      : LoginPhoneProof.fromJson(json['login_phone_proof'] as String?),
  hasPin: json['has_pin'] as bool,
  staffCode: json['staff_code'] as String?,
  telegramLinked: json['telegram_linked'] as bool,
  invitePending: json['invite_pending'] as bool,
  canEdit: json['can_edit'] as bool,
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$Staff6ToJson(Staff6 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'role_label': instance.roleLabel,
  'branch': instance.branch,
  'courier_type': instance.courierType,
  'phone': instance.phone,
  'login_phone_enabled': instance.loginPhoneEnabled,
  'login_phone_proof': instance.loginPhoneProof,
  'has_pin': instance.hasPin,
  'staff_code': instance.staffCode,
  'telegram_linked': instance.telegramLinked,
  'invite_pending': instance.invitePending,
  'can_edit': instance.canEdit,
  'created_at': instance.createdAt,
};
