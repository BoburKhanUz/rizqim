// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_pos_bookings_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1PosBookingsUuidResponse _$PatchV1PosBookingsUuidResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1PosBookingsUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data174.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PatchV1PosBookingsUuidResponseToJson(
  PatchV1PosBookingsUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
