// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bookings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBookingsResponse _$PostV1PosBookingsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBookingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data187.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosBookingsResponseToJson(
  PostV1PosBookingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
