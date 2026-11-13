// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_tender6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByTender6 _$ByTender6FromJson(Map<String, dynamic> json) => ByTender6(
  tender: json['tender'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByTender6ToJson(ByTender6 instance) => <String, dynamic>{
  'tender': instance.tender,
  'count': instance.count,
  'amount': instance.amount,
};
