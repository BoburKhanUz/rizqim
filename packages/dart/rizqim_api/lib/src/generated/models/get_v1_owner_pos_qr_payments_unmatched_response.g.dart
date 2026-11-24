// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_qr_payments_unmatched_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosQrPaymentsUnmatchedResponse
_$GetV1OwnerPosQrPaymentsUnmatchedResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerPosQrPaymentsUnmatchedResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data160.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerPosQrPaymentsUnmatchedResponseToJson(
  GetV1OwnerPosQrPaymentsUnmatchedResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
