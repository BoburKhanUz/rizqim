// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'applications.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Applications _$ApplicationsFromJson(Map<String, dynamic> json) => Applications(
  billUuid: json['bill_uuid'] as String,
  amount: json['amount'] as String,
  at: json['at'] as String,
);

Map<String, dynamic> _$ApplicationsToJson(Applications instance) =>
    <String, dynamic>{
      'bill_uuid': instance.billUuid,
      'amount': instance.amount,
      'at': instance.at,
    };
