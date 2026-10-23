// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cards5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Cards5 _$Cards5FromJson(Map<String, dynamic> json) => Cards5(
  cardType: json['card_type'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Cards5ToJson(Cards5 instance) => <String, dynamic>{
  'card_type': instance.cardType,
  'count': instance.count,
  'amount': instance.amount,
};
