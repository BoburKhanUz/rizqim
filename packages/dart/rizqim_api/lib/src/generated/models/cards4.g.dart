// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cards4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Cards4 _$Cards4FromJson(Map<String, dynamic> json) => Cards4(
  cardType: json['card_type'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Cards4ToJson(Cards4 instance) => <String, dynamic>{
  'card_type': instance.cardType,
  'count': instance.count,
  'amount': instance.amount,
};
