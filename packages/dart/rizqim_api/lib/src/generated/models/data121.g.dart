// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data121.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data121 _$Data121FromJson(Map<String, dynamic> json) => Data121(
  bill: Bill3.fromJson(json['bill'] as Map<String, dynamic>),
  copy: json['copy'] as bool,
  order: Order6.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data121ToJson(Data121 instance) => <String, dynamic>{
  'bill': instance.bill,
  'copy': instance.copy,
  'order': instance.order,
};
