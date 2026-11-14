// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'banquets.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Banquets _$BanquetsFromJson(Map<String, dynamic> json) => Banquets(
  taken: Taken2.fromJson(json['taken'] as Map<String, dynamic>),
  refunded: Refunded3.fromJson(json['refunded'] as Map<String, dynamic>),
);

Map<String, dynamic> _$BanquetsToJson(Banquets instance) => <String, dynamic>{
  'taken': instance.taken,
  'refunded': instance.refunded,
};
