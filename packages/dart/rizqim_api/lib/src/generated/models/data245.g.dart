// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data245.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data245 _$Data245FromJson(Map<String, dynamic> json) => Data245(
  settlement: Settlement4.fromJson(json['settlement'] as Map<String, dynamic>),
  bill: Bill.fromJson(json['bill'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data245ToJson(Data245 instance) => <String, dynamic>{
  'settlement': instance.settlement,
  'bill': instance.bill,
};
