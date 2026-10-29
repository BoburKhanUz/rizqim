// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_staff_pay_rules_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerStaffPayRulesRequestBody _$V1OwnerStaffPayRulesRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerStaffPayRulesRequestBody(
  effectiveFrom: json['effective_from'] as String,
  components: (json['components'] as List<dynamic>)
      .map((e) => e as Map<String, dynamic>)
      .toList(),
  branchId: (json['branch_id'] as num?)?.toInt(),
  userId: (json['user_id'] as num?)?.toInt(),
  role: json['role'] as String?,
  note: json['note'] as String?,
);

Map<String, dynamic> _$V1OwnerStaffPayRulesRequestBodyToJson(
  V1OwnerStaffPayRulesRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'user_id': instance.userId,
  'role': instance.role,
  'effective_from': instance.effectiveFrom,
  'components': instance.components,
  'note': instance.note,
};
