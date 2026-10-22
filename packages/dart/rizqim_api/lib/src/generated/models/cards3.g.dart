// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cards3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Cards3 _$Cards3FromJson(Map<String, dynamic> json) => Cards3(
  cardType: json['card_type'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Cards3ToJson(Cards3 instance) => <String, dynamic>{
  'card_type': instance.cardType,
  'count': instance.count,
  'amount': instance.amount,
};
