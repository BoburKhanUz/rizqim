// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_bookings_calendar_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBookingsCalendarResponse _$GetV1PosBookingsCalendarResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosBookingsCalendarResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data143.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosBookingsCalendarResponseToJson(
  GetV1PosBookingsCalendarResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
