// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_qr_uuid_simulate_paid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosQrUuidSimulatePaidResponse
_$PostV1PosQrUuidSimulatePaidResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosQrUuidSimulatePaidResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data331.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosQrUuidSimulatePaidResponseToJson(
  PostV1PosQrUuidSimulatePaidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
