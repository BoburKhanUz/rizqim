// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data120.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data120 _$Data120FromJson(Map<String, dynamic> json) => Data120(
  bill: Bill3.fromJson(json['bill'] as Map<String, dynamic>),
  copy: json['copy'] as bool,
  order: Order6.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data120ToJson(Data120 instance) => <String, dynamic>{
  'bill': instance.bill,
  'copy': instance.copy,
  'order': instance.order,
};
