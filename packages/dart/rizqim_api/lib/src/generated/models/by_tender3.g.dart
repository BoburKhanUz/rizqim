// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_tender3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByTender3 _$ByTender3FromJson(Map<String, dynamic> json) => ByTender3(
  tender: json['tender'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByTender3ToJson(ByTender3 instance) => <String, dynamic>{
  'tender': instance.tender,
  'count': instance.count,
  'amount': instance.amount,
};
