// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_modifier_groups_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerModifierGroupsResponse _$GetV1OwnerModifierGroupsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerModifierGroupsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data82.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1OwnerModifierGroupsResponseToJson(
  GetV1OwnerModifierGroupsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
