// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_tender5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByTender5 _$ByTender5FromJson(Map<String, dynamic> json) => ByTender5(
  tender: json['tender'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByTender5ToJson(ByTender5 instance) => <String, dynamic>{
  'tender': instance.tender,
  'count': instance.count,
  'amount': instance.amount,
};
