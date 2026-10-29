// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_format_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosFormatResponse _$GetV1OwnerPosFormatResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPosFormatResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data110.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerPosFormatResponseToJson(
  GetV1OwnerPosFormatResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
