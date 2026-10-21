// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_finance_ledger_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerFinanceLedgerResponse _$GetV1OwnerFinanceLedgerResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerFinanceLedgerResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data48.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerFinanceLedgerResponseToJson(
  GetV1OwnerFinanceLedgerResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
