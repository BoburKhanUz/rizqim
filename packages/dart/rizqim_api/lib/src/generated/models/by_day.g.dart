// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_day.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByDay _$ByDayFromJson(Map<String, dynamic> json) => ByDay(
  businessDate: json['business_date'] as String,
  amount: json['amount'] as String,
  personal: json['personal'] as String,
  pool: json['pool'] as String,
  cash: json['cash'] as String,
  card: json['card'] as String,
  qr: json['qr'] as String,
  distributions: (json['distributions'] as List<dynamic>)
      .map((e) => Distributions.fromJson(e as Map<String, dynamic>))
      .toList(),
  undistributed: json['undistributed'] as String,
);

Map<String, dynamic> _$ByDayToJson(ByDay instance) => <String, dynamic>{
  'business_date': instance.businessDate,
  'amount': instance.amount,
  'personal': instance.personal,
  'pool': instance.pool,
  'cash': instance.cash,
  'card': instance.card,
  'qr': instance.qr,
  'distributions': instance.distributions,
  'undistributed': instance.undistributed,
};
