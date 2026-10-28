// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data75.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data75 _$Data75FromJson(Map<String, dynamic> json) => Data75(
  uuid: json['uuid'] as String,
  number: json['number'] as String,
  period: json['period'] as String,
  periodStart: json['period_start'] as String,
  periodEnd: json['period_end'] as String,
  plan: json['plan'] == null
      ? null
      : Plan2.fromJson(json['plan'] as Map<String, dynamic>),
  terminalsCount: (json['terminals_count'] as num).toInt(),
  includedTerminals: (json['included_terminals'] as num).toInt(),
  baseFee: json['base_fee'] as num,
  feePerTerminal: json['fee_per_terminal'] as num,
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines4.fromJson(e as Map<String, dynamic>))
      .toList(),
  amount: json['amount'] as num,
  kind: json['kind'] as String,
  paidAmount: json['paid_amount'] as String,
  status: json['status'] as String,
  dueDate: json['due_date'] as String,
  issuedAt: json['issued_at'] as String,
  paidAt: json['paid_at'] as String?,
  voidedAt: json['voided_at'] as String?,
);

Map<String, dynamic> _$Data75ToJson(Data75 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'number': instance.number,
  'period': instance.period,
  'period_start': instance.periodStart,
  'period_end': instance.periodEnd,
  'plan': instance.plan,
  'terminals_count': instance.terminalsCount,
  'included_terminals': instance.includedTerminals,
  'base_fee': instance.baseFee,
  'fee_per_terminal': instance.feePerTerminal,
  'lines': instance.lines,
  'amount': instance.amount,
  'kind': instance.kind,
  'paid_amount': instance.paidAmount,
  'status': instance.status,
  'due_date': instance.dueDate,
  'issued_at': instance.issuedAt,
  'paid_at': instance.paidAt,
  'voided_at': instance.voidedAt,
};
