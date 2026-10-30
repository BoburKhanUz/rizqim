// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_combos_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerCombosIdResponse _$PutV1OwnerCombosIdResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerCombosIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data61.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerCombosIdResponseToJson(
  PutV1OwnerCombosIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
