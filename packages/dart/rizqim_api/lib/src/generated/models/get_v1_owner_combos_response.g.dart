// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_combos_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerCombosResponse _$GetV1OwnerCombosResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerCombosResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data59.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerCombosResponseToJson(
  GetV1OwnerCombosResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
