// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data241.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data241 _$Data241FromJson(Map<String, dynamic> json) => Data241(
  settlement: Settlement4.fromJson(json['settlement'] as Map<String, dynamic>),
  bill: Bill.fromJson(json['bill'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data241ToJson(Data241 instance) => <String, dynamic>{
  'settlement': instance.settlement,
  'bill': instance.bill,
};
