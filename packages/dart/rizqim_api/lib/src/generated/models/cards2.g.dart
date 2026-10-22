// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cards2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Cards2 _$Cards2FromJson(Map<String, dynamic> json) => Cards2(
  cardType: json['card_type'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Cards2ToJson(Cards2 instance) => <String, dynamic>{
  'card_type': instance.cardType,
  'count': instance.count,
  'amount': instance.amount,
};
