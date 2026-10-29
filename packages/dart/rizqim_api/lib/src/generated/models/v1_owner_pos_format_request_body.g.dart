// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_format_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosFormatRequestBody _$V1OwnerPosFormatRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerPosFormatRequestBody(
  modules: (json['modules'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as bool?),
  ),
  branchId: (json['branch_id'] as num?)?.toInt(),
  profile: json['profile'] as String?,
  mode: json['mode'] as String?,
);

Map<String, dynamic> _$V1OwnerPosFormatRequestBodyToJson(
  V1OwnerPosFormatRequestBody instance,
) => <String, dynamic>{
  'modules': instance.modules,
  'branch_id': instance.branchId,
  'profile': instance.profile,
  'mode': instance.mode,
};
