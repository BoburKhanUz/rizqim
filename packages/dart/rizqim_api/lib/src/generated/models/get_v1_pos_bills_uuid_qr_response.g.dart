// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_bills_uuid_qr_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBillsUuidQrResponse _$GetV1PosBillsUuidQrResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosBillsUuidQrResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data251.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosBillsUuidQrResponseToJson(
  GetV1PosBillsUuidQrResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
