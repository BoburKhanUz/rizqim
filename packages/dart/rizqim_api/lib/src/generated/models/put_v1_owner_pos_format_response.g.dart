// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_pos_format_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerPosFormatResponse _$PutV1OwnerPosFormatResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerPosFormatResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data111.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerPosFormatResponseToJson(
  PutV1OwnerPosFormatResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
