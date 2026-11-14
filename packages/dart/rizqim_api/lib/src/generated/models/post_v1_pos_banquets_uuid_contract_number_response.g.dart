// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_banquets_uuid_contract_number_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBanquetsUuidContractNumberResponse
_$PostV1PosBanquetsUuidContractNumberResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBanquetsUuidContractNumberResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data229.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosBanquetsUuidContractNumberResponseToJson(
  PostV1PosBanquetsUuidContractNumberResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
