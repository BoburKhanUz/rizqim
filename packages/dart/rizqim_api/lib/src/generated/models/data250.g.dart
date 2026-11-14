// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data250.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data250 _$Data250FromJson(Map<String, dynamic> json) => Data250(
  bill: Bill5.fromJson(json['bill'] as Map<String, dynamic>),
  copy: json['copy'] as bool,
  order: Order8.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data250ToJson(Data250 instance) => <String, dynamic>{
  'bill': instance.bill,
  'copy': instance.copy,
  'order': instance.order,
};
