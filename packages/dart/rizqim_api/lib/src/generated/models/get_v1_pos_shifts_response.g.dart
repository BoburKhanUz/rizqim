// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_shifts_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosShiftsResponse _$GetV1PosShiftsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosShiftsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data163.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1PosShiftsResponseToJson(
  GetV1PosShiftsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
