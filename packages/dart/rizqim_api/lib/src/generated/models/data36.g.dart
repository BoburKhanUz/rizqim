// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data36.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data36 _$Data36FromJson(Map<String, dynamic> json) => Data36(
  uuid: json['uuid'] as String,
  number: json['number'] as String,
  period: json['period'] as String,
  periodStart: json['period_start'] as String,
  periodEnd: json['period_end'] as String,
  plan: json['plan'] == null
      ? null
      : Plan.fromJson(json['plan'] as Map<String, dynamic>),
  terminalsCount: (json['terminals_count'] as num).toInt(),
  includedTerminals: (json['included_terminals'] as num).toInt(),
  baseFee: json['base_fee'] as num,
  feePerTerminal: json['fee_per_terminal'] as num,
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines.fromJson(e as Map<String, dynamic>))
      .toList(),
  amount: json['amount'] as num,
  status: json['status'] as String,
  dueDate: json['due_date'] as String,
  issuedAt: json['issued_at'] as String,
  paidAt: json['paid_at'] as String?,
  voidedAt: json['voided_at'] as String?,
);

Map<String, dynamic> _$Data36ToJson(Data36 instance) => <String, dynamic>{
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
  'status': instance.status,
  'due_date': instance.dueDate,
  'issued_at': instance.issuedAt,
  'paid_at': instance.paidAt,
  'voided_at': instance.voidedAt,
};
