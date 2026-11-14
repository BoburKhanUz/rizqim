// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_banquets_uuid_settlement_refund_excess_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBanquetsUuidSettlementRefundExcessResponse
_$PostV1PosBanquetsUuidSettlementRefundExcessResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBanquetsUuidSettlementRefundExcessResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data239.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic>
_$PostV1PosBanquetsUuidSettlementRefundExcessResponseToJson(
  PostV1PosBanquetsUuidSettlementRefundExcessResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
