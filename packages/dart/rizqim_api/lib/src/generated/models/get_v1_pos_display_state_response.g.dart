// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_display_state_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosDisplayStateResponse _$GetV1PosDisplayStateResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosDisplayStateResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data218.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosDisplayStateResponseToJson(
  GetV1PosDisplayStateResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
