// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_shifts_uuid_bank_report_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosShiftsUuidBankReportRequestBody
_$V1PosShiftsUuidBankReportRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosShiftsUuidBankReportRequestBody(
      source: json['source'] == null
          ? null
          : Source.fromJson(json['source'] as String),
      bankTotal: json['bank_total'] as String?,
      bankCount: (json['bank_count'] as num?)?.toInt(),
      transactions: (json['transactions'] as List<dynamic>?)
          ?.map((e) => Transactions6.fromJson(e as Map<String, dynamic>))
          .toList(),
      terminalRef: json['terminal_ref'] as String?,
      note: json['note'] as String?,
    );

Map<String, dynamic> _$V1PosShiftsUuidBankReportRequestBodyToJson(
  V1PosShiftsUuidBankReportRequestBody instance,
) => <String, dynamic>{
  'source': instance.source,
  'bank_total': instance.bankTotal,
  'bank_count': instance.bankCount,
  'transactions': instance.transactions,
  'terminal_ref': instance.terminalRef,
  'note': instance.note,
};
