// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_staff_pay_rules_uuid_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerStaffPayRulesUuidRequestBody
_$V1OwnerStaffPayRulesUuidRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerStaffPayRulesUuidRequestBody(
      effectiveFrom: json['effective_from'] as String?,
      components: (json['components'] as List<dynamic>?)
          ?.map((e) => e as Map<String, dynamic>)
          .toList(),
      note: json['note'] as String?,
    );

Map<String, dynamic> _$V1OwnerStaffPayRulesUuidRequestBodyToJson(
  V1OwnerStaffPayRulesUuidRequestBody instance,
) => <String, dynamic>{
  'effective_from': instance.effectiveFrom,
  'components': instance.components,
  'note': instance.note,
};
