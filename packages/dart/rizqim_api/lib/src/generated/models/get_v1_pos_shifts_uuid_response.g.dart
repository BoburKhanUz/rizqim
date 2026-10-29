// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_shifts_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosShiftsUuidResponse _$GetV1PosShiftsUuidResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosShiftsUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data202.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosShiftsUuidResponseToJson(
  GetV1PosShiftsUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
