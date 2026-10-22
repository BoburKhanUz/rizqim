// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_floor_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosFloorResponse _$GetV1PosFloorResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosFloorResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data147.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosFloorResponseToJson(
  GetV1PosFloorResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
