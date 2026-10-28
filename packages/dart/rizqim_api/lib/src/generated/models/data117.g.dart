// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data117.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data117 _$Data117FromJson(Map<String, dynamic> json) => Data117(
  subscription: json['subscription'] == null
      ? null
      : Subscription.fromJson(json['subscription'] as Map<String, dynamic>),
  commissionRate: json['commission_rate'] as num,
  terminals: Terminals.fromJson(json['terminals'] as Map<String, dynamic>),
  nextInvoice: json['next_invoice'] == null
      ? null
      : NextInvoice.fromJson(json['next_invoice'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data117ToJson(Data117 instance) => <String, dynamic>{
  'subscription': instance.subscription,
  'commission_rate': instance.commissionRate,
  'terminals': instance.terminals,
  'next_invoice': instance.nextInvoice,
};
