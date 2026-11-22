// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data244.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data244 _$Data244FromJson(Map<String, dynamic> json) => Data244(
  settlement: Settlement4.fromJson(json['settlement'] as Map<String, dynamic>),
  bill: Bill.fromJson(json['bill'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data244ToJson(Data244 instance) => <String, dynamic>{
  'settlement': instance.settlement,
  'bill': instance.bill,
};
