// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_qr_payments_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosQrPaymentsResponse _$GetV1OwnerPosQrPaymentsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPosQrPaymentsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data155.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerPosQrPaymentsResponseToJson(
  GetV1OwnerPosQrPaymentsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
