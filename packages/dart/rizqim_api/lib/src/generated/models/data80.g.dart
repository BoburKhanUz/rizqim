// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data80.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data80 _$Data80FromJson(Map<String, dynamic> json) => Data80(
  subscription: json['subscription'] == null
      ? null
      : Subscription.fromJson(json['subscription'] as Map<String, dynamic>),
  commissionRate: json['commission_rate'] as num,
  terminals: Terminals.fromJson(json['terminals'] as Map<String, dynamic>),
  nextInvoice: json['next_invoice'] == null
      ? null
      : NextInvoice.fromJson(json['next_invoice'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data80ToJson(Data80 instance) => <String, dynamic>{
  'subscription': instance.subscription,
  'commission_rate': instance.commissionRate,
  'terminals': instance.terminals,
  'next_invoice': instance.nextInvoice,
};
