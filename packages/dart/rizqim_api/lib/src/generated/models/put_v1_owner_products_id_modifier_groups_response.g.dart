// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_products_id_modifier_groups_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerProductsIdModifierGroupsResponse
_$PutV1OwnerProductsIdModifierGroupsResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerProductsIdModifierGroupsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data117.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerProductsIdModifierGroupsResponseToJson(
  PutV1OwnerProductsIdModifierGroupsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
