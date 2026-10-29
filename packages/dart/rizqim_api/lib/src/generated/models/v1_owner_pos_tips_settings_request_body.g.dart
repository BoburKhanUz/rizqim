// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_tips_settings_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosTipsSettingsRequestBody _$V1OwnerPosTipsSettingsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerPosTipsSettingsRequestBody(
  mode: json['mode'] == null ? null : TipMode.fromJson(json['mode'] as String),
  poolRule: json['pool_rule'] == null
      ? null
      : TipPoolRule.fromJson(json['pool_rule'] as String),
  poolPeriod: json['pool_period'] == null
      ? null
      : TipPoolPeriod.fromJson(json['pool_period'] as String),
  roles: (json['roles'] as List<dynamic>?)
      ?.map((e) => Roles.fromJson(e as String))
      .toList(),
  roleWeights: (json['role_weights'] as List<dynamic>?)
      ?.map((e) => e as num)
      .toList(),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1OwnerPosTipsSettingsRequestBodyToJson(
  V1OwnerPosTipsSettingsRequestBody instance,
) => <String, dynamic>{
  'mode': instance.mode,
  'pool_rule': instance.poolRule,
  'pool_period': instance.poolPeriod,
  'roles': instance.roles,
  'role_weights': instance.roleWeights,
  'branch_id': instance.branchId,
};
