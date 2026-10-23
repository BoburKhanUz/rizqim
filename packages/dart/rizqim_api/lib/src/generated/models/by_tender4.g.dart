// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_tender4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByTender4 _$ByTender4FromJson(Map<String, dynamic> json) => ByTender4(
  tender: json['tender'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByTender4ToJson(ByTender4 instance) => <String, dynamic>{
  'tender': instance.tender,
  'count': instance.count,
  'amount': instance.amount,
};
