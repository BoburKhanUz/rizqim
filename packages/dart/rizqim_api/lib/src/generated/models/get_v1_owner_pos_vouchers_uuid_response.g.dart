// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_vouchers_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosVouchersUuidResponse _$GetV1OwnerPosVouchersUuidResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPosVouchersUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data169.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerPosVouchersUuidResponseToJson(
  GetV1OwnerPosVouchersUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
