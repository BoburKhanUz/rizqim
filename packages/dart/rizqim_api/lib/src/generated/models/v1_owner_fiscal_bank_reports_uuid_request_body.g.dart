// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_fiscal_bank_reports_uuid_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerFiscalBankReportsUuidRequestBody
_$V1OwnerFiscalBankReportsUuidRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerFiscalBankReportsUuidRequestBody(
      transactions: (json['transactions'] as List<dynamic>?)
          ?.map((e) => Transactions3.fromJson(e as Map<String, dynamic>))
          .toList(),
      source: json['source'] == null
          ? null
          : Source.fromJson(json['source'] as String),
      bankTotal: json['bank_total'] as String?,
      bankCount: (json['bank_count'] as num?)?.toInt(),
      terminalRef: json['terminal_ref'] as String?,
      note: json['note'] as String?,
    );

Map<String, dynamic> _$V1OwnerFiscalBankReportsUuidRequestBodyToJson(
  V1OwnerFiscalBankReportsUuidRequestBody instance,
) => <String, dynamic>{
  'transactions': instance.transactions,
  'source': instance.source,
  'bank_total': instance.bankTotal,
  'bank_count': instance.bankCount,
  'terminal_ref': instance.terminalRef,
  'note': instance.note,
};
