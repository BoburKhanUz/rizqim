// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_modifier_groups_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerModifierGroupsIdResponse
_$DeleteV1OwnerModifierGroupsIdResponseFromJson(Map<String, dynamic> json) =>
    DeleteV1OwnerModifierGroupsIdResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: json['data'],
    );

Map<String, dynamic> _$DeleteV1OwnerModifierGroupsIdResponseToJson(
  DeleteV1OwnerModifierGroupsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
