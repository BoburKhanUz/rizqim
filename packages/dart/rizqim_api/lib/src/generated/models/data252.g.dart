// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data252.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data252 _$Data252FromJson(Map<String, dynamic> json) => Data252(
  bill: Bill5.fromJson(json['bill'] as Map<String, dynamic>),
  copy: json['copy'] as bool,
  order: Order8.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data252ToJson(Data252 instance) => <String, dynamic>{
  'bill': instance.bill,
  'copy': instance.copy,
  'order': instance.order,
};
