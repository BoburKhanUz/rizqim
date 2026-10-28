// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bookings_uuid_no_show_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBookingsUuidNoShowResponse
_$PostV1PosBookingsUuidNoShowResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosBookingsUuidNoShowResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data138.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosBookingsUuidNoShowResponseToJson(
  PostV1PosBookingsUuidNoShowResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
