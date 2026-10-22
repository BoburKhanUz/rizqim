// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_tender2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByTender2 _$ByTender2FromJson(Map<String, dynamic> json) => ByTender2(
  tender: json['tender'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByTender2ToJson(ByTender2 instance) => <String, dynamic>{
  'tender': instance.tender,
  'count': instance.count,
  'amount': instance.amount,
};
