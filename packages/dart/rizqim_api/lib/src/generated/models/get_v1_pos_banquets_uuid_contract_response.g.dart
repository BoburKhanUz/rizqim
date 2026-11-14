// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_banquets_uuid_contract_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBanquetsUuidContractResponse
_$GetV1PosBanquetsUuidContractResponseFromJson(Map<String, dynamic> json) =>
    GetV1PosBanquetsUuidContractResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data228.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1PosBanquetsUuidContractResponseToJson(
  GetV1PosBanquetsUuidContractResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
