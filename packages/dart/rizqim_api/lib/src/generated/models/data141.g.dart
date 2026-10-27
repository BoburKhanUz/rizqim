// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data141.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data141 _$Data141FromJson(Map<String, dynamic> json) => Data141(
  bill: Bill3.fromJson(json['bill'] as Map<String, dynamic>),
  copy: json['copy'] as bool,
  order: Order6.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data141ToJson(Data141 instance) => <String, dynamic>{
  'bill': instance.bill,
  'copy': instance.copy,
  'order': instance.order,
};
