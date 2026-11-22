// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_pos_banquets_uuid_payments_transfers_entry_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1PosBanquetsUuidPaymentsTransfersEntryResponse
_$DeleteV1PosBanquetsUuidPaymentsTransfersEntryResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1PosBanquetsUuidPaymentsTransfersEntryResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data240.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic>
_$DeleteV1PosBanquetsUuidPaymentsTransfersEntryResponseToJson(
  DeleteV1PosBanquetsUuidPaymentsTransfersEntryResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
