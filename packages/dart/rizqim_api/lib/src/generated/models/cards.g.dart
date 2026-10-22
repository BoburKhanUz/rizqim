// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cards.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Cards _$CardsFromJson(Map<String, dynamic> json) => Cards(
  cardType: json['card_type'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$CardsToJson(Cards instance) => <String, dynamic>{
  'card_type': instance.cardType,
  'count': instance.count,
  'amount': instance.amount,
};
