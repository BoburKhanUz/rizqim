// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'expired.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Expired _$ExpiredFromJson(Map<String, dynamic> json) => Expired(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ExpiredToJson(Expired instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
};
