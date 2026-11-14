// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_banquets_uuid_settlement_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBanquetsUuidSettlementResponse
_$GetV1PosBanquetsUuidSettlementResponseFromJson(Map<String, dynamic> json) =>
    GetV1PosBanquetsUuidSettlementResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data238.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1PosBanquetsUuidSettlementResponseToJson(
  GetV1PosBanquetsUuidSettlementResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
