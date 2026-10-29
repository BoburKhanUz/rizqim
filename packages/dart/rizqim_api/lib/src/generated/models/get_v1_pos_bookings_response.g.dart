// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_bookings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBookingsResponse _$GetV1PosBookingsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosBookingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data141.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosBookingsResponseToJson(
  GetV1PosBookingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
