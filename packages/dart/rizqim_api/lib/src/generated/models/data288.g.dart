// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data288.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data288 _$Data288FromJson(Map<String, dynamic> json) => Data288(
  account: Account6.fromJson(json['account'] as Map<String, dynamic>),
  from: json['from'] as String,
  to: json['to'] as String,
  openingBalance: json['opening_balance'] as String,
  closingBalance: json['closing_balance'] as String,
  chargesTotal: json['charges_total'] as String,
  repaymentsTotal: json['repayments_total'] as String,
  adjustmentsTotal: json['adjustments_total'] as String,
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data288ToJson(Data288 instance) => <String, dynamic>{
  'account': instance.account,
  'from': instance.from,
  'to': instance.to,
  'opening_balance': instance.openingBalance,
  'closing_balance': instance.closingBalance,
  'charges_total': instance.chargesTotal,
  'repayments_total': instance.repaymentsTotal,
  'adjustments_total': instance.adjustmentsTotal,
  'entries': instance.entries,
};
