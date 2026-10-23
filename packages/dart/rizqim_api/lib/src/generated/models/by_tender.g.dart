// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_tender.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByTender _$ByTenderFromJson(Map<String, dynamic> json) => ByTender(
  tender: json['tender'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByTenderToJson(ByTender instance) => <String, dynamic>{
  'tender': instance.tender,
  'count': instance.count,
  'amount': instance.amount,
};
