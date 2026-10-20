// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'next_invoice.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

NextInvoice _$NextInvoiceFromJson(Map<String, dynamic> json) => NextInvoice(
  period: json['period'] as String,
  issuedOn: json['issued_on'] as String,
  trial: json['trial'] as bool,
  amount: json['amount'] as num,
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$NextInvoiceToJson(NextInvoice instance) =>
    <String, dynamic>{
      'period': instance.period,
      'issued_on': instance.issuedOn,
      'trial': instance.trial,
      'amount': instance.amount,
      'lines': instance.lines,
    };
