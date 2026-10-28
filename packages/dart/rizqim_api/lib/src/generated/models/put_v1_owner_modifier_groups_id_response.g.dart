// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_modifier_groups_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerModifierGroupsIdResponse _$PutV1OwnerModifierGroupsIdResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerModifierGroupsIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data84.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerModifierGroupsIdResponseToJson(
  PutV1OwnerModifierGroupsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
