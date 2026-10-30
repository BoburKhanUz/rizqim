// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_bookings_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBookingsUuidResponse _$GetV1PosBookingsUuidResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosBookingsUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data173.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosBookingsUuidResponseToJson(
  GetV1PosBookingsUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
