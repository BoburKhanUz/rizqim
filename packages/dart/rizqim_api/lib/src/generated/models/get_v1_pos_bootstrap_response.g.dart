// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_bootstrap_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBootstrapResponse _$GetV1PosBootstrapResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosBootstrapResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data159.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosBootstrapResponseToJson(
  GetV1PosBootstrapResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
