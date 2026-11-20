// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data240.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data240 _$Data240FromJson(Map<String, dynamic> json) => Data240(
  settlement: Settlement4.fromJson(json['settlement'] as Map<String, dynamic>),
  bill: Bill.fromJson(json['bill'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data240ToJson(Data240 instance) => <String, dynamic>{
  'settlement': instance.settlement,
  'bill': instance.bill,
};
