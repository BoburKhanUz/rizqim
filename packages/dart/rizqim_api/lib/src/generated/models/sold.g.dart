// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sold.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Sold _$SoldFromJson(Map<String, dynamic> json) => Sold(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  byTender: (json['by_tender'] as List<dynamic>)
      .map((e) => ByTender.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$SoldToJson(Sold instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'by_tender': instance.byTender,
};
