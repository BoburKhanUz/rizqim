// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data250.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data250 _$Data250FromJson(Map<String, dynamic> json) => Data250(
  bill: Bill3.fromJson(json['bill'] as Map<String, dynamic>),
  order: Order6.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data250ToJson(Data250 instance) => <String, dynamic>{
  'bill': instance.bill,
  'order': instance.order,
};
