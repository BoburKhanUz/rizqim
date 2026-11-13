// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_pos_qr_payments_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerPosQrPaymentsResponse _$PutV1OwnerPosQrPaymentsResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerPosQrPaymentsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data133.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerPosQrPaymentsResponseToJson(
  PutV1OwnerPosQrPaymentsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
