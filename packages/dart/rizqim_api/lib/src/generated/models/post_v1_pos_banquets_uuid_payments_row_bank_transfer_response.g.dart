// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_banquets_uuid_payments_row_bank_transfer_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBanquetsUuidPaymentsRowBankTransferResponse
_$PostV1PosBanquetsUuidPaymentsRowBankTransferResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBanquetsUuidPaymentsRowBankTransferResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data234.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic>
_$PostV1PosBanquetsUuidPaymentsRowBankTransferResponseToJson(
  PostV1PosBanquetsUuidPaymentsRowBankTransferResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
